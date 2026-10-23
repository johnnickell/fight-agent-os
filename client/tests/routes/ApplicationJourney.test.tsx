import { useState } from 'react';

import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { apiFailure } from '@/api/ApiResult';
import { ApplicationView } from '@/routes/ApplicationJourney';
import { applicationRoutes } from '@/routes/applicationRoutes';

import { deferred } from '../apiFixtures';
import { applicationSession } from '../applicationFixtures';
import { principal } from '../authorityFixtures';

import type { PrincipalLoadResult } from '@/features/authority/AuthorityCache';

const owned: ReturnType<typeof applicationSession>[] = [];
function session(...args: Parameters<typeof applicationSession>) {
  const value = applicationSession(...args);
  owned.push(value);
  return value;
}
afterEach(() => {
  owned.splice(0).forEach((value) => value.dispose());
  window.history.replaceState(null, '', '/');
  vi.unstubAllGlobals();
});

function Mounted({
  owner,
  initial = '/app',
  changed = vi.fn()
}: {
  owner: ReturnType<typeof applicationSession>;
  initial?: string;
  changed?: (target: string, replace?: boolean) => void;
}) {
  const [path, setPath] = useState(initial);
  return (
    <ApplicationView
      session={owner}
      pathname={path}
      navigate={(target, replace) => {
        changed(target, replace);
        setPath(target);
      }}
    />
  );
}

