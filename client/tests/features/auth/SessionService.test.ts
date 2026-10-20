import { describe, expect, it, vi } from 'vitest';

import { ApiClient } from '@/api/ApiClient';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { LoginService } from '@/features/auth/LoginService';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { SessionCoordinator } from '@/features/auth/SessionCoordinator';
import { SessionService } from '@/features/auth/SessionService';

import { jsonResponse } from '../../activationFixtures';
import { deferred } from '../../apiFixtures';
import { loginSuccess } from '../../loginFixtures';

import type { ApiTransport } from '@/api/ApiClient';
import type { SessionChannel } from '@/features/auth/SessionChannel';
import type { SessionLocks } from '@/features/auth/SessionCoordinator';

function dependencies(post: ApiTransport) {
  const tokens = new MemoryAccessToken();
  const csrfCalls: string[] = [];
  const api = new ApiClient(
    { schemaVersion: 1, apiBasePath: '/api/v1' },
    {
      accessTokens: tokens,
      fetch: (url, init) => {
        if (url.endsWith('/auth/csrf')) {
          csrfCalls.push(url);
          const expires = Math.floor(Date.now() / 1000) + 900;
          return Promise.resolve(
            jsonResponse({ proof: `${expires}.${'c'.repeat(64)}`, expires_at: expires })
          );
        }
        return post(url, init);
      }
    }
  );
  const csrf = new CsrfProofService(api);
  return { service: new SessionService(api, csrf, tokens), tokens, csrf, csrfCalls, api };
}

