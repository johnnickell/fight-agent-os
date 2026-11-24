import { expect, it, vi } from 'vitest';

import { createAccountSession } from '../../../src/features/auth/AccountSession.js';
import { UserAdministrationService } from '../../../src/features/users/UserAdministrationService.js';

vi.mock('../../../src/features/users/UserAdministrationService.js', () => ({
  UserAdministrationService: vi.fn(class {})
}));

it.each([
  ['access-rejected', true, 'restore'],
  ['authority-rejected', false, 'authorityChanged'],
  ['committed', true, 'authorityChanged'],
  ['unconfirmed', true, 'signal']
] as const)(
  'retires stale views and selects the recovery owner for %s',
  (outcome, hasUser, recovery) => {
    const account = createAccountSession({ schemaVersion: 2, apiBasePath: '/api/v1' }, () => ({
      clear: vi.fn(),
      dispose: vi.fn()
    }));
    const userId = hasUser ? crypto.randomUUID() : undefined;
    const users = vi.spyOn(account.users, 'invalidate').mockImplementation(() => {});
    const invitations = vi.spyOn(account.invitations, 'invalidate').mockImplementation(() => {});
    const restore = vi
      .spyOn(account.coordinator, 'restore')
      .mockResolvedValue({ ok: true, value: true, correlationId: null });
    const authorityChanged = vi
      .spyOn(account.coordinator, 'authorityChanged')
      .mockImplementation(() => {});
    const signal = vi.spyOn(account.authority, 'signal').mockResolvedValue(undefined);
    try {
      const args = vi.mocked(UserAdministrationService).mock.lastCall;
      if (!args) throw new Error('Expected administration recovery callback');
      args[2](userId, outcome);

      expect(users).toHaveBeenCalledTimes(1);
      if (userId) expect(invitations).toHaveBeenCalledExactlyOnceWith(userId);
      else expect(invitations).not.toHaveBeenCalled();
      expect(restore).toHaveBeenCalledTimes(Number(recovery === 'restore'));
      expect(authorityChanged).toHaveBeenCalledTimes(Number(recovery === 'authorityChanged'));
      if (recovery === 'signal')
        expect(signal).toHaveBeenCalledExactlyOnceWith('authority-changed');
      else expect(signal).not.toHaveBeenCalled();
    } finally {
      account.coordinator.dispose();
      account.authority.dispose();
    }
  }
);
