import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import PasswordChangePage from '@/pages/PasswordChangePage';

import { jsonResponse, passwordGuidance } from '../activationFixtures';
import { deferred } from '../apiFixtures';
import { passwordChangeDependencies, passwordChangeSchema } from '../passwordChangeFixtures';

async function enter() {
  fireEvent.change(await screen.findByLabelText('Current password (required)'), {
    target: { value: ' old value ' }
  });
  fireEvent.change(screen.getByLabelText('New password (required)'), {
    target: { value: ' Example!9 ' }
  });
  fireEvent.change(screen.getByLabelText('Confirm new password (required)'), {
    target: { value: ' Example!9 ' }
  });
}
const submit = () =>
  userEvent.click(screen.getByRole('button', { name: 'Change password and sign out' }));
describe('real authenticated change form', () => {
  it('uses named metadata, accessible current/new autocomplete, focus, dependent confirmation and show/hide', async () => {
    const post = vi.fn();
    const d = passwordChangeDependencies(post);
    render(<PasswordChangePage {...d} />);
    await screen.findByRole('button', { name: 'Change password and sign out' });
    await submit();
    expect(screen.getByLabelText('Current password (required)')).toHaveFocus();
    expect(screen.getByLabelText('Current password (required)')).toHaveAttribute(
      'autocomplete',
      'current-password'
    );
    expect(screen.getByLabelText('New password (required)')).toHaveAttribute(
      'autocomplete',
      'new-password'
    );
    expect(screen.getByLabelText('New password (required)')).toHaveAccessibleDescription(
      /uppercase/
    );
    await enter();
    fireEvent.change(screen.getByLabelText('New password (required)'), {
      target: { value: ' Unicode É!9 ' }
    });
    expect(await screen.findByText(/Confirmation must be the same/)).toBeVisible();
    await userEvent.click(screen.getByRole('button', { name: 'Show passwords' }));
    expect(screen.getByLabelText('Current password (required)')).toHaveAttribute('type', 'text');
    await userEvent.click(screen.getByRole('button', { name: 'Hide passwords' }));
    expect(screen.getByLabelText('New password (required)')).toHaveAttribute('type', 'password');
    expect(post).not.toHaveBeenCalled();
    d.authority.dispose();
  });
  it('clears secrets on current-proof rejection, keeps session and permits explicit repeated submission', async () => {
    const post = vi.fn(() =>
      Promise.resolve(jsonResponse({ status: 'error', message: 'Conflict.' }, 409, false))
    );
    const d = passwordChangeDependencies(post);
    await d.authority.load();
    render(<PasswordChangePage {...d} />);
    await enter();
    await submit();
    expect(await screen.findByText(/Check your current password/)).toBeVisible();
    expect(screen.getByLabelText('Current password (required)')).toHaveValue('');
    expect(screen.getByLabelText('New password (required)')).toHaveValue('');
    expect(d.tokens.getAccessToken()).not.toBeNull();
    await enter();
    await submit();
    await waitFor(() => expect(post).toHaveBeenCalledTimes(2));
    d.authority.dispose();
  });
  it('fences A-B-A late feedback, retains unrelated errors, and honors success after edits or unmount', async () => {
    const pending = deferred<Response>();
    const terminal = vi.fn();
    const d = passwordChangeDependencies(() => pending.promise, undefined, terminal);
    await d.authority.load();
    const view = render(<PasswordChangePage {...d} />);
    await enter();
    await submit();
    const field = screen.getByLabelText('New password (required)');
    fireEvent.change(field, { target: { value: 'Other!9 value' } });
    fireEvent.change(field, { target: { value: ' Example!9 ' } });
    view.unmount();
    await act(async () => {
      pending.resolve(jsonResponse({ changed: true }));
      await pending.promise;
    });
    expect(terminal).toHaveBeenCalledOnce();
    expect(d.tokens.getAccessToken()).toBeNull();
    expect(document.querySelector('input')).toBeNull();
    d.authority.dispose();
  });
  it('ignores superseded server feedback without clearing edited fields or exposing private messages', async () => {
    const pending = deferred<Response>();
    const d = passwordChangeDependencies(() => pending.promise);
    await d.authority.load();
    render(<PasswordChangePage {...d} />);
    await enter();
    await submit();
    const field = screen.getByLabelText('New password (required)');
    fireEvent.change(field, { target: { value: 'Other!9 value' } });
    fireEvent.change(field, { target: { value: ' Example!9 ' } });
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
    expect(field).not.toHaveAttribute('aria-invalid', 'true');
    expect(field).toHaveValue(' Example!9 ');
    d.authority.dispose();
  });
  it('fails closed on schema failure with explicit retry and clears a local reset', async () => {
    let ready = false;
    const d = passwordChangeDependencies(vi.fn(), () =>
      ready ? jsonResponse(passwordChangeSchema) : jsonResponse(null)
    );
    render(<PasswordChangePage {...d} />);
    expect(await screen.findByText('Validation is unavailable')).toBeVisible();
    expect(screen.queryByLabelText('Current password (required)')).not.toBeInTheDocument();
    ready = true;
    await userEvent.click(screen.getByRole('button', { name: /try again/i }));
    await enter();
    await userEvent.click(screen.getByRole('button', { name: 'Clear password fields' }));
    expect(screen.getByLabelText('Current password (required)')).toHaveValue('');
    expect(screen.getByLabelText('Confirm new password (required)')).toHaveValue('');
    d.authority.dispose();
  });
});
