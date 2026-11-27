import { useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';

import { ApiClient } from '../../api/ApiClient.js';
import { AuthorityCache } from '../authority/AuthorityCache.js';
import { CatalogService } from '../authority/CatalogService.js';
import { CurrentPrincipalService } from '../authority/CurrentPrincipalService.js';
import { CustomRoleService } from '../authority/CustomRoleService.js';
import { InvitationIssuanceService } from '../invitations/InvitationIssuanceService.js';
import { InvitationService } from '../invitations/InvitationService.js';
import { OwnedSessionsService } from '../sessions/OwnedSessionsService.js';
import { UserAdministrationService } from '../users/UserAdministrationService.js';
import { UserDirectoryService } from '../users/UserDirectoryService.js';
import { PublicSchemaService } from '../validation/PublicSchemaService.js';
import { CsrfProofService } from './CsrfProofService.js';
import { LoginService } from './LoginService.js';
import { MemoryAccessToken } from './MemoryAccessToken.js';
import { PasswordChangeService } from './PasswordChangeService.js';
import { browserSessionPlatform } from './SessionChannel.js';
import { SessionCoordinator } from './SessionCoordinator.js';
import { SessionService } from './SessionService.js';

import type { ApiConfiguration } from '../../ApiConfiguration.js';

/** Constructs the sole volatile credential/principal graph for the mounted application context */
export function createAccountSession<T extends { clear(): void; dispose(): void }>(
  runtime: ApiConfiguration,
  createIntent: (cache: AuthorityCache) => T
) {
  const tokens = new MemoryAccessToken();
  const api = new ApiClient(runtime, { accessTokens: tokens });
  const csrf = new CsrfProofService(api);
  const principals = new CurrentPrincipalService(api);
  const authority = new AuthorityCache((signal) => {
    // A delayed cache timer may run before the credential timer after suspension.
    // Missing local access is not evidence of server rejection: renew through the sole owner.
    if (!tokens.getAccessToken()) {
      void coordinator.restore();
      return Promise.resolve({ status: 'network' });
    }
    return principals.load(signal);
  });
  const intent = createIntent(authority);
  const coordinator = new SessionCoordinator({
    tokens,
    authority,
    clearIntent: () => intent.clear(),
    coldAnonymous: true,
    session: new SessionService(api, csrf, tokens),
    login: new LoginService(api, csrf, tokens),
    ...browserSessionPlatform()
  });
  const sessions = new OwnedSessionsService(
    api,
    {
      accessRejected: () => {
        void coordinator.restore();
      },
      authorityRejected: () => {
        coordinator.authorityChanged();
      },
      currentSession: coordinator.identifyCurrentSession,
      revoked: coordinator.sessionRevoked,
      invalidated: coordinator.sessionsInvalidated
    },
    authority
  );
  coordinator.subscribeSessions(sessions.invalidate);
  const users = new UserDirectoryService(api, {
    accessRejected: () => {
      void coordinator.restore();
    },
    authorityRejected: () => {
      coordinator.authorityChanged();
    }
  });
  const invitations = new InvitationService(api, authority, {
    invalidated: users.invalidate,
    accessRejected: () => {
      void coordinator.restore();
    },
    authorityRejected: () => {
      coordinator.authorityChanged();
    }
  });
  return {
    authority,
    intent,
    coordinator,
    passwordChange: new PasswordChangeService(
      api,
      authority,
      coordinator.terminal,
      coordinator.passwordChanged
    ),
    transport: {
      get: api.get.bind(api),
      mutate: api.mutate.bind(api),
      getProtectedJson: api.getProtectedJson.bind(api)
    },
    users,
    authorityCatalog: new CatalogService(api),
    customRoles: new CustomRoleService(api, authority, (result) => {
      users.invalidate();
      if (!result.ok && result.error.kind === 'authentication') void coordinator.restore();
      else if (result.ok || result.error.kind === 'authorization') coordinator.authorityChanged();
      else void authority.signal('authority-changed');
    }),
    userAdministration: new UserAdministrationService(api, authority, (userId, outcome) => {
      users.invalidate();
      if (userId) invitations.invalidate(userId);
      if (outcome === 'access-rejected') void coordinator.restore();
      else if (outcome === 'committed' || outcome === 'authority-rejected')
        coordinator.authorityChanged();
      else void authority.signal('authority-changed');
    }),
    invitations,
    invitationIssuance: new InvitationIssuanceService(api, authority, {
      invalidated: (userId) => {
        users.invalidate();
        if (userId) invitations.invalidate(userId);
      },
      accessRejected: () => {
        void coordinator.restore();
      },
      authorityRejected: () => {
        coordinator.authorityChanged();
      }
    }),
    sessions,
    schemas: new PublicSchemaService(api)
  };
}
export type AccountSession = ReturnType<typeof createAccountSession>;

/** Retires the entire document context before bfcache can retain private fields or authority */
export function useAccountSession<
  T extends Pick<AccountSession, 'authority' | 'coordinator' | 'intent' | 'schemas'>
>(create: () => T) {
  const [factory] = useState(() => create);
  const [session, setSession] = useState<T | null>(null);
  const [retired, setRetired] = useState(false);
  useLayoutEffect(() => {
    if (retired) return;
    // Each committed setup owns a fresh graph, including after Strict Mode replay.
    const session = factory();
    // One lifecycle owner consumes the hidden -> visible edge, regardless of event order.
    let hidden = document.hidden;
    session.authority.setForeground(!hidden);
    if (hidden) session.coordinator.pause();
    const focus = () => {
      if (!hidden || document.hidden) return;
      hidden = false;
      session.authority.setForeground(true);
      session.coordinator.resume();
    };
    const visibility = () => {
      if (document.hidden) {
        hidden = true;
        session.authority.setForeground(false);
        session.coordinator.pause();
      } else focus();
    };
    let disposed = false;
    const clear = () => {
      if (disposed) return;
      disposed = true;
      window.removeEventListener('pagehide', retire);
      window.removeEventListener('focus', focus);
      document.removeEventListener('visibilitychange', visibility);
      session.intent.dispose();
      session.coordinator.dispose();
      session.authority.dispose();
      session.schemas.clear();
    };
    const retire = () => {
      clear();
      flushSync(() => {
        setSession(null);
        setRetired(true);
      });
    };
    window.addEventListener('pagehide', retire);
    window.addEventListener('focus', focus);
    document.addEventListener('visibilitychange', visibility);
    // Publish the committed external owner before paint; allocating it in render leaks discarded graphs.
    // eslint-disable-next-line react-hooks/set-state-in-effect -- The effect owns and retires the browser session.
    setSession(session);
    void session.coordinator.restore();
    return clear;
  }, [factory, retired]);
  return { session, retired };
}
