import { principal } from '@fixtures/authorityFixtures';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { apiFailure } from '../../src/api/ApiResult.js';
import { AuthorityCache } from '../../src/features/authority/AuthorityCache.js';
import UserDetail from '../../src/pages/UserDetail.js';
import UsersPage from '../../src/pages/UsersPage.js';

import type { UserAdministrationService } from '../../src/features/users/UserAdministrationService.js';
import type { DirectoryUser } from '../../src/features/users/UserDirectoryService.js';

vi.mock('../../src/features/users/UserAdministration.js', () => ({
  UserAdministration: ({ user }: { user: DirectoryUser }) => <p>Actions for {user.email}</p>
}));

const user: DirectoryUser = {
  userId: crypto.randomUUID(),
  email: 'target@example.test',
  state: 'active',
  roles: [],
  createdAt: '2026-01-01T00:00:00+00:00',
  updatedAt: '2026-01-01T00:00:00+00:00'
};
const ok = <T,>(value: T) => ({ ok: true as const, value, correlationId: null });
const caches: AuthorityCache[] = [];

afterEach(() => {
  caches.splice(0).forEach((cache) => cache.dispose());
  window.history.replaceState(null, '', '/');
});

async function setup(permissions = ['READ_USERS']) {
  const cache = new AuthorityCache(() =>
    Promise.resolve({ status: 'principal', principal: { ...principal, permissions } })
  );
  caches.push(cache);
  await cache.load();
  const service = {
    load: vi.fn().mockResolvedValue(ok({ records: [user], totalRecords: 1 })),
    detail: vi.fn().mockResolvedValue(ok(user)),
    retry: vi.fn()
  };
  return { cache, service };
}

describe('Users detail interactions with a stub directory', () => {
  it.each([true, false])('delegates detail navigation when host-owned (%s)', async (host) => {
    window.history.replaceState(null, '', '/admin/users');
    const { cache, service } = await setup();
    const onOpenDetail = vi.fn();
    const onCloseDetail = vi.fn();
    const callbacks = host ? { onOpenDetail, onCloseDetail } : {};
    const view = (detailId?: string) => (
      <UsersPage cache={cache} service={service} detailId={detailId} {...callbacks} />
    );
    const { rerender } = render(view());
    fireEvent.click(await screen.findByRole('button', { name: `View details for ${user.email}` }));
    if (host) {
      expect(onOpenDetail).toHaveBeenCalledExactlyOnceWith(user.userId);
      expect(service.detail).not.toHaveBeenCalled();
      rerender(view(user.userId));
    }
    fireEvent.click(await screen.findByRole('button', { name: 'Back to user list' }));
    if (host) {
      expect(onCloseDetail).toHaveBeenCalledTimes(1);
      expect(service.load).toHaveBeenCalledTimes(1);
    } else {
      expect(await screen.findByRole('table')).toBeVisible();
      expect(service.load).toHaveBeenCalledTimes(2);
    }
  });

  it('never reads detail using only self-service session authority', async () => {
    const { cache, service } = await setup(['READ_OWN_SESSIONS']);
    render(<UserDetail userId={user.userId} cache={cache} service={service} onBack={vi.fn()} />);
    expect(screen.getByText('User detail cleared')).toBeVisible();
    expect(service.detail).not.toHaveBeenCalled();
  });

  it('retires an old detail and restores its actions only after deliberate reload', async () => {
    const { cache, service } = await setup();
    let retire: (() => void) | undefined;
    const directory = {
      ...service,
      subscribe: (listener: () => void) => {
        retire = listener;
        return () => {};
      }
    };
    const { unmount } = render(
      <UserDetail
        userId={user.userId}
        cache={cache}
        service={directory}
        onBack={vi.fn()}
        administration={{} as UserAdministrationService}
        notice={{ message: 'Change confirmed', error: false }}
        onOutcome={vi.fn()}
      />
    );
    expect(await screen.findByText(`Actions for ${user.email}`)).toBeVisible();
    expect(screen.getByRole('status')).toHaveTextContent('Change confirmed');
    act(() => retire?.());
    expect(screen.queryByText(user.email)).not.toBeInTheDocument();
    expect(screen.queryByText(`Actions for ${user.email}`)).not.toBeInTheDocument();
    expect(service.detail).toHaveBeenCalledTimes(1);
    service.detail.mockResolvedValueOnce(apiFailure('authorization'));
    fireEvent.click(screen.getByRole('button', { name: 'Retry user detail' }));
    expect(await screen.findByText('Access forbidden')).toBeVisible();
    expect(screen.queryByText(`Actions for ${user.email}`)).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Retry user detail' }));
    expect(await screen.findByText(`Actions for ${user.email}`)).toBeVisible();
    expect(service.retry).toHaveBeenCalledTimes(2);
    unmount();
  });
});
