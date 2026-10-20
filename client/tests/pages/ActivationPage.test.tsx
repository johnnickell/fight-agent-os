import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { ActivationPage } from '@/pages/ActivationPage';

import {
  activationDependencies,
  activationSchemaSource,
  jsonResponse,
  passwordGuidance
} from '../activationFixtures';
import { deferred } from '../apiFixtures';

function fill() {
  fireEvent.change(screen.getByLabelText('New password (required)'), {
    target: { value: 'Éclair! ٩ Test' }
  });
  fireEvent.change(screen.getByLabelText('Confirm new password (required)'), {
    target: { value: 'Éclair! ٩ Test' }
  });
}
const complete = () =>
  jsonResponse({ access_token: 'header.payload.signature', issued_at: 1000, expires_at: 1900 });

describe('production invitation form', () => {
  it('loads explicit schema, announces validation and refreshes dependent confirmation without blocking Unicode or paste', async () => {
    const post = vi.fn().mockResolvedValue(complete());
    const deps = activationDependencies(post);
    render(<ActivationPage {...deps} />);
    const password = await screen.findByLabelText('New password (required)');
    const confirm = screen.getByLabelText('Confirm new password (required)');
    expect(password).toHaveAttribute('autocomplete', 'new-password');
    expect(password).toHaveAttribute('type', 'password');
    expect(password).toHaveAccessibleDescription(/Spaces and Unicode/);
    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: 'Activate account' }));
    expect(password).toHaveFocus();
    expect(password).toHaveAttribute('aria-invalid', 'true');
    expect(confirm).toHaveAttribute('aria-invalid', 'true');
    fill();
    expect(password).not.toHaveAttribute('aria-invalid');
    expect(confirm).not.toHaveAttribute('aria-invalid');
    fireEvent.change(password, { target: { value: 'Different! ٩ Unicode' } });
    expect(confirm).toHaveAccessibleDescription(/same as the value/);
    expect(post).not.toHaveBeenCalled();
    deps.service.dispose();
  });

  it('preserves actual success despite an edit while pending, then removes all secret fields', async () => {
    const pending = deferred<Response>();
    const post = vi.fn().mockImplementation(() => pending.promise);
    const deps = activationDependencies(post);
    render(<ActivationPage {...deps} />);
    await screen.findByLabelText('New password (required)');
    fill();
    fireEvent.click(screen.getByRole('button', { name: 'Activate account' }));
    await waitFor(() => expect(post).toHaveBeenCalledOnce());
    expect(screen.getByRole('button', { name: /Activating account/ })).toHaveAttribute(
      'aria-busy',
      'true'
    );
    fireEvent.change(screen.getByLabelText('New password (required)'), {
      target: { value: 'Edited while pending!9' }
    });
    await act(async () => {
      pending.resolve(complete());
      await pending.promise;
    });
    expect(await screen.findByText('Account activated')).toBeVisible();
    expect(screen.queryByLabelText('New password (required)')).not.toBeInTheDocument();
    expect(deps.credential.current()).toBeNull();
    expect(
      screen.getByRole('link', { name: 'Continue to the application foundation' })
    ).toHaveAttribute('href', '/app');
    deps.service.dispose();
  });

  it('presents specific safe common-password feedback, invalidates it on edits and never displays arbitrary server text', async () => {
    const deps = activationDependencies(() =>
      Promise.resolve(
        jsonResponse(
          { status: 'fail', data: { fields: { 'body.password': [passwordGuidance] } } },
          422,
          false
        )
      )
    );
    render(<ActivationPage {...deps} />);
    const password = await screen.findByLabelText('New password (required)');
    fill();
    fireEvent.click(screen.getByRole('button', { name: 'Activate account' }));
    await waitFor(() => expect(password).toHaveAttribute('aria-invalid', 'true'));
    expect(password).toHaveAccessibleDescription(/Known-common passwords cannot be used/);
    fireEvent.change(password, { target: { value: 'Changed password!9' } });
    expect(password).not.toHaveAttribute('aria-invalid');
    deps.service.dispose();
  });

  it('ignores an actual activation-form A to B to A obsolete rejection without suppressing future success', async () => {
    const pending = deferred<Response>();
    const post = vi.fn().mockImplementation(() => pending.promise);
    const deps = activationDependencies(post);
    render(<ActivationPage {...deps} />);
    const password = await screen.findByLabelText('New password (required)');
    fill();
    fireEvent.click(screen.getByRole('button', { name: 'Activate account' }));
    await waitFor(() => expect(post).toHaveBeenCalledOnce());
    fireEvent.change(password, { target: { value: 'Changed! 9 password' } });
    fireEvent.change(password, { target: { value: 'Éclair! ٩ Test' } });
    await act(async () => {
      pending.resolve(
        jsonResponse(
          { status: 'fail', data: { fields: { 'body.password': [passwordGuidance] } } },
          422,
          false
        )
      );
      await pending.promise;
    });
    expect(password).not.toHaveAttribute('aria-invalid');
    expect(screen.queryByText('Activation not completed')).not.toBeInTheDocument();
    deps.service.dispose();
  });

  it('retains unchanged neighboring server errors and revalidates edited confirmation locally', async () => {
    const pending = deferred<Response>();
    const post = vi.fn().mockImplementation(() => pending.promise);
    const deps = activationDependencies(post);
    render(<ActivationPage {...deps} />);
    const password = await screen.findByLabelText('New password (required)');
    const confirmation = screen.getByLabelText('Confirm new password (required)');
    fill();
    fireEvent.click(screen.getByRole('button', { name: 'Activate account' }));
    await waitFor(() => expect(post).toHaveBeenCalledOnce());
    fireEvent.change(confirmation, { target: { value: 'Different 9!' } });
    await act(async () => {
      pending.resolve(
        jsonResponse(
          {
            status: 'fail',
            data: {
              fields: {
                'body.password': [passwordGuidance],
                'body.password_confirmation': [
                  'Confirmation must be the same as the value in the password field'
                ]
              }
            }
          },
          422,
          false
        )
      );
      await pending.promise;
    });
    expect(password).toHaveAttribute('aria-invalid', 'true');
    expect(confirmation).toHaveAccessibleDescription(/same as the value/);
    fireEvent.change(confirmation, { target: { value: 'Éclair! ٩ Test' } });
    expect(confirmation).not.toHaveAttribute('aria-invalid');
    expect(password).toHaveAttribute('aria-invalid', 'true');
    deps.service.dispose();
  });

  it('shows generic terminal recovery and clears the transient owner after a conflict', async () => {
    const deps = activationDependencies(() =>
      Promise.resolve(jsonResponse({ status: 'error', message: 'Conflict.' }, 409, false))
    );
    render(<ActivationPage {...deps} />);
    await screen.findByLabelText('New password (required)');
    fill();
    fireEvent.click(screen.getByRole('button', { name: 'Activate account' }));
    expect(await screen.findByText('This link cannot be used')).toBeVisible();
    expect(screen.queryByLabelText('New password (required)')).not.toBeInTheDocument();
    expect(deps.credential.current()).toBeNull();
    deps.service.dispose();
  });

  it('requires an explicit schema retry rather than treating unavailable validation as valid', async () => {
    let failed = true;
    const deps = activationDependencies(
      () => Promise.resolve(complete()),
      () => {
        if (failed)
          return jsonResponse({ status: 'error', message: 'Internal server error.' }, 500, false);
        return jsonResponse(activationSchemaSource);
      }
    );
    render(<ActivationPage {...deps} />);
    expect(await screen.findByText('Validation is unavailable')).toBeVisible();
    expect(screen.queryByLabelText('New password (required)')).not.toBeInTheDocument();
    failed = false;
    fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
    expect(await screen.findByLabelText('New password (required)')).toBeVisible();
    deps.service.dispose();
  });
});
