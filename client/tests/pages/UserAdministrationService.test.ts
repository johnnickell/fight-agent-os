import { UserAdministrationService } from '@fight/react/account';
import {
  directoryDependencies,
  directoryPage,
  directoryResponse
} from '@fixtures/userDirectoryFixtures';
import { expect, it, vi } from 'vitest';

import { deferred } from '../apiFixtures';

import type { DirectoryUser } from '@fight/react/account';

type AdminAction = Parameters<UserAdministrationService['change']>[1];

const timestamp = '2026-10-25T12:00:00+00:00';
const user: DirectoryUser = {
  userId: crypto.randomUUID(),
  email: 'target@example.test',
  state: 'active',
  roles: [],
  createdAt: timestamp,
  updatedAt: timestamp
};
const custom = { id: crypto.randomUUID(), name: 'ROLE_CUSTOM', managed: false };
const elevated = { id: crypto.randomUUID(), name: 'ROLE_SUPER_ADMIN', managed: true };
const sessionId = crypto.randomUUID();
const permissions = [
  'READ_USERS',
  'DISABLE_USERS',
  'ENABLE_USERS',
  'RESTORE_USERS',
  'READ_ROLES',
  'MANAGE_USER_ROLES',
  'ASSIGN_SUPER_ADMIN',
  'READ_SESSIONS',
  'DELETE_SESSIONS'
];

async function setup(
  reply: () => Response | Promise<Response> = () => directoryResponse(null),
  grants = permissions
) {
  const fetch = vi.fn<(url: string, options: RequestInit) => Promise<Response>>(() =>
    Promise.resolve(reply())
  );
  const refresh = vi.fn();
  const { api, cache } = directoryDependencies(fetch, grants);
  await cache.load();
  return { fetch, cache, refresh, service: new UserAdministrationService(api, cache, refresh) };
}

it.each<{
  action: AdminAction;
  target?: DirectoryUser;
  suffix: string;
  method: string;
  body: object;
}>([
  { action: { kind: 'disable' }, suffix: '/disable', method: 'POST', body: { confirm: true } },
  {
    action: { kind: 'enable' },
    target: { ...user, state: 'disabled' },
    suffix: '/enable',
    method: 'POST',
    body: { confirm: true }
  },
  {
    action: { kind: 'restore', state: 'pending_activation' },
    target: { ...user, state: 'deleted' },
    suffix: '/restore',
    method: 'POST',
    body: { confirm: true, restoration_state: 'pending_activation' }
  },
  {
    action: { kind: 'assign', role: elevated },
    suffix: `/roles/${elevated.id}`,
    method: 'PUT',
    body: { confirm: true, confirm_super_admin: true }
  },
  {
    action: { kind: 'remove', role: custom },
    target: { ...user, roles: [custom] },
    suffix: `/roles/${custom.id}`,
    method: 'DELETE',
    body: { confirm: true }
  },
  {
    action: { kind: 'revoke', sessionId },
    suffix: `/sessions/${sessionId}`,
    method: 'DELETE',
    body: { reason: 'Device retired' }
  }
])(
  'uses the bounded $method $suffix contract',
  async ({ action, target = user, suffix, method, body }) => {
    const { service, fetch, refresh } = await setup();
    const onSettled = vi.fn();
    const result = await service.change(target, action, ' Device retired ', onSettled);
    expect(fetch).toHaveBeenCalledExactlyOnceWith(
      `/api/v1/users/${user.userId}${suffix}`,
      expect.objectContaining({ method, body: JSON.stringify(body) })
    );
    expect(result).toMatchObject({ ok: true, value: true });
    expect(onSettled).toHaveBeenCalledWith(result);
    expect(refresh).toHaveBeenCalledWith(user.userId, 'committed');
  }
);

it.each<{ target: DirectoryUser; action: AdminAction }>([
  { target: { ...user, userId: 'invalid' }, action: { kind: 'disable' } },
  { target: user, action: { kind: 'assign', role: { ...custom, id: 'invalid' } } },
  { target: user, action: { kind: 'revoke', sessionId: 'invalid' } },
  { target: user, action: { kind: 'revoke', sessionId } }
])('refuses a malformed $action.kind request before dispatch', async ({ target, action }) => {
  const { service, fetch } = await setup();
  expect(await service.change(target, action, 'token')).toMatchObject({
    error: { kind: 'protocol' }
  });
  expect(fetch).not.toHaveBeenCalled();
});

it.each([
  ' ',
  'x'.repeat(501),
  'retired\nnow',
  'retired\u007f',
  'https://example.test',
  'PaSsWoRd'
])('rejects unusable or sensitive reason text', async (reason) => {
  const { service, fetch } = await setup();
  expect(await service.change(user, { kind: 'revoke', sessionId }, reason)).toMatchObject({
    error: { kind: 'protocol' }
  });
  expect(fetch).not.toHaveBeenCalled();
});

it('requires a current principal and directory permission even with an action grant', async () => {
  const { service, fetch } = await setup(undefined, ['DISABLE_USERS']);
  expect(await service.change(user, { kind: 'disable' }, '')).toMatchObject({
    error: { kind: 'authorization' }
  });
  expect(fetch).not.toHaveBeenCalled();
});

it('does not submit a role change to the operator’s own account', async () => {
  const { service, cache, fetch } = await setup();
  const snapshot = cache.getSnapshot();
  if (snapshot.status !== 'authenticated') throw new Error('Expected fixture principal');
  const self = { ...user, userId: snapshot.principal.userId };
  expect(await service.change(self, { kind: 'assign', role: custom }, '')).toMatchObject({
    error: { kind: 'authorization' }
  });
  expect(fetch).not.toHaveBeenCalled();
});

