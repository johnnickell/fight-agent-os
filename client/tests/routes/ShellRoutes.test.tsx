import { act, render, screen } from '@testing-library/react';
import { expect, it, vi } from 'vitest';

import { ShellRoutes } from '@/routes/ShellRoutes';

const load = vi.hoisted(() => {
  let resolve!: () => void;
  const promise = new Promise<void>((complete) => {
    resolve = complete;
  });
  return { promise, resolve };
});

vi.mock('@/pages/HomePage', async (importOriginal) => {
  await load.promise;
  return importOriginal();
});

it('announces pending navigation until the real page module becomes available', async () => {
  render(<ShellRoutes pathname="/app" />);
  expect(screen.getByRole('heading', { name: 'Loading application' })).toBeVisible();
  expect(screen.getByRole('status')).toHaveTextContent('Please wait');
  expect(document.title).toBe('Loading application — Fight Agent OS');
  await act(() => {
    load.resolve();
    return load.promise;
  });
  expect(await screen.findByRole('heading', { name: 'Application foundation' })).toBeVisible();
  expect(screen.queryByRole('status')).not.toBeInTheDocument();
  expect(document.title).toBe('Application foundation — Fight Agent OS');
});
