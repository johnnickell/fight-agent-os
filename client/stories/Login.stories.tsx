import { useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { LoginPage } from '@/pages/LoginPage';

import { jsonResponse } from '../tests/activationFixtures';
import { loginDependencies, loginRejected, loginSuccess } from '../tests/loginFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario =
  'ready' | 'loading' | 'unavailable' | 'failure' | 'throttle' | 'outage' | 'success' | 'pending';
function LoginExample({ scenario = 'ready' }: { scenario?: Scenario }) {
  const [deps] = useState(() =>
    loginDependencies(
      () =>
        scenario === 'pending'
          ? new Promise<Response>(() => {})
          : Promise.resolve(
              scenario === 'success'
                ? loginSuccess()
                : scenario === 'throttle'
                  ? jsonResponse({ status: 'error', message: 'Too many requests.' }, 429, false)
                  : scenario === 'outage'
                    ? jsonResponse(
                        { status: 'error', message: 'Internal server error.' },
                        500,
                        false
                      )
                    : loginRejected()
            ),
      scenario === 'loading'
        ? () => new Promise<Response>(() => {})
        : scenario === 'unavailable'
          ? () => jsonResponse(null)
          : undefined
    )
  );
  return <LoginPage {...deps} />;
}
const meta = { title: 'Authentication/Login', component: LoginExample } satisfies Meta<
  typeof LoginExample
>;
export default meta;
type Story = StoryObj<typeof meta>;

export const ReadyLight: Story = {};
export const ReadyDark: Story = { parameters: { mode: 'dark' } };
export const RememberKeyboard: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const checkbox = await canvas.findByRole('checkbox');
    checkbox.focus();
    await userEvent.keyboard('[Space]');
    await expect(checkbox).toBeChecked();
    await expect(getComputedStyle(checkbox).outlineStyle).toBe('solid');
    await expect(getComputedStyle(checkbox).outlineWidth).toBe('3px');
  }
};
export const Loading: Story = { args: { scenario: 'loading' } };
export const Unavailable: Story = { args: { scenario: 'unavailable' } };
export const Validation: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const email = await canvas.findByLabelText('Email (required)');
    await userEvent.click(canvas.getByRole('button', { name: 'Sign in' }));
    await expect(email).toHaveFocus();
    await expect(email).toHaveAttribute('aria-invalid', 'true');
    await expect(canvas.getByLabelText('Password (required)')).toHaveAttribute(
      'aria-invalid',
      'true'
    );
  }
};
const submit: Story['play'] = async ({ canvasElement }) => {
  const canvas = within(canvasElement);
  await userEvent.type(await canvas.findByLabelText('Email (required)'), 'example@example.test');
  // Public dictionary input, not a live credential. Existing-password login has no composition gate.
  await userEvent.type(canvas.getByLabelText('Password (required)'), 'example');
  await userEvent.click(canvas.getByRole('button', { name: 'Sign in' }));
};
export const GenericFailure: Story = { args: { scenario: 'failure' }, play: submit };
export const Throttled: Story = { args: { scenario: 'throttle' }, play: submit };
export const Outage: Story = { args: { scenario: 'outage' }, play: submit };
export const SigningIn: Story = { args: { scenario: 'pending' }, play: submit };
export const SignedIn: Story = {
  args: { scenario: 'success' },
  play: async (context) => {
    await submit(context);
    const canvas = within(context.canvasElement);
    await expect(await canvas.findByRole('heading', { name: 'You’re signed in' })).toHaveFocus();
    await expect(canvas.queryByLabelText('Password (required)')).not.toBeInTheDocument();
  }
};