it('projects safe catalog roles and caps navigation at the endpoint page bound', async () => {
  const { service, fetch } = await setup(() =>
    directoryResponse(
      directoryPage([], {
        page: 2,
        total_pages: 1001,
        total_records: 25001,
        records: [
          {
            role_id: elevated.id,
            name: elevated.name,
            managed: true,
            revision: `${crypto.randomUUID()}:1`
          }
        ]
      })
    )
  );
  const signal = new AbortController().signal;
  expect(await service.roles(2, signal)).toMatchObject({
    ok: true,
    value: { rows: [elevated], totalPages: 1000 }
  });
  expect(fetch).toHaveBeenCalledExactlyOnceWith(
    '/api/v1/roles?page=2&per_page=25',
    expect.objectContaining({ signal })
  );
});

it.each([
  [401, 'authentication', 'access-rejected'],
  [403, 'authorization', 'authority-rejected']
] as const)(
  'retires a rejected catalog read (%s) until explicit retry',
  async (status, kind, outcome) => {
    const { service, fetch, refresh } = await setup(() => directoryResponse(null, status));
    const signal = new AbortController().signal;
    expect(await service.roles(1, signal)).toMatchObject({ error: { kind } });
    expect(await service.roles(1, signal)).toMatchObject({ error: { kind: 'authorization' } });
    expect(refresh).toHaveBeenCalledExactlyOnceWith(undefined, outcome);
    expect(fetch).toHaveBeenCalledTimes(1);
    service.retryRead();
    expect(await service.roles(1, signal)).toMatchObject({ error: { kind } });
    expect(fetch).toHaveBeenCalledTimes(2);
  }
);

it('does not treat an aborted catalog rejection as fresh authority evidence', async () => {
  const response = deferred<Response>();
  const { service, refresh } = await setup(() => response.promise);
  const controller = new AbortController();
  const loading = service.roles(1, controller.signal);
  controller.abort();
  response.resolve(directoryResponse(null, 403));
  expect(await loading).toMatchObject({ error: { kind: 'cancelled' } });
  expect(refresh).not.toHaveBeenCalled();
});

it('keeps admin session reads separate from self sessions and rejects malformed targets', async () => {
  const { service, cache, fetch } = await setup();
  const snapshot = cache.getSnapshot();
  if (snapshot.status !== 'authenticated') throw new Error('Expected fixture principal');
  const signal = new AbortController().signal;
  expect(await service.sessions(snapshot.principal.userId, 1, signal)).toMatchObject({
    error: { kind: 'authorization' }
  });
  expect(await service.sessions('invalid', 1, signal)).toMatchObject({
    error: { kind: 'protocol' }
  });
  expect(fetch).not.toHaveBeenCalled();
});

it('projects bounded other-user session results', async () => {
  const { service, fetch } = await setup(() =>
    directoryResponse(
      directoryPage([], {
        page: 2,
        total_records: 26,
        total_pages: 2,
        records: [
          {
            session_id: sessionId,
            browser_family: 'Firefox',
            device_class: 'desktop',
            current: false,
            created_at: timestamp,
            last_activity_at: timestamp,
            idle_expires_at: timestamp,
            absolute_expires_at: timestamp,
            remembered: false
          }
        ]
      })
    )
  );
  const signal = new AbortController().signal;
  expect(await service.sessions(user.userId, 2, signal)).toMatchObject({
    ok: true,
    value: { rows: [{ sessionId, current: false }], totalPages: 2 }
  });
  expect(fetch).toHaveBeenCalledExactlyOnceWith(
    `/api/v1/users/${user.userId}/sessions?page=2&per_page=25`,
    expect.objectContaining({ signal })
  );
});

it.each([
  [401, 'access-rejected'],
  [409, 'unconfirmed']
] as const)(
  'releases a pending target after %s and reconciles without replay',
  async (status, outcome) => {
    const response = deferred<Response>();
    const { service, fetch, refresh } = await setup(() => response.promise);
    const pending: boolean[] = [];
    const unsubscribe = service.subscribe(() => pending.push(service.isPending(user.userId)));
    const change = service.change(user, { kind: 'disable' }, '');
    expect(await service.change(user, { kind: 'disable' }, '')).toMatchObject({
      error: { kind: 'conflict' }
    });
    expect(refresh).not.toHaveBeenCalled();
    response.resolve(directoryResponse(null, status));
    expect(await change).toMatchObject({ ok: false });
    unsubscribe();
    expect(pending).toEqual([true, false]);
    expect(refresh).toHaveBeenCalledExactlyOnceWith(user.userId, outcome);
    expect(fetch).toHaveBeenCalledTimes(1);
  }
);

it('does not publish a settled result across a retired scope', async () => {
  const response = deferred<Response>();
  const { service, cache, refresh } = await setup(() => response.promise);
  const onSettled = vi.fn();
  const change = service.change(user, { kind: 'disable' }, '', onSettled);
  await cache.signal('authority-changed');
  response.resolve(directoryResponse(null));
  expect(await change).toMatchObject({ error: { kind: 'cancelled' } });
  expect(onSettled).not.toHaveBeenCalled();
  expect(refresh).toHaveBeenCalledExactlyOnceWith(user.userId, 'committed');
});
