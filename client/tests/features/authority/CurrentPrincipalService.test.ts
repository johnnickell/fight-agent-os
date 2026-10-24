import { afterEach, describe, expect, it, vi } from 'vitest';

import { ApiClient } from '@/api/ApiClient';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import {
  CurrentPrincipalService,
  decodePrincipalResponse
} from '@/features/authority/CurrentPrincipalService';
import { evaluateAccess } from '@/features/authority/evaluateAccess';

import { deferred } from '../../apiFixtures';

const userId = crypto.randomUUID();
const view = {
  user_id: userId,
  email: 'reader@example.test',
  roles: ['ROLE_USER'],
  permissions: ['READ_OWN_SESSIONS', 'VIEW_DASHBOARD']
};
const response = (data: unknown = view) =>
  new Response(JSON.stringify({ status: 'success', data }), {
    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
  });
const caches: AuthorityCache[] = [];
function setup(fetch = vi.fn(() => Promise.resolve(response()))) {
  let token: string | null = 'memory-only';
  const api = new ApiClient(
    { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
    { fetch, accessTokens: { getAccessToken: () => token } }
  );
  const service = new CurrentPrincipalService(api);
  const cache = new AuthorityCache(service.load);
  caches.push(cache);
  return { service, cache, fetch, token: (value: string | null) => (token = value) };
}
afterEach(() => {
  caches.splice(0).forEach((cache) => cache.dispose());
});

describe('production current-principal service', () => {
  it('maps exactly the safe View, reads current Bearer each time and never caches beside AuthorityCache', async () => {
    const d = setup();
    await d.cache.load();
    const state = d.cache.getSnapshot();
    expect(state).toEqual({
      status: 'authenticated',
      principal: { userId, email: view.email, roles: view.roles, permissions: view.permissions }
    });
    if (state.status === 'authenticated') {
      expect(Object.isFrozen(state.principal)).toBe(true);
      expect(Object.isFrozen(state.principal.permissions)).toBe(true);
    }
    await d.cache.load();
    expect(d.fetch).toHaveBeenCalledTimes(1);
    const [path, init] = d.fetch.mock.calls[0] as unknown as [string, RequestInit];
    expect(path).toBe('/api/v1/me');
    expect(init).toMatchObject({
      method: 'GET',
      cache: 'no-store',
      credentials: 'same-origin',
      mode: 'same-origin',
      redirect: 'error',
      referrerPolicy: 'no-referrer'
    });
    expect(init.body).toBeUndefined();
    expect(new Headers(init.headers).get('Authorization')).toBe('Bearer memory-only');
    d.token('replacement-memory-only');
    await d.cache.signal('credentials-changed');
    expect(d.fetch).toHaveBeenCalledTimes(2);
    const second = d.fetch.mock.calls[1] as unknown as [string, RequestInit];
    expect(new Headers(second[1].headers).get('Authorization')).toBe(
      'Bearer replacement-memory-only'
    );
  });

  it('accepts empty authority and custom package-shaped names without role-name bypass or catalog inference', async () => {
    const d = setup(
      vi.fn(() =>
        Promise.resolve(response({ ...view, roles: ['ROLE_SUPER_ADMIN'], permissions: [] }))
      )
    );
    await d.cache.load();
    expect(evaluateAccess(d.cache.getSnapshot(), [{ kind: 'authenticated' }])).toBe('allowed');
    expect(
      evaluateAccess(d.cache.getSnapshot(), [{ kind: 'permissions', all: ['VIEW_DASHBOARD'] }])
    ).toBe('forbidden');
    expect(
      decodePrincipalResponse({ ...view, roles: ['ROLE_CUSTOM'], permissions: ['READ_CUSTOM'] })
    ).not.toBeNull();
    const longest = decodePrincipalResponse({ ...view, permissions: ['A'.repeat(255)] });
    expect(longest).not.toBeNull();
    if (longest)
      expect(
        evaluateAccess({ status: 'authenticated', principal: longest }, [
          { kind: 'permissions', all: ['A'.repeat(255)] }
        ])
      ).toBe('allowed');
  });

  it.each([
    null,
    [],
    {},
    { ...view, userId },
    { ...view, user_id: 'invalid' },
    { ...view, email: 'Reader@example.test' },
    { ...view, roles: ['ROLE_USER', 'ROLE_USER'] },
    { ...view, permissions: ['VIEW_DASHBOARD', 'VIEW_DASHBOARD'] },
    { ...view, roles: ['administrator'] },
    { ...view, roles: ['ROLE_CUSTOM_2'] },
    { ...view, permissions: ['view_dashboard'] },
    { ...view, permissions: ['VIEW__DASHBOARD'] },
    { ...view, permissions: ['VIEW_DASHBOARD\n'] },
    { ...view, permissions: ['A'.repeat(256)] },
    { ...view, permissions: [42] },
    { ...view, permissions: null },
    { ...view, session_id: 'private-session-marker' },
    { ...view, authentication_version: 42 },
    { ...view, password_hash: 'private-hash-marker' },
    { ...view, grants: [] },
    { ...view, audit: 'private-sql-marker' }
  ])('rejects malformed or expanded authority atomically (%#)', async (data) => {
    const d = setup(vi.fn(() => Promise.resolve(response(data))));
    await d.cache.load();
    expect(d.cache.getSnapshot()).toEqual({ status: 'error', reason: 'protocol' });
    expect(JSON.stringify(d.cache.getSnapshot())).not.toContain('private-');
    expect(d.fetch).toHaveBeenCalledOnce();
  });

  it('rejects sparse lists and never salvages valid entries', () => {
    expect(decodePrincipalResponse({ ...view, roles: new Array<string>(1) })).toBeNull();
    expect(decodePrincipalResponse({ ...view, permissions: ['VIEW_DASHBOARD', '*'] })).toBeNull();
  });

  it.each([
    [401, 'Unauthorized.', 'terminal'],
    [403, 'Forbidden.', 'protocol'],
    [500, 'Internal server error.', 'network']
  ] as const)(
    'maps safe HTTP %i without restoration/retry inside the service',
    async (status, message, expected) => {
      const d = setup(
        vi.fn(() =>
          Promise.resolve(
            new Response(JSON.stringify({ status: 'error', message }), {
              status,
              headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
            })
          )
        )
      );
      expect(await d.service.load(new AbortController().signal)).toEqual({ status: expected });
      expect(d.fetch).toHaveBeenCalledOnce();
    }
  );

  it('requires credentials and no-store, and preserves network/protocol distinctions', async () => {
    const absent = setup();
    absent.token(null);
    await absent.cache.load();
    expect(absent.cache.getSnapshot().status).toBe('terminal');
    expect(absent.fetch).not.toHaveBeenCalled();
    const missingHeader = setup(
      vi.fn(() =>
        Promise.resolve(
          new Response(JSON.stringify({ status: 'success', data: view }), {
            headers: { 'Content-Type': 'application/json' }
          })
        )
      )
    );
    await missingHeader.cache.load();
    expect(missingHeader.cache.getSnapshot()).toEqual({ status: 'error', reason: 'protocol' });
    const offline = setup(vi.fn(() => Promise.reject(new Error('private-network-marker'))));
    await offline.cache.load();
    expect(offline.cache.getSnapshot()).toEqual({ status: 'error', reason: 'network' });
  });

  it('fences an old body success across authority change, logout and explicit reauthentication', async () => {
    const oldBody = deferred<string>();
    const old = new Response('', {
      headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
    });
    const text = vi.spyOn(old, 'text').mockReturnValue(oldBody.promise);
    const fetch = vi
      .fn()
      .mockResolvedValueOnce(old)
      .mockImplementation(() => Promise.resolve(response({ ...view, permissions: [] })));
    const d = setup(fetch);
    const first = d.cache.load();
    await vi.waitFor(() => expect(text).toHaveBeenCalled());
    await d.cache.signal('authority-changed');
    oldBody.resolve(JSON.stringify({ status: 'success', data: view }));
    await first;
    expect(d.cache.getSnapshot()).toMatchObject({
      status: 'authenticated',
      principal: { permissions: [] }
    });
    const scope = d.cache.captureScope();
    await d.cache.signal('logout');
    expect(scope.signal.aborted).toBe(true);
    expect(scope.isCurrent()).toBe(false);
    expect(d.cache.getSnapshot()).toEqual({ status: 'anonymous', reason: 'logout' });
    await d.cache.signal('credentials-changed');
    expect(d.fetch).toHaveBeenCalledTimes(2);
    await d.cache.beginAuthentication().acceptCredentials();
    expect(d.cache.getSnapshot()).toMatchObject({
      status: 'authenticated',
      principal: { permissions: [] }
    });
  });
});
