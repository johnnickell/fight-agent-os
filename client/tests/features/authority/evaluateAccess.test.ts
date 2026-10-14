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
