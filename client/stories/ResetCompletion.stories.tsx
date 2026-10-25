import { useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { ResetCompletionPage } from '@/pages/ResetCompletionPage';

import { jsonResponse, passwordGuidance } from '../tests/activationFixtures';
import { resetCompletionDependencies } from '../tests/resetCompletionFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario =
  | 'ready'
  | 'loading'
  | 'metadata'
  | 'invalid'
  | 'policy'
  | 'throttled'
  | 'system'
  | 'pending'
  | 'success';
function Example({ scenario = 'ready' }: { scenario?: Scenario }) {
  const [d] = useState(() => {
    const deps = resetCompletionDependencies(
      () => {
        if (scenario === 'pending') return new Promise<Response>(() => {});
        if (scenario === 'success') return Promise.resolve(jsonResponse({ completed: true }));
        const status = scenario === 'policy' ? 422 : scenario === 'throttled' ? 429 : 500;
        return Promise.resolve(
          jsonResponse(
            status === 422
              ? { status: 'fail', data: { fields: { 'body.password': [passwordGuidance] } } }
              : {
                  status: 'error',
                  message: status === 429 ? 'Too many requests.' : 'Internal server error.'
                },
            status,
            false
          )
        );
      },
      scenario === 'metadata'
        ? () => jsonResponse(null)
        : scenario === 'loading'
          ? () => new Promise<Response>(() => {})
          : undefined
    );
    if (scenario === 'invalid') deps.credential.clear();
    return deps;
  });
  return <ResetCompletionPage {...d} />;
}
const meta = { title: 'Authentication/ResetCompletion', component: Example } satisfies Meta<
  typeof Example
>;
export default meta;
type Story = StoryObj<typeof meta>;
export const ReadyLight: Story = {};
export const ReadyDark: Story = { parameters: { mode: 'dark' } };
export const Loading: Story = { args: { scenario: 'loading' } };
export const MetadataUnavailable: Story = { args: { scenario: 'metadata' } };
export const InvalidUsedExpired: Story = { args: { scenario: 'invalid' } };
export const Validation: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByRole('button', { name: 'Reset password' }));
    await expect(canvas.getByLabelText('New password (required)')).toHaveFocus();
  }
};
const submit: Story['play'] = async ({ canvasElement }) => {
  const canvas = within(canvasElement);
  // Public dictionary sample only, not a candidate installation password.
  await userEvent.type(await canvas.findByLabelText('New password (required)'), 'P@ssw0rd');
  await userEvent.type(canvas.getByLabelText('Confirm new password (required)'), 'P@ssw0rd');
  await userEvent.click(canvas.getByRole('button', { name: 'Reset password' }));
};
export const CommonPassword: Story = { args: { scenario: 'policy' }, play: submit };
export const Throttled: Story = { args: { scenario: 'throttled' }, play: submit };
export const SystemFailure: Story = { args: { scenario: 'system' }, play: submit };
export const Pending: Story = { args: { scenario: 'pending' }, play: submit };
export const Completed: Story = {
  args: { scenario: 'success' },
  play: async (context) => {
    await submit(context);
    const canvas = within(context.canvasElement);
    await expect(
      await canvas.findByRole('heading', { name: 'Password reset complete' })
    ).toHaveFocus();
    await expect(canvas.queryByLabelText('New password (required)')).not.toBeInTheDocument();
  }
};
