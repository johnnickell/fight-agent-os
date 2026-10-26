import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { ApiClient } from '@/api/ApiClient';
import { apiFailure } from '@/api/ApiResult';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { OwnedSessionsService } from '@/features/sessions/OwnedSessionsService';
import OwnedSessionsPage from '@/pages/OwnedSessionsPage';

import { deferred } from '../../apiFixtures';
import { principal } from '../../authorityFixtures';
import { sessionPage, sessionsResponse, sessionWire } from '../../sessionFixtures';

import type { ApiResult } from '@/api/ApiResult';

const caches: AuthorityCache[] = [];
afterEach(() => {
  for (const cache of caches.splice(0)) cache.dispose();
  vi.useRealTimers();
});
async function setup(permissions = ['READ_OWN_SESSIONS', 'DELETE_OWN_SESSIONS']) {
  const cache = new AuthorityCache(() =>
    Promise.resolve({ status: 'principal', principal: { ...principal, permissions } })
  );
  caches.push(cache);
  await cache.load();
  const target = sessionWire({ current: false, browser_family: 'Safari', device_class: 'tablet' });
  const current = sessionWire();
  const revoked = vi.fn();
  const identified = vi.fn();
  const pending = deferred<Response>();
  let committed = false;
  const fetch = vi.fn((_url: string, init: RequestInit) => {
    if (init.method === 'DELETE')
      return pending.promise.then((response) => {
        committed = response.status === 200;
        return response;
      });
    return Promise.resolve(
      sessionsResponse(sessionPage(committed ? [current] : [current, target]))
    );
  });
  const service = new OwnedSessionsService(
    new ApiClient(
      { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
      { fetch, accessTokens: { getAccessToken: () => 'synthetic.memory.only' } }
    ),
    { revoked, currentSession: identified },
    cache
  );
  return { cache, service, fetch, pending, target, revoked, identified };
}

describe('confirmed other-owned session revocation', () => {
  it('requires keyboard confirmation, focuses safe cancel, traps focus, cancels without mutation, suppresses duplicate pending activation and refetches on commit', async () => {
    const d = await setup();
    const logout = vi.fn();
    render(<OwnedSessionsPage cache={d.cache} service={d.service} logout={logout} />);
    const trigger = await screen.findByRole('button', { name: 'Revoke session' });
    trigger.focus();
    await userEvent.keyboard('{Enter}');
    const dialog = screen.getByRole('dialog', { name: 'Revoke this other session?' });
    expect(dialog).toHaveTextContent('tablet · Safari');
    expect(dialog).not.toHaveTextContent(d.target.session_id);
    await waitFor(() => expect(screen.getByRole('button', { name: 'Cancel' })).toHaveFocus());
    await userEvent.tab({ shift: true });
    expect(screen.getByRole('button', { name: 'Confirm revocation' })).toHaveFocus();
    await userEvent.tab();
    expect(screen.getByRole('button', { name: 'Cancel' })).toHaveFocus();
    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(trigger).toHaveFocus());
    expect(d.fetch.mock.calls.filter(([, init]) => init.method === 'DELETE')).toHaveLength(0);
    await userEvent.click(trigger);
    const confirm = screen.getByRole('button', { name: 'Confirm revocation' });
    await userEvent.dblClick(confirm);
    expect(screen.getByRole('button', { name: 'Revoking session' })).toHaveAttribute(
      'aria-disabled',
      'true'
    );
    await userEvent.keyboard('{Enter}{Escape}');
    expect(d.fetch.mock.calls.filter(([, init]) => init.method === 'DELETE')).toHaveLength(1);
    expect(screen.getByRole('dialog')).toBeVisible();
    await act(async () => {
      d.pending.resolve(sessionsResponse(null));
      await d.pending.promise;
    });
    expect(await screen.findByText(/Session revoked\. The session list/)).toBeVisible();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: 'Revoke session' })).not.toBeInTheDocument()
    );
    expect(d.revoked).toHaveBeenCalledExactlyOnceWith(d.target.session_id);
    expect(d.identified).toHaveBeenCalled();
    expect(screen.getByRole('heading', { name: 'Active Sessions' })).toHaveFocus();
    await userEvent.click(screen.getByRole('button', { name: 'Sign out (Logout)' }));
    expect(logout).toHaveBeenCalledOnce();
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);
  });

  it.each([
    [409, { status: 'error', message: 'Conflict.' }, 'That session is no longer available'],
    [
      409,
      { status: 'error', message: 'Use Logout for the current session.', code: 5801 },
      'Use Sign out (Logout)'
    ],
    [500, { status: 'error', message: 'Internal server error.' }, 'Revocation was not confirmed']
  ])(
    'announces safe %s outcome and offers refetch without replay',
    async (status, envelope, text) => {
      const d = await setup();
      render(<OwnedSessionsPage cache={d.cache} service={d.service} />);
      await userEvent.click(await screen.findByRole('button', { name: 'Revoke session' }));
      await userEvent.click(screen.getByRole('button', { name: 'Confirm revocation' }));
      await act(async () => {
        d.pending.resolve(
          new Response(JSON.stringify(envelope), {
            status,
            headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
          })
        );
        await d.pending.promise;
      });
      expect(
        await screen.findByText(new RegExp(text.replaceAll('(', '\\(').replaceAll(')', '\\)')))
      ).toBeVisible();
      expect(d.revoked).not.toHaveBeenCalled();
      await waitFor(() =>
        expect(d.fetch.mock.calls.filter(([, init]) => init.method === 'GET')).toHaveLength(2)
      );
      expect(d.fetch.mock.calls.filter(([, init]) => init.method === 'DELETE')).toHaveLength(1);
      expect(await screen.findByRole('button', { name: 'Reload sessions' })).toBeEnabled();
    }
  );

  it('hides mutation without exact permission and rejects non-HTTP service use or malformed targets', async () => {
    const d = await setup(['READ_OWN_SESSIONS', 'DELETE_SESSIONS']);
    render(<OwnedSessionsPage cache={d.cache} service={d.service} />);
    await screen.findByText('Current session — this request');
    expect(screen.queryByRole('button', { name: 'Revoke session' })).not.toBeInTheDocument();
    expect(await d.service.revoke(d.target.session_id)).toMatchObject(apiFailure('authorization'));
    expect(d.fetch).toHaveBeenCalledOnce();
    const allowed = await setup();
    expect(await allowed.service.revoke('private-marker')).toMatchObject(apiFailure('protocol'));
    expect(allowed.fetch).not.toHaveBeenCalled();
  });

  it('retires dialog and ignores late mutation/list outcomes on session end', async () => {
    const d = await setup();
    const view = render(<OwnedSessionsPage cache={d.cache} service={d.service} />);
    await userEvent.click(await screen.findByRole('button', { name: 'Revoke session' }));
    await userEvent.click(screen.getByRole('button', { name: 'Confirm revocation' }));
    await act(() => d.cache.signal('logout'));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    await act(async () => {
      d.pending.resolve(sessionsResponse(null));
      await d.pending.promise;
    });
    expect(d.revoked).not.toHaveBeenCalled();
    expect(screen.queryByText(/Session revoked\./)).not.toBeInTheDocument();
    view.unmount();
  });

  it('bounds uncertain mutation waiting and never broadcasts an unobserved commit or retries', async () => {
    vi.useFakeTimers();
    const d = await setup();
    const result = d.service.revoke(d.target.session_id);
    await vi.advanceTimersByTimeAsync(8_000);
    expect(await result).toMatchObject(apiFailure('network'));
    expect(d.revoked).not.toHaveBeenCalled();
    expect(d.fetch).toHaveBeenCalledOnce();
  });

  it('fences arbitrary late component results even when an injected revoke ignores lifecycle', async () => {
    const d = await setup();
    const late = deferred<ApiResult<true>>();
    const service = { load: d.service.load.bind(d.service), revoke: vi.fn(() => late.promise) };
    const view = render(<OwnedSessionsPage cache={d.cache} service={service} />);
    await userEvent.click(await screen.findByRole('button', { name: 'Revoke session' }));
    await userEvent.click(screen.getByRole('button', { name: 'Confirm revocation' }));
    view.unmount();
    await act(async () => {
      late.resolve({ ok: true, value: true, correlationId: null });
      await late.promise;
    });
    expect(document.body).not.toHaveTextContent('Session revoked.');
  });
});
