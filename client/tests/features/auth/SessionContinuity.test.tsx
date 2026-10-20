import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { apiFailure, type ApiResult } from '@/api/ApiResult';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { SessionContinuity } from '@/features/auth/SessionContinuity';
import { SessionCoordinator } from '@/features/auth/SessionCoordinator';

import { deferred } from '../../apiFixtures';

const owners: SessionCoordinator[] = [];
afterEach(() => {
  for (const owner of owners.splice(0)) owner.dispose();
});
function mount(result: Promise<ApiResult<true>>) {
  const tokens = new MemoryAccessToken();
  const session = {
    refresh: vi.fn(async () => {
      const response = await result;
      if (response.ok) tokens.accept('access.local.signature', performance.now());
      return response;
    }),
    logout: vi.fn(() =>
      Promise.resolve({ ok: true as const, value: true as const, correlationId: null })
    ),
    invalidate: () => tokens.clear(),
    dispose: () => tokens.clear()
  };
  const coordinator = new SessionCoordinator({
    tokens,
    session,
    login: { submit: () => Promise.resolve(apiFailure('authentication')), dispose: () => {} }
  });
  owners.push(coordinator);
  render(
    <SessionContinuity coordinator={coordinator}>
      <label>
        Password
        <input type="password" />
      </label>
    </SessionContinuity>
  );
  return { coordinator, session };
}

describe('accessible session restoration and expiry', () => {
  it('announces loading without mounting a secret form, then focuses credential-ready completion', async () => {
    const pending = deferred<ApiResult<true>>();
    const { coordinator } = mount(pending.promise);
    expect(screen.getByRole('status')).toHaveTextContent('Checking this browser’s session');
    expect(screen.queryByLabelText('Password')).not.toBeInTheDocument();
    await act(async () => {
      const restore = coordinator.restore();
      pending.resolve({ ok: true, value: true, correlationId: null });
      await restore;
    });
    expect(screen.getByRole('heading', { name: 'You’re signed in' })).toHaveFocus();
    expect(screen.getByRole('status')).toHaveTextContent('does not establish roles or permissions');
  });
  it('focuses/announces expiry, admits only explicit sign-in and clears mounted fields on a new terminal signal', async () => {
    const { coordinator } = mount(Promise.resolve(apiFailure('authentication')));
    await act(async () => {
      await coordinator.restore();
    });
    expect(
      screen.getByRole('heading', { name: 'Your session has expired or ended' })
    ).toHaveFocus();
    fireEvent.click(screen.getByRole('button', { name: 'Sign in again' }));
    fireEvent.change(screen.getByLabelText('Password'), {
      target: { value: 'SYNTHETIC-FORM-MARKER' }
    });
    act(() => coordinator.terminal());
    expect(screen.queryByLabelText('Password')).not.toBeInTheDocument();
    expect(document.body.innerHTML).not.toContain('SYNTHETIC-FORM-MARKER');
  });
  it('distinguishes network failure, focuses it and retries only on explicit activation', async () => {
    const { coordinator, session } = mount(Promise.resolve(apiFailure('network')));
    await act(async () => {
      await coordinator.restore();
    });
    expect(screen.getByRole('heading', { name: 'Session connection unavailable' })).toHaveFocus();
    expect(session.refresh).toHaveBeenCalledOnce();
    fireEvent.click(screen.getByRole('button', { name: 'Retry session restoration' }));
    await waitFor(() => expect(session.refresh).toHaveBeenCalledTimes(2));
  });
  it('ends local presentation after logout and focuses the signed-out result', async () => {
    const { coordinator } = mount(Promise.resolve({ ok: true, value: true, correlationId: null }));
    await act(async () => {
      await coordinator.restore();
    });
    fireEvent.click(screen.getByRole('button', { name: 'Sign out of this session' }));
    await waitFor(() =>
      expect(screen.getByRole('heading', { name: 'Signed out of this page' })).toHaveFocus()
    );
    expect(
      screen.queryByRole('button', { name: 'Sign out of this session' })
    ).not.toBeInTheDocument();
  });
});
