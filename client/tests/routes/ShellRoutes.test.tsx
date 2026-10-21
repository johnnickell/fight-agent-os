import { act, render, screen } from '@testing-library/react';
import { expect, it, vi } from 'vitest';

import { ApplicationView } from '@/routes/ApplicationJourney';

import { applicationSession } from '../applicationFixtures';

const load = vi.hoisted(() => {
  let resolve!: () => void;
  const promise = new Promise<void>((complete) => {
    resolve = complete;
  });
  return { promise, resolve };
});
vi.mock('@/pages/LandingPage', async (importOriginal) => {
  await load.promise;
  return importOriginal();
});

it('imports the protected page only after authority resolves, announcing the pending module', async () => {
  const session = applicationSession();
  const mounted = render(
    <ApplicationView session={session} pathname="/app/dashboard" navigate={() => {}} />
  );
  expect(screen.getByText('Restoring your session')).toBeVisible();
  await act(() => session.coordinator.restore());
  expect(screen.getByRole('heading', { name: 'Loading application' })).toBeVisible();
  expect(screen.getByText('Please wait while the page loads.')).toBeVisible();
  await act(() => {
    load.resolve();
    return load.promise;
  });
  expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeVisible();
  expect(screen.queryByText('Please wait while the page loads.')).not.toBeInTheDocument();
  mounted.unmount();
  session.dispose();
});
