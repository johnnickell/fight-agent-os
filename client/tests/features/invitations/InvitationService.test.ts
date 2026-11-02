import { afterEach, describe, expect, it, vi } from 'vitest';

import { deferred } from '../../apiFixtures';
import {
  invitationDependencies,
  invitationResponse,
  invitationTarget
} from '../../invitationFixtures';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';

const caches: AuthorityCache[] = [];
async function setup(
  fetch: (url: string, init: RequestInit) => Promise<Response>,
  permissions?: string[],
  invalidated?: () => void
) {
  const d = invitationDependencies(fetch, permissions, invalidated);
  caches.push(d.cache);
  await d.cache.load();
  return d;
}
afterEach(() => {
  caches.splice(0).forEach((cache) => cache.dispose());
  vi.useRealTimers();
});

describe('InvitationService', () => {
  it.each(
    [
      [],
      ['MANAGE_USER_INVITATIONS'],
      ['READ_USERS'],
      ['READ_USERS', 'MANAGE_USER_INVITATIONS']
    ].map((permissions) => ({ permissions }))
  )('requires read and separate change permissions for $permissions', async ({ permissions }) => {
    const target = invitationTarget();
    const fetch = vi.fn((_url: string, init: RequestInit) =>
      Promise.resolve(invitationResponse(init.method === 'GET' ? target : target.delivery))
    );
    const d = await setup(fetch, permissions);
    expect((await d.service.load(target.user_id)).ok).toBe(permissions.includes('READ_USERS'));
    expect((await d.service.change(target.user_id, 'resend')).ok).toBe(
      permissions.includes('READ_USERS') && permissions.includes('MANAGE_USER_INVITATIONS')
    );
    if (!permissions.includes('READ_USERS')) expect(fetch).not.toHaveBeenCalled();
  });
  it('uses exact bodyless POST and canonical PATCH wire without query, actor or provider input', async () => {
    const target = invitationTarget();
    const fetch = vi.fn((_url: string, init: RequestInit) =>
      Promise.resolve(invitationResponse(init.method === 'GET' ? target : target.delivery))
    );
    const invalidated = vi.fn();
    const d = await setup(fetch, undefined, invalidated);
    expect((await d.service.load(target.user_id)).ok).toBe(true);
    for (const op of ['retry', 'resend'] as const) {
      expect((await d.service.change(target.user_id, op)).ok).toBe(true);
      const [url, init] = fetch.mock.calls.at(-1) ?? [];
      expect(url).toBe(`/api/v1/invitations/${target.user_id}/${op}`);
      expect(init?.method).toBe('POST');
      expect(init?.body).toBeUndefined();
      expect(new Headers(init?.headers).has('Content-Type')).toBe(false);
    }
    expect((await d.service.change(target.user_id, 'correct', 'new@example.test')).ok).toBe(true);
    expect(fetch.mock.calls.at(-1)?.[1].body).toBe('{"email":"new@example.test"}');
    expect(invalidated).toHaveBeenCalledTimes(3);
    expect(new Headers(fetch.mock.calls[0]?.[1].headers).get('Authorization')).toBe(
      'Bearer synthetic.access.only'
    );
    expect(fetch.mock.calls[0]?.[1]).toMatchObject({
      cache: 'no-store',
      redirect: 'error',
      referrerPolicy: 'no-referrer'
    });
  });
  it.each([
    'credential',
    'digest',
    'ciphertext',
    'link',
    'provider_payload',
    'audit',
    'stack',
    'actor'
  ])('rejects extra %s without retaining its marker', async (field) => {
    const target = invitationTarget({ [field]: 'private-marker' });
    const d = await setup(() => Promise.resolve(invitationResponse(target)));
    const result = await d.service.load(target.user_id);
    expect(result).toMatchObject({ ok: false, error: { kind: 'protocol' } });
    expect(JSON.stringify(result)).not.toContain('private-marker');
  });
  it('rejects nested provider fields and strips unapproved validation prose from mutation results', async () => {
    const target = invitationTarget();
    const fetch = vi.fn((_url: string, init: RequestInit) =>
      init.method === 'GET'
        ? Promise.resolve(
            invitationResponse({
              ...target,
              delivery: { ...target.delivery, provider_payload: 'private-marker' }
            })
          )
        : Promise.resolve(
            new Response(
              JSON.stringify({
                status: 'fail',
                data: { 'body.email': ['private-marker SQL ciphertext'] }
              }),
              {
                status: 400,
                headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
              }
            )
          )
    );
    const d = await setup(fetch);
    expect(await d.service.load(target.user_id)).toMatchObject({
      ok: false,
      error: { kind: 'protocol' }
    });
    const result = await d.service.change(target.user_id, 'correct', 'new@example.test');
    expect(result.ok).toBe(false);
    expect(JSON.stringify(result)).not.toMatch(/private-marker|SQL|ciphertext/);
  });

  it.each([
    'pending',
    'claimed',
    'retry_pending',
    'delivered',
    'permanent_failure',
    'expired',
    'invalidated'
  ])('decodes only approved %s delivery', async (status) => {
    const target = invitationTarget();
    target.delivery.status = status;
    const d = await setup(() => Promise.resolve(invitationResponse(target)));
    expect(await d.service.load(target.user_id)).toMatchObject({
      ok: true,
      value: { delivery: { status } }
    });
  });
  it.each(['active', 'disabled', 'deleted', 'pending_activation'])(
    'decodes canonical lifecycle %s without a generation',
    async (state) => {
      const target = invitationTarget({ state, delivery: null });
      const d = await setup(() => Promise.resolve(invitationResponse(target)));
      expect(await d.service.load(target.user_id)).toMatchObject({
        ok: true,
        value: { state, delivery: null }
      });
    }
  );
  it('rejects mismatched target, unsafe IDs, noncanonical email and malformed dates atomically', async () => {
    const target = invitationTarget();
    const fetch = vi.fn(() => Promise.resolve(invitationResponse(target)));
    const d = await setup(fetch);
    expect((await d.service.load('invalid/target')).ok).toBe(false);
    expect(fetch).not.toHaveBeenCalled();
    expect((await d.service.load(crypto.randomUUID())).ok).toBe(false);
    target.email = 'UPPER@example.test';
    expect((await d.service.load(target.user_id)).ok).toBe(false);
    target.email = 'safe@example.test';
    target.delivery.expires_at = '2099-02-30T12:00:00+00:00';
    expect((await d.service.load(target.user_id)).ok).toBe(false);
  });
  it('adopts only the latest read generation even when an older transport ignores cancellation', async () => {
    const target = invitationTarget();
    const old = deferred<Response>();
    let reads = 0;
    const d = await setup(() =>
      ++reads === 1
        ? old.promise
        : Promise.resolve(invitationResponse({ ...target, email: 'latest@example.test' }))
    );
    const first = d.service.load(target.user_id);
    expect(await d.service.load(target.user_id)).toMatchObject({
      ok: true,
      value: { email: 'latest@example.test' }
    });
    old.resolve(invitationResponse(target));
    expect(await first).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
  });

  it('fences older reads and duplicate mutations; invalidates a list even on conflict or uncertainty', async () => {
    const target = invitationTarget();
    const old = deferred<Response>();
    const mutation = deferred<Response>();
    const fetch = vi.fn((_url: string, init: RequestInit) =>
      init.method === 'GET' ? old.promise : mutation.promise
    );
    const invalidated = vi.fn();
    const d = await setup(fetch, undefined, invalidated);
    const load = d.service.load(target.user_id);
    const change = d.service.change(target.user_id, 'resend');
    expect(await d.service.change(target.user_id, 'resend')).toMatchObject({
      ok: false,
      error: { kind: 'conflict' }
    });
    mutation.resolve(invitationResponse(null, 409));
    await change;
    old.resolve(invitationResponse(target));
    expect(await load).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    expect(invalidated).toHaveBeenCalledOnce();
    expect(fetch).toHaveBeenCalledTimes(2);
  });
  it('fences relevant reads promptly but preserves foreign-target reads and detached observers', async () => {
    const target = invitationTarget();
    const other = invitationTarget({ email: 'other@example.test' });
    const read = deferred<Response>();
    const mutation = deferred<Response>();
    const fetch = vi.fn((_url: string, init: RequestInit) =>
      init.method === 'GET' ? read.promise : mutation.promise
    );
    const d = await setup(fetch);
    const detached = vi.fn();
    d.service.subscribe(target.user_id, detached)();
    const old = d.service.load(target.user_id);
    const operation = d.service.change(target.user_id, 'retry');
    expect(await old).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    const foreign = d.service.load(other.user_id);
    mutation.resolve(invitationResponse(target.delivery));
    await operation;
    read.resolve(invitationResponse(other));
    expect(await foreign).toMatchObject({ ok: true, value: { email: other.email } });
    expect(detached).not.toHaveBeenCalled();
    expect(fetch).toHaveBeenCalledTimes(3);
  });
  it('suppresses late reads after logout and abort, and bounds unanswered mutations without replay', async () => {
    vi.useFakeTimers();
    const target = invitationTarget();
    const pending = deferred<Response>();
    const fetch = vi.fn(() => pending.promise);
    const d = await setup(fetch);
    const controller = new AbortController();
    const read = d.service.load(target.user_id, controller.signal);
    controller.abort();
    expect(await read).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    const mutation = d.service.change(target.user_id, 'resend');
    await vi.advanceTimersByTimeAsync(8_000);
    expect(await mutation).toMatchObject({ ok: false, error: { kind: 'network' } });
    const late = d.service.load(target.user_id);
    await d.cache.signal('logout');
    expect(await late).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
    expect(fetch).toHaveBeenCalledTimes(3);
  });
});
