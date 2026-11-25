import {
  AuthorityCache,
  CatalogService,
  CustomRoleService,
  PermissionsPage,
  RolesPage
} from '@fight/react/account';
import { principal } from '@fixtures/authorityFixtures';
import { ApiClient } from '@fixtures/transport';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

const custom = {
  role_id: crypto.randomUUID(),
  name: 'ROLE_CUSTOM',
  managed: false,
  revision: `${crypto.randomUUID()}:1`
};
const managed = { ...custom, role_id: crypto.randomUUID(), name: 'ROLE_USER', managed: true };
const safe = {
  permission_id: crypto.randomUUID(),
  name: 'VIEW_DASHBOARD',
  managed: true,
  tier: 'ADMIN_SAFE'
};
const protectedPermission = {
  ...safe,
  permission_id: crypto.randomUUID(),
  name: 'READ_ROLES',
  tier: 'SUPER_ADMIN_ONLY'
};
const future = {
  ...safe,
  permission_id: crypto.randomUUID(),
  name: 'FUTURE',
  tier: 'FUTURE_POLICY'
};
const all = [
  'READ_ROLES',
  'READ_PERMISSIONS',
  'CREATE_ROLES',
  'UPDATE_ROLES',
  'DELETE_ROLES',
  'MANAGE_ROLE_PERMISSIONS'
];
const caches: AuthorityCache[] = [];
const response = (data: unknown, status = 200) =>
  new Response(
    JSON.stringify(status === 200 ? { status: 'success', data } : { status: 'fail', data }),
    { status, headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' } }
  );
const page = (
  records: readonly unknown[],
  { page = 1, perPage = 25, total = records.length } = {}
) =>
  response({
    records,
    page,
    per_page: perPage,
    total_pages: Math.max(1, Math.ceil(total / perPage)),
    total_records: total
  });

afterEach(() => {
  caches.splice(0).forEach((cache) => cache.dispose());
  window.history.replaceState(null, '', '/');
});

async function setup(
  permissions: string[],
  network?: (url: string, init: RequestInit) => Promise<Response> | Response
) {
  const getPrincipal = vi.fn(() =>
    Promise.resolve({ status: 'principal' as const, principal: { ...principal, permissions } })
  );
  const cache = new AuthorityCache(getPrincipal);
  caches.push(cache);
  await cache.load();
  const fetch = vi.fn(async (url: string, init: RequestInit) =>
    network
      ? network(url, init)
      : url.startsWith('/api/v1/permissions')
        ? page([safe, protectedPermission, future])
        : page([managed, custom])
  );
  const api = new ApiClient(
    { schemaVersion: 2, apiBasePath: '/api/v1' },
    { fetch, accessTokens: { getAccessToken: () => 'volatile-credential' } }
  );
  return {
    cache,
    catalog: new CatalogService(api),
    mutations: new CustomRoleService(api, cache),
    fetch,
    getPrincipal
  };
}

describe('authority catalog pages', () => {
  it('does not fetch roles or offer create from a principal with unrelated permissions', async () => {
    const { fetch, ...services } = await setup(['READ_PERMISSIONS', 'CREATE_ROLES']);
    render(<RolesPage {...services} />);
    expect(screen.getByText('Access forbidden')).toBeVisible();
    expect(screen.queryByRole('button', { name: 'Create role' })).not.toBeInTheDocument();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('offers only the separately held mutation and leaves managed roles read-only', async () => {
    const services = await setup(['READ_ROLES', 'UPDATE_ROLES', 'MANAGE_ROLE_PERMISSIONS']);
    render(<RolesPage {...services} />);
    expect(
      await screen.findByRole('button', { name: 'Rename role for ROLE_CUSTOM' })
    ).toBeVisible();
    expect(screen.queryByRole('button', { name: /for ROLE_USER/ })).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', {
        name: /Create role|Remove role|Grant permission|Revoke permission/
      })
    ).not.toBeInTheDocument();
  });

  it('reads the ordered page from the server and ignores a retired response after the URL changes', async () => {
    window.history.replaceState(
      null,
      '',
      '/admin/permissions?page=2&per_page=1&sort=ID&order=desc&search=discard-me&host=keep'
    );
    let settle: ((result: Response) => void) | undefined;
    const { fetch, cache, catalog } = await setup(['READ_PERMISSIONS'], (url) =>
      url.includes('page=2&')
        ? new Promise<Response>((resolve) => {
            settle = resolve;
          })
        : page([protectedPermission], { perPage: 1, total: 2 })
    );
    render(<PermissionsPage catalog={catalog} cache={cache} />);
    await waitFor(() => expect(fetch).toHaveBeenCalledOnce());
    expect(fetch.mock.calls[0]?.[0]).toBe(
      '/api/v1/permissions?page=2&per_page=1&sort=ID&order=desc'
    );
    expect(window.location.search).toContain('host=keep');
    expect(window.location.href).not.toContain('discard-me');
    act(() => {
      window.history.replaceState(
        null,
        '',
        '/admin/permissions?page=1&per_page=1&sort=Created&order=asc'
      );
      window.dispatchEvent(new PopStateEvent('popstate'));
    });
    expect(await screen.findByText('Super Admin only — not delegable')).toBeVisible();
    await act(() => {
      settle?.(page([safe], { page: 2, perPage: 1, total: 2 }));
      return Promise.resolve();
    });
    expect(screen.queryByText('VIEW_DASHBOARD')).not.toBeInTheDocument();
    expect(screen.queryByRole('searchbox')).not.toBeInTheDocument();
    expect(document.body).not.toHaveTextContent('volatile-credential');
    expect(localStorage.length + sessionStorage.length).toBe(0);
  });

  it.each([
    ['State conflict.', /operation conflicts/],
    ['private-response-detail', /operation could not be confirmed/]
  ])('cancels without a write and refetches after a 409 with %s', async (message, notice) => {
    const { cache, catalog, mutations, fetch, getPrincipal } = await setup(all, (_url, init) =>
      init.method === 'DELETE' ? response({ message }, 409) : page([custom])
    );
    render(<RolesPage cache={cache} catalog={catalog} mutations={mutations} />);
    const trigger = await screen.findByRole('button', { name: 'Remove role for ROLE_CUSTOM' });
    fireEvent.click(trigger);
    fireEvent.click(
      within(await screen.findByRole('dialog')).getByRole('button', { name: 'Cancel' })
    );
    expect(fetch).toHaveBeenCalledOnce();
    fireEvent.click(trigger);
    fireEvent.click(
      within(await screen.findByRole('dialog')).getByRole('button', { name: 'Remove role' })
    );
    expect(await screen.findByText(notice)).toBeVisible();
    expect(screen.queryByText('private-response-detail')).not.toBeInTheDocument();
    expect(getPrincipal).toHaveBeenCalledTimes(2);
    await waitFor(() =>
      expect(fetch.mock.calls.filter(([url]) => url.startsWith('/api/v1/roles?'))).toHaveLength(2)
    );
  });

  it('never publishes an old mutation outcome after the authentication context ends', async () => {
    let settle: ((result: Response) => void) | undefined;
    const { cache, catalog, mutations, fetch, getPrincipal } = await setup(all, (_url, init) =>
      init.method === 'DELETE'
        ? new Promise<Response>((resolve) => {
            settle = resolve;
          })
        : page([custom])
    );
    const outcome = vi.fn();
    render(<RolesPage cache={cache} catalog={catalog} mutations={mutations} onOutcome={outcome} />);
    fireEvent.click(await screen.findByRole('button', { name: 'Remove role for ROLE_CUSTOM' }));
    fireEvent.click(
      within(await screen.findByRole('dialog')).getByRole('button', { name: 'Remove role' })
    );
    await waitFor(() => expect(fetch).toHaveBeenCalledTimes(2));
    await act(() => cache.signal('logout'));
    await act(() => {
      settle?.(response(null));
      return Promise.resolve();
    });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.queryByText(custom.name)).not.toBeInTheDocument();
    expect(outcome).not.toHaveBeenCalled();
    expect(getPrincipal).toHaveBeenCalledOnce();
  });

  it('only lets the picker select an eligible permission and never assumes membership', async () => {
    const services = await setup(all);
    render(<RolesPage {...services} />);
    fireEvent.click(
      await screen.findByRole('button', { name: 'Grant permission for ROLE_CUSTOM' })
    );
    const dialog = within(await screen.findByRole('dialog'));
    expect(await dialog.findByRole('option', { name: /READ_ROLES/ })).toBeDisabled();
    expect(dialog.getByRole('option', { name: /Unknown tier/ })).toBeDisabled();
    expect(dialog.getByRole('button', { name: 'Grant permission' })).toBeDisabled();
    expect(dialog.getByText(/does not list current associations/)).toBeVisible();
    fireEvent.change(dialog.getByRole('combobox'), { target: { value: safe.permission_id } });
    expect(dialog.getByRole('button', { name: 'Grant permission' })).toBeEnabled();
  });
});
