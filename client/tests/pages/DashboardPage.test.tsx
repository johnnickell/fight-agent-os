import { act, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { AuthorityCache } from '@/features/authority/AuthorityCache';
import DashboardPage from '@/pages/DashboardPage';
import { ApplicationView } from '@/routes/ApplicationJourney';
import { applicationRoutes } from '@/routes/applicationRoutes';

import { deferred } from '../apiFixtures';
import { applicationSession } from '../applicationFixtures';
import { principal } from '../authorityFixtures';

import type { PrincipalLoadResult } from '@/features/authority/AuthorityCache';

const owned: { dispose: () => void }[] = [];
afterEach(() => owned.splice(0).forEach((owner) => owner.dispose()));

const permissionNames = [
  'VIEW_DASHBOARD',
  'READ_USERS',
  'INVITE_USERS',
  'MANAGE_USER_INVITATIONS',
  'ASSIGN_SUPER_ADMIN',
  'READ_OWN_SESSIONS'
];
// Render every permission combination once. The shared evaluator owns the full
// role/permission cross-product; these extra denied renders catch page-level elevation.
const matrix = [
  ...Array.from({ length: 64 }, (_, mask) => ({
    mask,
    roles: [] as string[],
    permissions: permissionNames.filter((_, bit) => mask & (1 << bit))
  })),
  ...[['ROLE_USER'], ['ROLE_SUPER_ADMIN'], ['ROLE_VIEW_DASHBOARD', 'ROLE_ADMIN']].map((roles) => ({
    mask: 0,
    roles,
    permissions: [] as string[]
  }))
];

describe('honest Dashboard permission and destination contracts', () => {
  it.each(matrix)('mask $mask with roles $roles', async ({ mask, roles, permissions }) => {
    const owner = applicationSession(permissions, () =>
      Promise.resolve({ status: 'principal', principal: { ...principal, roles, permissions } })
    );
    owned.push(owner);
    render(<ApplicationView session={owner} pathname="/app/dashboard" navigate={vi.fn()} />);
    await act(() => owner.coordinator.restore());
    if (!(mask & 1)) {
      expect(await screen.findByText('Access forbidden')).toBeVisible();
      expect(
        screen.queryByRole('heading', { name: 'Dashboard', level: 1 })
      ).not.toBeInTheDocument();
      expect(
        screen.queryByRole('navigation', { name: 'Dashboard administration' })
      ).not.toBeInTheDocument();
      expect(screen.queryByLabelText('Password (required)')).not.toBeInTheDocument();
      return;
    }
    await screen.findByRole('heading', { name: 'Dashboard', level: 1 });
    const main = within(screen.getByRole('main', { name: 'Dashboard' }));
    const admin = main.queryByRole('navigation', { name: 'Dashboard administration' });
    for (const [title, bit, path] of [
      ['Users', 2, '/app/admin/users'],
      ['Invite User', 4, '/app/admin/invite'],
      ['Invitation Admin', 8, '/app/admin/invitations']
    ] as const) {
      const link = main.queryByRole('link', { name: title });
      if (mask & bit) {
        expect(link).toHaveAttribute('href', path);
        expect(admin).toContainElement(link);
      } else expect(link).not.toBeInTheDocument();
    }
    expect(Boolean(admin)).toBe(Boolean(mask & 14));
    expect(main.queryByRole('link', { name: 'Invite Super Admin' })).not.toBeInTheDocument();
    expect(main.getByRole('link', { name: 'Account & Security' })).toHaveAttribute(
      'href',
      '/app/account'
    );
    expect(Boolean(main.queryByRole('link', { name: 'Active Sessions' }))).toBe(Boolean(mask & 32));
    for (const link of main.getAllByRole('link')) {
      const route = applicationRoutes.find((route) => route.pathname === link.getAttribute('href'));
      expect(route).toBeDefined();
      if (route?.destination === 'users')
        expect(route.availability).toBe(
          'Read the installation-wide user directory and current role assignments.'
        );
      else expect(route?.availability).toMatch(/not available/);
      expect(main.getByText(route?.availability ?? '')).toBeVisible();
    }
  });

  it('shows only safe identity, honest availability and navigation, without fetching or fabricating data', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    const log = vi.spyOn(console, 'log');
    const error = vi.spyOn(console, 'error');
    const cache = new AuthorityCache(() =>
      Promise.resolve({
        status: 'principal',
        principal: { ...principal, permissions: permissionNames }
      })
    );
    owned.push(cache);
    await cache.load();
    const { container } = render(<DashboardPage cache={cache} />);
    expect(screen.getByText(principal.email)).toBeVisible();
    expect(container.textContent).not.toContain(principal.userId);
    expect(container.textContent).not.toContain('ROLE_SUPER_ADMIN');
    expect(screen.getByRole('heading', { name: 'Available today' })).toBeVisible();
    expect(screen.getByRole('heading', { name: 'Not installed yet' })).toBeVisible();
    expect(container.textContent).toMatch(
      /Planning, observability and agent sessions are not installed/
    );
    expect(container.textContent).toMatch(
      /Charts, metrics and workspace switching are not installed/
    );
    expect(container.querySelector('table, svg, canvas, form, input, iframe, img')).toBeNull();
    expect(container.querySelector('[role="table"], [role="img"], [role="feed"]')).toBeNull();
    expect(container.querySelector('button')).toBeNull();
    expect(container.textContent).not.toMatch(/\b\d+\b/);
    expect(fetch).not.toHaveBeenCalled();
    expect(log).not.toHaveBeenCalled();
    expect(error).not.toHaveBeenCalled();
  });

  it.each(['unknown', 'pending', 'malformed', 'network', 'anonymous', 'terminal'] as const)(
    'withholds content for %s authority and never echoes rejected private fields',
    async (outcome) => {
      const marker = 'token-cookie-session-hash-stack-private-user-marker';
      const cache = new AuthorityCache(() =>
        outcome === 'pending'
          ? new Promise(() => {})
          : Promise.resolve(
              outcome === 'malformed'
                ? {
                    status: 'principal',
                    principal: {
                      ...principal,
                      token: marker,
                      cookie: marker,
                      session: marker,
                      hash: marker,
                      stack: marker,
                      private_user: marker
                    }
                  }
                : outcome === 'network'
                  ? { status: 'network' }
                  : outcome === 'anonymous'
                    ? { status: 'anonymous' }
                    : { status: 'terminal' }
            )
      );
      owned.push(cache);
      if (outcome === 'pending') void cache.load();
      else if (outcome !== 'unknown') await cache.load();
      const { container } = render(<DashboardPage cache={cache} />);
      expect(container).toBeEmptyDOMElement();
      expect(document.body.innerHTML).not.toContain(marker);
    }
  );

  it('retains last-verified display but pauses shortcut admission when freshness expires', async () => {
    let now = 0;
    const cache = new AuthorityCache(
      () =>
        Promise.resolve({
          status: 'principal',
          principal: { ...principal, permissions: permissionNames }
        }),
      () => now
    );
    owned.push(cache);
    await cache.load();
    const { rerender } = render(<DashboardPage cache={cache} />);
    expect(screen.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    now = 60_000;
    rerender(<DashboardPage cache={cache} />);
    expect(screen.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    for (const link of screen.getAllByRole('link'))
      expect(link).toHaveAttribute('aria-disabled', 'true');
    expect(cache.getSnapshot().status).not.toBe('authenticated');
    await act(async () => {
      await cache.load();
    });
    for (const link of screen.getAllByRole('link'))
      expect(link).not.toHaveAttribute('aria-disabled');
  });

  it('retires identity and links immediately and ignores out-of-order successes and failures', async () => {
    const older = deferred<PrincipalLoadResult>();
    const current = deferred<PrincipalLoadResult>();
    const oldest = deferred<PrincipalLoadResult>();
    const owner = applicationSession(permissionNames);
    owned.push(owner);
    render(<ApplicationView session={owner} pathname="/app/dashboard" navigate={vi.fn()} />);
    await act(() => owner.coordinator.restore());
    await screen.findByRole('heading', { name: 'Dashboard', level: 1 });
    owner.load
      .mockImplementationOnce(() => oldest.promise)
      .mockImplementationOnce(() => older.promise)
      .mockImplementationOnce(() => current.promise);
    act(() => owner.coordinator.authorityChanged());
    expect(screen.queryByRole('heading', { name: 'Dashboard' })).not.toBeInTheDocument();
    expect(screen.queryByText(principal.email)).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument();
    await act(async () => {});
    act(() => owner.coordinator.authorityChanged());
    await act(async () => {});
    act(() => owner.coordinator.authorityChanged());
    await act(async () => {
      current.resolve({
        status: 'principal',
        principal: {
          ...principal,
          email: 'replacement@example.test',
          permissions: ['VIEW_DASHBOARD']
        }
      });
      await current.promise;
    });
    await screen.findByRole('heading', { name: 'Dashboard', level: 1 });
    await act(async () => {
      older.resolve({
        status: 'principal',
        principal: { ...principal, permissions: permissionNames }
      });
      oldest.resolve({ status: 'network' });
      await Promise.all([older.promise, oldest.promise]);
    });
    expect(screen.getAllByText('replacement@example.test')).toHaveLength(1);
    expect(screen.queryByText(principal.email)).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument();
    expect(owner.refresh).toHaveBeenCalledOnce();
  });
});
