import { describe, expect, it } from 'vitest';

import { decodeCurrentPrincipal } from '@/features/authority/CurrentPrincipal';

import { principal } from '../../authorityFixtures';

describe('safe current principal', () => {
  it('copies and freezes approved fields without retaining loader mutations', () => {
    const input = { ...principal, permissions: ['VIEW_REPORTS'], roles: [] };
    const decoded = decodeCurrentPrincipal(input);
    input.permissions.push('DELETE_REPORTS');
    expect(decoded).toEqual({ ...principal, permissions: ['VIEW_REPORTS'], roles: [] });
    expect(Object.isFrozen(decoded)).toBe(true);
    expect(Object.isFrozen(decoded?.permissions)).toBe(true);
    expect(Object.isFrozen(decoded?.roles)).toBe(true);
  });

  it.each([
    null,
    undefined,
    [],
    'token',
    {},
    { ...principal, token: 'not-allowed' },
    { ...principal, userId: 'improvised-id' },
    { ...principal, email: 'not-email' },
    { ...principal, email: 'Reader@example.test' },
    { ...principal, permissions: new Array<string>(1) },
    { ...principal, permissions: Object.assign(new Array<string>(2), { 0: 'VIEW_REPORTS' }) },
    { ...principal, email: 'a'.repeat(255) + '@example.test' },
    { ...principal, email: 'reader\n@example.test' },
    { ...principal, roles: null },
    { ...principal, permissions: ['VIEW_REPORTS', 'VIEW_REPORTS'] },
    { ...principal, permissions: ['VIEW_REPORTS', 9] },
    { ...principal, permissions: ['*'] },
    { ...principal, permissions: ['VIEW_REPORTS\n'] },
    { ...principal, permissions: ['a'.repeat(129)] },
    { ...principal, roles: new Array<string>(1025).fill('Reader') },
    { userId: principal.userId, email: principal.email, roles: [] }
  ])('rejects malformed projections atomically (%#)', (value) => {
    expect(decodeCurrentPrincipal(value)).toBeNull();
  });
});
