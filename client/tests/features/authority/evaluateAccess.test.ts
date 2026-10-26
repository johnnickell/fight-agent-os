import { describe, expect, it } from 'vitest';

import { evaluateAccess } from '@/features/authority/evaluateAccess';

import { principal } from '../../authorityFixtures';

import type { AuthorityState } from '@/features/authority/AuthorityCache';

const authenticated: AuthorityState = { status: 'authenticated', principal };
const protectedAccess = [{ kind: 'permissions', all: ['VIEW_REPORTS'] }];
describe('shared exact permission evaluation', () => {
  it.each<AuthorityState>([
    { status: 'unknown' },
    { status: 'loading' },
    { status: 'stale' },
    { status: 'anonymous', reason: 'absent' },
    { status: 'terminal' },
    { status: 'refresh-failed' },
    { status: 'error', reason: 'network' },
    { status: 'error', reason: 'protocol' }
  ])(
    'fails closed for protected content in $status without hiding explicit public content',
    (state) => {
      expect(evaluateAccess(state, protectedAccess)).toBe('unavailable');
      expect(evaluateAccess(state, [{ kind: 'authenticated' }])).toBe('unavailable');
      expect(evaluateAccess(state, [{ kind: 'public' }])).toBe('allowed');
    }
  );

  it('accumulates all nested requirements, including parents of a public leaf', () => {
    expect(
      evaluateAccess(authenticated, [
        { kind: 'authenticated' },
        { kind: 'permissions', all: ['VIEW_REPORTS', 'EDIT_REPORTS'] }
      ])
    ).toBe('allowed');
    expect(
      evaluateAccess(authenticated, [
        { kind: 'permissions', all: ['DELETE_REPORTS'] },
        { kind: 'public' }
      ])
    ).toBe('forbidden');
    expect(evaluateAccess(authenticated, [{ kind: 'authenticated' }])).toBe('allowed');
  });

  it.each(['ROLE_SUPER_ADMIN', 'view_reports', 'VIEW', 'VIEW_REPORTS_EXTRA', 'DELETE_REPORTS'])(
    'does not grant from roles, casing or substring %s',
    (permission) => {
      expect(evaluateAccess(authenticated, [{ kind: 'permissions', all: [permission] }])).toBe(
        'forbidden'
      );
    }
  );

  it.each([
    { roles: [] },
    { roles: ['ROLE_USER'] },
    { roles: ['ROLE_SUPER_ADMIN'] },
    { roles: ['ROLE_VIEW_DASHBOARD', 'ROLE_ADMIN'] }
  ])('keeps independent Dashboard destination permissions with role labels $roles', ({ roles }) => {
    const names = [
      'VIEW_DASHBOARD',
      'READ_USERS',
      'INVITE_USERS',
      'MANAGE_USER_INVITATIONS',
      'ASSIGN_SUPER_ADMIN',
      'READ_OWN_SESSIONS'
    ];
    for (let mask = 0; mask < 64; mask++) {
      const state: AuthorityState = {
        status: 'authenticated',
        principal: {
          ...principal,
          roles,
          permissions: names.filter((_, bit) => mask & (1 << bit))
        }
      };
      for (const [bit, name] of names.entries()) {
        expect(evaluateAccess(state, [{ kind: 'permissions', all: [name] }])).toBe(
          mask & (1 << bit) ? 'allowed' : 'forbidden'
        );
      }
      expect(
        evaluateAccess(state, [
          { kind: 'permissions', all: ['INVITE_USERS', 'ASSIGN_SUPER_ADMIN'] }
        ])
      ).toBe((mask & 20) === 20 ? 'allowed' : 'forbidden');
      expect(evaluateAccess(state, [{ kind: 'authenticated' }])).toBe('allowed');
    }
  });

  it.each([
    undefined,
    null,
    [],
    {},
    ['public'],
    [null],
    [{}],
    [{ kind: 'other' }],
    [{ kind: 'public', all: [] }],
    [{ kind: 'permissions', all: [] }],
    [{ kind: 'permissions' }],
    [{ kind: 'permissions', all: [42] }],
    [{ kind: 'permissions', all: ['VIEW_REPORTS\n'] }]
  ])('rejects absent or malformed complete-route metadata (%#)', (requirements) => {
    expect(evaluateAccess(authenticated, requirements)).toBe('invalid');
  });
});
