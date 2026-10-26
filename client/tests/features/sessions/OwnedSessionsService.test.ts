import { describe, expect, it, vi } from 'vitest';

import {
  sessionDependencies,
  sessionPage,
  sessionsResponse,
  sessionWire
} from '../../sessionFixtures';

describe('OwnedSessionsService', () => {
  it.each([401, 403])(
    'retires the sole owner on %s once and blocks remount replay until explicit retry',
    async (status) => {
      const fetch = vi.fn(() =>
        Promise.resolve(
          new Response(
            JSON.stringify({
              status: 'error',
              message: status === 401 ? 'Unauthorized.' : 'Forbidden.'
            }),
            { status, headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' } }
          )
        )
      );
      const accessRejected = vi.fn();
      const authorityRejected = vi.fn();
      const { service, cache } = sessionDependencies(fetch, { accessRejected, authorityRejected });
      const kind = status === 401 ? 'authentication' : 'authorization';
      expect(await service.load()).toMatchObject({ ok: false, error: { kind } });
      expect(await service.load()).toMatchObject({ ok: false, error: { kind } });
      expect(fetch).toHaveBeenCalledTimes(1);
      expect(status === 401 ? accessRejected : authorityRejected).toHaveBeenCalledTimes(1);
      service.retry();
      await service.load();
      expect(fetch).toHaveBeenCalledTimes(2);
      cache.dispose();
    }
  );
  it('accepts committed creation order when public second precision hides a subsecond difference', async () => {
    const ids = [crypto.randomUUID(), crypto.randomUUID()].sort().reverse();
    const rows = [
      sessionWire({ session_id: ids[0] }),
      sessionWire({ session_id: ids[1], current: false })
    ];
    const { service, cache } = sessionDependencies(() =>
      Promise.resolve(sessionsResponse(sessionPage(rows)))
    );
    expect(await service.load()).toMatchObject({ ok: true });
    cache.dispose();
  });
  it('strictly maps immutable explicit fields using the current protected no-store transport', async () => {
    const row = sessionWire();
    const fetch = vi.fn<(url: string, init: RequestInit) => Promise<Response>>(() =>
      Promise.resolve(sessionsResponse(sessionPage([row])))
    );
    const { service, cache } = sessionDependencies(fetch);
    const result = await service.load();
    expect(result).toMatchObject({
      ok: true,
      value: {
        records: [
          {
            sessionId: row.session_id,
            browserFamily: 'Firefox',
            deviceClass: 'desktop',
            current: true
          }
        ]
      }
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/api/v1/me/sessions?page=1&per_page=25');
    const init = fetch.mock.calls[0]?.[1] as RequestInit;
    expect(init).toMatchObject({
      method: 'GET',
      cache: 'no-store',
      redirect: 'error',
      referrerPolicy: 'no-referrer'
    });
    expect(new Headers(init.headers).get('Authorization')).toBe('Bearer synthetic.memory.only');
    if (result.ok) {
      expect(Object.isFrozen(result.value)).toBe(true);
      expect(Object.isFrozen(result.value.records)).toBe(true);
      expect(Object.isFrozen(result.value.records[0])).toBe(true);
    }
    cache.dispose();
  });

  it.each([
    { credential_digest: 'private-marker' },
    { user_id: crypto.randomUUID() },
    { browser_family: '<script>private-marker</script>' },
    { device_class: 'laptop' },
    { browser_family: 'Firefox/130' },
    { created_at: '2026-02-30T10:00:00+00:00' },
    { created_at: '2026-10-25T10:00:00Z' },
    { last_activity_at: '2026-10-25T09:00:00+00:00' },
    { idle_expires_at: '2026-10-28T10:00:00+00:00' },
    { current: 1 },
    { remembered: 'true' },
    { session_id: 'private-marker' },
    { session_id: crypto.randomUUID() + '\n' }
  ])('rejects malformed or extra session fields without reflecting them: %j', async (overrides) => {
    const { service, cache } = sessionDependencies(() =>
      Promise.resolve(sessionsResponse(sessionPage([sessionWire(overrides)])))
    );
    expect(await service.load()).toEqual({
      ok: false,
      error: { kind: 'protocol', correlationId: null }
    });
    cache.dispose();
  });

  it.each([
    { page: 2 },
    { per_page: 100 },
    { total_records: -1 },
    { total_pages: 2 },
    { raw_user_agent: 'private-marker' },
    { records: [sessionWire(), sessionWire()] },
    { records: new Array(1) }
  ])('rejects inconsistent or unsafe page state: %j', async (overrides) => {
    const { service, cache } = sessionDependencies(() =>
      Promise.resolve(sessionsResponse(sessionPage(undefined, overrides)))
    );
    expect(await service.load()).toMatchObject({ ok: false, error: { kind: 'protocol' } });
    cache.dispose();
  });

  it('rejects duplicates, competing current markers and unstable ordering', async () => {
    const a = sessionWire();
    for (const records of [
      [a, a],
      [a, sessionWire()],
      [
        sessionWire({ created_at: '2026-10-25T11:00:00+00:00', current: false }),
        sessionWire({ current: false })
      ]
    ]) {
      const { service, cache } = sessionDependencies(() =>
        Promise.resolve(sessionsResponse(sessionPage(records)))
      );
      expect(await service.load()).toMatchObject({ ok: false, error: { kind: 'protocol' } });
      cache.dispose();
    }
  });

  it('handles empty and later pages without requiring the current marker on every page', async () => {
    for (const data of [
      sessionPage([]),
      sessionPage([sessionWire({ current: false })], {
        page: 2,
        per_page: 1,
        total_pages: 2,
        total_records: 2
      })
    ]) {
      const { service, cache } = sessionDependencies(() => Promise.resolve(sessionsResponse(data)));
      expect(await service.load(data.page, data.per_page)).toMatchObject({ ok: true });
      cache.dispose();
    }
  });

  it.each([
    [0, 25],
    [1001, 25],
    [1, 0],
    [1, 101],
    [1.5, 1]
  ])('bounds work without fetching (%s, %s)', async (page, perPage) => {
    const fetch = vi.fn(() => Promise.resolve(sessionsResponse(sessionPage())));
    const { service, cache } = sessionDependencies(fetch);
    expect(await service.load(page, perPage)).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
    expect(fetch).not.toHaveBeenCalled();
    cache.dispose();
  });

  it('cancels a delayed response without retaining body or rows', async () => {
    const { service, cache } = sessionDependencies(() => new Promise(() => {}));
    const controller = new AbortController();
    const pending = service.load(1, 25, controller.signal);
    controller.abort();
    expect(await pending).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    cache.dispose();
  });
});
