import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { ShellApplication } from '@/ShellApplication';

import { deferred } from '../apiFixtures';
import { loginSuccess } from '../loginFixtures';
import { directoryPage, directoryResponse, directoryUser } from '../userDirectoryFixtures';

const configuration =
  '{"schema_version":2,"api_base_path":"/api/v1","app_debug":false,"swagger_available":false}';
const allowed = [
  'READ_USERS',
  'READ_SESSIONS',
  'READ_ROLES',
  'MANAGE_USER_ROLES',
  'DISABLE_USERS',
  'ENABLE_USERS'
];

function mount({
  permissions = () => allowed,
  roles = () => Promise.resolve(directoryResponse(directoryPage([]))),
  sessions = () => Promise.resolve(directoryResponse(directoryPage([]))),
  disable = () => Promise.resolve(directoryResponse(null))
}: {
  permissions?: (operator: string) => string[] | Promise<Response>;
  roles?: () => Promise<Response>;
  sessions?: () => Promise<Response>;
  disable?: () => Promise<Response>;
} = {}) {
  const operator = crypto.randomUUID();
  const target = directoryUser({ state: 'active' });
  const apiDetail = `/api/v1/users/${target.user_id}`;
  let state = 'active';
  const fetch = vi.fn(async (url: string) => {
    if (url.endsWith('/auth/csrf')) {
      const expiry = Math.floor(Date.now() / 1000) + 900;
      return directoryResponse({ proof: `${expiry}.${'c'.repeat(64)}`, expires_at: expiry });
    }
    if (url.endsWith('/auth/refresh')) return loginSuccess();
    if (url.endsWith('/me')) {
      const current = permissions(operator);
      return Array.isArray(current)
        ? directoryResponse({
            user_id: operator,
            email: 'operator@example.test',
            roles: ['ROLE_USER'],
            permissions: current
          })
        : current;
    }
    if (url === `${apiDetail}/disable`) {
      const result = await disable();
      if (result.ok) state = 'disabled';
      return result;
    }
    if (url.startsWith(`${apiDetail}/sessions?`)) return sessions();
    if (url.startsWith('/api/v1/roles?')) return roles();
    if (url === apiDetail) return directoryResponse({ ...target, state });
    return directoryResponse(null, 404);
  });
  vi.stubGlobal('fetch', fetch);
  const pathname = `/admin/users/${target.user_id}`;
  window.history.replaceState(null, '', pathname);
  render(<ShellApplication configuration={configuration} pathname={pathname} />);
  return {
    fetch,
    target,
    apiDetail,
    count: (matches: (url: string) => boolean) =>
      fetch.mock.calls.filter(([url]) => matches(url)).length
  };
}

afterEach(() => {
  vi.unstubAllGlobals();
  window.history.replaceState(null, '', '/');
});

describe('production user administration journey', () => {
  it('denies a direct detail route without fetching target data', async () => {
    const { apiDetail, count, target } = mount({ permissions: () => ['DISABLE_USERS'] });
    expect(await screen.findByText('Access forbidden')).toBeVisible();
    expect(count((url) => url.startsWith(apiDetail))).toBe(0);
    expect(screen.queryByText(target.email)).not.toBeInTheDocument();
  });

  it('refetches principal and target only after the confirmed change settles', async () => {
    const reply = deferred<Response>();
    const { apiDetail, count } = mount({ disable: () => reply.promise });
    const operator = userEvent.setup();
    await operator.click(await screen.findByRole('button', { name: 'Disable user' }));
    await operator.click(screen.getByRole('button', { name: 'Confirm change' }));
    await waitFor(() => expect(count((url) => url === `${apiDetail}/disable`)).toBe(1));
    expect(count((url) => url === apiDetail)).toBe(1);
    expect(screen.getByRole('dialog')).toBeVisible();
    expect(screen.queryByText('The server confirmed the change.')).not.toBeInTheDocument();
    await act(() => {
      reply.resolve(directoryResponse(null));
      return reply.promise;
    });
    expect(await screen.findByRole('button', { name: 'Enable user' })).toBeVisible();
    expect(screen.getByText('The server confirmed the change.')).toBeVisible();
    expect(screen.queryByRole('button', { name: 'Disable user' })).not.toBeInTheDocument();
    expect(count((url) => url === apiDetail)).toBe(2);
    expect(count((url) => url.endsWith('/me'))).toBe(2);
    expect(count((url) => url === `${apiDetail}/disable`)).toBe(1);
  });

  it.each([401, 403])(
    'retires the old view and does not replay a %i read during recovery',
    async (status) => {
      const first = deferred<Response>();
      const recovered = deferred<Response>();
      let projection = 0;
      let operatorId = '';
      const records = vi.fn<() => Promise<Response>>(() =>
        Promise.resolve(directoryResponse(directoryPage([])))
      );
      records.mockReturnValueOnce(first.promise);
      const { apiDetail, count, target } = mount({
        permissions: (operator) => {
          operatorId = operator;
          return ++projection === 1 ? allowed : recovered.promise;
        },
        ...(status === 401 ? { sessions: records } : { roles: records })
      });
      expect(await screen.findByRole('button', { name: 'Disable user' })).toBeVisible();
      await waitFor(() => expect(records).toHaveBeenCalledOnce());
      await act(() => {
        first.resolve(directoryResponse(null, status));
        return first.promise;
      });
      expect(screen.queryByText(target.email)).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Disable user' })).not.toBeInTheDocument();
      await waitFor(() => expect(projection).toBe(2));
      await act(() => {
        recovered.resolve(
          directoryResponse({
            user_id: operatorId,
            email: 'operator@example.test',
            roles: ['ROLE_USER'],
            permissions: status === 401 ? allowed : []
          })
        );
        return recovered.promise;
      });
      if (status === 403) {
        expect(await screen.findByText('Access forbidden')).toBeVisible();
        expect(count((url) => url === apiDetail)).toBe(1);
        expect(records).toHaveBeenCalledOnce();
      } else {
        expect(await screen.findByRole('button', { name: 'Disable user' })).toBeVisible();
        const reload = await screen.findByRole('button', { name: 'Reload admin sessions' });
        expect(records).toHaveBeenCalledOnce();
        expect(count((url) => url === apiDetail)).toBe(2);
        await userEvent.click(reload);
        expect(await screen.findByText('No active sessions for this user.')).toBeVisible();
        expect(records).toHaveBeenCalledTimes(2);
      }
      expect(count((url) => url.endsWith('/auth/refresh'))).toBe(status === 401 ? 2 : 1);
    }
  );
});
