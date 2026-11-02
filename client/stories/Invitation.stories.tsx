import { useEffect, useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { ApplicationFrame } from '@/layouts/ApplicationFrame';
import InvitationPage from '@/pages/InvitationPage';

import {
  invitationDependencies,
  invitationResponse,
  invitationTarget
} from '../tests/invitationFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario =
  | 'ready'
  | 'loading'
  | 'error'
  | 'read-only'
  | 'active'
  | 'expired'
  | 'delivered'
  | 'permanent_failure'
  | 'conflict'
  | 'pending'
  | 'long';
function Example({ scenario = 'ready', dark = false }: { scenario?: Scenario; dark?: boolean }) {
  useEffect(() => {
    // Modals portal to the document, matching production's document-owned theme.
    const root = document.documentElement;
    const previous = root.getAttribute('data-bs-theme');
    root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    return () => {
      if (previous === null) root.removeAttribute('data-bs-theme');
      else root.setAttribute('data-bs-theme', previous);
    };
  }, [dark]);
  const [d] = useState(() => {
    let corrected = false;
    const target = invitationTarget();
    if (scenario === 'long') target.email = `${'équipe'.repeat(25)}@example.test`;
    if (scenario === 'expired') target.delivery.expires_at = '2020-01-01T00:00:00+00:00';
    if (scenario === 'delivered' || scenario === 'permanent_failure')
      target.delivery.status = scenario;
    if (scenario === 'active') target.state = 'active';
    return {
      ...invitationDependencies(
        (_url, init) => {
          if (init.method === 'GET') {
            if (scenario === 'loading') return new Promise<Response>(() => {});
            if (scenario === 'error') return Promise.resolve(invitationResponse(null, 500));
            return Promise.resolve(
              invitationResponse(
                corrected ? { ...target, email: 'corrected@example.test' } : target
              )
            );
          }
          if (scenario === 'pending') return new Promise<Response>(() => {});
          if (scenario === 'conflict') return Promise.resolve(invitationResponse(null, 409));
          if (init.method === 'PATCH') corrected = true;
          return Promise.resolve(invitationResponse(target.delivery));
        },
        scenario === 'read-only' ? ['READ_USERS'] : undefined
      ),
      target
    };
  });
  const [ready, setReady] = useState(false);
  useEffect(() => {
    void d.cache.load().then(() => setReady(true));
    return () => d.cache.dispose();
  }, [d]);
  return ready ? (
    <ApplicationFrame
      cache={d.cache}
      pathname={`/app/admin/invitations/${d.target.user_id}`}
      title="Pending invitation"
      onLogout={() => {
        void d.cache.signal('logout');
      }}
    >
      <InvitationPage userId={d.target.user_id} service={d.service} cache={d.cache} />
    </ApplicationFrame>
  ) : (
    <p>Preparing example</p>
  );
}
const meta = {
  title: 'Administration/Pending invitation',
  component: Example,
  parameters: { layout: 'fullscreen', applicationFrame: true }
} satisfies Meta<typeof Example>;
export default meta;
type Story = StoryObj<typeof meta>;
export const RetryableFailure: Story = {};
export const Dark: Story = { args: { dark: true }, parameters: { mode: 'dark' } };
export const ReadOnly: Story = { args: { scenario: 'read-only' } };
export const Loading: Story = { args: { scenario: 'loading' } };
export const SystemFailure: Story = { args: { scenario: 'error' } };
export const Activated: Story = { args: { scenario: 'active' } };
export const Expired: Story = { args: { scenario: 'expired' } };
export const ProviderAccepted: Story = { args: { scenario: 'delivered' } };
export const PermanentFailure: Story = { args: { scenario: 'permanent_failure' } };
export const LongUnicodeEmail: Story = { args: { scenario: 'long' } };
export const ResendConfirmation: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByRole('button', { name: 'Resend invitation' }));
    const body = within(canvasElement.ownerDocument.body);
    const dialog = await body.findByRole('dialog', { name: 'Confirm replacement invitation' });
    await expect(within(dialog).getByRole('button', { name: 'Cancel' })).toHaveFocus();
    await expect(dialog).toHaveTextContent('old link becomes invalid');
  }
};
export const CorrectionConfirmation: Story = {
  args: { dark: true },
  parameters: { mode: 'dark' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByRole('button', { name: 'Correct email' }));
    await userEvent.type(canvas.getByLabelText('New email (required)'), 'corrected@example.test');
    await userEvent.click(canvas.getByRole('button', { name: 'Review email correction' }));
    const dialog = await within(canvasElement.ownerDocument.body).findByRole('dialog', {
      name: 'Confirm email correction'
    });
    await expect(dialog).toHaveTextContent('pending@example.test');
    await expect(dialog).toHaveTextContent('corrected@example.test');
  }
};
export const PendingRecovery: Story = {
  args: { scenario: 'pending' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByRole('button', { name: 'Retry delivery' }));
    await expect(canvas.getByRole('button', { name: 'Retrying delivery' })).toHaveAttribute(
      'aria-busy',
      'true'
    );
  }
};
export const Conflict: Story = {
  args: { scenario: 'conflict' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByRole('button', { name: 'Retry delivery' }));
    await expect(canvas.findByText(/no longer eligible/)).resolves.toBeVisible();
  }
};
export const Corrected: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByRole('button', { name: 'Correct email' }));
    await userEvent.type(canvas.getByLabelText('New email (required)'), 'corrected@example.test');
    await userEvent.click(canvas.getByRole('button', { name: 'Review email correction' }));
    const body = within(canvasElement.ownerDocument.body);
    await userEvent.click(await body.findByRole('button', { name: 'Confirm correction' }));
    await expect(canvas.findByText('corrected@example.test')).resolves.toBeVisible();
  }
};
