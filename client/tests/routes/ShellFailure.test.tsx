import { render, screen } from '@testing-library/react';
import { expect, it, vi } from 'vitest';

import { ShellApplication } from '@/ShellApplication';

import { applicationSession } from '../applicationFixtures';

import type { ApplicationSession } from '@/features/auth/useApplicationSession';

const owner = vi.hoisted(() => ({ session: null as ApplicationSession | null }));
vi.mock('@/features/auth/useApplicationSession', () => ({
  useApplicationSession: () => ({ session: owner.session, retired: false })
}));
vi.mock('@/pages/LandingPage', () => {
  throw new Error('token-cookie-password-stack-SQL-private-module-failure');
});

it('removes the entire protected frame on render/import failure and presents safe bounded recovery', async () => {
  const session = applicationSession();
  owner.session = session;
  await session.coordinator.restore();
  const mounted = render(
    <ShellApplication
      configuration={'{"schema_version":1,"api_base_path":"/api/v1"}'}
      pathname="/app/dashboard"
    />,
    { onCaughtError: () => {} }
  );
  expect(await screen.findByRole('heading', { name: 'Application unavailable' })).toBeVisible();
  expect(screen.getByRole('alert')).toBeVisible();
  expect(screen.getByRole('main')).toBeVisible();
  expect(screen.getByRole('link', { name: 'Reload application' })).toHaveAttribute('href', '/app');
  expect(screen.queryByRole('button', { name: 'Sign out' })).not.toBeInTheDocument();
  expect(document.body.textContent).not.toContain(
    'token-cookie-password-stack-SQL-private-module-failure'
  );
  mounted.unmount();
  session.dispose();
});
