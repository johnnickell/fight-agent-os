import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import {
  apiFailure,
  MemoryAccessToken,
  SessionContinuity,
  SessionCoordinator
} from '@fight/react/account';

function mount(state: ReturnType<SessionCoordinator['getSnapshot']>) {
  const action = () => Promise.resolve(apiFailure('cancelled'));
  const dispose = () => {};
  const coordinator = new SessionCoordinator({
    tokens: new MemoryAccessToken(),
    session: { refresh: action, logout: action, invalidate: dispose, dispose },
    login: { submit: action, dispose }
  });
  vi.spyOn(coordinator, 'getSnapshot').mockReturnValue(state);
  const restore = vi.spyOn(coordinator, 'restore').mockImplementation(action);
  const logout = vi.spyOn(coordinator, 'logout').mockImplementation(action);
  const showLogin = vi.spyOn(coordinator, 'showLogin').mockImplementation(dispose);
  render(
    <SessionContinuity coordinator={coordinator}>
      <label>
        Password
        <input type="password" />
      </label>
    </SessionContinuity>
  );
  return { restore, logout, showLogin };
}

describe('standalone session presentation', () => {
  it('withholds the form during unresolved restoration', () => {
    const { restore } = mount({ status: 'restoring' });
    expect(screen.getByRole('status')).toHaveTextContent('Checking this browser’s session');
    expect(screen.queryByLabelText('Password')).not.toBeInTheDocument();
    expect(restore).not.toHaveBeenCalled();
  });

  it('admits a form after an anonymous snapshot, without restoring on its own', () => {
    const { restore } = mount({ status: 'anonymous' });
    expect(screen.getByLabelText('Password')).toBeVisible();
    expect(restore).not.toHaveBeenCalled();
  });

  it('focuses credential readiness without promising access and offers explicit sign-out', () => {
    const { logout } = mount({ status: 'authenticated' });
    expect(screen.getByRole('heading', { name: 'You’re signed in' })).toHaveFocus();
    expect(screen.getByRole('status')).toHaveTextContent('does not establish roles or permissions');
    fireEvent.click(screen.getByRole('button', { name: 'Sign out of this session' }));
    expect(logout).toHaveBeenCalledOnce();
  });

  it('does not auto-retry an unavailable connection', () => {
    const { restore } = mount({ status: 'network-degraded' });
    expect(screen.getByRole('heading', { name: 'Session connection unavailable' })).toHaveFocus();
    expect(restore).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole('button', { name: 'Retry session restoration' }));
    expect(restore).toHaveBeenCalledOnce();
  });

  it('warns about unconfirmed server sign-out and requires explicit sign-in', () => {
    const { showLogin } = mount({ status: 'anonymous', signOutUnconfirmed: true });
    expect(screen.getByText('Server sign-out unconfirmed')).toBeVisible();
    expect(screen.queryByLabelText('Password')).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Sign in again' }));
    expect(showLogin).toHaveBeenCalledOnce();
  });
});
