import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { ShellApplication } from '@/ShellApplication';

import { jsonResponse } from './activationFixtures';
import { loginSchemaSource, loginSuccess } from './loginFixtures';

const configuration =
  '{"schema_version":2,"api_base_path":"/api/v1","app_debug":false,"swagger_available":false}';

describe('foundation shell', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    window.history.replaceState(null, '', '/');
  });
  it.each(['/', '/app', '/app/'])(
    'sends cold anonymous root %s to canonical Login after bounded restoration',
    async (pathname) => {
      vi.stubGlobal(
        'fetch',
        vi.fn((url: string) => {
          if (url.endsWith('/validations/login'))
            return Promise.resolve(jsonResponse(loginSchemaSource));
          if (url.endsWith('/auth/csrf')) {
            const expiry = Math.floor(Date.now() / 1000) + 900;
            return Promise.resolve(
              jsonResponse({ proof: `${expiry}.${'c'.repeat(64)}`, expires_at: expiry })
            );
          }
          return Promise.resolve(
            jsonResponse({ status: 'error', message: 'Unauthorized.' }, 401, false)
          );
        })
      );
      window.history.replaceState(null, '', pathname);
      render(<ShellApplication configuration={configuration} pathname={pathname} />);
      expect(screen.getByText('Restoring your session')).toBeVisible();
      expect(await screen.findByLabelText('Password (required)')).toBeVisible();
      expect(window.location.pathname).toBe('/app/login');
      expect(screen.getByRole('main')).toBeVisible();
      expect(screen.queryByRole('navigation', { name: 'Primary' })).not.toBeInTheDocument();
      expect(document.title).toBe('Sign in — Fight Agent OS');
    }
  );

  it('handles public direct navigation and back/forward without restoring on inert routes', async () => {
    const fetch = vi.fn();
    vi.stubGlobal('fetch', fetch);
    render(<ShellApplication configuration={configuration} pathname="/app/missing" />);
    act(() => {
      window.history.pushState(null, '', '/app/login/recovery');
      window.dispatchEvent(new PopStateEvent('popstate'));
    });
    expect(await screen.findByRole('heading', { name: 'Need help signing in?' })).toBeVisible();
    act(() => {
      window.history.pushState(null, '', '/app/missing');
      window.dispatchEvent(new PopStateEvent('popstate'));
    });
    expect(screen.getByRole('heading', { name: 'Page not found' })).toBeVisible();
    expect(fetch).not.toHaveBeenCalled();
    let intercepted = true;
    document.addEventListener(
      'click',
      (event) => {
        intercepted = event.defaultPrevented;
        // Observe native-link ownership without asking jsdom to navigate another document.
        event.preventDefault();
      },
      { once: true }
    );
    fireEvent.click(screen.getByRole('link', { name: 'Fight Agent OS' }), { ctrlKey: true });
    expect(intercepted).toBe(false);
    expect(fetch).not.toHaveBeenCalled();
  });

  it('preserves one classified direct destination through cold 401 and explicit Login, then keeps one owner across history', async () => {
    let projection = 0;
    const fetch = vi.fn((url: string) => {
      if (url.endsWith('/validations/login'))
        return Promise.resolve(jsonResponse(loginSchemaSource));
      if (url.endsWith('/auth/csrf')) {
        const expiry = Math.floor(Date.now() / 1000) + 900;
        return Promise.resolve(
          jsonResponse({ proof: `${expiry}.${'c'.repeat(64)}`, expires_at: expiry })
        );
      }
      if (url.endsWith('/auth/refresh'))
        return Promise.resolve(
          jsonResponse({ status: 'error', message: 'Unauthorized.' }, 401, false)
        );
      if (url.endsWith('/me')) {
        projection++;
        return Promise.resolve(
          jsonResponse({
            user_id: identity,
            email: 'reader@example.test',
            roles: ['ROLE_USER'],
            permissions: ['VIEW_DASHBOARD', 'READ_OWN_SESSIONS']
          })
        );
      }
      return Promise.resolve(loginSuccess());
    });
    const identity = crypto.randomUUID();
    vi.stubGlobal('fetch', fetch);
    window.history.replaceState(null, '', '/app/account/sessions');
    render(<ShellApplication configuration={configuration} pathname="/app/account/sessions" />);
    fireEvent.change(await screen.findByLabelText('Email (required)'), {
      target: { value: 'reader@example.test' }
    });
    fireEvent.change(screen.getByLabelText('Password (required)'), {
      target: { value: 'synthetic' }
    });
    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));
    expect(await screen.findByRole('heading', { name: 'Active sessions' })).toBeVisible();
    expect(window.location.pathname).toBe('/app/account/sessions');
    act(() => {
      window.history.pushState(null, '', '/app/dashboard');
      window.dispatchEvent(new PopStateEvent('popstate'));
    });
    expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeVisible();
    act(() => {
      window.history.pushState(null, '', '/app/account/sessions');
      window.dispatchEvent(new PopStateEvent('popstate'));
    });
    expect(await screen.findByRole('heading', { name: 'Active sessions' })).toBeVisible();
    expect(projection).toBe(1);
    expect(fetch.mock.calls.filter(([url]) => url.endsWith('/auth/refresh'))).toHaveLength(1);
  });

  it.each(['/app/unknown', '/app/nested/missing.file', '/app/%3Cscript%3E'])(
    'offers safe native recovery from %s',
    async (pathname) => {
      render(<ShellApplication configuration={configuration} pathname={pathname} />);
      expect(screen.getByRole('heading', { name: 'Page not found' })).toBeVisible();
      expect(screen.getByRole('link', { name: 'Return to application' })).toHaveAttribute(
        'href',
        '/app'
      );
      expect(document.body.textContent).not.toContain(pathname);
      await waitFor(() => expect(document.title).toBe('Page not found — Fight Agent OS'));
    }
  );

  it('scrubs unknown credential-like locations without echo, storage or authentication requests', () => {
    const fetch = vi.fn();
    vi.stubGlobal('fetch', fetch);
    window.history.replaceState(
      { password: 'not-retained' },
      '',
      '/app/private-stack-SQL-marker?token=cookie-marker#form-marker'
    );
    render(
      <ShellApplication configuration={configuration} pathname="/app/private-stack-SQL-marker" />
    );
    expect(window.location.pathname).toBe('/app/not-found');
    expect(window.location.search).toBe('');
    expect(window.location.hash).toBe('');
    expect(window.history.state).toBeNull();
    expect(document.body.textContent).not.toMatch(
      /private-stack-SQL-marker|cookie-marker|form-marker|not-retained/
    );
    expect(fetch).not.toHaveBeenCalled();
  });

  it('puts the skip link first in keyboard order and targets the main landmark', async () => {
    const user = userEvent.setup();
    render(<ShellApplication configuration={configuration} pathname="/app/missing" />);
    await user.tab();
    const skip = screen.getByRole('link', { name: 'Skip to content' });
    expect(skip).toHaveFocus();
    expect(skip).toHaveAttribute('href', `#${screen.getByRole('main').id}`);
    expect(screen.getByRole('main')).toHaveAttribute('tabindex', '-1');
    await user.tab();
    expect(screen.getByRole('link', { name: 'Fight Agent OS' })).toHaveFocus();
    await user.tab();
    expect(screen.getByRole('link', { name: 'Return to application' })).toHaveFocus();
  });

  it.each([
    null,
    'private-value-not-for-display',
    '{"schema_version":1,"api_base_path":"/api/v1","token":"private-value-not-for-display"}'
  ])('fails boot safely before route rendering %#', async (source) => {
    render(<ShellApplication configuration={source} pathname="/app/missing" />);
    expect(screen.getByRole('heading', { name: 'Application unavailable' })).toBeVisible();
    expect(screen.getByRole('alert')).toHaveTextContent('contact the installation operator');
    expect(screen.getByRole('link', { name: 'Reload application' })).toHaveAttribute(
      'href',
      '/app'
    );
    expect(screen.queryByText('Page not found')).not.toBeInTheDocument();
    expect(document.body.textContent).not.toContain('private-value-not-for-display');
    await waitFor(() => expect(document.title).toBe('Application unavailable — Fight Agent OS'));
  });
});
