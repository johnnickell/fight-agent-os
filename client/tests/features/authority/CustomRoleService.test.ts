import { describe, expect, it, vi } from 'vitest';

import { ApiClient } from '@/api/ApiClient';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { CustomRoleService } from '@/features/authority/CustomRoleService';

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
            : { status: 'error', message: status === 409 ? 'Conflict.' : 'Internal server error.' }
        ),
        { status, headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' } }
      )
    );
  const api = new ApiClient(
    { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
    {
      fetch,
      accessTokens: { getAccessToken: () => 'current-token' }
    }
  );
  return { service: new CustomRoleService(api, authority), load, fetch };
}

describe('CustomRoleService', () => {
  it('sends only a name, accepts the generated identity with 201 and refetches authority', async () => {
    const { service, load, fetch } = setup(201, {
      role_id: roleId,
      name: 'ROLE_CUSTOM',
      managed: false,
      revision
    });
    expect(await service.create('ROLE_CUSTOM')).toMatchObject({
      ok: true,
      value: displayed
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
    { revision: 'invalid' },
    { revision: undefined },
    { managed: true },
    { permission_ids: [] }
  ])('rejects malformed or non-custom creation data %j without replaying', async (patch) => {
    const { service, fetch, load } = setup(201, {
      role_id: roleId,
      name: 'ROLE_CUSTOM',
      managed: false,
      revision,
      ...patch
    });
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

  it('rejects an unexpected creation status even with a valid generated view', async () => {
    const { service, fetch, load } = setup(200, {
      role_id: roleId,
      name: 'ROLE_CUSTOM',
      managed: false,
      revision
    });
    expect(await service.create('ROLE_CUSTOM')).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
    expect(fetch).toHaveBeenCalledOnce();
    expect(load).toHaveBeenCalledOnce();
  });

  it('rejects malformed target paths before transport', async () => {
    const { service, fetch } = setup(200, null);
    expect(await service.rename({ ...displayed, roleId: '../roles' }, 'ROLE_OTHER')).toMatchObject({
      ok: false
    });
    expect(await service.remove({ ...displayed, roleId: '../roles' }, true)).toMatchObject({
      ok: false
    });
    expect(fetch).not.toHaveBeenCalled();
  });
});
