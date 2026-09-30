import { lazy, Suspense, useEffect } from 'react';

import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { PermissionButton } from '@/components/PermissionButton';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { GuardedRoute } from '@/routes/GuardedRoute';
import { IntendedRoute } from '@/routes/IntendedRoute';

import { deferred } from '../apiFixtures';
import { principal, reportRoute } from '../authorityFixtures';

import type { AuthorityScope, PrincipalLoadResult } from '@/features/authority/AuthorityCache';

const caches: AuthorityCache[] = [];
afterEach(() => {
  caches.forEach((cache) => cache.dispose());
  caches.length = 0;
  vi.useRealTimers();
});
function cacheWith(result: PrincipalLoadResult) {
  const cache = new AuthorityCache(() => Promise.resolve(result));
  caches.push(cache);
  return cache;
}
function Outlet() {
  return <h2>Protected report</h2>;
}

describe('complete route guard', () => {
  it.each(['logout', 'terminal', 'dispose'] as const)(
    'never restores protected content or actions after %s interrupts identity adoption (R-01)',
    async (ending) => {
      const response = deferred<PrincipalLoadResult>();
      const loader = vi
        .fn()
        .mockResolvedValueOnce({ status: 'principal', principal })
        .mockReturnValueOnce(response.promise);
      const cache = new AuthorityCache(loader);
      caches.push(cache);
      await cache.load();
      const action = vi.fn();
      render(
        <>
          <GuardedRoute cache={cache} route={reportRoute} outlet={Outlet} />
          <PermissionButton
            cache={cache}
            permissions={['EDIT_REPORTS']}
            denied="disable"
            onAction={action}
          >
            Edit
          </PermissionButton>
        </>
      );
      const button = screen.getByRole('button', { name: 'Edit' });
      expect(screen.getByText('Protected report')).toBeVisible();
      await act(async () => {
        const loading = cache.signal('credentials-changed');
        cache.captureScope().signal.addEventListener(
          'abort',
          () => {
            if (ending === 'dispose') cache.dispose();
            else void cache.signal(ending);
            // Invoke the still-rendered button before React can process the ended state.
            fireEvent.click(button);
          },
          { once: true }
        );
        response.resolve({
          status: 'principal',
          principal: { ...principal, userId: crypto.randomUUID() }
        });
        await loading;
      });
      expect(screen.queryByText('Protected report')).not.toBeInTheDocument();
      expect(button).toBeDisabled();
      fireEvent.click(button);
      expect(action).not.toHaveBeenCalled();
    }
  );
  it('removes a mounted protected outlet at foreground expiry without another navigation', async () => {
    vi.useFakeTimers();
    let now = 0;
    const loader = vi.fn(() => Promise.resolve({ status: 'principal', principal } as const));
    const cache = new AuthorityCache(loader, () => now);
    caches.push(cache);
    await cache.load();
    render(<GuardedRoute cache={cache} route={reportRoute} outlet={Outlet} />);
    expect(screen.getByText('Protected report')).toBeVisible();
    now = 60_000;
    await act(() => vi.advanceTimersByTimeAsync(60_000));
    expect(screen.queryByText('Protected report')).not.toBeInTheDocument();
    expect(screen.getByText('Access needs checking')).toBeVisible();
    expect(loader).toHaveBeenCalledOnce();
    expect(screen.getByRole('button', { name: 'Try again' })).toBeEnabled();
  });
  it('does not even import a denied outlet or run its protected loader, then retires data on invalidation', async () => {
    const protectedLoad = vi.fn();
    let scope: AuthorityScope | undefined;
    function LoadedOutlet({ scope: current }: { scope: AuthorityScope }) {
      useEffect(() => {
        scope = current;
        protectedLoad();
      }, [current]);
      return <Outlet />;
    }
    const importOutlet = vi.fn(() => Promise.resolve({ default: LoadedOutlet }));
    const LazyOutlet = lazy(importOutlet);
    const cache = cacheWith({ status: 'principal', principal });
    render(
      <Suspense fallback="Importing">
        <GuardedRoute cache={cache} route={reportRoute} outlet={LazyOutlet} />
      </Suspense>
    );
    expect(screen.getByText('Authority unknown')).toBeVisible();
    expect(importOutlet).not.toHaveBeenCalled();
    await act(() => cache.load());
    expect(await screen.findByRole('heading', { name: 'Protected report' })).toBeVisible();
    expect(importOutlet).toHaveBeenCalledOnce();
    expect(protectedLoad).toHaveBeenCalledOnce();
    await act(() => cache.signal('refresh-started'));
    expect(screen.queryByRole('heading', { name: 'Protected report' })).not.toBeInTheDocument();
    expect(screen.getByText('Access needs checking')).toBeVisible();
    expect(screen.queryByRole('button', { name: 'Try again' })).not.toBeInTheDocument();
    expect(scope?.signal.aborted).toBe(true);
    expect(scope?.isCurrent()).toBe(false);
  });

  it('renders forbidden for valid denied authority, not sign-in or repeated loads', async () => {
    const cache = cacheWith({ status: 'principal', principal: { ...principal, permissions: [] } });
    await cache.load();
    render(<GuardedRoute cache={cache} route={reportRoute} outlet={Outlet} />);
    expect(screen.getByText('Access forbidden')).toBeVisible();
    expect(screen.queryByText('Sign in required')).not.toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.getByText(/Server authorization remains authoritative/)).toBeVisible();
  });

  it('offers an explicit safe retry after a protocol failure and shows pending without old content', async () => {
    const response = deferred<PrincipalLoadResult>();
    const loader = vi
      .fn()
      .mockResolvedValueOnce({ status: 'protocol' })
      .mockReturnValueOnce(response.promise);
    const cache = new AuthorityCache(loader);
    caches.push(cache);
    await cache.load();
    render(<GuardedRoute cache={cache} route={reportRoute} outlet={Outlet} />);
    await userEvent.click(screen.getByRole('button', { name: 'Try again' }));
    expect(screen.getByText('Checking access')).toBeVisible();
    expect(screen.queryByText('Protected report')).not.toBeInTheDocument();
    await act(async () => {
      response.resolve({ status: 'principal', principal });
      await response.promise;
    });
    expect(await screen.findByText('Protected report')).toBeVisible();
  });

  it('keeps explicitly public routes available and malformed metadata closed', () => {
    const cache = cacheWith({ status: 'anonymous' });
    const { rerender } = render(
      <GuardedRoute
        cache={cache}
        route={{ ...reportRoute, requirements: [{ kind: 'public' }] }}
        outlet={Outlet}
      />
    );
    expect(screen.getByText('Protected report')).toBeVisible();
    rerender(
      <GuardedRoute cache={cache} route={{ ...reportRoute, requirements: [] }} outlet={Outlet} />
    );
    expect(screen.getByText('Route unavailable')).toBeVisible();
    expect(screen.queryByText('Protected report')).not.toBeInTheDocument();
  });

  it('captures only eligible anonymous navigation and clears it on logout without recapture', async () => {
    const loader = vi
      .fn()
      .mockResolvedValueOnce({ status: 'anonymous' })
      .mockResolvedValue({ status: 'principal', principal });
    const cache = new AuthorityCache(loader);
    caches.push(cache);
    const intent = new IntendedRoute(cache, () => [reportRoute], 'https://example.test');
    await cache.load();
    render(
      <GuardedRoute
        cache={cache}
        route={reportRoute}
        outlet={Outlet}
        intent={intent}
        location="/app/reports?page=2"
      />
    );
    expect(screen.getByText('Sign in required')).toBeVisible();
    await act(() => cache.beginAuthentication().acceptCredentials());
    expect(intent.consume()).toBe('/app/reports?page=2');
    await act(() => cache.signal('logout'));
    await act(() => cache.beginAuthentication().acceptCredentials());
    expect(intent.consume()).toBeNull();
    intent.dispose();
  });
});
