import { act, render, screen } from '@testing-library/react';
import { expect, it, vi } from 'vitest';

import { createApplicationSession } from '@/features/auth/useApplicationSession';
import { ApplicationView } from '@/routes/ApplicationJourney';

import { jsonResponse } from '../activationFixtures';
import { loginSuccess } from '../loginFixtures';

it('coordinates renewal before a delayed freshness callback can mistake absent memory access for server rejection', async () => {
  const identity = crypto.randomUUID();
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
  const owner = createApplicationSession({
    schemaVersion: 2,
    apiBasePath: '/api/v1',
    appDebug: false,
    swaggerAvailable: false
  });
  const navigate = vi.fn();
  const view = render(
    <ApplicationView session={owner} pathname="/app/dashboard" navigate={navigate} />
  );
  try {
    await act(() => owner.coordinator.restore());
    await screen.findByRole('heading', { name: 'Dashboard', level: 1 });
    const main = screen.getByRole('main');
    let now = Math.ceil(performance.now());
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    vi.spyOn(performance, 'now').mockImplementation(() => now);
    await act(async () => {
      owner.coordinator.resume();
      await owner.authority.load();
    });
    // A suspended foreground timer wakes after both principal and credential budgets elapsed.
    // Run the older cache callback before the coordinator's later scheduled callback.
    now += 900_000;
    await act(() => vi.advanceTimersByTimeAsync(60_000));
    expect(owner.coordinator.getSnapshot().status).toBe('authenticated');
    expect(owner.authority.getSnapshot().status).toBe('authenticated');
    expect(screen.getByRole('main')).toBe(main);
    expect(navigate).not.toHaveBeenCalled();
    expect(fetch.mock.calls.filter(([url]) => url.endsWith('/auth/refresh'))).toHaveLength(2);
  } finally {
    view.unmount();
    owner.coordinator.dispose();
    owner.authority.dispose();
    owner.intent.dispose();
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  }
});
