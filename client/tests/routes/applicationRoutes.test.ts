import { describe, expect, it } from 'vitest';

import { matchApplicationRoute, resolveAccountLink } from '@/routes/applicationRoutes';

const authenticated = { kind: 'authenticated' };
const requires = (...all: string[]) => [authenticated, { kind: 'permissions', all }];

describe('matchApplicationRoute user detail', () => {
  it('classifies a target under directory read access and preserves only table state', () => {
    const targetUserId = crypto.randomUUID();
    const pathname = `/admin/users/${targetUserId}`;
    const route = matchApplicationRoute(pathname);
    expect(route).toMatchObject({
      pathname,
      targetUserId,
      access: 'protected',
      requirements: requires('READ_USERS'),
      destination: 'users'
    });
    const search = '?page=2&unrelated=discarded';
    const canonical = { search: 'page=2&per_page=25&sort=Created&order=asc', invalid: false };
    expect(route?.normalizeSearch?.(search)).toEqual(canonical);
    if (typeof route?.intendedRoute !== 'function') throw new Error('Expected detail intent');
    expect(route.intendedRoute(search)).toEqual(canonical);
  });

  it('does not classify malformed targets or nested paths as user detail', () => {
    expect(matchApplicationRoute('/admin/users/not-a-user')).toBeUndefined();
    expect(matchApplicationRoute(`/admin/users/${crypto.randomUUID()}/detail`)).toBeUndefined();
  });
});

describe('resolveAccountLink', () => {
  it.each([
    ['home', '/', requires('VIEW_DASHBOARD')],
    ['account', '/account', [authenticated]],
    ['signIn', '/login', [{ kind: 'public' }]],
    ['requestReset', '/reset-password', [{ kind: 'public' }]],
    ['users', '/admin/users', requires('READ_USERS')],
    ['invite', '/admin/invite', requires('INVITE_USERS')],
    ['inviteElevated', '/admin/invite-super-admin', requires('INVITE_USERS', 'ASSIGN_SUPER_ADMIN')]
  ] as const)(
    'resolves %s to its host path and authority requirements',
    (destination, href, requirements) => {
      expect(resolveAccountLink(destination)).toEqual({ href, requirements });
    }
  );

  it('links to the requested invitation under the directory read requirements', () => {
    const userId = crypto.randomUUID();

    expect(resolveAccountLink('invitation', userId)).toEqual({
      href: `/admin/invitations/${userId}`,
      requirements: requires('READ_USERS')
    });
  });

  it('offers no invitation link without a user ID', () => {
    expect(resolveAccountLink('invitation')).toBeNull();
  });

  it('offers no invitation link for an unrecognized target path', () => {
    expect(resolveAccountLink('invitation', `${crypto.randomUUID()}/retry`)).toBeNull();
  });
});
