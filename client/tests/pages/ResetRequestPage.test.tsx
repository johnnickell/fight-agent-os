import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { ResetRequestPage } from '@/pages/ResetRequestPage';

import { jsonResponse } from '../activationFixtures';
import { deferred } from '../apiFixtures';
import { resetAccepted, resetDependencies, resetSchemaSource } from '../resetRequestFixtures';

const fill = (value = 'Person@example.test') =>
  fireEvent.change(screen.getByLabelText('Email (required)'), { target: { value } });
const submit = () => fireEvent.click(screen.getByRole('button', { name: 'Request reset link' }));
const rejected = () =>
  jsonResponse(
    {
      status: 'fail',
      data: {
        fields: {
          'body.email': [
            'Email must contain no less than 1 characters',
            'Email must contain no more than 254 characters'
          ]
        }
      }
    },
    400,
    false
  );

describe('real schema-backed reset request form', () => {
  it('provides labels, manager conventions, keyboard invalid focus and a generic success without echo', async () => {
    const post = vi.fn().mockResolvedValue(resetAccepted());
    const deps = resetDependencies(post);
    render(<ResetRequestPage {...deps} />);
    const email = await screen.findByLabelText('Email (required)');
    expect(email).toHaveAttribute('autocomplete', 'email');
    expect(email).toHaveAccessibleDescription('Use the email address you sign in with.');
    submit();
    await waitFor(() => expect(email).toHaveFocus());
    fill();
    await userEvent.setup().tab();
    expect(screen.getByRole('button', { name: 'Request reset link' })).toHaveFocus();
    await userEvent.setup().keyboard('{Enter}');
    const success = await screen.findByRole('heading', { name: 'Check your inbox' });
    expect(success).toHaveFocus();
    expect(screen.getByText(/If this address is eligible/)).toBeVisible();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(document.body).not.toHaveTextContent('Person@example.test');
    expect(JSON.parse((post.mock.calls[0]?.[1] as RequestInit).body as string)).toEqual({
      email: 'person@example.test'
    });
    expect(screen.getByRole('link', { name: 'Return to sign in' })).toHaveAttribute(
      'href',
      '/app/login'
    );
    expect(deps.calls.some((url) => url.endsWith('/me') || url.endsWith('/auth/refresh'))).toBe(
      false
    );
    deps.service.dispose();
  });

  it('shows multiple approved messages then invalidates them on edit', async () => {
    const deps = resetDependencies(() => Promise.resolve(rejected()));
    render(<ResetRequestPage {...deps} />);
    const email = await screen.findByLabelText('Email (required)');
    fill();
    submit();
    await waitFor(() => expect(email).toHaveAttribute('aria-invalid', 'true'));
    expect(email).toHaveAccessibleDescription(/no less.*no more/);
    fill('edited@example.test');
    expect(email).not.toHaveAttribute('aria-invalid');
    expect(screen.queryByText(/no more than/)).not.toBeInTheDocument();
    deps.service.dispose();
  });

  it('drops obsolete rejection after A to B to A without stealing focus', async () => {
    const pending = deferred<Response>();
    const post = vi.fn(() => pending.promise);
    const deps = resetDependencies(post);
    render(<ResetRequestPage {...deps} />);
    const email = await screen.findByLabelText('Email (required)');
    fill();
    submit();
    await waitFor(() => expect(post).toHaveBeenCalledOnce());
    fill('other@example.test');
    fill();
    email.focus();
    await act(async () => {
      pending.resolve(rejected());
      await pending.promise;
    });
    expect(email).not.toHaveAttribute('aria-invalid');
    expect(email).toHaveFocus();
    expect(screen.queryByText('Request not confirmed')).not.toBeInTheDocument();
    deps.service.dispose();
  });

  it('preserves actual accepted server work after intervening edits', async () => {
    const pending = deferred<Response>();
    const post = vi.fn(() => pending.promise);
    const deps = resetDependencies(post);
    render(<ResetRequestPage {...deps} />);
    await screen.findByLabelText('Email (required)');
    fill();
    submit();
    await waitFor(() => expect(post).toHaveBeenCalledOnce());
    fill('edited@example.test');
    fireEvent.click(screen.getByRole('button', { name: 'Requesting reset…' }));
    expect(post).toHaveBeenCalledOnce();
    await act(async () => {
      pending.resolve(resetAccepted());
      await pending.promise;
    });
    expect(await screen.findByRole('heading', { name: 'Check your inbox' })).toHaveFocus();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    deps.service.dispose();
  });

  it('fails closed on missing schema and permits explicit retry', async () => {
    const schema = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({}, 500, false))
      .mockResolvedValue(jsonResponse(resetSchemaSource));
    const post = vi.fn();
    const deps = resetDependencies(post, schema);
    render(<ResetRequestPage {...deps} />);
    expect(await screen.findByText('Validation is unavailable')).toBeVisible();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(post).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
    expect(await screen.findByLabelText('Email (required)')).toBeVisible();
    deps.service.dispose();
  });

  it.each([
    [429, 'Too many requests'],
    [500, 'Password recovery is unavailable'],
    [403, 'secure request could not be verified']
  ] as const)('offers account-independent recovery for %s', async (status, text) => {
    const message =
      status === 429
        ? 'Too many requests.'
        : status === 403
          ? 'Forbidden.'
          : 'An internal error occurred.';
    const deps = resetDependencies(() =>
      Promise.resolve(jsonResponse({ status: 'error', message }, status, false))
    );
    render(<ResetRequestPage {...deps} />);
    await screen.findByLabelText('Email (required)');
    fill();
    submit();
    expect(await screen.findByText(new RegExp(text))).toBeVisible();
    expect(screen.queryByText(/Person@example.test/)).not.toBeInTheDocument();
    deps.service.dispose();
  });

  it('cleans pending form state on unmount without treating accepted work as cancellation', async () => {
    const pending = deferred<Response>();
    const post = vi.fn(() => pending.promise);
    const deps = resetDependencies(post);
    const view = render(<ResetRequestPage {...deps} />);
    await screen.findByLabelText('Email (required)');
    fill();
    submit();
    await waitFor(() => expect(post).toHaveBeenCalledOnce());
    view.unmount();
    deps.service.dispose();
    await act(async () => {
      pending.resolve(resetAccepted());
      await pending.promise;
    });
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });
});
