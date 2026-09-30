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
    expect(intent.capture('/app/reports?sort=recent&page=2#discard-me')).toBe(true);
    expect(intent.consume()).toBeNull();
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBe('/app/reports?page=2&sort=recent');
    expect(intent.consume()).toBeNull();
  });

  it.each([
    'https://evil.test/app/reports',
    'https://example.test/app/reports',
    '//evil.test/app/reports',
    'javascript:alert(1)',
    'data:text/html,test',
    '/app//evil.test',
    '/app/reports/../reports',
    '/app/%2freports',
    '/app/%5creports',
    '/app/%252freports',
    '/app/rep%6frts',
    '/app/reports%00',
    '/app/reports\\evil',
    '/app/reports\n',
    '/app/reports\t',
    '/app/reports?password=secret',
    '/app/reports?email=person%40example.test',
    '/app/reports?token=abc',
    '/app/reports?returnTo=https://evil.test',
    '/app/reports?page=1&page=2',
    '/app/reports?page=3',
    '/app/reports?page=%0a',
    '/app/reports?page=%ff',
    '/app/reports?page=%',
    '/app/reports?page=%252f',
    '/app/reports?sort=recent%00',
    '/app/reports?sort=recent+value',
    '/app/unknown',
    '/app',
    '/app/reports#' + 'a'.repeat(2048),
    '/app/reports?username:password@evil.test'
  ])('rejects unsafe, unregistered or unclassified targets (%#)', (target) => {
    expect(intentWith().capture(target)).toBe(false);
  });

  it('only restores explicitly opted-in protected routes, never public or grant/auth paths', () => {
    for (const route of [
      { ...reportRoute, requirements: [{ kind: 'public' }] as const },
      { pathname: reportRoute.pathname, requirements: reportRoute.requirements },
      { ...reportRoute, requirements: [] },
      { ...reportRoute, pathname: '/app/password-reset' },
      { ...reportRoute, pathname: '/app/invitation/accept' }
    ]) {
      expect(intentWith(() => [route]).capture(route.pathname)).toBe(false);
    }
    expect(intentWith(() => [reportRoute, reportRoute]).capture(reportRoute.pathname)).toBe(false);
  });

  it('will not accept credential fields even if a route mistakenly opts them in', () => {
    const intent = intentWith(() => [{ ...reportRoute, intendedRoute: { token: ['secret'] } }]);
    expect(intent.capture('/app/reports?token=secret')).toBe(false);
  });

  it('does not replace or renew an existing intent on redirects', async () => {
    let now = 0;
    const intent = intentWith(undefined, () => now);
    expect(intent.capture('/app/reports?page=1')).toBe(true);
    now = 599_999;
    expect(intent.capture('/app/reports?page=2')).toBe(false);
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBe('/app/reports?page=1');
  });

  it.each([600_000, 600_001, -1, Number.NaN])(
    'discards expired or clock-discontinuous intent (%s)',
    async (elapsed) => {
      let now = 1000;
      const intent = intentWith(undefined, () => now);
      intent.capture('/app/reports');
      now += elapsed;
      await cache.beginAuthentication().acceptCredentials();
      expect(intent.consume()).toBeNull();
    }
  );

  it('re-resolves current route metadata instead of replaying old permission requirements', async () => {
    let routes = [reportRoute];
    const intent = intentWith(() => routes);
    intent.capture('/app/reports');
    routes = [{ ...reportRoute, requirements: [{ kind: 'permissions', all: ['DELETE_REPORTS'] }] }];
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBeNull();
    routes = [reportRoute];
    expect(intent.consume()).toBeNull();
    intent.capture('/app/reports');
    routes = [];
    expect(intent.consume()).toBeNull();
  });

  it('consumes denied intent without a login redirect loop and does not grant a fallback dashboard', async () => {
    const deniedCache = authenticatedCache({ ...principal, permissions: [] });
    const intent = new IntendedRoute(deniedCache, () => [reportRoute], 'https://example.test');
    intent.capture('/app/reports');
    await deniedCache.load();
    expect(intent.consume()).toBeNull();
    intent.dispose();
    deniedCache.dispose();
  });

  it('clears on explicit logout but can retain eligible intent across involuntary terminal expiry', async () => {
    const intent = intentWith();
    intent.capture('/app/reports');
    await cache.signal('terminal');
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBe('/app/reports');
    intent.capture('/app/reports');
    await cache.signal('logout');
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBeNull();
  });

  it('fails closed if the route registry cannot resolve current metadata', async () => {
    let unavailable = false;
    const intent = intentWith(() => {
      if (unavailable) throw new Error('Registry unavailable');
      return [reportRoute];
    });
    expect(intent.capture('/app/reports')).toBe(true);
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
