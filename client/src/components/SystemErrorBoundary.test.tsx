import { render, screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import { SystemErrorBoundary } from './SystemErrorBoundary';

it('contains a render failure without exposing its message', () => {
  function BrokenPage(): never {
    throw new Error('private-render-failure');
  }
  render(
    <SystemErrorBoundary>
      <BrokenPage />
    </SystemErrorBoundary>,
    { onCaughtError: () => {} },
  );
  expect(
    screen.getByRole('heading', { name: 'Application unavailable' }),
  ).toBeVisible();
  expect(document.body.textContent).not.toContain('private-render-failure');
});
