import { render, screen } from '@testing-library/react';
import { expect, it, vi } from 'vitest';
import { ShellApplication } from '../ShellApplication';

vi.mock('../pages/HomePage', () => {
  throw new Error('private-module-failure');
});

it('replaces a failed page import with generic recovery rather than raw diagnostics', async () => {
  render(
    <ShellApplication
      configuration={'{"schema_version":1,"api_base_path":"/api/v1"}'}
      pathname="/app"
    />,
    { onCaughtError: () => {} },
  );
  expect(
    await screen.findByRole('heading', { name: 'Application unavailable' }),
  ).toBeVisible();
  expect(screen.getByRole('alert')).toBeVisible();
  expect(screen.getByRole('main')).toBeVisible();
  expect(
    screen.getByRole('link', { name: 'Reload application' }),
  ).toHaveAttribute('href', '/app');
  expect(document.body.textContent).not.toContain('private-module-failure');
});
