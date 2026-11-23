import { describe, expect, it } from 'vitest';

import { resolveAccountLink } from '@/routes/applicationRoutes';

const authenticated = { kind: 'authenticated' };
const requires = (...all: string[]) => [authenticated, { kind: 'permissions', all }];

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
