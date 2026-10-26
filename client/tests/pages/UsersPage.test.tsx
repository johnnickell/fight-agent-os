import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { apiFailure } from '@/api/ApiResult';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import UsersPage from '@/pages/UsersPage';
import { matchApplicationRoute } from '@/routes/applicationRoutes';

import {
  directoryDependencies,
  directoryPage,
  directoryPermissions,
  directoryResponse,
  directoryUser
} from '../userDirectoryFixtures';

const caches: AuthorityCache[] = [];
afterEach(() => {
  for (const cache of caches.splice(0)) cache.dispose();
});
async function mount(
  fetch?: (url: string, init: RequestInit) => Promise<Response>,
  permissions = directoryPermissions
) {
  const d = directoryDependencies(fetch, permissions);
  caches.push(d.cache);
  await d.cache.load();
  const rendered = render(<UsersPage {...d} />);
  return { ...d, ...rendered };
}

describe('UsersPage', () => {
  it.each(Array.from({ length: 16 }, (_, mask) => mask))(
    'uses independent exact permission controls for subset %i, never role names',
    async (mask) => {
      const permissions = directoryPermissions.filter((_, index) => Boolean(mask & (1 << index)));
      const fetch = vi.fn(() => Promise.resolve(directoryResponse(directoryPage())));
      await mount(fetch, permissions);
      if (mask & 1) await screen.findByRole('table');
      else expect(fetch).not.toHaveBeenCalled();
      const ordinary = screen.queryByRole('link', { name: 'Invite User' });
      const elevated = screen.queryByRole('link', { name: 'Invite Super Admin' });
      const status = screen.queryByRole('link', { name: /Invitation status for/ });
      expect(Boolean(ordinary)).toBe(Boolean(mask & 2));
      expect(Boolean(elevated)).toBe(Boolean(mask & 2 && mask & 8));
      expect(Boolean(status)).toBe(Boolean(mask & 1 && mask & 4));
      for (const link of screen.queryAllByRole('link')) {
        const path = link.getAttribute('href') ?? '';
        expect(matchApplicationRoute(path)).toBeDefined();
        expect(link).not.toHaveAttribute('hidden');
        expect(link).not.toHaveAttribute('tabindex', '-1');
      }
    }
  );

  it('renders a single semantic table with lifecycle, managed/custom/empty roles and escaped long Unicode email', async () => {
    const email = `${'équipe'.repeat(20)}@example.test`;
    await mount(() =>
      Promise.resolve(
        directoryResponse(
          directoryPage([
            directoryUser({
              email,
              roles: [
                { name: 'ROLE_CUSTOM', managed: false },
                { name: 'ROLE_USER', managed: true }
              ]
            }),
            directoryUser({ email: 'active@example.test', state: 'active', roles: [] }),
            directoryUser({ email: 'disabled@example.test', state: 'disabled' }),
            directoryUser({ email: 'deleted@example.test', state: 'deleted' })
          ])
        )
      )
    );
    const table = await screen.findByRole('table', { name: 'Installation users — current page' });
    expect(within(table).getAllByRole('row')).toHaveLength(5);
    expect(within(table).getAllByRole('columnheader')).toHaveLength(5);
    expect(within(table).getByRole('rowheader', { name: email })).toBeVisible();
    expect(screen.getByText('No roles assigned')).toBeVisible();
    expect(screen.getByText('(Custom)')).toBeVisible();
    expect(screen.getByText('Pending activation')).toBeVisible();
    expect(screen.getByText('Disabled')).toBeVisible();
    expect(screen.getByText('Deleted')).toBeVisible();
    expect(document.querySelectorAll('time')).toHaveLength(8);
    expect(screen.getByRole('status')).toHaveTextContent('4 users');
    expect(screen.queryByRole('searchbox')).not.toBeInTheDocument();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('R59-01 lets an ordinary reader view another identity with quoted canonical email', async () => {
    const emails = ['ordinary@example.test', '"a@b"@example.test'];
    const fetch = vi.fn(() =>
      Promise.resolve(
        directoryResponse(directoryPage(emails.map((email) => directoryUser({ email }))))
      )
    );
    await mount(fetch, ['READ_USERS']);
    const table = await screen.findByRole('table', { name: 'Installation users — current page' });
    expect(within(table).getAllByRole('row')).toHaveLength(3);
    for (const email of emails)
      expect(within(table).getByRole('rowheader', { name: email })).toBeVisible();
    expect(screen.getByRole('status')).toHaveTextContent('2 users');
    expect(screen.queryByText('Users unavailable')).not.toBeInTheDocument();
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it('escapes all presentation values without creating markup or private diagnostics', async () => {
    await mount(() =>
      Promise.resolve(
        directoryResponse(directoryPage([directoryUser({ email: '<script>@example.test' })]))
      )
    );
    expect(await screen.findByRole('rowheader', { name: '<script>@example.test' })).toBeVisible();
    expect(document.querySelector('script, svg, iframe')).toBeNull();
    expect(
      screen.getByRole('link', {
        name: 'Invitation status for <script>@example.test (not available yet)'
      })
    ).toHaveAttribute('href', '/app/admin/invitations');
  });

  it('rechecks link activation against expired dispatch authority before the next render', async () => {
    let now = 0;
    const cache = new AuthorityCache(
      () =>
        Promise.resolve({
          status: 'principal',
          principal: {
            userId: crypto.randomUUID(),
            email: 'reader@example.test',
            roles: ['ROLE_SUPER_ADMIN'],
            permissions: directoryPermissions
          }
        }),
      () => now
    );
    caches.push(cache);
    await cache.load();
    const { service, cache: unused } = directoryDependencies();
    unused.dispose();
    render(<UsersPage cache={cache} service={service} />);
    await screen.findByRole('table');
    const link = screen.getByRole('link', { name: 'Invite User' });
    now = 60_000;
    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    await act(async () => {
      link.dispatchEvent(click);
      await Promise.resolve();
    });
    expect(click.defaultPrevented).toBe(true);
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });

  it('announces loading and empty and hands keyboard retry focus to the heading', async () => {
    let finish: ((response: Response) => void) | undefined;
    const fetch = vi.fn(
      () =>
        new Promise<Response>((resolve) => {
          finish = resolve;
        })
    );
    await mount(fetch);
    expect(screen.getByText('Loading users')).toBeVisible();
    await act(async () => {
      finish?.(directoryResponse(directoryPage([])));
      await Promise.resolve();
    });
    expect(await screen.findByText('No users on this page')).toBeVisible();
    const reload = screen.getByRole('button', { name: 'Reload users' });
    reload.focus();
    await userEvent.keyboard('{Enter}');
    expect(fetch).toHaveBeenCalledTimes(2);
    await act(async () => {
      finish?.(directoryResponse(directoryPage([])));
      await Promise.resolve();
    });
    await waitFor(() => expect(screen.getByRole('heading', { name: 'Users' })).toHaveFocus());
  });

  it('pages on the server, clears old rows while pending and supports First page with focus', async () => {
    const fetch = vi.fn((url: string) =>
      Promise.resolve(
        directoryResponse(
          url.includes('page=2&')
            ? directoryPage([directoryUser({ email: 'last@example.test' })], {
                page: 2,
                total_pages: 2,
                total_records: 26
              })
            : directoryPage(
                Array.from({ length: 25 }, (_, i) =>
                  directoryUser({ email: `person${i}@example.test` })
                ),
                { total_pages: 2, total_records: 26 }
              )
        )
      )
    );
    await mount(fetch);
    await screen.findByRole('table');
    await userEvent.click(screen.getByRole('button', { name: 'Next page' }));
    expect(await screen.findByText('last@example.test')).toBeVisible();
    expect(screen.queryByText('person0@example.test')).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Users' })).toHaveFocus();
    expect(screen.getByRole('button', { name: 'Next page' })).toBeDisabled();
    await userEvent.click(screen.getByRole('button', { name: 'First page' }));
    await screen.findByText('person0@example.test');
    expect(fetch).toHaveBeenCalledTimes(3);
  });

  it('retains no late private page after logout, and removes links even when an old handler is activated', async () => {
    let finish: ((response: Response) => void) | undefined;
    const d = await mount(
      () =>
        new Promise<Response>((resolve) => {
          finish = resolve;
        })
    );
    const link = screen.getByRole('link', { name: 'Invite User' });
    await act(() => d.cache.signal('logout'));
    await act(async () => {
      finish?.(
        directoryResponse(directoryPage([directoryUser({ email: 'late-private@example.test' })]))
      );
      await Promise.resolve();
    });
    expect(screen.getByText('User view cleared')).toBeVisible();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
    expect(link.isConnected).toBe(false);
    expect(screen.queryByText('late-private@example.test')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Retry user list' })).toBeDisabled();
  });

  it('suppresses callbacks after unmount and exposes safe explicit error recovery without replay', async () => {
    const d = directoryDependencies();
    caches.push(d.cache);
    await d.cache.load();
    const load = vi.fn(() => Promise.resolve(apiFailure('authorization')));
    const retry = vi.fn();
    const view = render(<UsersPage cache={d.cache} service={{ load, retry }} />);
    expect(await screen.findByText('Access forbidden')).toBeVisible();
    expect(load).toHaveBeenCalledTimes(1);
    await userEvent.click(screen.getByRole('button', { name: 'Retry user list' }));
    expect(retry).toHaveBeenCalledTimes(1);
    expect(load).toHaveBeenCalledTimes(2);
    view.unmount();
    const pending = vi.fn(() => new Promise<ReturnType<typeof apiFailure>>(() => {}));
    const next = render(<UsersPage cache={d.cache} service={{ load: pending, retry }} />);
    next.unmount();
    expect(pending.mock.calls).toHaveLength(1);
  });
});