describe('authoritative frame transitions', () => {
  it('withholds all private DOM through restoration and /me, then replaces root once', async () => {
    const pending = deferred<PrincipalLoadResult>();
    const owner = session(undefined, () => pending.promise);
    const changed = vi.fn();
    render(<Mounted owner={owner} changed={changed} />);
    expect(screen.getByText('Restoring your session')).toBeVisible();
    await act(() => owner.coordinator.restore());
    expect(screen.getByText('Checking access')).toBeVisible();
    expect(screen.queryByRole('navigation', { name: 'Primary' })).not.toBeInTheDocument();
    expect(screen.queryByText(principal.email)).not.toBeInTheDocument();
    expect(changed).not.toHaveBeenCalled();
    await act(async () => {
      pending.resolve({
        status: 'principal',
        principal: { ...principal, permissions: ['VIEW_DASHBOARD'] }
      });
      await pending.promise;
    });
    expect(await screen.findByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible();
    expect(changed).toHaveBeenCalledExactlyOnceWith('/app/dashboard', true);
    expect(owner.load).toHaveBeenCalledOnce();
  });

  it('uses authenticated 403 without treating a role name as a dashboard grant or looping', async () => {
    const owner = session([]);
    const changed = vi.fn();
    render(<Mounted owner={owner} changed={changed} />);
    await act(() => owner.coordinator.restore());
    expect(await screen.findByText('Access forbidden')).toBeVisible();
    expect(screen.queryByRole('heading', { name: 'Dashboard' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Password (required)')).not.toBeInTheDocument();
    expect(changed).toHaveBeenCalledTimes(1);
  });

  it.each(applicationRoutes.filter((route) => route.access === 'protected'))(
    'guards $pathname and keeps truthful destination copy',
    async (route) => {
      const all = route.requirements.flatMap((requirement) =>
        requirement.kind === 'permissions' ? [...requirement.all] : []
      );
      const owner = session(all);
      render(<Mounted owner={owner} initial={route.pathname} />);
      await act(() => owner.coordinator.restore());
      expect(await screen.findByRole('heading', { name: route.title, level: 1 })).toBeVisible();
      if (route.destination === 'dashboard')
        expect(screen.getByRole('heading', { name: 'Not installed yet' })).toBeVisible();
      else expect(screen.getByText(route.availability ?? '')).toBeVisible();
      expect(screen.queryByRole('table')).not.toBeInTheDocument();
      fireEvent.click(screen.getByRole('button', { name: 'User Menu' }));
      expect(screen.getByRole('button', { name: 'Sign Out' })).toBeVisible();
    }
  );

  it.each([
    ['/app/account/sessions', 'READ_OWN_SESSIONS'],
    ['/app/admin/users', 'READ_USERS'],
    ['/app/admin/invite', 'INVITE_USERS'],
    ['/app/admin/invite-super-admin', 'ASSIGN_SUPER_ADMIN'],
    ['/app/admin/invitations', 'MANAGE_USER_INVITATIONS']
  ])('denies %s when exact %s is missing, including joint requirements', async (path, missing) => {
    const owner = session(
      [
        'VIEW_DASHBOARD',
        'READ_OWN_SESSIONS',
        'READ_USERS',
        'INVITE_USERS',
        'ASSIGN_SUPER_ADMIN',
        'MANAGE_USER_INVITATIONS'
      ].filter((permission) => permission !== missing)
    );
    render(<Mounted owner={owner} initial={path} />);
    await act(() => owner.coordinator.restore());
    expect(await screen.findByText('Access forbidden')).toBeVisible();
    const forbiddenLink = screen
      .queryAllByRole('link')
      .find((link) => link.getAttribute('href') === path);
    expect(forbiddenLink).toBeUndefined();
  });

  it('retires page, user and controls on invalidation and adopts permission change only after fresh /me', async () => {
    const pending = deferred<PrincipalLoadResult>();
    const owner = session();
    render(<Mounted owner={owner} initial="/app/dashboard" />);
    await act(() => owner.coordinator.restore());
    await screen.findByRole('heading', { name: 'Dashboard' });
    owner.load.mockImplementationOnce(() => pending.promise);
    act(() => owner.coordinator.authorityChanged());
    expect(screen.queryByText(principal.email)).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Dashboard' })).not.toBeInTheDocument();
    await act(async () => {
      pending.resolve({ status: 'principal', principal: { ...principal, permissions: [] } });
      await pending.promise;
    });
    expect(await screen.findByText('Access forbidden')).toBeVisible();
    expect(owner.refresh).toHaveBeenCalledOnce();
  });

  it('removes all protected controls on terminal expiry, clears intent, and offers explicit sign in', async () => {
    const owner = session();
    render(<Mounted owner={owner} initial="/app/dashboard" />);
    await act(() => owner.coordinator.restore());
    await screen.findByRole('heading', { name: 'Dashboard' });
    owner.intent.capture('/app/account');
    act(() => owner.coordinator.terminal());
    expect(screen.getByText('Your session has expired or ended')).toBeVisible();
    expect(screen.queryByText(principal.email)).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Open navigation' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'User Menu' })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Sign in again' }));
    expect(await screen.findByLabelText('Password (required)')).toBeVisible();
    expect(owner.intent.capture('/app/account')).toBe(true);
  });

  it('keeps network recovery explicit and bounded, never displaying retained principal data', async () => {
    const owner = session();
    owner.refresh.mockResolvedValueOnce(apiFailure('network'));
    render(<Mounted owner={owner} />);
    await act(() => owner.coordinator.restore());
    expect(screen.getByText('Session connection unavailable')).toBeVisible();
    expect(owner.refresh).toHaveBeenCalledOnce();
    expect(screen.queryByText(principal.email)).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Use sign in instead' }));
    expect(await screen.findByLabelText('Password (required)')).toBeVisible();
    expect(owner.refresh).toHaveBeenCalledOnce();
  });

  it.each(['network', 'protocol'] as const)(
    'holds a %s projection failure until explicit retry',
    async (status) => {
      const owner = session(undefined, () => Promise.resolve({ status }));
      render(<Mounted owner={owner} initial="/app/dashboard" />);
      await act(() => owner.coordinator.restore());
      expect(await screen.findByText('Access check failed')).toBeVisible();
      expect(owner.load).toHaveBeenCalledOnce();
      expect(screen.queryByText(principal.email)).not.toBeInTheDocument();
      fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
      await waitFor(() => expect(owner.load).toHaveBeenCalledTimes(2));
      expect(owner.refresh).toHaveBeenCalledOnce();
    }
  );

  it('consumes permitted intended path once after login and discards denied intent', async () => {
    const owner = session();
    owner.intent.capture('/app/account/sessions');
    render(<Mounted owner={owner} initial="/app/login" />);
    await act(() => owner.coordinator.restore());
    expect(await screen.findByRole('heading', { name: 'Active Sessions' })).toBeVisible();
    expect(owner.intent.consume()).toBeNull();
  });

  it('rejects credential-bearing direct query before sanitizing history', async () => {
    window.history.replaceState(null, '', '/app/account?token=private-marker#cookie-marker');
    const owner = session();
    render(<Mounted owner={owner} initial="/app/account" />);
    expect(window.location.search).toBe('');
    expect(window.location.hash).toBe('');
    await act(() => owner.coordinator.restore());
    expect(await screen.findByRole('heading', { name: 'Account & Security' })).toBeVisible();
    expect(owner.intent.consume()).toBeNull();
    expect(document.body.textContent).not.toMatch(/private-marker|cookie-marker/);
  });

  it('clears intent and displays truthful uncertain logout without further restoration', async () => {
    const owner = session();
    owner.logout.mockResolvedValueOnce(apiFailure('network'));
    render(<Mounted owner={owner} initial="/app/dashboard" />);
    await act(() => owner.coordinator.restore());
    await screen.findByRole('heading', { name: 'Dashboard' });
    fireEvent.click(screen.getByRole('button', { name: 'User Menu' }));
    fireEvent.click(screen.getByRole('button', { name: 'Sign Out' }));
    expect(screen.queryByText(principal.email)).not.toBeInTheDocument();
    expect(await screen.findByText(/Server sign-out is unconfirmed/)).toBeVisible();
    expect(owner.refresh).toHaveBeenCalledOnce();
    expect(owner.logout).toHaveBeenCalledOnce();
  });
});
