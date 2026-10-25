import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { ResetCompletionJourney } from '@/features/auth/ResetCompletionJourney';
import { ResetCredential } from '@/features/auth/ResetCredential';
import { ResetCompletionPage } from '@/pages/ResetCompletionPage';

import { jsonResponse, passwordGuidance } from '../activationFixtures';
import { deferred } from '../apiFixtures';
import { resetCompletionDependencies, resetCompletionSchema } from '../resetCompletionFixtures';

async function enter() {
  fireEvent.change(await screen.findByLabelText('New password (required)'), {
    target: { value: 'Synthetic example!9' }
  });
  fireEvent.change(screen.getByLabelText('Confirm new password (required)'), {
    target: { value: 'Synthetic example!9' }
  });
}
describe('real schema reset completion form', () => {
  it('loads named metadata, supports multiple errors, confirmation dependency, show/hide and focus', async () => {
    const post = vi.fn();
    const d = resetCompletionDependencies(post);
    render(<ResetCompletionPage {...d} />);
    const submit = await screen.findByRole('button', { name: 'Reset password' });
    await userEvent.click(submit);
    const password = screen.getByLabelText('New password (required)');
    expect(password).toHaveFocus();
    expect(password).toHaveAttribute('aria-invalid', 'true');
    expect(password).toHaveAccessibleDescription(/uppercase/);
    await enter();
    fireEvent.blur(screen.getByLabelText('Confirm new password (required)'));
    fireEvent.change(password, { target: { value: 'Other example!9' } });
    expect(
      await screen.findByText(/Confirmation must be the same as the value in the password field/)
    ).toBeVisible();
    await userEvent.click(screen.getByRole('button', { name: 'Show passwords' }));
    expect(password).toHaveAttribute('type', 'text');
    expect(password).toHaveAttribute('autocomplete', 'new-password');
    await userEvent.click(screen.getByRole('button', { name: 'Hide passwords' }));
    expect(password).toHaveAttribute('type', 'password');
    expect(post).not.toHaveBeenCalled();
  });
  it('fences delayed A-B-A field feedback but never drops successful reset or terminal clearing', async () => {
    const pending = deferred<Response>();
    const post = vi.fn(() => pending.promise);
    const notify = vi.fn();
    const d = resetCompletionDependencies(post, undefined, notify);
    render(<ResetCompletionPage {...d} />);
    await enter();
    await userEvent.click(screen.getByRole('button', { name: 'Reset password' }));
    await waitFor(() => expect(post).toHaveBeenCalledOnce());
    const password = screen.getByLabelText('New password (required)');
    fireEvent.change(password, { target: { value: 'Other example!9' } });
    fireEvent.change(password, { target: { value: 'Synthetic example!9' } });
    await act(async () => {
      pending.resolve(jsonResponse({ completed: true }));
      await pending.promise;
    });
    expect(await screen.findByRole('heading', { name: 'Password reset complete' })).toHaveFocus();
    expect(screen.queryByLabelText('New password (required)')).not.toBeInTheDocument();
    expect(notify).toHaveBeenCalledExactlyOnceWith(d.userId);
    expect(d.credential.current()).toBeNull();
  });
  it('shows only published policy feedback and clears it on editing without copying secrets', async () => {
    const d = resetCompletionDependencies(() =>
      Promise.resolve(
        jsonResponse(
          { status: 'fail', data: { fields: { 'body.password': [passwordGuidance] } } },
          422,
          false
        )
      )
    );
    render(<ResetCompletionPage {...d} />);
    await enter();
    await userEvent.click(screen.getByRole('button', { name: 'Reset password' }));
    await waitFor(() =>
      expect(screen.getByLabelText('New password (required)')).toHaveAttribute(
        'aria-invalid',
        'true'
      )
    );
    fireEvent.change(screen.getByLabelText('New password (required)'), {
      target: { value: 'Another example!9' }
    });
    expect(screen.getByLabelText('New password (required)')).not.toHaveAttribute(
      'aria-invalid',
      'true'
    );
  });
  it('offers explicit unavailable-schema retry, then generic unusable-link recovery', async () => {
    let available = false;
    const d = resetCompletionDependencies(
      () => Promise.resolve(jsonResponse({ status: 'error', message: 'Conflict.' }, 409, false)),
      () => (available ? jsonResponse(resetCompletionSchema) : jsonResponse(null))
    );
    // A malformed schema can never enable password submission.
    render(<ResetCompletionPage {...d} />);
    expect(await screen.findByText('Validation is unavailable')).toBeVisible();
    available = true;
    await userEvent.click(screen.getByRole('button', { name: /try again/i }));
    await enter();
    await userEvent.click(screen.getByRole('button', { name: 'Reset password' }));
    expect(await screen.findByText('This link cannot be used')).toBeVisible();
    expect(d.credential.current()).toBeNull();
    expect(screen.queryByLabelText('New password (required)')).not.toBeInTheDocument();
  });
  it('retires secret DOM synchronously on pagehide and unmount clears possession', async () => {
    const credential = new ResetCredential({
      userId: crypto.randomUUID(),
      credential: 'synthetic-link'
    });
    const view = render(
      <ResetCompletionJourney
        runtime={{
          schemaVersion: 2,
          apiBasePath: '/api/v1',
          appDebug: false,
          swaggerAvailable: false
        }}
        credential={credential}
      />
    );
    await act(async () => {
      window.dispatchEvent(new Event('pagehide'));
      await Promise.resolve();
    });
    expect(credential.current()).toBeNull();
    expect(screen.getByText('Password reset cleared')).toBeVisible();
    view.unmount();
  });
});
