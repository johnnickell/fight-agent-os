import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, expect, it, vi } from 'vitest';

import { createApplicationSession } from '@/features/auth/useApplicationSession';
import { ApplicationView } from '@/routes/ApplicationJourney';

import { jsonResponse } from '../activationFixtures';
import { deferred } from '../apiFixtures';
import { issuanceData, issuanceResponse } from '../ordinaryInvitationFixtures';
import { directoryUser } from '../userDirectoryFixtures';

const owners: ReturnType<typeof createApplicationSession>[] = [];
afterEach(() => {
  owners.splice(0).forEach((owner) => {
    owner.intent.dispose();
    owner.coordinator.dispose();
    owner.authority.dispose();
    owner.schemas.clear();
  });
  vi.useRealTimers();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});
async function mount(response?: Response | Promise<Response>) {
  const data = issuanceData();
  const identity = crypto.randomUUID();
  const detail = deferred<Response>();
  const fetch = vi.fn<(url: string, init?: RequestInit) => Promise<Response>>((url) => {
    if (url.endsWith('/auth/csrf')) {
      const expiry = Math.floor(Date.now() / 1000) + 900;
      return Promise.resolve(
        jsonResponse({ proof: `${expiry}.${'c'.repeat(64)}`, expires_at: expiry })
      );
    }
    if (url.endsWith('/auth/refresh'))
      return Promise.resolve(
        jsonResponse({
          access_token: 'header.payload.signature',
          issued_at: 1000,
          expires_at: 1900
        })
      );
    if (url.endsWith('/me'))
      return Promise.resolve(
        jsonResponse({
          user_id: identity,
          email: 'actor@example.test',
          roles: ['ROLE_SUPER_ADMIN'],
          permissions: ['INVITE_USERS', 'ASSIGN_SUPER_ADMIN', 'READ_USERS']
        })
      );
    if (url.endsWith('/invitations')) return Promise.resolve(response ?? issuanceResponse(data));
    if (url.includes('/users/')) return detail.promise;
    throw new Error('Unexpected application request');
  });
  vi.stubGlobal('fetch', fetch);
  const owner = createApplicationSession({
    schemaVersion: 2,
    apiBasePath: '/api/v1',
    appDebug: false,
    swaggerAvailable: false
  });
  owners.push(owner);
  render(
    <ApplicationView session={owner} pathname="/app/admin/invite-super-admin" navigate={vi.fn()} />
  );
  await act(() => owner.coordinator.restore());
  await screen.findByLabelText('Email (required)');
  return { owner, data, detail, fetch };
}
async function submit() {
  fireEvent.change(screen.getByLabelText('Email (required)'), {
    target: { value: ' Elevated@EXAMPLE.test ' }
  });
  await userEvent.click(screen.getByRole('button', { name: 'Review Super Admin invitation' }));
  await screen.findByRole('dialog');
  await userEvent.click(screen.getByRole('button', { name: 'Confirm Super Admin invitation' }));
}
it('uses the sole graph and retires pending directory detail and known-target invitation observations after elevated issuance', async () => {
  const d = await mount();
  const notified = vi.fn();
  const stop = d.owner.invitations.subscribe(d.data.user_id, notified);
  const stale = d.owner.users.detail(d.data.user_id);
  await submit();
  expect(
    await screen.findByRole('heading', { name: 'Super Admin invitation accepted' })
  ).toBeVisible();
  expect(notified).toHaveBeenCalledOnce();
  d.detail.resolve(jsonResponse(directoryUser({ user_id: d.data.user_id })));
  expect(await stale).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
  const calls = d.fetch.mock.calls.filter(([url]) => url.endsWith('/invitations'));
  expect(calls).toHaveLength(1);
  expect(JSON.parse(calls[0]![1]!.body as string)).toEqual({
    email: 'elevated@example.test',
    mode: 'super_admin',
    confirm_super_admin: true
  });
  stop();
});
it.each(['late response', 'lost response'] as const)(
  'keeps investigation-only disposition through foreground verification of pending %s issuance',
  async (completion) => {
    const work = deferred<Response>();
    const d = await mount(work.promise);
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] });
    fireEvent.change(screen.getByLabelText('Email (required)'), {
      target: { value: 'elevated@example.test' }
    });
    fireEvent.click(screen.getByRole('button', { name: 'Review Super Admin invitation' }));
    fireEvent.click(screen.getByRole('button', { name: 'Confirm Super Admin invitation' }));
    await act(async () => {
      await Promise.resolve();
      d.owner.authority.resume();
      await d.owner.authority.load();
    });
    expect(screen.getByRole('heading', { name: /stop and investigate/ })).toBeVisible();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(document.body.textContent).not.toContain('elevated@example.test');
    await act(async () => {
      if (completion === 'late response') {
        work.resolve(issuanceResponse(d.data));
        await work.promise;
      } else await vi.advanceTimersByTimeAsync(8_001);
    });
    await act(async () => {
      d.owner.authority.resume();
      await d.owner.authority.load();
    });
    expect(screen.getByRole('heading', { name: /stop and investigate/ })).toBeVisible();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', { name: /invitation|resend|retry/i })
    ).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Read invitation status' })).not.toBeInTheDocument();
    expect(d.fetch.mock.calls.filter(([url]) => url.endsWith('/invitations'))).toHaveLength(1);
  }
);
it('discards injected credential, provider, SQL and stack fields from uncertain browser state and never stores or logs input', async () => {
  const markers = [
    'grant-private-marker',
    'password-private-marker',
    'token-private-marker',
    'https://private.invalid/secret',
    'provider-private-marker',
    'SELECT private_marker',
    'stack-private-marker'
  ];
  const d = await mount(issuanceResponse({ ...issuanceData(), private: markers }));
  const setItem = vi.spyOn(Storage.prototype, 'setItem');
  const push = vi.spyOn(history, 'pushState');
  const replace = vi.spyOn(history, 'replaceState');
  const log = vi.spyOn(console, 'log');
  const warn = vi.spyOn(console, 'warn');
  const error = vi.spyOn(console, 'error');
  await submit();
  await screen.findByRole('heading', { name: /stop and investigate/ });
  await act(async () => {
    d.owner.authority.resume();
    await d.owner.authority.load();
  });
  expect(screen.getByRole('heading', { name: /stop and investigate/ })).toBeVisible();
  for (const marker of [...markers, 'elevated@example.test'])
    expect(document.body.textContent).not.toContain(marker);
  expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  for (const spy of [setItem, push, replace, log, warn, error]) expect(spy).not.toHaveBeenCalled();
  expect(d.fetch.mock.calls.filter(([url]) => url.endsWith('/invitations'))).toHaveLength(1);
});
