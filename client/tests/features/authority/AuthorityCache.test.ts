import { afterEach, describe, expect, it, vi } from 'vitest';

import { AuthorityCache, observeAuthorityResume } from '@/features/authority/AuthorityCache';

import { deferred } from '../../apiFixtures';
import { principal } from '../../authorityFixtures';

import type { PrincipalLoader, PrincipalLoadResult } from '@/features/authority/AuthorityCache';

const caches: AuthorityCache[] = [];
function cacheWith(loader: PrincipalLoader, now?: () => number) {
  const cache = new AuthorityCache(loader, now);
  caches.push(cache);
  return cache;
}
afterEach(() => {
  caches.forEach((cache) => cache.dispose());
  caches.length = 0;
  vi.useRealTimers();
  vi.restoreAllMocks();
});
const success = { status: 'principal', principal } as const;

describe('principal ownership and transitions', () => {
  it('does not dispatch a queued request retired before the loader starts', async () => {
    const loader = vi.fn<PrincipalLoader>().mockResolvedValue(success);
    const cache = cacheWith(loader);
    const queued = cache.load();
    await cache.signal('logout');
    await queued;
    expect(loader).not.toHaveBeenCalled();
  });

  it('does not reopen the presentation of a disposed owner', async () => {
    const cache = cacheWith(() => Promise.resolve(success));
    cache.dispose();
    await cache.beginAuthentication().acceptCredentials();
    expect(cache.getSnapshot()).toEqual({ status: 'terminal' });
  });
  it('starts unknown, coalesces demands and adopts only a decoded result', async () => {
    const response = deferred<PrincipalLoadResult>();
    const loader = vi.fn(() => response.promise);
    const cache = cacheWith(loader);
    expect(cache.getSnapshot()).toEqual({ status: 'unknown' });
    const loading = cache.load();
    expect(cache.getSnapshot()).toEqual({ status: 'loading' });
    expect(cache.load()).toBe(loading);
    response.resolve(success);
    await loading;
    expect(cache.getSnapshot()).toEqual({ status: 'authenticated', principal });
    await cache.load();
    expect(loader).toHaveBeenCalledOnce();
    expect(cache.captureScope().isCurrent()).toBe(true);
  });

  it.each([
    [{ status: 'anonymous' }, { status: 'anonymous', reason: 'absent' }],
    [{ status: 'terminal' }, { status: 'terminal' }],
    [{ status: 'network' }, { status: 'error', reason: 'network' }],
    [{ status: 'protocol' }, { status: 'error', reason: 'protocol' }],
    [
      { status: 'principal', principal: { ...principal, permissions: ['OK', 3] } },
      { status: 'error', reason: 'protocol' }
    ]
  ] as const)('maps safe loader outcome %j without partial authority', async (result, expected) => {
    const cache = cacheWith(() => Promise.resolve(result));
    await cache.load();
    expect(cache.getSnapshot()).toEqual(expected);
    expect(cache.captureScope().isCurrent()).toBe(false);
  });

  it('rejects malformed runtime loader outcomes rather than treating them as anonymous', async () => {
    const cache = cacheWith(() => Promise.resolve(null as unknown as PrincipalLoadResult));
    await cache.load();
    expect(cache.getSnapshot()).toEqual({ status: 'error', reason: 'protocol' });
  });

  it('turns thrown loader errors into a safe failure and permits one explicit retry', async () => {
    const loader = vi
      .fn<PrincipalLoader>()
      .mockRejectedValueOnce(new Error('private response data'))
      .mockResolvedValue(success);
    const cache = cacheWith(loader);
    await cache.load();
    expect(cache.getSnapshot()).toEqual({ status: 'error', reason: 'network' });
    expect(loader).toHaveBeenCalledOnce();
    await cache.load();
    expect(cache.getSnapshot()).toEqual({ status: 'authenticated', principal });
  });

  it('removes authority before protected-data abort callbacks can read it', async () => {
    const cache = cacheWith(() => Promise.resolve(success));
    await cache.load();
    const observed: string[] = [];
    cache.captureScope().signal.addEventListener('abort', () => {
      observed.push(cache.getSnapshot().status);
    });
    await cache.signal('refresh-started');
    expect(observed).toEqual(['stale']);
  });

  it('does not dispatch reentrant load demand while an invalidation is retiring private data', async () => {
    const loader = vi.fn<PrincipalLoader>().mockResolvedValue(success);
    const cache = cacheWith(loader);
    await cache.load();
    let reentrant = Promise.resolve();
    cache.captureScope().signal.addEventListener('abort', () => {
      reentrant = cache.load();
    });
    await cache.signal('refresh-started');
    await reentrant;
    expect(cache.getSnapshot()).toEqual({ status: 'stale' });
    expect(loader).toHaveBeenCalledOnce();
    await cache.signal('credentials-changed');
    expect(cache.getSnapshot().status).toBe('authenticated');
    expect(loader).toHaveBeenCalledTimes(2);
  });

  it('retires principal and protected-data scope at refresh start, not only after success', async () => {
    const loader = vi.fn<PrincipalLoader>().mockResolvedValue(success);
    const cache = cacheWith(loader);
    await cache.load();
    const scope = cache.captureScope();
    await cache.signal('refresh-started');
    expect(cache.getSnapshot()).toEqual({ status: 'stale' });
    expect(scope.signal.aborted).toBe(true);
    expect(scope.isCurrent()).toBe(false);
    await cache.load();
    expect(loader).toHaveBeenCalledOnce();
    await cache.signal('credentials-changed');
    expect(loader).toHaveBeenCalledTimes(2);
    expect(cache.getSnapshot()).toEqual({ status: 'authenticated', principal });
  });

  it('distinguishes refresh outage and requires an explicit new recovery attempt', async () => {
    const cache = cacheWith(() => Promise.resolve(success));
    await cache.load();
    await cache.signal('refresh-failed');
    expect(cache.getSnapshot()).toEqual({ status: 'refresh-failed' });
    await cache.load();
    expect(cache.getSnapshot().status).toBe('refresh-failed');
    await cache.beginAuthentication().acceptCredentials();
    expect(cache.getSnapshot().status).toBe('authenticated');
  });

  it('refetches after authority changes and exposes the new empty permission projection honestly', async () => {
    const response = deferred<PrincipalLoadResult>();
    const loader = vi
      .fn<PrincipalLoader>()
      .mockResolvedValueOnce(success)
      .mockReturnValueOnce(response.promise);
    const cache = cacheWith(loader);
    await cache.load();
    const oldScope = cache.captureScope();
    const reload = cache.signal('authority-changed');
    expect(cache.getSnapshot().status).toBe('loading');
    expect(oldScope.signal.aborted).toBe(true);
    response.resolve({ status: 'principal', principal: { ...principal, permissions: [] } });
    await reload;
    expect(cache.getSnapshot()).toEqual({
      status: 'authenticated',
      principal: { ...principal, permissions: [] }
    });
  });

  it.each(['logout', 'terminal'] as const)(
    'fences old load and unsolicited credentials after %s',
    async (signal) => {
      const old = deferred<PrincipalLoadResult>();
      const loader = vi
        .fn<PrincipalLoader>()
        .mockReturnValueOnce(old.promise)
        .mockResolvedValue(success);
      const cache = cacheWith(loader);
      const loading = cache.load();
      await Promise.resolve();
      await cache.signal(signal);
      expect(loader.mock.calls[0]?.[0].aborted).toBe(true);
      old.resolve(success);
      await loading;
      await cache.signal('credentials-changed');
      await cache.signal('authority-changed');
      await cache.load();
      expect(loader).toHaveBeenCalledOnce();
      expect(cache.getSnapshot()).toEqual(
        signal === 'logout' ? { status: 'anonymous', reason: 'logout' } : { status: 'terminal' }
      );
      await cache.beginAuthentication().acceptCredentials();
      expect(cache.getSnapshot().status).toBe('authenticated');
    }
  );

  it('delivers explicit logout cleanup after authority retirement, not for involuntary expiry', async () => {
    const cache = cacheWith(() => Promise.resolve(success));
    await cache.load();
    const scope = cache.captureScope();
    const observed: boolean[] = [];
    const unsubscribe = cache.subscribeLogout(() => {
      observed.push(scope.signal.aborted && !scope.isCurrent());
    });
    await cache.signal('logout');
    expect(observed).toEqual([true]);
    await cache.signal('terminal');
    expect(observed).toEqual([true]);
    await cache.signal('logout');
    expect(observed).toEqual([true, true]);
    unsubscribe();
    await cache.signal('logout');
    expect(observed).toEqual([true, true]);
  });

  it('delivers in-progress logout cleanup even if an abort callback disposes the owner', async () => {
    const cache = cacheWith(() => Promise.resolve(success));
    await cache.load();
    const cleanup = vi.fn();
    cache.subscribeLogout(cleanup);
    cache.captureScope().signal.addEventListener('abort', () => cache.dispose(), { once: true });
    await cache.signal('logout');
    expect(cleanup).toHaveBeenCalledOnce();
    expect(cache.getSnapshot()).toEqual({ status: 'terminal' });
    await cache.signal('logout');
    expect(cleanup).toHaveBeenCalledOnce();
  });

  it('does not overwrite a newer authentication attempt started by logout cleanup', async () => {
    const cache = cacheWith(() => Promise.resolve(success));
    await cache.load();
    let attempt: ReturnType<AuthorityCache['beginAuthentication']> | undefined;
    cache.subscribeLogout(() => {
      attempt = cache.beginAuthentication();
    });
    await cache.signal('logout');
    expect(cache.getSnapshot()).toEqual({ status: 'unknown' });
    expect(attempt).toBeDefined();
    await attempt?.acceptCredentials();
    expect(cache.getSnapshot()).toEqual({ status: 'authenticated', principal });
  });

  it.each(['success', 'terminal', 'throw'] as const)(
    'ignores old %s after a newer principal, even when abort is ignored',
    async (outcome) => {
      const old = deferred<PrincipalLoadResult>();
      const updated = { ...principal, userId: crypto.randomUUID(), email: 'new@example.test' };
      const loader = vi
        .fn<PrincipalLoader>()
        .mockReturnValueOnce(old.promise)
        .mockResolvedValue({ status: 'principal', principal: updated });
      const cache = cacheWith(loader);
      const first = cache.load();
      await Promise.resolve();
      await cache.signal('credentials-changed');
      if (outcome === 'throw') old.reject(new Error('old failure'));
      else old.resolve(outcome === 'success' ? success : { status: 'terminal' });
      await first;
      expect(cache.getSnapshot()).toEqual({ status: 'authenticated', principal: updated });
    }
  );

  it('rejects superseded authentication attempts, including delayed completion after logout', async () => {
    const loader = vi.fn<PrincipalLoader>().mockResolvedValue(success);
    const cache = cacheWith(loader);
    const first = cache.beginAuthentication();
    const second = cache.beginAuthentication();
    await first.acceptCredentials();
    first.fail();
    await cache.signal('credentials-changed');
    expect(loader).not.toHaveBeenCalled();
    await cache.signal('logout');
    await second.acceptCredentials();
    expect(cache.getSnapshot()).toEqual({ status: 'anonymous', reason: 'logout' });
    const current = cache.beginAuthentication();
    current.fail();
    await current.acceptCredentials();
    expect(cache.getSnapshot()).toEqual({ status: 'refresh-failed' });
    await cache.beginAuthentication().acceptCredentials();
    expect(loader).toHaveBeenCalledOnce();
  });

  it('retires the request scope when a different identity is discovered', async () => {
    const loader = vi
      .fn<PrincipalLoader>()
      .mockResolvedValueOnce(success)
      .mockResolvedValueOnce({
        status: 'principal',
        principal: { ...principal, userId: crypto.randomUUID() }
      });
    const cache = cacheWith(loader);
    await cache.load();
    const loading = cache.signal('credentials-changed');
    const scopeDuringLoad = cache.captureScope();
    await loading;
    expect(scopeDuringLoad.isCurrent()).toBe(false);
    expect(scopeDuringLoad.signal.aborted).toBe(true);
    expect(cache.captureScope().isCurrent()).toBe(true);
  });

  it('does not automatically reload anonymous sessions and retires permanently on disposal', async () => {
    const loader = vi.fn<PrincipalLoader>().mockResolvedValue({ status: 'anonymous' });
    const cache = cacheWith(loader);
    await cache.load();
    await cache.load();
    expect(loader).toHaveBeenCalledOnce();
    cache.dispose();
    await cache.signal('credentials-changed');
    await cache.beginAuthentication().acceptCredentials();
    await cache.load();
    expect(loader).toHaveBeenCalledOnce();
  });
});

