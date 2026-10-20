import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { LoginPage, LoginRecoveryPage } from '@/pages/LoginPage';
import { IntendedRoute } from '@/routes/IntendedRoute';

import { jsonResponse } from '../activationFixtures';
import { deferred } from '../apiFixtures';
import { authenticatedCache, reportRoute } from '../authorityFixtures';
import {
  loginDependencies,
  loginRejected,
  loginSchemaSource,
  loginSuccess
} from '../loginFixtures';

function fill() {
  fireEvent.change(screen.getByLabelText('Email (required)'), {
    target: { value: 'person@example.test' }
  });
  fireEvent.change(screen.getByLabelText('Password (required)'), { target: { value: 'é' } });
}
function submit() {
  fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));
}
const structuralFailure = () =>
  jsonResponse(
    {
      status: 'fail',
      data: {
        fields: {
          'body.email': [
            'Email must contain no less than 1 characters',
            'Email must contain no more than 254 characters'
          ],
          'body.password': ['Password must contain no less than 1 characters']
        }
      }
    },
    400,
    false
  );

describe('schema-backed production login', () => {
  it('provides managers, paste, labels, first-invalid focus and no password-setting gate', async () => {
    const post = vi.fn().mockResolvedValue(loginRejected());
    const deps = loginDependencies(post);
    render(<LoginPage {...deps} />);
    const email = await screen.findByLabelText('Email (required)');
    const password = screen.getByLabelText('Password (required)');
    expect(email).toHaveAttribute('autocomplete', 'username');
    expect(password).toHaveAttribute('autocomplete', 'current-password');
    expect(password).not.toHaveAttribute('minlength');
    expect(password).toHaveAttribute('type', 'password');
    expect(screen.getByRole('checkbox')).not.toBeChecked();
    submit();
    await waitFor(() => expect(email).toHaveFocus());
    expect(password).toHaveAttribute('aria-invalid', 'true');
    fill();
    fireEvent.click(screen.getByRole('checkbox'));
    submit();
    expect(await screen.findByText(/We couldn’t sign you in/)).toBeVisible();
    expect(email).not.toHaveAttribute('aria-invalid');
    expect(password).not.toHaveAttribute('aria-invalid');
    expect(post).toHaveBeenCalledOnce();
    expect(JSON.parse((post.mock.calls[0]?.[1] as RequestInit).body as string)).toMatchObject({
      remember: true,
      password: 'é'
    });
    expect(screen.getByRole('link', { name: 'Forgot your password?' })).toHaveAttribute(
      'href',
      '/app/login/recovery'
    );
    deps.service.dispose();
  });

  it('retains only current neighboring errors and drops A to B to A field responses without stealing focus', async () => {
    const pending = deferred<Response>();
    const post = vi.fn().mockImplementation(() => pending.promise);
    const deps = loginDependencies(post);
    render(<LoginPage {...deps} />);
    const email = await screen.findByLabelText('Email (required)');
    const password = screen.getByLabelText('Password (required)');
    fill();
    submit();
    await waitFor(() => expect(post).toHaveBeenCalledOnce());
    fireEvent.change(password, { target: { value: 'changed' } });
    fireEvent.change(password, { target: { value: 'é' } });
    password.focus();
    await act(async () => {
      pending.resolve(structuralFailure());
      await pending.promise;
    });
    expect(password).not.toHaveAttribute('aria-invalid');
    expect(password).toHaveFocus();
    expect(email).toHaveAttribute('aria-invalid', 'true');
    expect(email).toHaveAccessibleDescription(/no less.*no more/);
    fireEvent.change(email, { target: { value: 'edited@example.test' } });
    expect(email).not.toHaveAttribute('aria-invalid');
    deps.service.dispose();
  });

  it('does not restore obsolete generic failure after edits and permits an explicit retry', async () => {
    const pending = deferred<Response>();
    const post = vi
      .fn()
      .mockImplementationOnce(() => pending.promise)
      .mockResolvedValue(loginSuccess());
    const deps = loginDependencies(post);
    render(<LoginPage {...deps} />);
    const email = await screen.findByLabelText('Email (required)');
    fill();
    submit();
    await waitFor(() => expect(post).toHaveBeenCalledOnce());
    expect(screen.getByRole('button', { name: 'Signing in…' })).toHaveAttribute(
      'aria-busy',
      'true'
    );
    expect(screen.getByRole('checkbox')).toBeDisabled();
    fireEvent.change(email, { target: { value: 'other@example.test' } });
    await act(async () => {
      pending.resolve(loginRejected());
      await pending.promise;
    });
    expect(screen.queryByText('Sign-in not completed')).not.toBeInTheDocument();
    submit();
    expect(await screen.findByRole('heading', { name: 'You’re signed in' })).toHaveFocus();
    expect(screen.queryByLabelText('Password (required)')).not.toBeInTheDocument();
    deps.service.dispose();
  });

  it('preserves genuine mutation success after an edit and prevents duplicate POST', async () => {
    const pending = deferred<Response>();
    const post = vi.fn().mockImplementation(() => pending.promise);
    const deps = loginDependencies(post);
    render(<LoginPage {...deps} />);
    await screen.findByLabelText('Email (required)');
    fill();
    submit();
    await waitFor(() => expect(post).toHaveBeenCalledOnce());
    fireEvent.change(screen.getByLabelText('Password (required)'), {
      target: { value: 'changed' }
    });
    fireEvent.submit(screen.getByRole('button', { name: 'Signing in…' }).closest('form')!);
    await act(async () => {
      pending.resolve(loginSuccess());
      await pending.promise;
    });
    expect(await screen.findByRole('heading', { name: 'You’re signed in' })).toBeVisible();
    expect(screen.queryByLabelText('Password (required)')).not.toBeInTheDocument();
    expect(post).toHaveBeenCalledOnce();
    expect(deps.tokens.getAccessToken()).not.toBeNull();
    deps.service.dispose();
  });

  it('requires schema failure recovery before any form is available', async () => {
    let unavailable = true;
    const deps = loginDependencies(undefined, () =>
      unavailable ? jsonResponse(null) : jsonResponse(loginSchemaSource)
    );
    render(<LoginPage {...deps} />);
    expect(await screen.findByText('Validation is unavailable')).toBeVisible();
    expect(screen.queryByLabelText('Password (required)')).not.toBeInTheDocument();
    unavailable = false;
    await userEvent.click(screen.getByRole('button', { name: 'Try again' }));
    expect(await screen.findByLabelText('Password (required)')).toBeVisible();
    deps.service.dispose();
  });

  it.each([429, 500])(
    'offers safe form-level %s recovery without field or account detail',
    async (status) => {
      const deps = loginDependencies(() =>
        Promise.resolve(
          jsonResponse(
            {
              status: 'error',
              message: status === 429 ? 'Too many requests.' : 'Internal server error.'
            },
            status,
            false
          )
        )
      );
      render(<LoginPage {...deps} />);
      await screen.findByLabelText('Email (required)');
      fill();
      submit();
      expect(
        await screen.findByText(status === 429 ? /Too many attempts/ : /Sign-in is unavailable/)
      ).toBeVisible();
      expect(screen.getByLabelText('Password (required)')).not.toHaveAttribute('aria-invalid');
      deps.service.dispose();
    }
  );

  it('retains classified navigation through failure and token success until fresh authority permits restoration', async () => {
    const cache = authenticatedCache();
    const intent = new IntendedRoute(cache, () => [reportRoute], 'https://example.test');
    expect(intent.capture('/app/reports?password=private')).toBe(false);
    expect(intent.capture('/app/reports?page=2')).toBe(true);
    const deps = loginDependencies(
      vi.fn().mockResolvedValueOnce(loginRejected()).mockResolvedValue(loginSuccess())
    );
    const onAuthenticated = vi.fn();
    render(<LoginPage {...deps} onAuthenticated={onAuthenticated} />);
    await screen.findByLabelText('Email (required)');
    fill();
    submit();
    await screen.findByText(/We couldn’t sign you in/);
    expect(onAuthenticated).not.toHaveBeenCalled();
    expect(intent.capture('/app/reports?page=1')).toBe(false);
    submit();
    await screen.findByRole('heading', { name: 'You’re signed in' });
    expect(
      screen.queryByRole('link', { name: 'Continue to your requested page' })
    ).not.toBeInTheDocument();
    expect(onAuthenticated).toHaveBeenCalledExactlyOnceWith();
    expect(intent.consume()).toBeNull();
    await cache.beginAuthentication().acceptCredentials();
    expect(intent.consume()).toBe('/app/reports?page=2');
    expect(intent.consume()).toBeNull();
    intent.dispose();
    cache.dispose();
    deps.service.dispose();
  });

  it('does not consume intent using authority cached before the new login', async () => {
    const cache = authenticatedCache();
    await cache.beginAuthentication().acceptCredentials();
    const intent = new IntendedRoute(cache, () => [reportRoute], 'https://example.test');
    expect(intent.capture('/app/reports?page=2')).toBe(true);
    const deps = loginDependencies(() => Promise.resolve(loginSuccess()));
    render(<LoginPage {...deps} />);
    await screen.findByLabelText('Email (required)');
    fill();
    submit();
    await screen.findByRole('heading', { name: 'You’re signed in' });
    expect(
      screen.queryByRole('link', { name: 'Continue to your requested page' })
    ).not.toBeInTheDocument();
    expect(intent.capture('/app/reports?page=1')).toBe(false);
    intent.dispose();
    cache.dispose();
    deps.service.dispose();
  });

  it('provides honest recovery rather than pretending to send a reset', () => {
    render(<LoginRecoveryPage />);
    expect(screen.getByText(/Password recovery is not available/)).toBeVisible();
    expect(screen.getByRole('link', { name: 'Return to sign in' })).toHaveAttribute(
      'href',
      '/app/login'
    );
  });
});
