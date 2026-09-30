import { AuthorityCache } from '@/features/authority/AuthorityCache';

import type { CurrentPrincipal } from '@/features/authority/CurrentPrincipal';
import type { RegisteredRoute } from '@/routes/GuardedRoute';

export const principal: CurrentPrincipal = {
  userId: crypto.randomUUID(),
  email: 'reader@example.test',
  roles: ['ROLE_SUPER_ADMIN'],
  permissions: ['VIEW_REPORTS', 'EDIT_REPORTS']
};
export const reportRoute: RegisteredRoute = {
  pathname: '/app/reports',
  requirements: [{ kind: 'authenticated' }, { kind: 'permissions', all: ['VIEW_REPORTS'] }],
  intendedRoute: { page: ['1', '2'], sort: ['recent', 'name'] }
};
export function authenticatedCache(value: CurrentPrincipal = principal) {
  return new AuthorityCache(() => Promise.resolve({ status: 'principal', principal: value }));
}
