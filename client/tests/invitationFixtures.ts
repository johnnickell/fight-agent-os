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
      ? 'Permission denied.'
      : status === 401
        ? 'Authentication required.'
        : status === 404
          ? 'Resource not found.'
          : status === 429
            ? 'Too many requests.'
            : status === 409
              ? 'State conflict.'
              : 'Internal server error.';
  // Delivery-shaped mutation fixtures now mean an empty command acknowledgement.
  const payload =
    data && typeof data === 'object' && 'activation_delivery_id' in data ? null : data;
  return new Response(
    JSON.stringify(
      status === 200
        ? { status: 'success', data: payload }
        : status >= 500
          ? { status: 'error', message }
          : { status: 'fail', data: { message } }
    ),
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
    {
      fetch: async (url, init) => {
        const response = await fetch(url, init);
        // One synthetic target fixture supplies the two real read representations.
        // Clone because controlled promises may intentionally serve the same snapshot twice.
        const copy = response.clone();
        if (init.method !== 'GET' || response.status !== 200) return copy;
        const envelope = (await copy.json()) as { status: string; data: unknown };
        const data = envelope.data;
        if (!data || typeof data !== 'object' || !('delivery' in data)) return response.clone();
        const { delivery, ...identity } = data;
        const payload = url.includes('/users/')
          ? {
              ...identity,
              roles: [],
              created_at: '2026-11-01T12:00:00+00:00',
              updated_at: '2026-11-01T12:00:00+00:00'
            }
          : delivery;
        return new Response(JSON.stringify({ status: 'success', data: payload }), {
          status: 200,
          headers: response.headers
        });
      },
      accessTokens: { getAccessToken: () => 'synthetic.access.only' }
    }
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