describe('reentrant retirement barriers (R-01)', () => {
  const endings = ['logout', 'terminal', 'dispose'] as const;
  function end(cache: AuthorityCache, ending: (typeof endings)[number]) {
    if (ending === 'dispose') cache.dispose();
    else void cache.signal(ending);
  }
  function endedState(ending: (typeof endings)[number]) {
    return ending === 'logout' ? { status: 'anonymous', reason: 'logout' } : { status: 'terminal' };
  }

  it.each(endings)('preserves %s reentered during identity adoption', async (ending) => {
    const loader = vi
      .fn<PrincipalLoader>()
      .mockResolvedValueOnce(success)
      .mockResolvedValue({
        status: 'principal',
        principal: { ...principal, userId: crypto.randomUUID() }
      });
    const cache = cacheWith(loader);
    await cache.load();
    const loading = cache.signal('credentials-changed');
    const scope = cache.captureScope();
    scope.signal.addEventListener('abort', () => end(cache, ending), { once: true });
    await loading;
    expect(cache.getSnapshot()).toEqual(endedState(ending));
    expect(scope.isCurrent()).toBe(false);
    expect(cache.captureScope().isCurrent()).toBe(false);
    await cache.signal('credentials-changed');
    expect(loader).toHaveBeenCalledTimes(2);
  });

  describe.each(endings)('when an abort callback invokes %s', (ending) => {
    it.each([
      'load',
      'invalidation',
      'attempt',
      'resume',
      'anonymous',
      'logout',
      'terminal'
    ] as const)(
      'does not let the interrupted %s transition overwrite the ended context',
      async (operation) => {
        const response = deferred<PrincipalLoadResult>();
        const loader = vi.fn<PrincipalLoader>().mockResolvedValue(success);
        const cache = cacheWith(loader);
        if (operation !== 'load') await cache.load();
        let loading = Promise.resolve();
        if (operation === 'anonymous') {
          loader.mockReturnValueOnce(response.promise);
          loading = cache.signal('credentials-changed');
          await Promise.resolve();
        }
        const scope = cache.captureScope();
        scope.signal.addEventListener('abort', () => end(cache, ending), { once: true });
        switch (operation) {
          case 'load':
            await cache.load();
            break;
          case 'invalidation':
            await cache.signal('authority-changed');
            break;
          case 'attempt': {
            const attempt = cache.beginAuthentication();
            await attempt.acceptCredentials();
            attempt.fail();
            break;
          }
          case 'resume':
            cache.resume();
            break;
          case 'anonymous':
            response.resolve({ status: 'anonymous' });
            await loading;
            break;
          default:
            await cache.signal(operation);
        }
        expect(cache.getSnapshot()).toEqual(endedState(ending));
        expect(scope.signal.aborted).toBe(true);
        expect(cache.captureScope().isCurrent()).toBe(false);
        await cache.load();
        expect(loader).toHaveBeenCalledTimes(
          operation === 'load' ? 0 : operation === 'anonymous' ? 2 : 1
        );
        if (ending !== 'dispose') {
          await cache.beginAuthentication().acceptCredentials();
          expect(cache.getSnapshot()).toEqual({ status: 'authenticated', principal });
          expect(cache.captureScope().isCurrent()).toBe(true);
        }
      }
    );
  });

  it('keeps nested retirement closed until both scope and request abort callbacks finish', async () => {
    const response = deferred<PrincipalLoadResult>();
    const loader = vi
      .fn<PrincipalLoader>()
      .mockReturnValueOnce(response.promise)
      .mockResolvedValue(success);
    const cache = cacheWith(loader);
    const loading = cache.load();
    await Promise.resolve();
    const scope = cache.captureScope();
    scope.signal.addEventListener(
      'abort',
      () => {
        void cache.signal('terminal');
        void cache.load();
      },
      { once: true }
    );
    loader.mock.calls[0]?.[0].addEventListener(
      'abort',
      () => {
        void cache.load();
        void cache.signal('terminal');
      },
      { once: true }
    );
    await cache.signal('credentials-changed');
    response.resolve(success);
    await loading;
    expect(cache.getSnapshot()).toEqual({ status: 'terminal' });
    expect(loader).toHaveBeenCalledOnce();
    await cache.beginAuthentication().acceptCredentials();
    expect(cache.captureScope().isCurrent()).toBe(true);
    expect(loader).toHaveBeenCalledTimes(2);
  });
});

