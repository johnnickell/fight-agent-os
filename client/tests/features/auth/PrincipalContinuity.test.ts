import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ApiClient } from '@/api/ApiClient';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { LoginService } from '@/features/auth/LoginService';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { SessionCoordinator } from '@/features/auth/SessionCoordinator';
import { SessionService } from '@/features/auth/SessionService';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { CurrentPrincipalService } from '@/features/authority/CurrentPrincipalService';
import { evaluateAccess } from '@/features/authority/evaluateAccess';

import { jsonResponse } from '../../activationFixtures';
import { deferred } from '../../apiFixtures';
import { loginSuccess } from '../../loginFixtures';

import type { ApiTransport } from '@/api/ApiClient';
import type { SessionChannel } from '@/features/auth/SessionChannel';

const view = {
  user_id: crypto.randomUUID(),
  email: 'reader@example.test',
  roles: ['ROLE_USER'],
  permissions: ['VIEW_DASHBOARD']
};
const owners: (() => void)[] = [];
function setup(
  me: ApiTransport = () => Promise.resolve(jsonResponse(view)),
  channel?: SessionChannel
) {
  const tokens = new MemoryAccessToken();
  const refresh = deferred<Response>();
  let pendingRefresh = false;
  const fetch = vi.fn((url: string, init: RequestInit) => {
    if (url.endsWith('/me')) return me(url, init);
    if (url.endsWith('/csrf')) {
      const expiry = Math.floor(Date.now() / 1000) + 900;
      return Promise.resolve(
        jsonResponse({ proof: `${expiry}.${'c'.repeat(64)}`, expires_at: expiry })
      );
    }
    if (url.endsWith('/logout')) return Promise.resolve(jsonResponse(null));
    return pendingRefresh && url.endsWith('/refresh')
      ? refresh.promise
      : Promise.resolve(loginSuccess());
  });
  const api = new ApiClient(
    { schemaVersion: 1, apiBasePath: '/api/v1' },
    { fetch, accessTokens: tokens }
  );
  const csrf = new CsrfProofService(api);
  const authority = new AuthorityCache(new CurrentPrincipalService(api).load);
  const coordinator = new SessionCoordinator({
    tokens,
    authority,
    session: new SessionService(api, csrf, tokens),
    login: new LoginService(api, csrf, tokens),
    ...(channel ? { channel } : {})
  });
  owners.push(() => {
    coordinator.dispose();
    authority.dispose();
  });
  return {
    tokens,
    authority,
    coordinator,
    fetch,
    refresh,
    holdRefresh: () => {
      pendingRefresh = true;
    }
  };
}
const flush = () => vi.advanceTimersByTimeAsync(0);
const allowed = (authority: AuthorityCache) =>
  evaluateAccess(authority.getSnapshot(), [{ kind: 'permissions', all: ['VIEW_DASHBOARD'] }]);

beforeEach(() => vi.useFakeTimers());
afterEach(() => {
  owners.splice(0).forEach((dispose) => dispose());
  vi.useRealTimers();
});

