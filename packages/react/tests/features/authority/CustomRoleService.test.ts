import { AuthorityCache } from '@fight/react/account';
import { CustomRoleService } from '@fight/react/account';
import { ApiClient } from '@fixtures/transport';
import { describe, expect, it, vi } from 'vitest';

const roleId = crypto.randomUUID();
const revision = `${crypto.randomUUID()}:7`;
const displayed = { roleId, name: 'ROLE_CUSTOM', managed: false, revision };

function setup(status: number, data: unknown) {
  const load = vi.fn().mockResolvedValue({ status: 'network' });
  const authority = new AuthorityCache(load);
  const fetch = vi
    .fn()
    .mockResolvedValue(
      new Response(
        JSON.stringify(
          status < 300
            ? { status: 'success', data }
            : status === 409
              ? { status: 'fail', data: { message: 'State conflict.' } }
              : { status: 'error', message: 'Internal server error.' }
        ),
        { status, headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' } }
      )
    );
  const api = new ApiClient(
    { schemaVersion: 2, apiBasePath: '/api/v1' },
    {
      fetch,
      accessTokens: { getAccessToken: () => 'current-token' }
    }
  );
  return { service: new CustomRoleService(api, authority), load, fetch };
}

describe('CustomRoleService', () => {
  it('sends only a name, accepts the generated identity with 201 and refetches authority', async () => {
    const { service, load, fetch } = setup(201, { role_id: roleId });
    expect(await service.create('ROLE_CUSTOM')).toMatchObject({
      ok: true,
      value: { roleId }
    });
    const init = fetch.mock.calls[0]?.[1] as RequestInit;
    expect(init.method).toBe('POST');
    expect(new Headers(init.headers).get('Authorization')).toBe('Bearer current-token');
    expect(JSON.parse(init.body as string)).toEqual({ name: 'ROLE_CUSTOM' });
    expect(load).toHaveBeenCalledOnce();
  });

  it('keeps the displayed revision on rename without auto-retrying a conflict', async () => {
    const { service, fetch, load } = setup(409, null);
    expect(await service.rename(displayed, 'ROLE_RENAMED')).toMatchObject({
      ok: false,
      error: { kind: 'conflict' }
    });
    expect(fetch).toHaveBeenCalledOnce();
    const init = fetch.mock.calls[0]?.[1] as RequestInit;
    expect(init.method).toBe('PATCH');
    expect(JSON.parse(init.body as string)).toEqual({
      name: 'ROLE_RENAMED',
      expected_revision: revision
    });
    expect(load).toHaveBeenCalledOnce();
    expect(displayed.revision).toBe(revision);
  });

  it('requires confirmation before sending an exact revision removal', async () => {
    const { service, fetch, load } = setup(200, null);
    expect(await service.remove(displayed, false)).toMatchObject({ ok: false });
    expect(fetch).not.toHaveBeenCalled();
    expect(await service.remove(displayed, true)).toMatchObject({ ok: true, value: true });
    const init = fetch.mock.calls[0]?.[1] as RequestInit;
    expect(init.method).toBe('DELETE');
    expect(JSON.parse(init.body as string)).toEqual({ expected_revision: revision, confirm: true });
    expect(load).toHaveBeenCalledOnce();
  });

  it('refetches after uncertain server failure without claiming rollback or replaying', async () => {
    const { service, fetch, load } = setup(500, null);
    expect(await service.rename(displayed, 'ROLE_RENAMED')).toMatchObject({
      ok: false,
      error: { kind: 'system' }
    });
    expect(fetch).toHaveBeenCalledOnce();
    expect(load).toHaveBeenCalledOnce();
  });

  it('rejects a mismatched rename response identity and still refetches', async () => {
    const { service, load } = setup(200, {
      role_id: crypto.randomUUID(),
      name: 'ROLE_CUSTOM',
      managed: false,
      revision
    });
    expect(await service.rename(displayed, 'ROLE_CUSTOM')).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
    expect(load).toHaveBeenCalledOnce();
  });

  it.each([
    { role_id: 'invalid' },
    { role_id: undefined },
    { revision },
    { managed: true },
    { permission_ids: [] }
  ])('rejects malformed or extra creation data %j without replaying', async (patch) => {
    const { service, fetch, load } = setup(201, { role_id: roleId, ...patch });
    expect(await service.create('ROLE_CUSTOM')).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
    expect(fetch).toHaveBeenCalledOnce();
    expect(load).toHaveBeenCalledOnce();
  });

  it.each([409, 500])('refetches an unsuccessful create (%s) without replaying', async (status) => {
    const { service, fetch, load } = setup(status, null);
    expect(await service.create('ROLE_CUSTOM')).toMatchObject({
      ok: false,
      error: { kind: status === 409 ? 'conflict' : 'system' }
    });
    expect(fetch).toHaveBeenCalledOnce();
    expect(load).toHaveBeenCalledOnce();
  });

  it('refetches after a lost creation response without retrying', async () => {
    const { service, fetch, load } = setup(201, null);
    fetch.mockRejectedValueOnce(new TypeError('Connection lost'));
    expect(await service.create('ROLE_CUSTOM')).toMatchObject({
      ok: false,
      error: { kind: 'network' }
    });
    expect(fetch).toHaveBeenCalledOnce();
    expect(load).toHaveBeenCalledOnce();
  });

  it('rejects an unexpected creation status even with a valid generated identity', async () => {
    const { service, fetch, load } = setup(200, { role_id: roleId });
    expect(await service.create('ROLE_CUSTOM')).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
    expect(fetch).toHaveBeenCalledOnce();
    expect(load).toHaveBeenCalledOnce();
  });

  it.each([
    ['grant', 'PUT'],
    ['revoke', 'DELETE']
  ] as const)(
    'sends a confirmed %s without guessing current membership',
    async (action, method) => {
      const permissionId = crypto.randomUUID();
      const { service, fetch, load } = setup(200, null);
      const permission = {
        permissionId,
        name: 'VIEW_DASHBOARD',
        tier: 'ADMIN_SAFE',
        managed: true
      } as const;
      expect(await service.changePermission(displayed, permission, action, true)).toMatchObject({
        ok: true
      });
      expect(fetch).toHaveBeenCalledOnce();
      const [url, init] = fetch.mock.calls[0] as [string, RequestInit];
      expect(url).toBe(`/api/v1/roles/${roleId}/permissions/${permissionId}`);
      expect(init.method).toBe(method);
      expect(JSON.parse(init.body as string)).toEqual({ confirm: true });
      expect(load).toHaveBeenCalledOnce();
    }
  );

  it('does not dispatch unconfirmed, managed-target or ineligible permission changes', async () => {
    const { service, fetch, load } = setup(200, null);
    const permission = {
      permissionId: crypto.randomUUID(),
      name: 'VIEW_DASHBOARD',
      tier: 'ADMIN_SAFE',
      managed: true
    } as const;
    const managed = { ...displayed, managed: true };
    for (const attempt of [
      service.rename(managed, 'ROLE_OTHER'),
      service.remove(managed, true),
      service.changePermission(managed, permission, 'grant', true),
      service.changePermission(displayed, permission, 'revoke', false),
      service.changePermission(
        displayed,
        { ...permission, tier: 'SUPER_ADMIN_ONLY' },
        'grant',
        true
      ),
      service.changePermission(displayed, { ...permission, tier: 'UNKNOWN' }, 'grant', true),
      service.changePermission(displayed, { ...permission, managed: false }, 'grant', true)
    ])
      expect(await attempt).toMatchObject({ ok: false, error: { kind: 'protocol' } });
    expect(fetch).not.toHaveBeenCalled();
    expect(load).not.toHaveBeenCalled();
  });

  it('rejects malformed target paths before transport', async () => {
    const { service, fetch } = setup(200, null);
    expect(
      await service.rename({ ...displayed, roleId: '@fixtures/features/roles' }, 'ROLE_OTHER')
    ).toMatchObject({
      ok: false
    });
    expect(
      await service.remove({ ...displayed, roleId: '@fixtures/features/roles' }, true)
    ).toMatchObject({
      ok: false
    });
    expect(fetch).not.toHaveBeenCalled();
  });
});
