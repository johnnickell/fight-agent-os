import { apiFailure } from '@fight/react/account';
import { MemoryAccessToken } from '@fight/react/account';
import { PasswordChangeService } from '@fight/react/account';
import { SessionCoordinator } from '@fight/react/account';
import { AuthorityCache } from '@fight/react/account';
import { CatalogService, CustomRoleService } from '@fight/react/account';
import { InvitationIssuanceService } from '@fight/react/account';
import { InvitationService } from '@fight/react/account';
import { OwnedSessionsService } from '@fight/react/account';
import { UserAdministrationService } from '@fight/react/account';
import { UserDirectoryService } from '@fight/react/account';
import { PublicSchemaService } from '@fight/react/account';
import { ApiClient } from '@fixtures/transport';
import { vi } from 'vitest';

import { SwaggerService } from '@/features/swagger/SwaggerService';
import { applicationRoutes } from '@/routes/applicationRoutes';
import { IntendedRoute } from '@/routes/IntendedRoute';

import { jsonResponse } from './activationFixtures';
import { principal } from './authorityFixtures';
import { loginSchemaSource } from './loginFixtures';
import { passwordChangeSchema } from './passwordChangeFixtures';

import type { ApiResult } from '@fight/react/account';
import type { PrincipalLoader } from '@fight/react/account';

export function applicationSession(
  permissions: string[] = ['VIEW_DASHBOARD', 'READ_OWN_SESSIONS'],
  loader?: PrincipalLoader,
  coldAnonymous = false
) {
  const tokens = new MemoryAccessToken();
  const load = vi.fn<PrincipalLoader>(
    loader ??
      (() => Promise.resolve({ status: 'principal', principal: { ...principal, permissions } }))
  );
  const authority = new AuthorityCache(load);
  const intent = new IntendedRoute(authority, () => applicationRoutes, window.location.origin);
  const accept = (): Promise<ApiResult<true>> => {
    tokens.accept('test.access.signature', performance.now());
    return Promise.resolve({ ok: true as const, value: true as const, correlationId: null });
  };
  const refresh = vi.fn(accept);
  const logout = vi.fn((): Promise<ApiResult<true>> =>
    Promise.resolve({ ok: true as const, value: true as const, correlationId: null })
  );
  const coordinator = new SessionCoordinator({
    tokens,
    authority,
    clearIntent: () => intent.clear(),
    coldAnonymous,
    session: { refresh, logout, invalidate: () => tokens.clear(), dispose: () => tokens.clear() },
    login: { submit: accept, dispose: () => tokens.clear() }
  });
  const schemas = new PublicSchemaService(
    new ApiClient(
      { schemaVersion: 2, apiBasePath: '/api/v1' },
      {
        fetch: (url) =>
          Promise.resolve(
            jsonResponse(
              url.endsWith('/validations/password_change')
                ? passwordChangeSchema
                : loginSchemaSource
            )
          )
      }
    )
  );
  const catalogApi = new ApiClient({ schemaVersion: 2, apiBasePath: '/api/v1' });
  return {
    authorityCatalog: new CatalogService(catalogApi),
    customRoles: new CustomRoleService(catalogApi, authority),
    authority,
    intent,
    coordinator,
    schemas,
    passwordChange: new PasswordChangeService(
      new ApiClient({
        schemaVersion: 2,
        apiBasePath: '/api/v1'
      }),
      authority,
      coordinator.terminal
    ),
    invitationIssuance: new InvitationIssuanceService(
      new ApiClient({
        schemaVersion: 2,
        apiBasePath: '/api/v1'
      }),
      authority,
      { invalidated: () => {}, accessRejected: () => {}, authorityRejected: () => {} }
    ),
    invitations: new InvitationService(
      new ApiClient({
        schemaVersion: 2,
        apiBasePath: '/api/v1'
      }),
      authority,
      {
        invalidated: () => {},
        accessRejected: () => {},
        authorityRejected: () => {}
      }
    ),
    users: new UserDirectoryService(
      new ApiClient({
        schemaVersion: 2,
        apiBasePath: '/api/v1'
      })
    ),
    userAdministration: new UserAdministrationService(
      new ApiClient({ schemaVersion: 2, apiBasePath: '/api/v1' }),
      authority,
      () => {}
    ),
    sessions: new OwnedSessionsService(
      new ApiClient({
        schemaVersion: 2,
        apiBasePath: '/api/v1'
      })
    ),
    swagger: new SwaggerService(
      new ApiClient({
        schemaVersion: 2,
        apiBasePath: '/api/v1'
      }),
      authority,
      false,
      { accessRejected: () => {}, authorityChanged: () => {} },
      window.location.origin
    ),
    load,
    refresh,
    logout,
    apiFailure,
    dispose: () => {
      intent.dispose();
      coordinator.dispose();
      authority.dispose();
      schemas.clear();
    }
  };
}
