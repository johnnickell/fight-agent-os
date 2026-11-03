import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { apiFailure } from '@/api/ApiResult';
import UserDetail from '@/pages/UserDetail';

import { directoryDependencies, directoryResponse, directoryUser } from '../userDirectoryFixtures';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';

const caches: AuthorityCache[] = [];
afterEach(() => {
  for (const cache of caches.splice(0)) cache.dispose();
});

async function mount(
  fetch?: (url: string, init: RequestInit) => Promise<Response>,
  permissions = ['READ_USERS']
) {
  const user = directoryUser({
    email: '"a@b"@example.test',
    roles: [{ name: 'ROLE_CUSTOM', managed: false }]
  });
  const d = directoryDependencies(
    fetch ?? (() => Promise.resolve(directoryResponse(user))),
    permissions
  );
  caches.push(d.cache);
  await d.cache.load();
  const back = vi.fn();
  const rendered = render(
    <UserDetail userId={user.user_id} cache={d.cache} service={d.service} onBack={back} />
  );
  return { ...d, ...rendered, user, back };
}

describe('UserDetail', () => {
  it('shows exact safe read-only state, supports keyboard return and focuses its heading', async () => {
    const d = await mount();
    expect(await screen.findByText('"a@b"@example.test')).toBeVisible();
    expect(screen.getByText(d.user.user_id)).toBeVisible();
    expect(screen.getByText('ROLE_CUSTOM (Custom)')).toBeVisible();
    expect(screen.getByText('pending activation')).toBeVisible();
    expect(screen.getByRole('heading', { name: 'User detail' })).toHaveFocus();
    expect(document.querySelectorAll('time')).toHaveLength(2);
    const back = screen.getByRole('button', { name: 'Back to user list' });
    back.focus();
    await userEvent.keyboard('{Enter}');
    expect(d.back).toHaveBeenCalledTimes(1);
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('does not dispatch with role-name-only or self-service permissions', async () => {
    const fetch = vi.fn();
    await mount(fetch, ['READ_OWN_SESSIONS', 'DELETE_OWN_SESSIONS']);
    expect(fetch).not.toHaveBeenCalled();
    expect(screen.getByText('User detail cleared')).toBeVisible();
    expect(screen.getByRole('button', { name: 'Back to user list' })).toBeDisabled();
  });

  it('clears late detail on authority retirement and never persists target data', async () => {
    let finish: ((response: Response) => void) | undefined;
    const fetch = vi.fn(
      () =>
        new Promise<Response>((resolve) => {
          finish = resolve;
        })
    );
    const d = await mount(fetch);
    expect(screen.getByText('Loading user detail')).toBeVisible();
    await act(() => d.cache.signal('logout'));
    await act(async () => {
      finish?.(directoryResponse(d.user));
      await Promise.resolve();
    });
    expect(screen.getByText('User detail cleared')).toBeVisible();
    expect(screen.queryByText(d.user.user_id)).not.toBeInTheDocument();
    expect(screen.queryByText(d.user.email)).not.toBeInTheDocument();
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it.each([
    ['authorization', 'Access forbidden'],
    ['not-found', 'User not found'],
    ['system', 'User detail unavailable']
  ] as const)(
    'shows safe %s and requires explicit recovery without retaining detail',
    async (kind, title) => {
      const d = directoryDependencies();
      caches.push(d.cache);
      await d.cache.load();
      const detail = vi.fn(() => Promise.resolve(apiFailure(kind)));
      const retry = vi.fn();
      render(
        <UserDetail
          userId={crypto.randomUUID()}
          cache={d.cache}
          service={{ detail, retry }}
          onBack={vi.fn()}
        />
      );
      expect(await screen.findByText(title)).toBeVisible();
      expect(detail).toHaveBeenCalledTimes(1);
      await userEvent.click(screen.getByRole('button', { name: 'Retry user detail' }));
      expect(retry).toHaveBeenCalledTimes(1);
      expect(detail).toHaveBeenCalledTimes(2);
      expect(screen.getByRole('heading', { name: 'User detail' })).toHaveFocus();
    }
  );

  it('aborts a pending detail on unmount', async () => {
    let signal: AbortSignal | null | undefined;
    const d = await mount((_url, init) => {
      signal = init.signal;
      return new Promise<Response>(() => {});
    });
    expect(signal?.aborted).toBe(false);
    d.unmount();
    expect(signal?.aborted).toBe(true);
  });
});
