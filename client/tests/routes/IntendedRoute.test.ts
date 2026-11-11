import { afterEach, describe, expect, it } from 'vitest';

import { IntendedRoute } from '@/routes/IntendedRoute';

import { authenticatedCache, principal, reportRoute } from '../authorityFixtures';

import type { RegisteredRoute } from '@/routes/GuardedRoute';

const cache = authenticatedCache();
const intents: IntendedRoute[] = [];
function intentWith(
  routes: () => readonly RegisteredRoute[] = () => [reportRoute],
  now?: () => number
) {
  const intent = new IntendedRoute(cache, routes, 'https://example.test', now);
  intents.push(intent);
  return intent;
}
afterEach(async () => {
  intents.forEach((intent) => intent.dispose());
  intents.length = 0;
  await cache.signal('logout');
});

describe('bounded volatile intended navigation', () => {
  it('builds a canonical route with sorted approved queries, drops fragments and consumes once after fresh authority', async () => {
    const intent = intentWith();
    expect(intent.capture('/reports?sort=recent&page=2#discard-me')).toBe(true);
    expect(intent.consume()).toBeNull();
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBe('/reports?page=2&sort=recent');
    expect(intent.consume()).toBeNull();
  });

  it('restores the canonical root only when registered, opted in and currently permitted', async () => {
    const intent = intentWith(() => [{ ...reportRoute, pathname: '/' }]);
    expect(intent.capture('/#discard-me')).toBe(true);
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBe('/');
    expect(intent.consume()).toBeNull();
  });

  it.each([
    'https://evil.test/reports',
    'https://example.test/reports',
    '//evil.test/reports',
    'javascript:alert(1)',
    'data:text/html,test',
    '//evil.test',
    '/reports/../reports',
    '/%2freports',
    '/%5creports',
    '/%252freports',
    '/rep%6frts',
    '/reports%00',
    '/reports\\evil',
    '/reports\n',
    '/reports\t',
    '/reports?password=secret',
    '/reports?email=person%40example.test',
    '/reports?token=abc',
    '/reports?returnTo=https://evil.test',
    '/reports?page=1&page=2',
    '/reports?page=3',
    '/reports?page=%0a',
    '/reports?page=%ff',
    '/reports?page=%',
    '/reports?page=%252f',
    '/reports?sort=recent%00',
    '/reports?sort=recent+value',
    '/unknown',
    '/app',
    '/app/reports',
    '/reports#' + 'a'.repeat(2048),
    '/reports?username:password@evil.test'
  ])('rejects unsafe, unregistered or unclassified targets (%#)', (target) => {
    expect(intentWith().capture(target)).toBe(false);
  });

  it('only restores explicitly opted-in protected routes, never public or grant/auth paths', () => {
    for (const route of [
      { ...reportRoute, requirements: [{ kind: 'public' }] as const },
      { pathname: reportRoute.pathname, requirements: reportRoute.requirements },
      { ...reportRoute, requirements: [] },
      { ...reportRoute, pathname: '/password-reset' },
      { ...reportRoute, pathname: '/invitation/accept' }
    ]) {
      expect(intentWith(() => [route]).capture(route.pathname)).toBe(false);
    }
    expect(intentWith(() => [reportRoute, reportRoute]).capture(reportRoute.pathname)).toBe(false);
  });

  it('will not accept credential fields even if a route mistakenly opts them in', () => {
    const intent = intentWith(() => [{ ...reportRoute, intendedRoute: { token: ['secret'] } }]);
    expect(intent.capture('/reports?token=secret')).toBe(false);
  });

  it('does not replace or renew an existing intent on redirects', async () => {
    let now = 0;
    const intent = intentWith(undefined, () => now);
    expect(intent.capture('/reports?page=1')).toBe(true);
    now = 599_999;
    expect(intent.capture('/reports?page=2')).toBe(false);
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBe('/reports?page=1');
  });

  it.each([600_000, 600_001, -1, Number.NaN])(
    'discards expired or clock-discontinuous intent (%s)',
    async (elapsed) => {
      let now = 1000;
      const intent = intentWith(undefined, () => now);
      intent.capture('/reports');
      now += elapsed;
      await cache.beginAuthentication().acceptCredentials();
      expect(intent.consume()).toBeNull();
    }
  );

  it.each([600_000, -1, Number.NaN])(
    'rejects capture and clears previous intent after expiry or clock discontinuity (%s)',
    async (elapsed) => {
      let now = 1000;
      const intent = intentWith(undefined, () => now);
      expect(intent.capture('/reports?page=1')).toBe(true);
      now += elapsed;
      expect(intent.capture('/reports?page=2')).toBe(false);
      now = 700_000;
      await cache.beginAuthentication().acceptCredentials();
      expect(intent.consume()).toBeNull();
      expect(intent.capture('/reports?page=2')).toBe(true);
      expect(intent.consume()).toBe('/reports?page=2');
    }
  );

  it('re-resolves current route metadata instead of replaying old permission requirements', async () => {
    let routes = [reportRoute];
    const intent = intentWith(() => routes);
    intent.capture('/reports');
    routes = [{ ...reportRoute, requirements: [{ kind: 'permissions', all: ['DELETE_REPORTS'] }] }];
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBeNull();
    routes = [reportRoute];
    expect(intent.consume()).toBeNull();
    intent.capture('/reports');
    routes = [];
    expect(intent.consume()).toBeNull();
  });

  it('consumes denied intent without a login redirect loop and does not grant a fallback dashboard', async () => {
    const deniedCache = authenticatedCache({ ...principal, permissions: [] });
    const intent = new IntendedRoute(deniedCache, () => [reportRoute], 'https://example.test');
    intent.capture('/reports');
    await deniedCache.load();
    expect(intent.consume()).toBeNull();
    intent.dispose();
    deniedCache.dispose();
  });

  it('clears on explicit logout but can retain eligible intent across involuntary terminal expiry', async () => {
    const intent = intentWith();
    intent.capture('/reports');
    await cache.signal('terminal');
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBe('/reports');
    intent.capture('/reports');
    await cache.signal('logout');
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBeNull();
  });

  it.each(['scope-abort', 'subscriber', 'logout-listener'] as const)(
    'R-02: discards logout intent when a %s callback supersedes logout with terminal',
    async (callback) => {
      await cache.beginAuthentication().acceptCredentials();
      // Register first so the navigation owner cannot rely on observing logout's snapshot.
      const unsubscribe =
        callback === 'logout-listener'
          ? cache.subscribeLogout(() => void cache.signal('terminal'))
          : cache.subscribe(() => {
              const state = cache.getSnapshot();
              if (
                callback === 'subscriber' &&
                state.status === 'anonymous' &&
                state.reason === 'logout'
              )
                void cache.signal('terminal');
            });
      try {
        const intent = intentWith();
        expect(intent.capture('/reports')).toBe(true);
        if (callback === 'scope-abort') {
          cache
            .captureScope()
            .signal.addEventListener('abort', () => void cache.signal('terminal'), {
              once: true
            });
        }
        await cache.signal('logout');
        expect(cache.getSnapshot()).toEqual({ status: 'terminal' });
        await cache.beginAuthentication().acceptCredentials();
        expect(cache.getSnapshot().status).toBe('authenticated');
        expect(intent.consume()).toBeNull();

        // The cleanup must not permanently prevent a genuinely later navigation intent.
        expect(intent.capture('/reports?page=2')).toBe(true);
        await cache.signal('terminal');
        await cache.beginAuthentication().acceptCredentials();
        expect(intent.consume()).toBe('/reports?page=2');
      } finally {
        unsubscribe();
      }
    }
  );

  it('fails closed if the route registry cannot resolve current metadata', async () => {
    let unavailable = false;
    const intent = intentWith(() => {
      if (unavailable) throw new Error('Registry unavailable');
      return [reportRoute];
    });
    expect(intent.capture('/reports')).toBe(true);
    unavailable = true;
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBeNull();
  });

  it('rejects non-origin configuration rather than trusting an arbitrary redirect base', () => {
    for (const origin of [
      'https://user:password@example.test',
      'https://example.test/path',
      'file:///app'
    ]) {
      expect(() => new IntendedRoute(cache, () => [reportRoute], origin)).toThrow(
        'Invalid application origin'
      );
    }
  });
});
