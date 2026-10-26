import { ApiClient } from '@/api/ApiClient';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { UserDirectoryService } from '@/features/users/UserDirectoryService';

export function directoryUser(overrides: Record<string, unknown> = {}) {
  return {
    user_id: crypto.randomUUID(),
    email: 'pending@example.test',
    state: 'pending_activation',
    roles: [{ name: 'ROLE_USER', managed: true }],
    created_at: '2026-10-25T12:00:00+00:00',
    updated_at: '2026-10-25T12:00:00+00:00',
    ...overrides
  };
}
export function directoryPage(
  records = [directoryUser()],
  overrides: Record<string, unknown> = {}
) {
  return {
    page: 1,
    per_page: 25,
    total_pages: 1,
    total_records: records.length,
    records,
    ...overrides
  };
}
export function directoryResponse(data: unknown, status = 200) {
  return new Response(
    JSON.stringify(
      status === 200
        ? { status: 'success', data }
        : { status: 'error', message: status === 403 ? 'Forbidden.' : 'Unauthorized.' }
    ),
    {
      status,
      headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
    }
  );
}
export const directoryPermissions = [
  'READ_USERS',
  'INVITE_USERS',
  'MANAGE_USER_INVITATIONS',
  'ASSIGN_SUPER_ADMIN'
];
export function directoryDependencies(
  fetch: (url: string, init: RequestInit) => Promise<Response> = () =>
    Promise.resolve(directoryResponse(directoryPage())),
  permissions = directoryPermissions
) {
  const identity = crypto.randomUUID();
  const cache = new AuthorityCache(() =>
    Promise.resolve({
      status: 'principal',
      principal: {
        userId: identity,
        email: 'reader@example.test',
        roles: ['ROLE_SUPER_ADMIN'],
        permissions
      }
    })
  );
  const api = new ApiClient(
    { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
    {
      fetch,
      accessTokens: { getAccessToken: () => 'test.access.signature' }
    }
  );
  return { cache, service: new UserDirectoryService(api), api };
}
