import { principal } from '@fixtures/authorityFixtures';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { apiFailure } from '../../../src/api/ApiResult.js';
import { AuthorityCache } from '../../../src/features/authority/AuthorityCache.js';
import { UserAdministration } from '../../../src/features/users/UserAdministration.js';

import type { ApiResult } from '../../../src/api/ApiResult.js';
import type { OwnedSession } from '../../../src/features/sessions/OwnedSessionsService.js';
import type { UserAdministrationService } from '../../../src/features/users/UserAdministrationService.js';
import type { DirectoryUser } from '../../../src/features/users/UserDirectoryService.js';

const date = '2026-01-01T00:00:00+00:00';
const target: DirectoryUser = {
  userId: crypto.randomUUID(),
  email: 'target@example.test',
  state: 'active',
  roles: [{ name: 'ROLE_USER', managed: true }],
  createdAt: date,
  updatedAt: date
};
const session: OwnedSession = {
  sessionId: crypto.randomUUID(),
  browserFamily: 'Chrome',
  deviceClass: 'desktop',
  createdAt: date,
  lastActivityAt: date,
  idleExpiresAt: date,
  absoluteExpiresAt: date,
  remembered: false,
  current: false
};
const role = { id: crypto.randomUUID(), name: 'ROLE_USER', managed: true };
const elevatedRole = { id: crypto.randomUUID(), name: 'ROLE_SUPER_ADMIN', managed: true };
const ok = <T,>(value: T): ApiResult<T> => ({ ok: true, value, correlationId: null });
const page = <T,>(rows: T[], totalPages = 1) => ok({ rows, totalPages });
const permissions = [
  'READ_USERS',
  'READ_ROLES',
  'MANAGE_USER_ROLES',
  'ASSIGN_SUPER_ADMIN',
  'READ_SESSIONS',
  'DELETE_SESSIONS',
  'DISABLE_USERS'
];
const caches: AuthorityCache[] = [];

afterEach(() => caches.splice(0).forEach((cache) => cache.dispose()));

async function setup(user = target) {
  const cache = new AuthorityCache(() =>
    Promise.resolve({ status: 'principal', principal: { ...principal, permissions } })
  );
  caches.push(cache);
  await cache.load();
  const port = {
    roles: vi.fn<UserAdministrationService['roles']>().mockResolvedValue(page([])),
    sessions: vi.fn<UserAdministrationService['sessions']>().mockResolvedValue(page([])),
    change: vi.fn<UserAdministrationService['change']>(),
    retryRead: vi.fn<UserAdministrationService['retryRead']>(),
    subscribe: () => () => {},
    isPending: vi.fn(() => false)
  } satisfies Pick<
    UserAdministrationService,
    'roles' | 'sessions' | 'change' | 'retryRead' | 'subscribe' | 'isPending'
  >;
  const onOutcome = vi.fn();
  // Supply only the component's service ports, without constructing transport or recovery owners.
  const mount = () =>
    render(
      <UserAdministration
        user={user}
        cache={cache}
        service={port as unknown as UserAdministrationService}
        onOutcome={onOutcome}
      />
    );
  return { ...port, mount, onOutcome };
}

describe('UserAdministration with stub read and change ports', () => {
  it('returns a shrunken role catalog to page one and retries an unavailable read explicitly', async () => {
    const d = await setup();
    d.roles
      .mockResolvedValueOnce(page([role, elevatedRole], 2))
      .mockResolvedValueOnce(page([elevatedRole], 2))
      .mockResolvedValueOnce(page([role], 2))
      .mockResolvedValueOnce(page([], 1))
      .mockResolvedValueOnce(apiFailure('authorization'));
    d.mount();
    expect(await screen.findByRole('button', { name: 'Remove ROLE_USER' })).toBeVisible();
    const assign = screen.getByRole('button', { name: 'Assign ROLE_SUPER_ADMIN' });
    fireEvent.click(assign);
    expect(screen.getByText(/grants Super Admin authority/)).toBeVisible();
    expect(screen.getByRole('button', { name: 'Cancel' })).toHaveFocus();
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape', keyCode: 27 });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(d.change).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole('button', { name: 'Next role catalog page' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Previous role catalog page' }));
    expect(await screen.findByRole('button', { name: 'Remove ROLE_USER' })).toBeVisible();
    fireEvent.click(screen.getByRole('button', { name: 'Next role catalog page' }));
    expect(await screen.findByText('Access forbidden.')).toBeVisible();
    expect(d.roles.mock.calls.map(([number]) => number)).toEqual([1, 2, 1, 2, 1]);
    fireEvent.click(screen.getByRole('button', { name: 'Reload role catalog' }));
    expect(await screen.findByText('No roles in this catalog.')).toBeVisible();
    expect(d.retryRead).toHaveBeenCalledExactlyOnceWith();
  });

  it('holds a revocation pending until settlement and forwards the exact safe reason', async () => {
    const d = await setup();
    d.sessions.mockResolvedValue(page([session]));
    let finish: (() => void) | undefined;
    d.change.mockImplementation(
      (_user, _action, _reason, settled) =>
        new Promise((resolve) => {
          finish = () => {
            const result = ok(true as const);
            settled?.(result);
            resolve(result);
          };
        })
    );
    d.mount();
    fireEvent.click(await screen.findByRole('button', { name: /^Revoke desktop · Chrome/ }));
    const confirm = screen.getByRole('button', { name: 'Confirm revocation' });
    expect(confirm).toBeDisabled();
    fireEvent.change(screen.getByLabelText('Reason (required)'), {
      target: { value: 'Routine rotation' }
    });
    fireEvent.click(confirm);
    expect(d.change).toHaveBeenCalledExactlyOnceWith(
      target,
      { kind: 'revoke', sessionId: session.sessionId },
      'Routine rotation',
      expect.any(Function)
    );
    expect(d.onOutcome).not.toHaveBeenCalled();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
    act(() => finish?.());
    expect(d.onOutcome).toHaveBeenCalledExactlyOnceWith({
      message: 'The server confirmed the change.',
      error: false
    });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Reload admin sessions' }));
    expect(d.retryRead).toHaveBeenCalledExactlyOnceWith(target.userId);
  });

  it('leaves own sign-ins to Account and Security without calling admin reads', async () => {
    const d = await setup({ ...target, userId: principal.userId });
    d.mount();
    expect(screen.getByText(/open Active Sessions in Account/)).toBeVisible();
    expect(d.roles).not.toHaveBeenCalled();
    expect(d.sessions).not.toHaveBeenCalled();
  });
});
