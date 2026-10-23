import { useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';

import { ApiClient } from '@/api/ApiClient';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { LoginService } from '@/features/auth/LoginService';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { browserSessionPlatform } from '@/features/auth/SessionChannel';
import { SessionCoordinator } from '@/features/auth/SessionCoordinator';
import { SessionService } from '@/features/auth/SessionService';
import { AuthorityCache, observeAuthorityResume } from '@/features/authority/AuthorityCache';
import { CurrentPrincipalService } from '@/features/authority/CurrentPrincipalService';
import { SwaggerService } from '@/features/swagger/SwaggerService';
import { PublicSchemaService } from '@/features/validation/PublicSchemaService';
import { applicationRoutes } from '@/routes/applicationRoutes';
import { IntendedRoute } from '@/routes/IntendedRoute';
import { safeDiagnostics } from '@/SafeDiagnostics';

import type { RuntimeConfiguration } from '@/runtimeConfiguration';

/** Constructs the sole volatile credential/principal graph for the mounted application context */
export function createApplicationSession(runtime: RuntimeConfiguration) {
  const tokens = new MemoryAccessToken();
  const api = new ApiClient(runtime, { accessTokens: tokens });
  const csrf = new CsrfProofService(api);
  const authority = new AuthorityCache(new CurrentPrincipalService(api).load);
  const intent = new IntendedRoute(authority, () => applicationRoutes, window.location.origin);
  const coordinator = new SessionCoordinator({
    tokens,
    authority,
    clearIntent: () => intent.clear(),
    coldAnonymous: true,
    session: new SessionService(api, csrf, tokens),
    login: new LoginService(api, csrf, tokens),
    ...browserSessionPlatform()
  });
  return {
    authority,
    intent,
    coordinator,
    swagger: new SwaggerService(
      api,
      authority,
      runtime.swaggerAvailable,
      {
        accessRejected: () => {
          void coordinator.restore();
        },
        authorityChanged: () => {
          void coordinator.authorityChanged();
        }
      },
      window.location.origin,
      safeDiagnostics(runtime)
    ),
    schemas: new PublicSchemaService(api)
  };
}
export type ApplicationSession = ReturnType<typeof createApplicationSession>;

/** Retires the entire document context before bfcache can retain private fields or authority */
export function useApplicationSession(runtime: RuntimeConfiguration) {
  const [session] = useState(() => createApplicationSession(runtime));
  const [retired, setRetired] = useState(false);
  useLayoutEffect(() => {
    const stopAuthorityResume = observeAuthorityResume(session.authority, document, window);
    const clear = () => {
      stopAuthorityResume();
      session.intent.dispose();
      session.coordinator.dispose();
      session.authority.dispose();
      session.schemas.clear();
    };
    const retire = () => {
      clear();
      flushSync(() => setRetired(true));
    };
    let hidden = document.hidden;
    const visibility = () => {
      if (hidden && !document.hidden) session.coordinator.resume();
      hidden = document.hidden;
    };
    const focus = () => {
      if (hidden) session.coordinator.resume();
    };
    window.addEventListener('pagehide', retire);
    window.addEventListener('focus', focus);
    document.addEventListener('visibilitychange', visibility);
    void session.coordinator.restore();
    return () => {
      window.removeEventListener('pagehide', retire);
      window.removeEventListener('focus', focus);
      document.removeEventListener('visibilitychange', visibility);
      clear();
    };
  }, [session]);
  return { session, retired };
}