describe('one production /me cache across session transitions', () => {
  it('does not turn an authority-change hint into authentication work before credentials exist', async () => {
    const channel: SessionChannel = { onmessage: null, postMessage: vi.fn(), close: vi.fn() };
    const d = setup(undefined, channel);
    channel.onmessage?.({
      data: {
        version: 1,
        sender: crypto.randomUUID().replaceAll('-', ''),
        sequence: 1,
        kind: 'authority-changed'
      }
    } as MessageEvent<unknown>);
    await flush();
    expect(d.fetch).not.toHaveBeenCalled();
    expect(d.coordinator.getSnapshot().status).toBe('unknown');
    expect(d.authority.getSnapshot().status).toBe('stale');
  });
  it('loads after login, retires at refresh start, refetches after rotation, and clears on logout', async () => {
    const next = deferred<Response>();
    let reads = 0;
    const d = setup(() => (++reads === 1 ? Promise.resolve(jsonResponse(view)) : next.promise));
    expect(d.authority.getSnapshot().status).toBe('unknown');
    await d.coordinator.submit({ email: 'reader@example.test', password: 'synthetic' }, false);
    await flush();
    expect(allowed(d.authority)).toBe('allowed');
    const scope = d.authority.captureScope();
    d.holdRefresh();
    const rotating = d.coordinator.restore();
    expect(d.tokens.getAccessToken()).toBeNull();
    expect(d.authority.getSnapshot().status).toBe('stale');
    expect(scope.signal.aborted).toBe(true);
    expect(allowed(d.authority)).toBe('unavailable');
    await flush();
    d.refresh.resolve(loginSuccess());
    await rotating;
    await flush();
    expect(d.authority.getSnapshot().status).toBe('loading');
    next.resolve(jsonResponse({ ...view, permissions: [] }));
    await flush();
    expect(allowed(d.authority)).toBe('forbidden');
    const logout = d.coordinator.logout();
    expect(d.authority.getSnapshot()).toEqual({ status: 'anonymous', reason: 'logout' });
    expect(d.tokens.getAccessToken()).toBeNull();
    await logout;
    expect(reads).toBe(2);
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);
  });

  it('clears authority on recoverable refresh failure without confusing it with revocation', async () => {
    const d = setup();
    await d.coordinator.restore();
    await flush();
    expect(allowed(d.authority)).toBe('allowed');
    d.holdRefresh();
    const refresh = d.coordinator.restore();
    await flush();
    d.refresh.resolve(
      jsonResponse({ status: 'error', message: 'Internal server error.' }, 500, false)
    );
    await refresh;
    expect(d.tokens.getAccessToken()).toBeNull();
    expect(d.authority.getSnapshot().status).toBe('refresh-failed');
    expect(d.coordinator.getSnapshot().status).toBe('network-degraded');
    expect(allowed(d.authority)).toBe('unavailable');
  });

  it('rejects a pending /me after logout and never lets it replace an explicit new login', async () => {
    const old = deferred<Response>();
    let reads = 0;
    const d = setup(() =>
      ++reads === 1
        ? old.promise
        : Promise.resolve(jsonResponse({ ...view, user_id: crypto.randomUUID(), permissions: [] }))
    );
    await d.coordinator.restore();
    await flush();
    const logout = d.coordinator.logout();
    await logout;
    await d.coordinator.submit({ email: 'reader@example.test', password: 'synthetic' }, false);
    await flush();
    expect(allowed(d.authority)).toBe('forbidden');
    old.resolve(jsonResponse(view));
    await flush();
    expect(allowed(d.authority)).toBe('forbidden');
    expect(d.coordinator.getSnapshot().status).toBe('authenticated');
  });

  it('rejects old /me success and error after replacement without ending the newer session', async () => {
    for (const oldStatus of [200, 401]) {
      const old = deferred<Response>();
      let reads = 0;
      const d = setup(() =>
        ++reads === 1 ? old.promise : Promise.resolve(jsonResponse({ ...view, permissions: [] }))
      );
      await d.coordinator.restore();
      await flush();
      expect(d.authority.getSnapshot().status).toBe('loading');
      d.coordinator.authorityChanged();
      await flush();
      expect(allowed(d.authority)).toBe('forbidden');
      old.resolve(
        oldStatus === 200
          ? jsonResponse(view)
          : jsonResponse({ status: 'error', message: 'Unauthorized.' }, 401, false)
      );
      await flush();
      expect(allowed(d.authority)).toBe('forbidden');
      expect(d.coordinator.getSnapshot().status).toBe('authenticated');
      expect(d.tokens.getAccessToken()).not.toBeNull();
    }
  });

  it('ends credentials on a current /me 401, never loops, and requires explicit login across the barrier', async () => {
    let rejected = true;
    const d = setup(() =>
      Promise.resolve(
        rejected
          ? jsonResponse({ status: 'error', message: 'Unauthorized.' }, 401, false)
          : jsonResponse(view)
      )
    );
    await d.coordinator.restore();
    await flush();
    expect(d.authority.getSnapshot().status).toBe('terminal');
    expect(d.coordinator.getSnapshot().status).toBe('terminal');
    expect(d.tokens.getAccessToken()).toBeNull();
    const calls = d.fetch.mock.calls.length;
    await d.coordinator.restore();
    d.coordinator.authorityChanged();
    await flush();
    expect(d.fetch).toHaveBeenCalledTimes(calls);
    rejected = false;
    await d.coordinator.submit({ email: 'reader@example.test', password: 'synthetic' }, false);
    await flush();
    expect(allowed(d.authority)).toBe('allowed');
  });

  it('keeps malformed/network results non-authoritative and clears on terminal/late load', async () => {
    let result: Response | Error = jsonResponse({ ...view, session_id: 'private-session-marker' });
    const d = setup(() =>
      result instanceof Error ? Promise.reject(result) : Promise.resolve(result)
    );
    await d.coordinator.restore();
    await flush();
    expect(d.authority.getSnapshot()).toEqual({ status: 'error', reason: 'protocol' });
    result = new Error('private-sql-marker');
    await d.authority.load();
    expect(d.authority.getSnapshot()).toEqual({ status: 'error', reason: 'network' });
    result = jsonResponse(view);
    await d.authority.load();
    expect(allowed(d.authority)).toBe('allowed');
    d.coordinator.terminal();
    expect(d.tokens.getAccessToken()).toBeNull();
    expect(d.authority.getSnapshot().status).toBe('terminal');
    expect(JSON.stringify(d.authority)).toBe('{}');
  });

  it('refetches separately after a bounded peer credential handoff and authority-change intent, never a projection', async () => {
    const peers = new Set<SessionChannel>();
    const messages: unknown[] = [];
    const channel = (): SessionChannel => {
      const result: SessionChannel = {
        onmessage: null,
        close: () => {
          peers.delete(result);
        },
        postMessage: (data: unknown) => {
          messages.push(data);
          for (const peer of peers)
            if (peer !== result)
              queueMicrotask(() => peer.onmessage?.({ data } as MessageEvent<unknown>));
        }
      };
      peers.add(result);
      return result;
    };
    let permissions = ['VIEW_DASHBOARD'];
    const read = () => Promise.resolve(jsonResponse({ ...view, permissions }));
    const a = setup(read, channel());
    const first = a.coordinator.restore();
    await vi.advanceTimersByTimeAsync(50);
    await first;
    await flush();
    const b = setup(read, channel());
    const second = b.coordinator.restore();
    await vi.advanceTimersByTimeAsync(50);
    await second;
    await flush();
    expect(allowed(a.authority)).toBe('allowed');
    expect(allowed(b.authority)).toBe('allowed');
    expect(b.fetch.mock.calls.some(([url]) => url.endsWith('/refresh'))).toBe(false);
    permissions = [];
    const aScope = a.authority.captureScope();
    const bScope = b.authority.captureScope();
    a.coordinator.authorityChanged();
    await flush();
    expect(aScope.isCurrent()).toBe(false);
    expect(bScope.isCurrent()).toBe(false);
    expect(allowed(a.authority)).toBe('forbidden');
    expect(allowed(b.authority)).toBe('forbidden');
    expect(a.fetch.mock.calls.filter(([url]) => url.endsWith('/me'))).toHaveLength(2);
    expect(b.fetch.mock.calls.filter(([url]) => url.endsWith('/me'))).toHaveLength(2);
    const change = messages.find(
      (message) => (message as { kind: string }).kind === 'authority-changed'
    );
    expect(Object.keys(change as object).sort()).toEqual(['kind', 'sender', 'sequence', 'version']);
    const serialized = JSON.stringify(messages);
    for (const forbidden of [view.user_id, view.email, 'ROLE_USER', 'VIEW_DASHBOARD', 'private-'])
      expect(serialized).not.toContain(forbidden);
    const logout = a.coordinator.logout();
    await flush();
    await logout;
    expect(a.tokens.getAccessToken()).toBeNull();
    expect(b.tokens.getAccessToken()).toBeNull();
    expect(b.authority.getSnapshot().status).toBe('anonymous');
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);
  });
});
