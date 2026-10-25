import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { ApiClient } from '@/api/ApiClient';
import { PasswordChangeService } from '@/features/auth/PasswordChangeService';
import { ApplicationView } from '@/routes/ApplicationJourney';

import { jsonResponse } from '../activationFixtures';
import { deferred } from '../apiFixtures';
import { applicationSession } from '../applicationFixtures';

describe('password-change terminal application transition', () => {
  it('retires the sole principal, secret DOM and intended route and requests fresh Login even after edits', async () => {
    const owner = applicationSession([]);
    const pending = deferred<Response>();
    const navigate = vi.fn();
    owner.passwordChange = new PasswordChangeService(
      new ApiClient(
        { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
        {
          accessTokens: { getAccessToken: () => 'synthetic.access.signature' },
          fetch: () => pending.promise
        }
      ),
      owner.authority,
      owner.coordinator.terminal
    );
    render(
      <ApplicationView session={owner} pathname="/app/account/password" navigate={navigate} />
    );
    await act(() => owner.coordinator.restore());
    const current = await screen.findByLabelText('Current password (required)');
    fireEvent.change(current, { target: { value: 'old synthetic' } });
    fireEvent.change(screen.getByLabelText('New password (required)'), {
      target: { value: 'Synthetic new!9' }
    });
    fireEvent.change(screen.getByLabelText('Confirm new password (required)'), {
      target: { value: 'Synthetic new!9' }
    });
    await userEvent.click(screen.getByRole('button', { name: 'Change password and sign out' }));
    fireEvent.change(current, { target: { value: 'edited synthetic' } });
    await act(async () => {
      pending.resolve(jsonResponse({ changed: true }));
      await pending.promise;
    });
    expect(await screen.findByText('Password changed — sign in again')).toBeVisible();
    expect(screen.queryByLabelText('Current password (required)')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'User Menu' })).not.toBeInTheDocument();
    expect(owner.authority.getSnapshot().status).toBe('terminal');
    expect(owner.intent.consume()).toBeNull();
    await waitFor(() => expect(navigate).toHaveBeenCalledWith('/app/login', true));
    expect(owner.refresh).toHaveBeenCalledOnce();
    await userEvent.click(screen.getByRole('button', { name: 'Sign in again' }));
    expect(await screen.findByLabelText('Email (required)')).toBeVisible();
    owner.dispose();
  });
});
