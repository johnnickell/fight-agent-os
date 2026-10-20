import { useState } from 'react';

import { expect, userEvent, waitFor, within } from 'storybook/test';

import { ApiClient } from '@/api/ApiClient';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { CurrentSessionControls } from '@/features/auth/CurrentSessionControls';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { SessionService } from '@/features/auth/SessionService';

import { jsonResponse } from '../tests/activationFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

function SessionExample({ outcome = 'ended' }: { outcome?: 'ended' | 'uncertain' | 'pending' }) {
  const [service] = useState(() => {
    const api = new ApiClient(
      { schemaVersion: 1, apiBasePath: '/api/v1' },
      {
        fetch: (url) => {
          const expiry = Math.floor(Date.now() / 1000) + 900;
          if (url.endsWith('/csrf'))
            return Promise.resolve(
              jsonResponse({ proof: `${expiry}.${'a'.repeat(64)}`, expires_at: expiry })
            );
          if (outcome === 'pending') return new Promise<Response>(() => {});
          return Promise.resolve(
            outcome === 'ended'
              ? jsonResponse(null)
              : jsonResponse({ status: 'error', message: 'Internal server error.' }, 500, false)
          );
        }
      }
    );
    return new SessionService(api, new CsrfProofService(api), new MemoryAccessToken());
  });
  return (
    <CurrentSessionControls service={service}>
      <h2>You’re signed in</h2>
      <p>This is a local presentation example, not a live session.</p>
    </CurrentSessionControls>
  );
}
const meta = { title: 'Authentication/Current session', component: SessionExample } satisfies Meta<
  typeof SessionExample
>;
export default meta;
type Story = StoryObj<typeof meta>;
export const ReadyLight: Story = {};
export const ReadyDark: Story = { parameters: { mode: 'dark' } };
export const Pending: Story = {
  args: { outcome: 'pending' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(canvas.getByRole('button', { name: 'Sign out of this session' }));
    await expect(canvas.getByRole('button', { name: 'Signing out' })).toHaveAttribute(
      'aria-busy',
      'true'
    );
  }
};
const end: Story['play'] = async ({ canvasElement }) => {
  const canvas = within(canvasElement);
  await userEvent.click(canvas.getByRole('button', { name: 'Sign out of this session' }));
  const heading = await canvas.findByRole('heading', { name: 'Signed out of this page' });
  await waitFor(() => expect(heading).toHaveFocus());
  await expect(canvas.queryByRole('heading', { name: 'You’re signed in' })).not.toBeInTheDocument();
};
export const Ended: Story = { play: end };
export const Uncertain: Story = { args: { outcome: 'uncertain' }, play: end };
