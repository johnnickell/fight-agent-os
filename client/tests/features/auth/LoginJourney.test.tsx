import { act, fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { LoginJourney } from '@/features/auth/LoginJourney';

import { jsonResponse } from '../../activationFixtures';
import { loginSchemaSource, loginSuccess } from '../../loginFixtures';

const runtime = {
  schemaVersion: 2,
  apiBasePath: '/api/v1',
  appDebug: false,
  swaggerAvailable: false
} as const;

describe('document-scoped login retirement', () => {
  it('ends the mounted credential-ready journey when its authoritative /me rejects authentication', async () => {
    const fetch = vi.fn((url: string) => {
      if (url.endsWith('/auth/csrf')) {
        const expiry = Math.floor(Date.now() / 1000) + 900;
        return Promise.resolve(
          jsonResponse({ proof: `${expiry}.${'c'.repeat(64)}`, expires_at: expiry })
        );
      }
      if (url.endsWith('/me'))
        return Promise.resolve(
          jsonResponse({ status: 'error', message: 'Unauthorized.' }, 401, false)
        );
      return Promise.resolve(loginSuccess());
    });
    vi.stubGlobal('fetch', fetch);
    const mounted = render(<LoginJourney runtime={runtime} />);
    expect(
      await screen.findByRole('heading', { name: 'Your session has expired or ended' })
    ).toBeVisible();
    expect(
      screen.queryByRole('button', { name: 'Sign out of this session' })
    ).not.toBeInTheDocument();
    mounted.unmount();
    vi.unstubAllGlobals();
  });
  it('removes secret fields synchronously on pagehide rather than retaining a frozen form', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockImplementation(() => Promise.resolve(jsonResponse(loginSchemaSource)))
    );
    const mounted = render(<LoginJourney runtime={runtime} />);
    fireEvent.click(await screen.findByRole('button', { name: 'Use sign in instead' }));
    const password = await screen.findByLabelText('Password (required)');
    fireEvent.change(password, { target: { value: 'synthetic' } });
    act(() => {
      window.dispatchEvent(new Event('pagehide'));
    });
    expect(screen.queryByLabelText('Password (required)')).not.toBeInTheDocument();
    expect(screen.getByText('Sign-in cleared')).toBeVisible();
    mounted.unmount();
    vi.unstubAllGlobals();
  });
});
