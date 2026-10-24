import { useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { ResetRequestPage } from '@/pages/ResetRequestPage';

import { jsonResponse } from '../tests/activationFixtures';
import { resetAccepted, resetDependencies } from '../tests/resetRequestFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario = 'ready' | 'loading' | 'unavailable' | 'pending' | 'success' | 'throttle' | 'outage';
function ResetExample({ scenario = 'ready' }: { scenario?: Scenario }) {
  const [deps] = useState(() =>
    resetDependencies(
      () =>
        scenario === 'pending'
          ? new Promise<Response>(() => {})
          : Promise.resolve(
              scenario === 'throttle'
                ? jsonResponse({ status: 'error', message: 'Too many requests.' }, 429, false)
                : scenario === 'outage'
                  ? jsonResponse({ status: 'error', message: 'Internal server error.' }, 500, false)
                  : resetAccepted()
            ),
      scenario === 'loading'
        ? () => new Promise<Response>(() => {})
        : scenario === 'unavailable'
          ? () => jsonResponse(null)
          : undefined
    )
  );
  return <ResetRequestPage {...deps} />;
}
const meta = { title: 'Authentication/ResetRequest', component: ResetExample } satisfies Meta<
  typeof ResetExample
>;
export default meta;
type Story = StoryObj<typeof meta>;
export const ReadyLight: Story = {};
export const ReadyDark: Story = { parameters: { mode: 'dark' } };
export const Loading: Story = { args: { scenario: 'loading' } };
export const Unavailable: Story = { args: { scenario: 'unavailable' } };
export const Validation: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const email = await canvas.findByLabelText('Email (required)');
    await userEvent.click(canvas.getByRole('button', { name: 'Request reset link' }));
    await expect(email).toHaveFocus();
    await expect(email).toHaveAttribute('aria-invalid', 'true');
  }
};
const submit: Story['play'] = async ({ canvasElement }) => {
  const canvas = within(canvasElement);
  await userEvent.type(await canvas.findByLabelText('Email (required)'), 'example@example.test');
  await userEvent.click(canvas.getByRole('button', { name: 'Request reset link' }));
};
export const Pending: Story = { args: { scenario: 'pending' }, play: submit };
export const Throttled: Story = { args: { scenario: 'throttle' }, play: submit };
export const Outage: Story = { args: { scenario: 'outage' }, play: submit };
export const Accepted: Story = {
  args: { scenario: 'success' },
  play: async (context) => {
    await submit(context);
    const canvas = within(context.canvasElement);
    await expect(await canvas.findByRole('heading', { name: 'Check your inbox' })).toHaveFocus();
    await expect(canvas.queryByRole('textbox')).not.toBeInTheDocument();
  }
};
