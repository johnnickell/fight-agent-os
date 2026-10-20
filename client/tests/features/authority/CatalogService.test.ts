import { describe, expect, it, vi } from 'vitest';

import { ApiClient } from '@/api/ApiClient';
import { CatalogService } from '@/features/authority/CatalogService';

const roleId = crypto.randomUUID();
const permissionId = crypto.randomUUID();
const role = { role_id: roleId, name: 'ROLE_CUSTOM', managed: false };
const permission = {
  permission_id: permissionId,
  name: 'READ_ROLES',
  tier: 'SUPER_ADMIN_ONLY',
  managed: true
};
const response = (data: unknown) =>
  new Response(JSON.stringify({ status: 'success', data }), {
    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
  });
function setup(data: unknown, token: string | null = 'memory-only') {
  const fetch = vi.fn().mockImplementation(() => Promise.resolve(response(data)));
  const api = new ApiClient(
    { schemaVersion: 1, apiBasePath: '/api/v1' },
    {
      fetch,
      accessTokens: { getAccessToken: () => token }
    }
  );
  return { service: new CatalogService(api), fetch };
}

const page = (records: readonly unknown[]) => ({
  page: 2,
  per_page: 1,
  total_pages: 2,
  total_records: 2,
  records
});

describe('CatalogService', () => {
  it('maps exact safe role and permission fields with current Bearer reads', async () => {
    const roles = setup(role);
    expect(await roles.service.role(roleId)).toMatchObject({
      ok: true,
      value: { roleId, name: 'ROLE_CUSTOM', managed: false }
    });
    expect(roles.fetch.mock.calls[0]?.[0]).toBe(`/api/v1/roles/${roleId}`);
    const init = roles.fetch.mock.calls[0]?.[1] as RequestInit;
    expect(new Headers(init.headers).get('Authorization')).toBe('Bearer memory-only');
    expect(init).toMatchObject({
      method: 'GET',
      cache: 'no-store',
      redirect: 'error',
      referrerPolicy: 'no-referrer'
    });
    expect(init.body).toBeUndefined();
    const permissions = setup(permission);
    expect(await permissions.service.permission(permissionId)).toMatchObject({
      ok: true,
      value: { permissionId, name: 'READ_ROLES', tier: 'SUPER_ADMIN_ONLY', managed: true }
    });
    expect(permissions.fetch.mock.calls[0]?.[0]).toBe(`/api/v1/permissions/${permissionId}`);
  });

  it('maps bounded pages and generates only the explicit numeric query keys', async () => {
    const roles = setup(page([role]));
    expect(await roles.service.roles({ page: 2, perPage: 1 })).toMatchObject({
      ok: true,
      value: {
        page: 2,
        perPage: 1,
        totalPages: 2,
        totalRecords: 2,
        records: [{ roleId, name: 'ROLE_CUSTOM', managed: false }]
      }
    });
    expect(roles.fetch.mock.calls[0]?.[0]).toBe('/api/v1/roles?page=2&per_page=1');
    const permissions = setup(page([permission]));
    const result = await permissions.service.permissions({ page: 2, perPage: 1 });
    expect(result).toMatchObject({ ok: true, value: { records: [{ permissionId }] } });
    expect(permissions.fetch.mock.calls[0]?.[0]).toBe('/api/v1/permissions?page=2&per_page=1');
    if (result.ok) {
      expect(Object.isFrozen(result.value)).toBe(true);
      expect(Object.isFrozen(result.value.records)).toBe(true);
      expect(Object.isFrozen(result.value.records[0])).toBe(true);
    }
    const empty = setup({ page: 1, per_page: 25, total_pages: 1, total_records: 0, records: [] });
    expect(await empty.service.roles()).toMatchObject({ ok: true, value: { records: [] } });
  });

  it.each([
    { page: 0, perPage: 25 },
    { page: 1001, perPage: 25 },
    { page: 1, perPage: 0 },
    { page: 1, perPage: 101 },
    { page: 1.5, perPage: 1 },
    { page: NaN, perPage: 1 },
    { page: 1, perPage: Infinity }
  ])('refuses unsafe page bounds without network work: %j', async (pagination) => {
    const { service, fetch } = setup(page([role]));
    expect(await service.roles(pagination)).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
    expect(fetch).not.toHaveBeenCalled();
  });

  it('refuses unsafe IDs and missing credentials before every real read', async () => {
    const { service, fetch } = setup(role, null);
    for (const id of ['invalid', '../roles', `${roleId}?page=1`, `${roleId}\n`]) {
      expect(await service.role(id)).toMatchObject({ ok: false, error: { kind: 'protocol' } });
      expect(await service.permission(id)).toMatchObject({
        ok: false,
        error: { kind: 'protocol' }
      });
    }
    for (const result of [
      await service.role(roleId),
      await service.permission(permissionId),
      await service.roles(),
      await service.permissions()
    ]) {
      expect(result).toMatchObject({ ok: false, error: { kind: 'authentication' } });
    }
    expect(fetch).not.toHaveBeenCalled();
  });

  it.each([
    { ...role, permission_ids: [permissionId] },
    { ...role, managed: 'false' },
    { ...role, name: 'admin' },
    { ...role, role_id: crypto.randomUUID() },
    { ...role, name: 'ROLE_CUSTOM\n' },
    null
  ])('fails closed on broader or malformed role representations', async (data) => {
    const { service } = setup(data);
    expect(await service.role(roleId)).toMatchObject({ ok: false, error: { kind: 'protocol' } });
  });

  it.each([
    { ...permission, tier: null },
    { ...permission, tier: 'UNKNOWN' },
    { ...permission, grants: [] },
    { ...permission, permission_id: crypto.randomUUID() },
    { ...permission, name: 'read_roles' },
    { ...permission, managed: null },
    { ...permission, name: 'READ_ROLES\n' }
  ])('rejects malformed tiers, identities and private grant fields', async (data) => {
    const { service } = setup(data);
    expect(await service.permission(permissionId)).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
  });

  it.each([
    { ...page([role]), page: 1 },
    { ...page([role]), per_page: 2 },
    { ...page([role]), total_pages: 3 },
    { ...page([role]), total_records: -1 },
    { ...page([role]), records: [role, role] },
    { ...page([role]), records: [null] },
    { ...page([role]), private: 'secret' },
    null
  ])('rejects inconsistent or unbounded page contracts', async (data) => {
    const { service } = setup(data);
    expect(await service.roles({ page: 2, perPage: 1 })).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
  });

  it.each([
    [401, 'Unauthorized.', 'authentication'],
    [403, 'Forbidden.', 'authorization'],
    [404, 'Not found.', 'not-found']
  ] as const)('preserves safe %i outcomes without retries', async (status, message, kind) => {
    const fetch = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ status: 'error', message }), {
        status,
        headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
      })
    );
    const service = new CatalogService(
      new ApiClient(
        { schemaVersion: 1, apiBasePath: '/api/v1' },
        {
          fetch,
          accessTokens: { getAccessToken: () => 'memory-only' }
        }
      )
    );
    expect(await service.role(roleId)).toMatchObject({ ok: false, error: { kind } });
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it('cancels protected reads without retaining private results', async () => {
    const { service, fetch } = setup(role);
    const controller = new AbortController();
    controller.abort();
    expect(await service.role(roleId, controller.signal)).toMatchObject({
      ok: false,
      error: { kind: 'cancelled' }
    });
    expect(fetch).not.toHaveBeenCalled();
  });
});
