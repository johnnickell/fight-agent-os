import { useEffect, useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { apiFailure } from '@/api/ApiResult';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { SessionContinuity } from '@/features/auth/SessionContinuity';
import { SessionCoordinator } from '@/features/auth/SessionCoordinator';

import type { Meta, StoryObj } from '@storybook/react-vite';

function ContinuityExample({
  outcome = 'pending'
}: {
  outcome?: 'pending' | 'ready' | 'network' | 'terminal';
}) {
  const [coordinator] = useState(() => {
    const tokens = new MemoryAccessToken();
    return new SessionCoordinator({
      tokens,
      session: {
        refresh: async () => {
          if (outcome === 'pending') return new Promise(() => {});
          if (outcome === 'network') return apiFailure('network');
          if (outcome === 'terminal') return apiFailure('authentication');
          tokens.accept('catalog.access.signature', performance.now());
          return { ok: true, value: true, correlationId: null };
        },
        logout: () => Promise.resolve({ ok: true, value: true, correlationId: null }),
        invalidate: () => tokens.clear(),
        dispose: () => tokens.clear()
      },
      login: { submit: () => Promise.resolve(apiFailure('authentication')), dispose: () => {} }
    });
  });
  useEffect(() => {
    void coordinator.restore();
    return () => coordinator.dispose();
  }, [coordinator]);
  return (
    <SessionContinuity coordinator={coordinator}>
      <h1>Sign in explicitly</h1>
      <p>Synthetic local state example; no live credentials.</p>
    </SessionContinuity>
  );
}
const meta = { title: 'Authentication/Continuity', component: ContinuityExample } satisfies Meta<
  typeof ContinuityExample
>;
export default meta;
type Story = StoryObj<typeof meta>;
export const Restoring: Story = {};
export const Ready: Story = { args: { outcome: 'ready' } };
export const Network: Story = { args: { outcome: 'network' } };
export const ExpiredLight: Story = { args: { outcome: 'terminal' } };
export const ExpiredDark: Story = { args: { outcome: 'terminal' }, parameters: { mode: 'dark' } };
export const ExplicitReauthentication: Story = {
  args: { outcome: 'terminal' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByRole('button', { name: 'Sign in again' }));
    await expect(canvas.getByRole('heading', { name: 'Sign in explicitly' })).toBeVisible();
  }
};
