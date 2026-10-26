import { describe, expect, it, vi } from 'vitest';

import { apiFailure } from '@/api/ApiResult';
import { UserDirectoryService } from '@/features/users/UserDirectoryService';

import {
  directoryDependencies,
  directoryPage,
  directoryResponse,
  directoryUser
} from '../../userDirectoryFixtures';

function serviceFor(data: unknown) {
  const fetch = vi.fn<(url: string, init: RequestInit) => Promise<Response>>(() =>
    Promise.resolve(directoryResponse(data))
  );
  const { service, cache } = directoryDependencies(fetch);
  cache.dispose();
  return { service, fetch };
}

describe('UserDirectoryService', () => {
  it('sends only current Bearer and bounded pagination; maps exact immutable safe fields without persistence', async () => {
    const row = directoryUser({
      email: 'équipe@example.test',
      roles: [
        { name: 'ROLE_CUSTOM', managed: false },
        { name: 'ROLE_USER', managed: true }
      ]
    });
    const { service, fetch } = serviceFor(
      directoryPage([row], { page: 2, per_page: 1, total_records: 2, total_pages: 2 })
    );
    const result = await service.load(2, 1);
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.value.records[0]).toEqual({
        userId: row.user_id,
        email: 'équipe@example.test',
        state: 'pending_activation',
        roles: row.roles,
        createdAt: row.created_at,
        updatedAt: row.updated_at
      });
      expect(Object.isFrozen(result.value.records)).toBe(true);
      expect(Object.isFrozen(result.value.records[0]?.roles)).toBe(true);
    }
    expect(fetch).toHaveBeenCalledWith(
      '/api/v1/users?page=2&per_page=1',
      expect.objectContaining({
        cache: 'no-store',
        referrerPolicy: 'no-referrer',
        redirect: 'error'
      })
    );
    expect(new Headers(fetch.mock.calls[0]?.[1]?.headers).get('Authorization')).toBe(
      'Bearer test.access.signature'
    );
  });

  it('R59-01 preserves package-accepted quoted canonical email without dropping any row', async () => {
    // Installed EmailAddress and the production PHP View emit this exact canonical value.
    const emails = ['ordinary@example.test', '"a@b"@example.test'];
    const { service } = serviceFor(directoryPage(emails.map((email) => directoryUser({ email }))));
    const result = await service.load();
    expect(result.ok).toBe(true);
    if (result.ok) expect(result.value.records.map((user) => user.email)).toEqual(emails);
  });

  it.each([
    { state: ['active'] },
    { state: ['pending_activation'] },
    { state: ['disabled'] },
    { state: ['deleted'] },
    { state: [] },
    { state: ['active', 'deleted'] },
    { state: null },
    { state: 1 },
    { state: true },
    { state: {} }
  ])('R59-02 rejects a non-string lifecycle value %j', async (override) => {
    const { service } = serviceFor(directoryPage([directoryUser(override)]));
    expect(await service.load()).toEqual(apiFailure('protocol'));
  });

  it.each([
    { password_hash: 'private-hash-marker' },
    { authentication_version: 8 },
    { sessions: [] },
    { grant: 'private-grant-marker' },
    { pending_email_change: 'private-reservation@example.test' },
    { permission_ids: [] },
    { audit: 'private-audit-marker' },
    { roles: [{ name: 'ROLE_USER', managed: true, permissions: [] }] },
    {
      roles: [
        { name: 'ROLE_USER', managed: true },
        { name: 'ROLE_USER', managed: true }
      ]
    },
    {
      roles: [
        { name: 'ROLE_USER', managed: true },
        { name: 'ROLE_CUSTOM', managed: false }
      ]
    },
    { roles: [{ name: '<script>private-marker</script>', managed: false }] },
    { state: 'unknown' },
    { email: '' },
    { email: null },
    { email: ['ordinary@example.test'] },
    { email: 'a'.repeat(255) },
    { email: 'MIXED@example.test' },
    { email: 'private\n@example.test' },
    { user_id: 'private-invalid-marker' },
    { created_at: '2026-02-30T12:00:00+00:00' },
    { updated_at: '2025-01-01T00:00:00+00:00' }
  ])('atomically rejects unsafe, ambiguous or malformed identity %j', async (override) => {
    const { service } = serviceFor(directoryPage([directoryUser(override)]));
    expect(await service.load()).toEqual(apiFailure('protocol'));
  });

  it('rejects duplicate identities and malformed or mismatched page metadata', async () => {
    const user = directoryUser();
    for (const page of [
      directoryPage([user, user]),
      directoryPage([], { page: 2 }),
      directoryPage([], { per_page: 100 }),
      directoryPage([], { total_pages: 0 }),
      directoryPage([], { total_records: -1 }),
      directoryPage([], { total_pages: 2 }),
      directoryPage([user], { total_records: 0 }),
      directoryPage(Array.from({ length: 26 }, () => directoryUser())),
      directoryPage([
        directoryUser({
          created_at: '2026-10-26T12:00:00+00:00',
          updated_at: '2026-10-26T12:00:00+00:00'
        }),
        user
      ])
    ]) {
      expect((await serviceFor(page).service.load()).ok).toBe(false);
    }
  });

  it('accepts empty pages and empty assignments without inventing a role', async () => {
    expect((await serviceFor(directoryPage([])).service.load()).ok).toBe(true);
    expect(
      (await serviceFor(directoryPage([directoryUser({ roles: [] })])).service.load()).ok
    ).toBe(true);
  });

  it.each(['authentication', 'authorization'] as const)(
    'invalidates %s once, blocks remount replay and requires explicit retry',
    async (kind) => {
      const status = kind === 'authentication' ? 401 : 403;
      const fetch = vi.fn(() => Promise.resolve(directoryResponse(null, status)));
      const { api, cache } = directoryDependencies(fetch);
      cache.dispose();
      const accessRejected = vi.fn();
      const authorityRejected = vi.fn();
      const service = new UserDirectoryService(api, { accessRejected, authorityRejected });
      expect((await service.load()).ok).toBe(false);
      expect((await service.load()).ok).toBe(false);
      expect(fetch).toHaveBeenCalledTimes(1);
      expect(kind === 'authentication' ? accessRejected : authorityRejected).toHaveBeenCalledTimes(
        1
      );
      service.retry();
      await service.load();
      expect(fetch).toHaveBeenCalledTimes(2);
    }
  );

  it('cancels ignored late reads and never invokes rejection lifecycle after cancellation', async () => {
    let finish: ((response: Response) => void) | undefined;
    const { api, cache } = directoryDependencies(
      () =>
        new Promise((resolve) => {
          finish = resolve;
        })
    );
    cache.dispose();
    const rejected = vi.fn();
    const service = new UserDirectoryService(api, { authorityRejected: rejected });
    const controller = new AbortController();
    const result = service.load(1, 25, controller.signal);
    controller.abort();
    finish?.(directoryResponse(null, 403));
    expect(await result).toEqual(apiFailure('cancelled'));
    expect(rejected).not.toHaveBeenCalled();
  });

  it('rejects invalid pagination locally without network dispatch', async () => {
    const { service, fetch } = serviceFor(directoryPage());
    for (const [page, perPage] of [
      [0, 25],
      [1001, 25],
      [1, 101],
      [1, 0],
      [1.5, 25]
    ])
      await service.load(page, perPage);
    expect(fetch).not.toHaveBeenCalled();
  });
});
