import { vi } from 'vitest';

import { ApiClient } from '@/api/ApiClient';
import { apiFailure } from '@/api/ApiResult';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { PasswordChangeService } from '@/features/auth/PasswordChangeService';
import { SessionCoordinator } from '@/features/auth/SessionCoordinator';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { OwnedSessionsService } from '@/features/sessions/OwnedSessionsService';
import { SwaggerService } from '@/features/swagger/SwaggerService';
import { PublicSchemaService } from '@/features/validation/PublicSchemaService';
import { applicationRoutes } from '@/routes/applicationRoutes';
import { IntendedRoute } from '@/routes/IntendedRoute';

import { jsonResponse } from './activationFixtures';
import { principal } from './authorityFixtures';
import { loginSchemaSource } from './loginFixtures';
import { passwordChangeSchema } from './passwordChangeFixtures';

import type { ApiResult } from '@/api/ApiResult';
import type { PrincipalLoader } from '@/features/authority/AuthorityCache';

export function applicationSession(
  permissions: string[] = ['VIEW_DASHBOARD', 'READ_OWN_SESSIONS'],
  loader?: PrincipalLoader
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
    session: { refresh, logout, invalidate: () => tokens.clear(), dispose: () => tokens.clear() },
    login: { submit: accept, dispose: () => tokens.clear() }
  });
  const schemas = new PublicSchemaService(
    new ApiClient(
      { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
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
  return {
    authority,
    intent,
    coordinator,
    schemas,
    passwordChange: new PasswordChangeService(
      new ApiClient({
        schemaVersion: 2,
        apiBasePath: '/api/v1',
        appDebug: false,
        swaggerAvailable: false
      }),
      authority,
      coordinator.terminal
    ),
    sessions: new OwnedSessionsService(
      new ApiClient({
        schemaVersion: 2,
        apiBasePath: '/api/v1',
        appDebug: false,
        swaggerAvailable: false
      })
    ),
    swagger: new SwaggerService(
      new ApiClient({
        schemaVersion: 2,
        apiBasePath: '/api/v1',
        appDebug: false,
        swaggerAvailable: false
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
