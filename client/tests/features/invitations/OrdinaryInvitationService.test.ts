import { afterEach, describe, expect, it, vi } from 'vitest';

import { canonicalInvitationEmail } from '@/features/invitations/OrdinaryInvitationService';

import { deferred, jsonResponse } from '../../apiFixtures';
import {
  issuanceData,
  issuanceResponse,
  ordinaryInvitationDependencies
} from '../../ordinaryInvitationFixtures';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';

const owned: AuthorityCache[] = [];
afterEach(() => {
  owned.splice(0).forEach((cache) => cache.dispose());
  vi.useRealTimers();
});
async function setup(
  fetch = vi.fn<(url: string, init: RequestInit) => Promise<Response>>(() =>
    Promise.resolve(issuanceResponse())
  ),
  permissions?: string[]
) {
  const d = ordinaryInvitationDependencies(fetch, permissions);
  owned.push(d.cache);
  await d.cache.load();
  const lifecycle = { invalidated: vi.fn(), accessRejected: vi.fn(), authorityRejected: vi.fn() };
  return { ...d, lifecycle, service: d.create(lifecycle), fetch };
}

describe('OrdinaryInvitationService', () => {
  it('sends only canonical email and fixed ordinary mode through current Bearer transport, retaining only the safe ID', async () => {
    const data = issuanceData();
    const fetch = vi.fn<(url: string, init: RequestInit) => Promise<Response>>(() =>
      Promise.resolve(issuanceResponse(data))
    );
    const d = await setup(fetch);
    expect(await d.service.submit(' Person@EXAMPLE.test ')).toEqual({
      kind: 'accepted',
      userId: data.user_id
    });
    expect(fetch).toHaveBeenCalledOnce();
    const [url, init] = fetch.mock.calls[0]!;
    expect(url).toBe('/api/v1/invitations');
    expect(init.method).toBe('POST');
    expect(JSON.parse(init.body as string)).toEqual({
      email: 'person@example.test',
      mode: 'ordinary'
    });
    expect(new Headers(init.headers).get('Authorization')).toBe('Bearer synthetic.memory.only');
    expect(init).toMatchObject({
      cache: 'no-store',
      redirect: 'error',
      referrerPolicy: 'no-referrer'
    });
    expect(d.lifecycle.invalidated).toHaveBeenCalledWith(data.user_id);
  });
  it('dispatches a supported address literal as canonical email with fixed ordinary mode', async () => {
    const d = await setup();
    expect((await d.service.submit(' Person@[IPv6:2001:db8::1] ')).kind).toBe('accepted');
    expect(d.fetch).toHaveBeenCalledOnce();
    const [url, init] = d.fetch.mock.calls[0]!;
    expect(url).toBe('/api/v1/invitations');
    expect(init.method).toBe('POST');
    expect(JSON.parse(init.body as string)).toEqual({
      email: 'person@[ipv6:2001:db8::1]',
      mode: 'ordinary'
    });
  });
  it.each(
    [[], ['READ_USERS'], ['MANAGE_USER_INVITATIONS'], ['ASSIGN_SUPER_ADMIN']].map(
      (permissions) => ({ permissions })
    )
  )(
    'denies misleading role without exact invitation authority $permissions',
    async ({ permissions }) => {
      const d = await setup(undefined, permissions);
      expect(await d.service.submit('person@example.test')).toEqual({
        kind: 'rejected',
        reason: 'authorization'
      });
      expect(d.fetch).not.toHaveBeenCalled();
    }
  );
  it('allows invitation without any read or management permission and blocks stale authority before dispatch', async () => {
    const d = await setup(undefined, ['INVITE_USERS']);
    expect((await d.service.submit('person@example.test')).kind).toBe('accepted');
    await d.cache.signal('refresh-started');
    expect((await d.service.submit('another@example.test')).kind).toBe('rejected');
    expect(d.fetch).toHaveBeenCalledOnce();
  });
  it.each(['', 'not-email', 'x'.repeat(255) + '@example.test', '"a\u0001b"@example.test'])(
    'does not dispatch invalid local input',
    async (value) => {
      const d = await setup();
      expect(await d.service.submit(value)).toEqual({ kind: 'rejected', reason: 'validation' });
      expect(d.fetch).not.toHaveBeenCalled();
    }
  );
  it('leaves semantic Unicode decisions to the server and supports quoted local parts', () => {
    expect(canonicalInvitationEmail(' Équipe@EXAMPLE.test ')).toBe('équipe@example.test');
    expect(canonicalInvitationEmail('"two words"@example.test')).toBe('"two words"@example.test');
  });
  it.each([
    [400, 'Invalid input.', 'bad-request'],
    [401, 'Authentication required.', 'authentication'],
    [403, 'Permission denied.', 'authorization'],
    [409, 'State conflict.', 'conflict'],
    [422, 'Rule violation.', 'rule-violation'],
    [429, 'Too many requests.', 'rate-limited']
  ] as const)(
    'maps controlled HTTP %s without server prose or replay',
    async (status, message, reason) => {
      const d = await setup(
        vi.fn(() => Promise.resolve(jsonResponse({ status: 'fail', data: { message } }, status)))
      );
      expect(await d.service.submit('person@example.test')).toEqual({ kind: 'rejected', reason });
      expect(d.fetch).toHaveBeenCalledOnce();
      expect(d.lifecycle.accessRejected).toHaveBeenCalledTimes(status === 401 ? 1 : 0);
      expect(d.lifecycle.authorityRejected).toHaveBeenCalledTimes(status === 403 ? 1 : 0);
    }
  );
  it('discards even bounded private validation details', async () => {
    const d = await setup(
      vi.fn(() =>
        Promise.resolve(
          jsonResponse(
            { status: 'fail', data: { fields: { 'body.email': ['SQL private-marker'] } } },
            400
          )
        )
      )
    );
    expect(await d.service.submit('person@example.test')).toEqual({
      kind: 'rejected',
      reason: 'validation'
    });
  });
  it.each([
    'credential',
    'grant',
    'digest',
    'ciphertext',
    'link',
    'provider',
    'stack',
    'audit_internal'
  ])('rejects extra %s markers rather than retaining them', async (key) => {
    const d = await setup(
      vi.fn(() => Promise.resolve(issuanceResponse({ ...issuanceData(), [key]: 'private-marker' })))
    );
    expect(await d.service.submit('person@example.test')).toEqual({
      kind: 'unconfirmed',
      userId: null
    });
  });
  it('projects partial uncertainty to safe ID only, with no success or retry claim', async () => {
    const data = { ...issuanceData(), assignment: 'unconfirmed', audit: 'unconfirmed' };
    const d = await setup(
      vi.fn(() =>
        Promise.resolve(
          jsonResponse(
            {
              status: 'error',
              message:
                'Invitation completion is unconfirmed. Read invitation status before another operation.',
              data
            },
            503
          )
        )
      )
    );
    expect(await d.service.submit('person@example.test')).toEqual({
      kind: 'unconfirmed',
      userId: data.user_id
    });
    expect(d.fetch).toHaveBeenCalledOnce();
  });
  it.each([
    { ...issuanceData(), user_id: null, delivery: null },
    { ...issuanceData(), delivery: null },
    { ...issuanceData(), assignment: 'unknown' },
    { ...issuanceData(), audit: 'unconfirmed' },
    { ...issuanceData(), user_id: 'private-marker' },
    { ...issuanceData(), delivery: { ...issuanceData().delivery, provider: 'private-marker' } }
  ])('treats inconsistent or incomplete 201 as unconfirmed, not accepted', async (data) => {
    const d = await setup(vi.fn(() => Promise.resolve(issuanceResponse(data))));
    expect(await d.service.submit('person@example.test')).toEqual({
      kind: 'unconfirmed',
      userId: null
    });
  });
  it('handles network and system failures without claiming rollback', async () => {
    const d = await setup(vi.fn(() => Promise.reject(new Error('SQL credential-marker'))));
    expect(await d.service.submit('person@example.test')).toEqual({
      kind: 'unconfirmed',
      userId: null
    });
    const failed = await setup(
      vi.fn(() =>
        Promise.resolve(jsonResponse({ status: 'error', message: 'Internal server error.' }, 500))
      )
    );
    expect(await failed.service.submit('person@example.test')).toEqual({
      kind: 'unconfirmed',
      userId: null
    });
  });
  it('suppresses duplicates through a bounded deadline, and invalidates again for late completion', async () => {
    vi.useFakeTimers();
    const pending = deferred<Response>();
    const d = await setup(vi.fn(() => pending.promise));
    const first = d.service.submit('person@example.test');
    expect(await d.service.submit('person@example.test')).toEqual({ kind: 'cancelled' });
    await vi.advanceTimersByTimeAsync(8_000);
    expect(await first).toEqual({ kind: 'unconfirmed', userId: null });
    expect(await d.service.submit('person@example.test')).toEqual({ kind: 'cancelled' });
    const data = issuanceData();
    pending.resolve(issuanceResponse(data));
    await vi.advanceTimersByTimeAsync(0);
    expect(d.lifecycle.invalidated.mock.calls).toEqual([[null], [data.user_id]]);
    expect(d.fetch).toHaveBeenCalledOnce();
  });
  it.each([201, 403])(
    'fences retired %s outcomes while still invalidating possible commits',
    async (status) => {
      const pending = deferred<Response>();
      const d = await setup(vi.fn(() => pending.promise));
      const result = d.service.submit('person@example.test');
      await d.cache.signal('logout');
      pending.resolve(
        status === 201
          ? issuanceResponse()
          : jsonResponse({ status: 'fail', data: { message: 'Permission denied.' } }, 403)
      );
      expect(await result).toEqual({ kind: 'cancelled' });
      expect(d.lifecycle.invalidated).toHaveBeenCalledOnce();
      expect(d.lifecycle.authorityRejected).not.toHaveBeenCalled();
    }
  );
});
