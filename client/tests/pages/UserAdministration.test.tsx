import { UserAdministrationService, UsersPage } from '@fight/react/account';
import {
  directoryDependencies,
  directoryPage,
  directoryResponse,
  directoryUser
} from '@fixtures/userDirectoryFixtures';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { matchApplicationRoute } from '@/routes/applicationRoutes';

import { deferred } from '../apiFixtures';

const permissions = ['READ_USERS', 'READ_ROLES', 'MANAGE_USER_ROLES'];
const cleanups: (() => void)[] = [];
beforeEach(() => window.history.replaceState(null, '', '/admin/users'));
afterEach(() => cleanups.splice(0).forEach((dispose) => dispose()));

function role(name: string, managed: boolean) {
  return { role_id: crypto.randomUUID(), name, managed, revision: `${crypto.randomUUID()}:1` };
}

async function mount({
  state = 'active',
  self = false,
  extra = [],
  assigned = ['ROLE_USER']
}: {
  state?: string;
  self?: boolean;
  extra?: string[];
  assigned?: string[];
} = {}) {
  const catalog = [
    role('ROLE_CUSTOM', false),
    role('ROLE_SUPER_ADMIN', true),
    role('ROLE_USER', true)
  ];
  const user = directoryUser({ state, roles: assigned.map((name) => ({ name, managed: true })) });
  let targetId: string = user.user_id;
  const fetch = vi.fn((url: string, options: RequestInit) =>
    Promise.resolve(
      url.startsWith('/api/v1/roles?')
        ? directoryResponse({
            ...directoryPage([]),
            total_records: catalog.length,
            records: catalog
          })
        : options.method === 'PUT'
          ? directoryResponse(null, 403)
          : directoryResponse({ ...user, user_id: targetId })
    )
  );
  const { api, cache, service } = directoryDependencies(fetch, [...permissions, ...extra]);
  cleanups.push(() => cache.dispose());
  await cache.load();
  const snapshot = cache.getSnapshot();
  if (self && snapshot.status === 'authenticated') targetId = snapshot.principal.userId;
  const refresh = vi.fn();
  const onOutcome = vi.fn();
  render(
    <UsersPage
      service={service}
      cache={cache}
      detailId={targetId}
      administration={new UserAdministrationService(api, cache, refresh)}
      onOutcome={onOutcome}
    />
  );
  await screen.findByText(user.email);
  return { catalog, user, fetch, refresh, onOutcome };
}

