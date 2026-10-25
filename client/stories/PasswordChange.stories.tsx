import { useEffect, useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { ContentState } from '@/components/ContentState';
import PasswordChangePage from '@/pages/PasswordChangePage';

import { jsonResponse, passwordGuidance } from '../tests/activationFixtures';
import { passwordChangeDependencies } from '../tests/passwordChangeFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario =
  | 'ready'
  | 'loading'
  | 'metadata'
  | 'proof'
  | 'policy'
  | 'throttled'
  | 'system'
  | 'pending'
  | 'success';
function Example({ scenario = 'ready' }: { scenario?: Scenario }) {
  const [complete, setComplete] = useState(false);
  const [d] = useState(() =>
    passwordChangeDependencies(
      () => {
        if (scenario === 'pending') return new Promise<Response>(() => {});
        if (scenario === 'success') return Promise.resolve(jsonResponse({ changed: true }));
        const status =
          scenario === 'policy'
            ? 422
            : scenario === 'throttled'
              ? 429
              : scenario === 'proof'
                ? 409
                : 500;
        return Promise.resolve(
          jsonResponse(
            status === 422
              ? { status: 'fail', data: { fields: { 'body.password': [passwordGuidance] } } }
              : {
                  status: 'error',
                  message:
                    status === 409
                      ? 'Conflict.'
                      : status === 429
                        ? 'Too many requests.'
                        : 'Internal server error.'
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
          : undefined,
      () => setComplete(true)
    )
  );
  useEffect(() => {
    void d.authority.load();
    return () => d.authority.dispose();
  }, [d]);
  return complete ? (
    <ContentState
      kind="success"
      title="Password changed — sign in again"
      message="All sessions have ended. Password fields and local access are cleared. No replacement session was created."
    />
  ) : (
    <PasswordChangePage {...d} />
  );
}
const meta = { title: 'Authentication/PasswordChange', component: Example } satisfies Meta<
  typeof Example
>;
export default meta;
type Story = StoryObj<typeof meta>;
export const ReadyLight: Story = {};
export const ReadyDark: Story = { parameters: { mode: 'dark' } };
export const Loading: Story = { args: { scenario: 'loading' } };
export const MetadataUnavailable: Story = { args: { scenario: 'metadata' } };
export const Validation: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(
      await canvas.findByRole('button', { name: 'Change password and sign out' })
    );
    await expect(canvas.getByLabelText('Current password (required)')).toHaveFocus();
  }
};
const submit: Story['play'] = async ({ canvasElement }) => {
  const canvas = within(canvasElement);
  await userEvent.type(
    await canvas.findByLabelText('Current password (required)'),
    'synthetic old value'
  );
  await userEvent.type(canvas.getByLabelText('New password (required)'), 'P@ssw0rd');
  await userEvent.type(canvas.getByLabelText('Confirm new password (required)'), 'P@ssw0rd');
  await userEvent.click(canvas.getByRole('button', { name: 'Change password and sign out' }));
};
export const WrongCurrent: Story = { args: { scenario: 'proof' }, play: submit };
export const CommonPassword: Story = { args: { scenario: 'policy' }, play: submit };
export const Throttled: Story = { args: { scenario: 'throttled' }, play: submit };
export const SystemFailure: Story = { args: { scenario: 'system' }, play: submit };
export const Pending: Story = { args: { scenario: 'pending' }, play: submit };
export const Completed: Story = {
  args: { scenario: 'success' },
  play: async (context) => {
    await submit(context);
    const canvas = within(context.canvasElement);
    await expect(await canvas.findByText('Password changed — sign in again')).toBeVisible();
    await expect(canvas.queryByLabelText('Current password (required)')).not.toBeInTheDocument();
  }
};
