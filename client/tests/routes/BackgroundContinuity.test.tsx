import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { PermissionButton } from '@/components/PermissionButton';
import { observeAuthorityResume } from '@/features/authority/AuthorityCache';
import { ApplicationView } from '@/routes/ApplicationJourney';
import { applicationRoutes } from '@/routes/applicationRoutes';
import { GuardedRoute } from '@/routes/GuardedRoute';

import { deferred } from '../apiFixtures';
import { applicationSession } from '../applicationFixtures';
import { principal } from '../authorityFixtures';

import type { PrincipalLoadResult } from '@/features/authority/AuthorityCache';

const owners: ReturnType<typeof applicationSession>[] = [];
afterEach(() => {
  owners.splice(0).forEach((owner) => owner.dispose());
  vi.useRealTimers();
  vi.restoreAllMocks();
});

async function mount(path = '/app/dashboard') {
  const owner = applicationSession();
  owners.push(owner);
  const navigate = vi.fn();
  render(<ApplicationView session={owner} pathname={path} navigate={navigate} />);
  await act(() => owner.coordinator.restore());
  await screen.findByRole('heading', {
    name: path === '/app/dashboard' ? 'Dashboard' : 'Account & Security',
    level: 1
  });
  return { owner, navigate };
}

describe('background continuity through the composed credential/cache/frame graph', () => {
  it('revalidates each foreground interval without replacing the page, navigation or focus', async () => {
    const { owner, navigate } = await mount();
    const main = screen.getByRole('main');
    const focus = screen.getByRole('button', { name: 'User Menu' });
    focus.focus();
    let now = Math.ceil(performance.now());
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    vi.spyOn(performance, 'now').mockImplementation(() => now);
    // Start a budget in the same fake monotonic domain.
    await act(async () => {
      owner.authority.resume();
      await owner.authority.load();
    });
    const pending = deferred<PrincipalLoadResult>();
    owner.load.mockImplementationOnce(() => pending.promise);
    now += 60_000;
    await act(() => vi.advanceTimersByTimeAsync(60_000));
    expect(owner.load).toHaveBeenCalledTimes(3);
    expect(screen.getByRole('main')).toBe(main);
    expect(screen.getByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible();
    expect(focus).toHaveFocus();
    expect(owner.authority.getSnapshot().status).not.toBe('authenticated');
    await act(async () => {
      pending.resolve({
        status: 'principal',
        principal: { ...principal, permissions: ['VIEW_DASHBOARD'] }
      });
      await pending.promise;
    });
    now += 60_000;
    await act(() => vi.advanceTimersByTimeAsync(60_000));
    expect(owner.load).toHaveBeenCalledTimes(4);
    expect(screen.getByRole('main')).toBe(main);
    expect(focus).toHaveFocus();
    expect(navigate).not.toHaveBeenCalled();
  });

  it('coalesces hidden return without refreshing a usable credential or replacing a non-Dashboard page', async () => {
    const { owner, navigate } = await mount('/app/account');
    const main = screen.getByRole('main');
    const focus = screen.getByRole('button', { name: 'User Menu' });
    focus.focus();
    let hidden = false;
    vi.spyOn(document, 'visibilityState', 'get').mockImplementation(() =>
      hidden ? 'hidden' : 'visible'
    );
    const stop = observeAuthorityResume(owner.authority, document, window);
    hidden = true;
    fireEvent(document, new Event('visibilitychange'));
    hidden = false;
    await act(async () => {
      fireEvent(document, new Event('visibilitychange'));
      owner.coordinator.resume();
      fireEvent(window, new Event('focus'));
      await Promise.resolve();
    });
    expect(owner.refresh).toHaveBeenCalledOnce();
    expect(owner.load).toHaveBeenCalledTimes(2);
    expect(screen.getByRole('main')).toBe(main);
    expect(focus).toHaveFocus();
    expect(navigate).not.toHaveBeenCalled();
    stop();
  });

  it('retains an admitted outlet but blocks new protected mounts and permission actions during checks', async () => {
    const { owner } = await mount();
    const pending = deferred<PrincipalLoadResult>();
    const action = vi.fn();
    const loader = vi.fn(() => <p>New private loader</p>);
    const route = applicationRoutes.find((entry) => entry.pathname === '/app/account');
    if (!route) throw new Error('Missing registered account route');
    render(
      <PermissionButton
        cache={owner.authority}
        permissions={['VIEW_DASHBOARD']}
        denied="disable"
        onAction={action}
      >
        Protected action
      </PermissionButton>
    );
    const button = screen.getByRole('button', { name: 'Protected action' });
    owner.load.mockImplementationOnce(() => pending.promise);
    act(() => owner.authority.resume());
    render(<GuardedRoute cache={owner.authority} route={route} outlet={loader} />);
    fireEvent.click(button);
    expect(action).not.toHaveBeenCalled();
    expect(loader).not.toHaveBeenCalled();
    expect(screen.getByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible();
    await act(async () => {
      pending.resolve({ status: 'principal', principal: { ...principal, permissions: [] } });
      await pending.promise;
    });
    expect(screen.queryByRole('heading', { name: 'Dashboard', level: 1 })).not.toBeInTheDocument();
    expect(screen.getByText('Access forbidden')).toBeVisible();
  });

  it.each(['network', 'protocol'] as const)(
    'holds a routine %s failure for explicit recovery without losing context',
    async (status) => {
      const { owner, navigate } = await mount('/app/account');
      const main = screen.getByRole('main');
      owner.load.mockResolvedValueOnce({ status });
      await act(async () => {
        owner.authority.resume();
        await owner.authority.load();
      });
      expect(screen.getByRole('main')).toBe(main);
      expect(screen.getByText(/Current access could not be verified/)).toBeVisible();
      expect(screen.getByRole('button', { name: 'Retry access check' })).toBeVisible();
      expect(owner.load).toHaveBeenCalledTimes(2);
      owner.coordinator.resume();
      expect(owner.load).toHaveBeenCalledTimes(2);
      expect(navigate).not.toHaveBeenCalled();
      fireEvent.click(screen.getByRole('button', { name: 'Retry access check' }));
      await act(() => owner.authority.load());
      expect(owner.load).toHaveBeenCalledTimes(3);
      expect(screen.getByRole('main')).toBe(main);
    }
  );

  it.each(['principal', 'network'] as const)(
    'keeps meaningful keyboard focus through access retry pending and %s completion',
    async (status) => {
      const { owner } = await mount();
      owner.load.mockResolvedValueOnce({ status: 'network' });
      await act(async () => {
        owner.authority.resume();
        await owner.authority.load();
      });
      const retry = screen.getByRole('button', { name: 'Retry access check' });
      retry.focus();
      const pending = deferred<PrincipalLoadResult>();
      owner.load.mockImplementationOnce(() => pending.promise);
      fireEvent.click(retry);
      expect(screen.getByRole('main')).toHaveFocus();
      await act(async () => {
        pending.resolve(
          status === 'principal'
            ? { status, principal: { ...principal, permissions: ['VIEW_DASHBOARD'] } }
            : { status }
        );
        await pending.promise;
      });
      expect(screen.getByRole('main')).toHaveFocus();
      expect(screen.queryByRole('button', { name: 'Retry access check' }) !== null).toBe(
        status === 'network'
      );
    }
  );

  it.each([true, false])(
    'keeps meaningful keyboard focus through session retry pending and success=%s',
    async (success) => {
      const { owner } = await mount();
      const accept = owner.refresh.getMockImplementation();
      if (!accept) throw new Error('Missing fixture credential acceptance');
      owner.refresh.mockResolvedValueOnce(owner.apiFailure('network'));
      await act(() => owner.coordinator.restore());
      const retry = screen.getByRole('button', { name: 'Retry session connection' });
      retry.focus();
      const pending = deferred<void>();
      owner.refresh.mockImplementationOnce(async () => {
        await pending.promise;
        return success ? accept() : owner.apiFailure('network');
      });
      fireEvent.click(retry);
      expect(screen.getByRole('main')).toHaveFocus();
      await act(async () => {
        pending.resolve();
        await owner.coordinator.restore();
      });
      expect(screen.getByRole('main')).toHaveFocus();
      expect(screen.getByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible();
    }
  );

  it('does not claim focus for an access retry activated without owning keyboard focus', async () => {
    const { owner } = await mount();
    owner.load.mockResolvedValueOnce({ status: 'network' });
    await act(async () => {
      owner.authority.resume();
      await owner.authority.load();
    });
    const unrelated = screen.getByRole('button', { name: 'User Menu' });
    unrelated.focus();
    const pending = deferred<PrincipalLoadResult>();
    owner.load.mockImplementationOnce(() => pending.promise);
    fireEvent.click(screen.getByRole('button', { name: 'Retry access check' }));
    expect(unrelated).toHaveFocus();
    await act(async () => {
      pending.resolve({
        status: 'principal',
        principal: { ...principal, permissions: ['VIEW_DASHBOARD'] }
      });
      await pending.promise;
    });
    expect(unrelated).toHaveFocus();
  });

  it('does not reclaim focus moved away from a pending explicit connection retry', async () => {
    const { owner } = await mount();
    const accept = owner.refresh.getMockImplementation();
    if (!accept) throw new Error('Missing fixture credential acceptance');
    owner.refresh.mockResolvedValueOnce(owner.apiFailure('network'));
    await act(() => owner.coordinator.restore());
    const retry = screen.getByRole('button', { name: 'Retry session connection' });
    retry.focus();
    const pending = deferred<void>();
    owner.refresh.mockImplementationOnce(async () => {
      await pending.promise;
      return accept();
    });
    fireEvent.click(retry);
    const unrelated = screen.getByRole('button', { name: 'User Menu' });
    unrelated.focus();
    await act(async () => {
      pending.resolve();
      await owner.coordinator.restore();
    });
    expect(unrelated).toHaveFocus();
  });

  it('keeps the page during scheduled renewal and removes it on logout before a late response', async () => {
    const { owner } = await mount('/app/account');
    const main = screen.getByRole('main');
    let now = performance.now();
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    vi.spyOn(performance, 'now').mockImplementation(() => now);
    // Re-establish the renewal timer in the controlled domain without changing context.
    owner.coordinator.resume();
    await act(() => owner.authority.load());
    const pending = deferred<{ ok: true; value: true; correlationId: null }>();
    owner.refresh.mockImplementationOnce(() => pending.promise);
    now += 840_000;
    await act(() => vi.advanceTimersByTimeAsync(840_000));
    expect(owner.refresh).toHaveBeenCalledTimes(2);
    expect(screen.getByRole('main')).toBe(main);
    expect(owner.authority.getSnapshot().status).not.toBe('authenticated');
    await act(() => owner.coordinator.logout());
    expect(
      screen.queryByRole('heading', { name: 'Account & Security', level: 1 })
    ).not.toBeInTheDocument();
    await act(async () => {
      pending.resolve({ ok: true, value: true, correlationId: null });
      await pending.promise;
    });
    expect(owner.authority.getPresentationSnapshot().status).not.toBe('authenticated');
    expect(screen.queryByText(principal.email)).not.toBeInTheDocument();
  });
});
