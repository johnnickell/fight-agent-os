import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
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
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});
async function mount() {
  const data = issuanceData();
  const identity = crypto.randomUUID();
  let permissions = ['INVITE_USERS', 'READ_USERS'];
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
          permissions
        })
      );
    if (url.endsWith('/invitations')) return Promise.resolve(issuanceResponse(data));
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
  const rendered = render(
    <ApplicationView session={owner} pathname="/admin/invite" navigate={vi.fn()} />
  );
  await act(() => owner.coordinator.restore());
  await screen.findByLabelText('Email (required)');
  return {
    ...rendered,
    owner,
    fetch,
    data,
    detail,
    revoke: () => {
      permissions = [];
    }
  };
}
it('uses the sole session graph, retires outstanding directory detail/status and checks authority again before later submission', async () => {
  const d = await mount();
  const notified = vi.fn();
  const stop = d.owner.invitations.subscribe(d.data.user_id, notified);
  const staleDetail = d.owner.users.detail(d.data.user_id);
  fireEvent.change(screen.getByLabelText('Email (required)'), {
    target: { value: 'ordinary@example.test' }
  });
  fireEvent.click(screen.getByRole('button', { name: 'Invite user' }));
  expect(await screen.findByRole('heading', { name: 'Invitation accepted' })).toBeVisible();
  expect(notified).toHaveBeenCalledOnce();
  d.detail.resolve(jsonResponse(directoryUser({ user_id: d.data.user_id })));
  expect(await staleDetail).toMatchObject({ ok: false, error: { kind: 'cancelled' } });
  const calls = d.fetch.mock.calls.filter(([url]) => url.endsWith('/invitations'));
  expect(calls).toHaveLength(1);
  expect(new Headers(calls[0]?.[1]?.headers).get('Authorization')).toBe(
    'Bearer header.payload.signature'
  );
  d.revoke();
  act(() => {
    d.owner.coordinator.authorityChanged();
  });
  await waitFor(() => expect(screen.queryByLabelText('Email (required)')).not.toBeInTheDocument());
  expect(await d.owner.invitationIssuance.submit('later@example.test')).toEqual({
    kind: 'rejected',
    reason: 'authorization'
  });
  expect(d.fetch.mock.calls.filter(([url]) => url.endsWith('/invitations'))).toHaveLength(1);
  stop();
});
it('never retains private response markers or form values in DOM, history, storage or diagnostics', async () => {
  const d = await mount();
  const setItem = vi.spyOn(Storage.prototype, 'setItem');
  const push = vi.spyOn(history, 'pushState');
  const replace = vi.spyOn(history, 'replaceState');
  const log = vi.spyOn(console, 'log');
  const warn = vi.spyOn(console, 'warn');
  const error = vi.spyOn(console, 'error');
  d.fetch.mockImplementationOnce(() =>
    Promise.resolve(
      issuanceResponse({
        ...d.data,
        credential: 'credential-marker',
        grant: 'grant-marker',
        digest: 'digest-marker',
        ciphertext: 'ciphertext-marker',
        link: 'https://secret.invalid/link-marker',
        provider: 'provider-marker',
        audit_internal: 'audit-marker',
        sql: 'SQL-marker',
        stack: 'stack-marker'
      })
    )
  );
  fireEvent.change(screen.getByLabelText('Email (required)'), {
    target: { value: 'volatile-form@example.test' }
  });
  fireEvent.click(screen.getByRole('button', { name: 'Invite user' }));
  expect(
    await screen.findByRole('heading', { name: 'Invitation outcome unconfirmed' })
  ).toBeVisible();
  expect(d.container.textContent).not.toMatch(
    /credential-marker|grant-marker|digest-marker|ciphertext-marker|link-marker|provider-marker|audit-marker|SQL-marker|stack-marker|volatile-form/
  );
  expect(setItem).not.toHaveBeenCalled();
  expect(push).not.toHaveBeenCalled();
  expect(replace).not.toHaveBeenCalled();
  expect(log).not.toHaveBeenCalled();
  expect(warn).not.toHaveBeenCalled();
  expect(error).not.toHaveBeenCalled();
});
