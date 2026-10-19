import { useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { ActivationPage } from '@/pages/ActivationPage';

import {
  activationDependencies,
  jsonResponse,
  passwordGuidance
} from '../tests/activationFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario = 'ready' | 'loading' | 'metadata' | 'recovery' | 'common' | 'throttled' | 'success';
function ActivationExample({ scenario = 'ready' }: { scenario?: Scenario }) {
  const [deps] = useState(() => {
    const dependencies = activationDependencies(
      () =>
        Promise.resolve(
          scenario === 'success'
            ? jsonResponse({
                access_token: 'header.payload.signature',
                issued_at: 1000,
                expires_at: 1900
              })
            : scenario === 'common'
              ? jsonResponse(
                  { status: 'fail', data: { fields: { 'body.password': [passwordGuidance] } } },
                  422,
                  false
                )
              : jsonResponse({ status: 'error', message: 'Too many requests.' }, 429, false)
        ),
      scenario === 'metadata'
        ? () => jsonResponse(null)
        : scenario === 'loading'
          ? () => new Promise<Response>(() => {})
          : undefined
    );
    if (scenario === 'recovery') dependencies.credential.clear();
    return dependencies;
  });
  // The loading view uses a real pending metadata read, not a local form-state enum.
  return <ActivationPage service={deps.service} schemas={deps.schemas} />;
}
const meta = { title: 'Authentication/Activation', component: ActivationExample } satisfies Meta<
  typeof ActivationExample
>;
export default meta;
type Story = StoryObj<typeof meta>;

export const ReadyLight: Story = {};
export const ReadyDark: Story = { parameters: { mode: 'dark' } };
export const Loading: Story = { args: { scenario: 'loading' } };
export const MetadataUnavailable: Story = { args: { scenario: 'metadata' } };
export const UnusableInvitation: Story = { args: { scenario: 'recovery' } };
export const PolicyValidation: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const button = await canvas.findByRole('button', { name: 'Activate account' });
    await userEvent.click(button);
    await expect(canvas.getByLabelText('New password (required)')).toHaveFocus();
  }
};
const submitExample: Story['play'] = async ({ canvasElement }) => {
  const canvas = within(canvasElement);
  // Public dictionary example only; never use an installation's actual password in catalog evidence.
  await userEvent.type(await canvas.findByLabelText('New password (required)'), 'P@ssw0rd');
  await userEvent.type(canvas.getByLabelText('Confirm new password (required)'), 'P@ssw0rd');
  await userEvent.click(canvas.getByRole('button', { name: 'Activate account' }));
};
export const CommonPassword: Story = { args: { scenario: 'common' }, play: submitExample };
export const Throttled: Story = { args: { scenario: 'throttled' }, play: submitExample };
export const Activated: Story = {
  args: { scenario: 'success' },
  play: async (context) => {
    await submitExample(context);
    const canvas = within(context.canvasElement);
    await expect(await canvas.findByText('Account activated')).toBeVisible();
    await expect(canvas.queryByLabelText('New password (required)')).not.toBeInTheDocument();
  }
};
