import { act, fireEvent, render, screen } from '@testing-library/react';
import { expect, it, vi } from 'vitest';

import { ApplicationJourney } from '@/routes/ApplicationJourney';

import { jsonResponse } from '../activationFixtures';
import { loginSuccess } from '../loginFixtures';

it('ignores focus while still hidden and coalesces focus-before-visibility return exactly once', async () => {
  const identity = crypto.randomUUID();
  let hidden = false;
  vi.spyOn(document, 'hidden', 'get').mockImplementation(() => hidden);
  vi.spyOn(document, 'visibilityState', 'get').mockImplementation(() =>
    hidden ? 'hidden' : 'visible'
  );
  const fetch = vi.fn((url: string) => {
    if (url.endsWith('/auth/csrf')) {
      const expiry = Math.floor(Date.now() / 1000) + 900;
      return Promise.resolve(
        jsonResponse({ proof: `${expiry}.${'c'.repeat(64)}`, expires_at: expiry })
      );
    }
    if (url.endsWith('/me'))
      return Promise.resolve(
        jsonResponse({
          user_id: identity,
          email: 'reader@example.test',
          roles: ['ROLE_USER'],
          permissions: ['VIEW_DASHBOARD']
        })
      );
    return Promise.resolve(loginSuccess());
  });
  vi.stubGlobal('fetch', fetch);
  const view = render(
    <ApplicationJourney
      runtime={{ schemaVersion: 1, apiBasePath: '/api/v1' }}
      pathname="/app/dashboard"
      navigate={vi.fn()}
    />
  );
  const count = () => fetch.mock.calls.filter(([url]) => url.endsWith('/me')).length;
  try {
    await screen.findByRole('heading', { name: 'Dashboard', level: 1 });
    const main = screen.getByRole('main');
    hidden = true;
    fireEvent(document, new Event('visibilitychange'));
    await act(async () => {
      fireEvent(window, new Event('focus'));
      await Promise.resolve();
    });
    expect(count()).toBe(1);
    hidden = false;
    await act(async () => {
      fireEvent(window, new Event('focus'));
      await Promise.resolve();
    });
    // Give the first demand time to finish before the later visibility notification.
    await act(async () => {
      await Promise.resolve();
    });
    await act(async () => {
      fireEvent(document, new Event('visibilitychange'));
      await Promise.resolve();
    });
    expect(count()).toBe(2);
    expect(fetch.mock.calls.filter(([url]) => url.endsWith('/auth/refresh'))).toHaveLength(1);
    expect(screen.getByRole('main')).toBe(main);
  } finally {
    view.unmount();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  }
});