describe('browser user administration', () => {
  it.each([
    { state: 'pending_activation' },
    { state: 'disabled' },
    { state: 'deleted' },
    { self: true }
  ])('does not load role choices for %j', async (target) => {
    const { fetch } = await mount({ ...target, extra: ['ASSIGN_SUPER_ADMIN'] });
    expect(screen.queryByRole('button', { name: /assign|remove/i })).not.toBeInTheDocument();
    expect(fetch.mock.calls.some(([url]) => url.startsWith('/api/v1/roles?'))).toBe(false);
  });

  it('offers only applicable ordinary controls and never offers Super Admin removal', async () => {
    await mount({ assigned: ['ROLE_SUPER_ADMIN', 'ROLE_USER'] });
    expect(await screen.findByRole('button', { name: 'Assign ROLE_CUSTOM' })).toBeVisible();
    expect(screen.getByRole('button', { name: 'Remove ROLE_USER' })).toBeVisible();
    expect(
      screen.queryByRole('button', {
        name: /remove ROLE_SUPER_ADMIN|delete user|assign ROLE_USER/i
      })
    ).not.toBeInTheDocument();
  });

  it('requires separate elevation permission and an explicit confirmation; a server denial is not success', async () => {
    const { catalog, fetch, user, onOutcome, refresh } = await mount({
      extra: ['ASSIGN_SUPER_ADMIN']
    });
    const operator = userEvent.setup();
    const action = await screen.findByRole('button', { name: 'Assign ROLE_SUPER_ADMIN' });
    await operator.click(action);
    const dialog = screen.getByRole('dialog', { name: 'Assign ROLE_SUPER_ADMIN?' });
    expect(within(dialog).getByRole('button', { name: 'Cancel' })).toHaveFocus();
    await operator.keyboard('{Escape}');
    expect(dialog).not.toBeInTheDocument();
    expect(fetch.mock.calls.some(([, options]) => options.method === 'PUT')).toBe(false);
    await operator.click(action);
    await operator.click(screen.getByRole('button', { name: 'Confirm Super Admin assignment' }));
    const [path, options] = fetch.mock.calls.find(([, init]) => init.method === 'PUT') ?? [];
    expect(path).toBe(`/api/v1/users/${user.user_id}/roles/${catalog[1]?.role_id}`);
    expect(options?.body).toBe(JSON.stringify({ confirm: true, confirm_super_admin: true }));
    expect(onOutcome).toHaveBeenCalledWith({
      message: 'The server denied the change.',
      error: true
    });
    expect(refresh).toHaveBeenCalledWith(user.user_id, 'unconfirmed');
  });

  it('does not offer elevation without the distinct permission even when the role is available', async () => {
    await mount();
    await screen.findByRole('button', { name: 'Assign ROLE_CUSTOM' });
    expect(
      screen.queryByRole('button', { name: 'Assign ROLE_SUPER_ADMIN' })
    ).not.toBeInTheDocument();
  });

  it('keeps the admin session identifier out of the page and submits only a confirmed safe reason', async () => {
    const id = crypto.randomUUID();
    const target = directoryUser({ state: 'active' });
    const session = {
      session_id: id,
      browser_family: 'Firefox',
      device_class: 'desktop',
      current: false,
      created_at: '2026-10-25T12:00:00+00:00',
      last_activity_at: '2026-10-25T12:00:00+00:00',
      idle_expires_at: '2026-10-25T18:00:00+00:00',
      absolute_expires_at: '2026-10-26T12:00:00+00:00',
      remembered: false
    };
    const fetch = vi.fn((url: string, options: RequestInit) =>
      Promise.resolve(
        directoryResponse(
          options.method === 'DELETE'
            ? null
            : url.includes('/sessions?')
              ? { ...directoryPage([]), total_records: 1, records: [session] }
              : target
        )
      )
    );
    const { api, cache, service } = directoryDependencies(fetch, [
      'READ_USERS',
      'READ_SESSIONS',
      'DELETE_SESSIONS'
    ]);
    cleanups.push(() => cache.dispose());
    await cache.load();
    const refresh = vi.fn();
    const onOutcome = vi.fn();
    render(
      <UsersPage
        service={service}
        cache={cache}
        detailId={target.user_id}
        administration={new UserAdministrationService(api, cache, refresh)}
        onOutcome={onOutcome}
      />
    );
    const operator = userEvent.setup();
    const trigger = await screen.findByRole('button', { name: /revoke desktop.*Firefox/i });
    await operator.click(trigger);
    const reason = screen.getByRole('textbox', { name: 'Reason (required)' });
    const confirm = screen.getByRole('button', { name: 'Confirm revocation' });
    expect(confirm).toBeDisabled();
    await operator.type(reason, 'password');
    expect(confirm).toBeDisabled();
    await operator.clear(reason);
    await operator.type(reason, 'Device retired');
    expect(document.body.innerHTML).not.toContain(id);
    await operator.click(confirm);
    expect(fetch).toHaveBeenCalledWith(
      `/api/v1/users/${target.user_id}/sessions/${id}`,
      expect.objectContaining({
        method: 'DELETE',
        body: JSON.stringify({ reason: 'Device retired' })
      })
    );
    expect(onOutcome).toHaveBeenCalledWith({
      message: 'The server confirmed the change.',
      error: false
    });
    expect(refresh).toHaveBeenCalledWith(target.user_id, 'committed');
  });

  it('clears obsolete detail on a conflict and rechecks the principal and target without replay', async () => {
    const target = directoryUser({ state: 'active' });
    const updated = { ...target, state: 'disabled' };
    const detailUrl = `/api/v1/users/${target.user_id}`;
    let reads = 0;
    const fetch = vi.fn((url: string, options: RequestInit) =>
      Promise.resolve(
        options.method === 'POST'
          ? new Response(JSON.stringify({ status: 'fail', data: { message: 'State conflict.' } }), {
              status: 409,
              headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
            })
          : directoryResponse(url === detailUrl && reads++ === 0 ? target : updated)
      )
    );
    const { api, cache, service } = directoryDependencies(fetch, ['READ_USERS', 'DISABLE_USERS']);
    cleanups.push(() => cache.dispose());
    await cache.load();
    const refresh = vi.fn(() => {
      service.invalidate();
      void cache.signal('authority-changed');
    });
    render(
      <UsersPage
        service={service}
        cache={cache}
        detailId={target.user_id}
        administration={new UserAdministrationService(api, cache, refresh)}
        onOutcome={() => {}}
      />
    );
    const operator = userEvent.setup();
    await operator.click(await screen.findByRole('button', { name: 'Disable user' }));
    await operator.click(screen.getByRole('button', { name: 'Confirm change' }));
    expect(await screen.findByText('disabled')).toBeVisible();
    expect(screen.queryByRole('button', { name: 'Disable user' })).not.toBeInTheDocument();
    expect(refresh).toHaveBeenCalledWith(target.user_id, 'unconfirmed');
    expect(fetch.mock.calls.filter(([, options]) => options.method === 'POST')).toHaveLength(1);
    expect(fetch.mock.calls.filter(([url]) => url === detailUrl)).toHaveLength(2);
  });

  it('waits for the actual write settlement rather than reconciling an unresolved change', async () => {
    const target = directoryUser({ state: 'active' });
    const write = deferred<Response>();
    const { api, cache, service } = directoryDependencies(
      (_url, options) =>
        options.method === 'POST' ? write.promise : Promise.resolve(directoryResponse(target)),
      ['READ_USERS', 'DISABLE_USERS']
    );
    cleanups.push(() => cache.dispose());
    await cache.load();
    const detail = await service.detail(target.user_id);
    if (!detail.ok) throw new Error('Expected fixture detail');
    const refresh = vi.fn();
    const admin = new UserAdministrationService(api, cache, refresh);
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    try {
      const settled = vi.fn();
      const notice = vi.fn();
      const change = admin.change(detail.value, { kind: 'disable' }, '', notice).then(settled);
      await vi.advanceTimersByTimeAsync(8_000);
      expect(settled).not.toHaveBeenCalled();
      expect(refresh).not.toHaveBeenCalled();
      await cache.signal('authority-changed');
      expect(admin.isPending(target.user_id)).toBe(true);
      expect(await admin.change(detail.value, { kind: 'disable' }, '')).toMatchObject({
        ok: false,
        error: { kind: 'conflict' }
      });
      write.resolve(directoryResponse(null));
      await change;
      expect(notice).not.toHaveBeenCalled();
      expect(refresh).toHaveBeenCalledExactlyOnceWith(target.user_id, 'committed');
      expect(admin.isPending(target.user_id)).toBe(false);
    } finally {
      vi.useRealTimers();
    }
  });

  it.each([401, 403])(
    'retires stale authority after an ordinary %i admin session read without replay',
    async (status) => {
      const target = directoryUser();
      const fetch = vi.fn(() => Promise.resolve(directoryResponse(null, status)));
      const { api, cache } = directoryDependencies(fetch, ['READ_USERS', 'READ_SESSIONS']);
      cleanups.push(() => cache.dispose());
      await cache.load();
      const refresh = vi.fn(() => {
        void cache.signal('authority-changed');
      });
      const admin = new UserAdministrationService(api, cache, refresh);
      const signal = new AbortController().signal;
      await admin.sessions(target.user_id, 1, signal);
      expect(refresh).toHaveBeenCalledExactlyOnceWith(
        target.user_id,
        status === 401 ? 'access-rejected' : 'authority-rejected'
      );
      await cache.load();
      await admin.sessions(target.user_id, 1, signal);
      expect(fetch).toHaveBeenCalledTimes(1);
    }
  );

  it('discards an obsolete denied read after a newer authority scope has read successfully', async () => {
    const oldRead = deferred<Response>();
    const fetch = vi.fn(() => Promise.resolve(directoryResponse(directoryPage([]))));
    fetch.mockReturnValueOnce(oldRead.promise);
    const { api, cache } = directoryDependencies(fetch, ['READ_USERS', 'READ_SESSIONS']);
    cleanups.push(() => cache.dispose());
    await cache.load();
    const refresh = vi.fn();
    const admin = new UserAdministrationService(api, cache, refresh);
    const target = directoryUser();
    const signal = new AbortController().signal;
    const first = admin.sessions(target.user_id, 1, signal);
    await cache.signal('authority-changed');
    expect((await admin.sessions(target.user_id, 1, signal)).ok).toBe(true);
    oldRead.resolve(directoryResponse(null, 403));
    expect(await first).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    expect(refresh).not.toHaveBeenCalled();
  });

  it('registers a complete protected detail route without carrying extra query fields', () => {
    const id = crypto.randomUUID();
    const route = matchApplicationRoute(`/admin/users/${id}`);
    expect(route).toMatchObject({
      targetUserId: id,
      access: 'protected',
      requirements: [{ kind: 'authenticated' }, { kind: 'permissions', all: ['READ_USERS'] }]
    });
    expect(route?.normalizeSearch?.('?page=2&secret=no').search).not.toContain('secret');
    expect(matchApplicationRoute('/admin/users/not-a-user')).toBeUndefined();
  });
});
