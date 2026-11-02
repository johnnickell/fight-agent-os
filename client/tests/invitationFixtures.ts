import { ApiClient } from '@/api/ApiClient';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { InvitationService } from '@/features/invitations/InvitationService';

export function invitationTarget(overrides: Record<string, unknown> = {}) {
  const userId = crypto.randomUUID();
  return {
    user_id: userId,
    email: 'pending@example.test',
    state: 'pending_activation',
    delivery: {
      user_id: userId,
      activation_delivery_id: crypto.randomUUID(),
      status: 'retry_pending',
      expires_at: '2099-11-09T12:00:00+00:00'
    },
    ...overrides
  };
}
export function invitationResponse(data: unknown, status = 200) {
  const message =
    status === 403
      ? 'Forbidden.'
      : status === 401
        ? 'Unauthorized.'
        : status === 404
          ? 'Not found.'
          : status === 429
            ? 'Too many requests.'
            : status === 409
              ? 'Conflict.'
              : 'Internal server error.';
  return new Response(
    JSON.stringify(status === 200 ? { status: 'success', data } : { status: 'error', message }),
    { status, headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' } }
  );
}
export function invitationDependencies(
  fetch: (url: string, init: RequestInit) => Promise<Response>,
  permissions = ['READ_USERS', 'MANAGE_USER_INVITATIONS'],
  invalidated = () => {}
) {
  const identity = crypto.randomUUID();
  const cache = new AuthorityCache(() =>
    Promise.resolve({
      status: 'principal',
      principal: {
        userId: identity,
        email: 'operator@example.test',
        roles: ['ROLE_SUPER_ADMIN'],
        permissions
      }
    })
  );
  const api = new ApiClient(
    { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
    { fetch, accessTokens: { getAccessToken: () => 'synthetic.access.only' } }
  );
  return {
    cache,
    service: new InvitationService(api, cache, {
      invalidated,
      accessRejected: () => {},
      authorityRejected: () => {}
    })
  };
}