describe('bounded freshness and tab lifecycle', () => {
  it('rejects a request started on an invalid clock even when the clock later recovers', async () => {
    let now = Number.NaN;
    const cache = cacheWith(
      () => {
        now = 1000;
        return Promise.resolve(success);
      },
      () => now
    );
    await cache.load();
    expect(cache.getSnapshot().status).toBe('stale');
  });
  it('expires mounted subscriptions 60 seconds from request start, not receipt, without polling', async () => {
    vi.useFakeTimers();
    let now = 1000;
    const response = deferred<PrincipalLoadResult>();
    const loader = vi.fn(() => response.promise);
    const cache = cacheWith(loader, () => now);
    const listener = vi.fn();
    const unsubscribe = cache.subscribe(listener);
    const loading = cache.load();
    now += 50_000;
    response.resolve(success);
    await loading;
    now += 9999;
    expect(cache.getSnapshot().status).toBe('authenticated');
    now++;
    await vi.advanceTimersByTimeAsync(10_000);
    expect(cache.getSnapshot()).toEqual({ status: 'stale' });
    expect(listener).toHaveBeenCalledTimes(3);
    await vi.advanceTimersByTimeAsync(180_000);
    expect(loader).toHaveBeenCalledOnce();
    unsubscribe();
    cache.resume();
    expect(listener).toHaveBeenCalledTimes(3);
  });

  it.each([60_000, 60_001, -1, Number.NaN])(
    'denies results outside the request budget or discontinuous clock (%s)',
    async (elapsed) => {
      let now = 1000;
      const response = deferred<PrincipalLoadResult>();
      const cache = cacheWith(
        () => response.promise,
        () => now
      );
      const loading = cache.load();
      now += elapsed;
      response.resolve(success);
      await loading;
      expect(cache.getSnapshot()).toEqual({ status: 'stale' });
    }
  );

  it('checks expiry synchronously on an action even when the expiry timer has not run', async () => {
    let now = 0;
    const cache = cacheWith(
      () => Promise.resolve(success),
      () => now
    );
    await cache.load();
    const scope = cache.captureScope();
    now = 60_000;
    expect(scope.isCurrent()).toBe(false);
    expect(scope.signal.aborted).toBe(true);
    expect(cache.getSnapshot().status).toBe('stale');
  });

  it('invalidates once on hidden-tab return/focus and detaches listeners on teardown', async () => {
    const cache = cacheWith(() => Promise.resolve(success));
    await cache.load();
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    const detach = observeAuthorityResume(cache, document, window);
    window.dispatchEvent(new Event('focus'));
    expect(cache.getSnapshot().status).toBe('authenticated');
    visibility.mockReturnValue('hidden');
    document.dispatchEvent(new Event('visibilitychange'));
    visibility.mockReturnValue('visible');
    window.dispatchEvent(new Event('focus'));
    expect(cache.getSnapshot().status).toBe('stale');
    await cache.load();
    document.dispatchEvent(new Event('visibilitychange'));
    expect(cache.getSnapshot().status).toBe('authenticated');
    detach();
    visibility.mockReturnValue('hidden');
    document.dispatchEvent(new Event('visibilitychange'));
    visibility.mockReturnValue('visible');
    window.dispatchEvent(new Event('focus'));
    expect(cache.getSnapshot().status).toBe('authenticated');
  });

  it('fences a load interrupted by visibility return', async () => {
    const response = deferred<PrincipalLoadResult>();
    const cache = cacheWith(() => response.promise);
    const load = cache.load();
    cache.resume();
    response.resolve(success);
    await load;
    expect(cache.getSnapshot().status).toBe('stale');
  });
});