describe('single-tab cookie-only refresh and logout', () => {
  it('coalesces rotation, retires old access immediately and accepts only the replacement in memory', async () => {
    const pending = deferred<Response>();
    const post = vi.fn().mockImplementation(() => pending.promise);
    const { service, tokens, csrfCalls } = dependencies(post);
    tokens.accept('old.access.token', performance.now());
    const first = service.refresh();
    expect(service.refresh()).toBe(first);
    expect(tokens.getAccessToken()).toBeNull();
    await vi.waitFor(() => expect(post).toHaveBeenCalledOnce());
    const [url, init] = post.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/api/v1/auth/refresh');
    expect(init.body).toBe('{}');
    expect(init.credentials).toBe('same-origin');
    expect(new Headers(init.headers).has('Authorization')).toBe(false);
    expect(new Headers(init.headers).has('Cookie')).toBe(false);
    pending.resolve(loginSuccess());
    expect(await first).toEqual({ ok: true, value: true, correlationId: null });
    expect(tokens.getAccessToken()).toBe('header.payload.signature');
    expect(csrfCalls).toHaveLength(1);
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);
    expect(JSON.stringify(service)).toBe('{}');
    service.dispose();
    expect(tokens.getAccessToken()).toBeNull();
  });

  it.each([
    [401, 'Unauthorized.', 'authentication'],
    [409, 'Conflict.', 'conflict'],
    [403, 'Forbidden.', 'authorization'],
    [429, 'Too many requests.', 'rate-limited'],
    [500, 'Internal server error.', 'system']
  ] as const)(
    'maps %s safely without retaining access or automatically retrying',
    async (status, message, kind) => {
      const post = vi
        .fn()
        .mockResolvedValue(jsonResponse({ status: 'error', message }, status, false));
      const { service, tokens, csrf } = dependencies(post);
      tokens.accept('old.access.token', performance.now());
      expect(await service.refresh()).toMatchObject({ ok: false, error: { kind } });
      expect(tokens.getAccessToken()).toBeNull();
      expect(post).toHaveBeenCalledOnce();
      expect(csrf.current() !== null).toBe(kind === 'conflict');
      service.dispose();
    }
  );

  it('clears access synchronously and lets an in-flight cookie land before revoking that current session', async () => {
    const pending = deferred<Response>();
    const post = vi
      .fn()
      .mockImplementation((url: string) =>
        url.endsWith('/refresh') ? pending.promise : Promise.resolve(jsonResponse(null))
      );
    const { service, tokens } = dependencies(post);
    const refresh = service.refresh();
    await vi.waitFor(() => expect(post).toHaveBeenCalledOnce());
    const logout = service.logout();
    expect(service.logout()).toBe(logout);
    expect(tokens.getAccessToken()).toBeNull();
    expect(await service.refresh()).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    expect(post).toHaveBeenCalledOnce();
    pending.resolve(loginSuccess());
    expect(await refresh).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    expect(await logout).toEqual({ ok: true, value: true, correlationId: null });
    expect(post).toHaveBeenCalledTimes(2);
    const [url, init] = post.mock.calls[1] as [string, RequestInit];
    expect(url).toBe('/api/v1/auth/logout');
    expect(init.body).toBe('{}');
    expect(tokens.getAccessToken()).toBeNull();
  });

  it('reports failed logout as uncertainty while retaining no local authority or proof', async () => {
    const post = vi.fn().mockRejectedValue(new Error('private-token SQL marker'));
    const { service, tokens, csrf } = dependencies(post);
    tokens.accept('old.access.token', performance.now());
    const pending = service.logout();
    expect(tokens.getAccessToken()).toBeNull();
    const result = await pending;
    expect(result).toMatchObject({ ok: false, error: { kind: 'network' } });
    expect(JSON.stringify(result)).not.toContain('private-token');
    expect(csrf.current()).toBeNull();
    expect(post).toHaveBeenCalledOnce();
  });

  it('fences delayed refresh and login results after teardown or local invalidation', async () => {
    const pending = deferred<Response>();
    const post = vi.fn().mockImplementation(() => pending.promise);
    const { service, tokens, api, csrf } = dependencies(post);
    const refresh = service.refresh();
    await vi.waitFor(() => expect(post).toHaveBeenCalledOnce());
    service.dispose();
    pending.resolve(loginSuccess());
    expect(await refresh).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    expect(tokens.getAccessToken()).toBeNull();
    expect(await service.refresh()).toMatchObject({ ok: false });
    expect(await service.logout()).toMatchObject({ ok: false });
    const loginPending = deferred<Response>();
    post.mockImplementation(() => loginPending.promise);
    const login = new LoginService(api, csrf, tokens);
    const result = login.submit({ email: 'person@example.test', password: 'synthetic' }, false);
    await vi.waitFor(() => expect(post).toHaveBeenCalledTimes(2));
    tokens.clear();
    loginPending.resolve(loginSuccess());
    expect(await result).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    expect(tokens.getAccessToken()).toBeNull();
  });

  it('does not dispatch a retired queued logout or clear a newer composed login', async () => {
    const queued = deferred<void>();
    const locks: SessionLocks = {
      request: async (_name, _options, work) => {
        await queued.promise;
        await work();
      }
    };
    let cookieContext = 'old';
    const revokedContexts: string[] = [];
    const post = vi.fn<ApiTransport>().mockImplementation((url) => {
      if (url.endsWith('/auth/login')) {
        cookieContext = 'new';
        return Promise.resolve(loginSuccess());
      }
      if (url.endsWith('/auth/logout')) {
        revokedContexts.push(cookieContext);
        return Promise.resolve(jsonResponse(null));
      }
      throw new Error('Unexpected mutation');
    });
    const { service, tokens, csrf, api } = dependencies(post);
    const channel: SessionChannel = { onmessage: null, postMessage: () => {}, close: () => {} };
    const coordinator = new SessionCoordinator({
      session: service,
      login: new LoginService(api, csrf, tokens),
      tokens,
      locks,
      channel
    });
    try {
      const oldLogout = coordinator.logout();
      channel.onmessage?.({
        data: {
          version: 1,
          sender: crypto.randomUUID().replaceAll('-', ''),
          sequence: 1,
          kind: 'logout'
        }
      } as MessageEvent<unknown>);
      coordinator.showLogin();
      expect(
        await coordinator.submit({ email: 'person@example.test', password: 'synthetic' }, false)
      ).toMatchObject({ ok: true });
      expect(tokens.getAccessToken()).toBe('header.payload.signature');
      queued.resolve();
      expect(await oldLogout).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
      expect(revokedContexts).toEqual([]);
      expect(post).toHaveBeenCalledOnce();
      expect(tokens.getAccessToken()).toBe('header.payload.signature');
      expect(coordinator.getSnapshot().status).toBe('authenticated');
    } finally {
      coordinator.dispose();
    }
  });

  it('rejects malformed refresh/logout success without adopting untrusted material', async () => {
    const post = vi
      .fn()
      .mockImplementation(() =>
        Promise.resolve(jsonResponse({ access_token: 'private', refresh: 'forbidden' }))
      );
    const { service, tokens } = dependencies(post);
    expect(await service.refresh()).toMatchObject({ ok: false, error: { kind: 'protocol' } });
    expect(tokens.getAccessToken()).toBeNull();
    expect(await service.logout()).toMatchObject({ ok: false, error: { kind: 'protocol' } });
    expect(tokens.getAccessToken()).toBeNull();
  });
});
