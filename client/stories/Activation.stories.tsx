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
  globals: { viewport: { value: 'narrow' } },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const button = await canvas.findByRole('button', { name: 'Activate account' });
    const password = canvas.getByLabelText('New password (required)');
    const confirmation = canvas.getByLabelText('Confirm new password (required)');
    const document = canvasElement.ownerDocument;
    const user = userEvent.setup({ document });
    const previousWidth = canvasElement.style.width;
    // R43-01: browser layout must keep the original release point on the submit control.
    // A click dispatched straight to the button cannot detect blur stealing the first press.
    canvasElement.style.width = '320px';
    try {
      await document.fonts.ready;
      password.focus();
      button.scrollIntoView({ block: 'center', behavior: 'instant' });
      await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
      const bounds = button.getBoundingClientRect();
      const point = { x: bounds.x + bounds.width / 2, y: bounds.y + bounds.height / 2 };
      await expect(document.elementFromPoint(point.x, point.y)?.closest('button')).toBe(button);
      await user.pointer({ target: button, keys: '[MouseLeft>]', coords: point });
      await new Promise<void>((resolve) =>
        requestAnimationFrame(() => requestAnimationFrame(() => resolve()))
      );
      const releaseTarget = document.elementFromPoint(point.x, point.y);
      await expect(releaseTarget?.closest('button')).toBe(button);
      await user.pointer({ target: button, keys: '[/MouseLeft]', coords: point });
      await expect(password).toHaveFocus();
      await expect(password).toHaveAttribute('aria-invalid', 'true');
      await expect(confirmation).toHaveAttribute('aria-invalid', 'true');
      await expect(password).toHaveAccessibleDescription(/Error:/);
      await expect(confirmation).toHaveAccessibleDescription(/Error:/);
    } finally {
      canvasElement.style.width = previousWidth;
    }
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
