import { useEffect, useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { ApplicationFrame } from '@/layouts/ApplicationFrame';
import InviteUserPage from '@/pages/InviteUserPage';

import { jsonResponse } from '../tests/apiFixtures';
import {
  issuanceData,
  issuanceResponse,
  ordinaryInvitationDependencies
} from '../tests/ordinaryInvitationFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario =
  | 'ready'
  | 'pending'
  | 'validation'
  | 'conflict'
  | 'throttle'
  | 'unconfirmed'
  | 'system'
  | 'success'
  | 'long'
  | 'invite-only';
function Example({ scenario = 'ready', dark = false }: { scenario?: Scenario; dark?: boolean }) {
  const [d] = useState(() => {
    const dependencies = ordinaryInvitationDependencies(
      () => {
        if (scenario === 'pending') return new Promise<Response>(() => {});
        if (scenario === 'conflict' || scenario === 'throttle')
          return Promise.resolve(
            jsonResponse(
              {
                status: 'fail',
                data: {
                  message: scenario === 'conflict' ? 'State conflict.' : 'Too many requests.'
                }
              },
              scenario === 'conflict' ? 409 : 429
            )
          );
        if (scenario === 'system')
          return Promise.resolve(
            jsonResponse({ status: 'error', message: 'Internal server error.' }, 500)
          );
        if (scenario === 'unconfirmed')
          return Promise.resolve(
            jsonResponse(
              {
                status: 'error',
                message:
                  'Invitation completion is unconfirmed. Read invitation status before another operation.',
                data: { ...issuanceData(), assignment: 'unconfirmed', audit: 'unconfirmed' }
              },
              503
            )
          );
        return Promise.resolve(issuanceResponse());
      },
      scenario === 'invite-only'
        ? ['INVITE_USERS']
        : ['INVITE_USERS', 'READ_USERS', 'VIEW_DASHBOARD']
    );
    return {
      ...dependencies,
      service: dependencies.create({
        invalidated: () => {},
        accessRejected: () => {},
        authorityRejected: () => {}
      })
    };
  });
  const [ready, setReady] = useState(false);
  useEffect(() => {
    void d.cache.load().then(() => setReady(true));
    return () => d.cache.dispose();
  }, [d]);
  return (
    <div data-bs-theme={dark ? 'dark' : 'light'} className="catalog-preview catalog-frame-preview">
      {ready ? (
        <ApplicationFrame
          cache={d.cache}
          pathname="/admin/invite"
          title="Invite User"
          onLogout={() => {
            void d.cache.signal('logout');
          }}
        >
          <InviteUserPage service={d.service} cache={d.cache} />
        </ApplicationFrame>
      ) : (
        <p>Preparing example</p>
      )}
    </div>
  );
}
const meta = {
  title: 'Administration/Invite User',
  component: Example,
  parameters: { layout: 'fullscreen', applicationFrame: true }
} satisfies Meta<typeof Example>;
export default meta;
type Story = StoryObj<typeof meta>;
const submit: NonNullable<Story['play']> = async ({ canvasElement }) => {
  const canvas = within(canvasElement);
  const email = await canvas.findByLabelText('Email (required)');
  await userEvent.click(email);
  await userEvent.paste('Person@EXAMPLE.test');
  await userEvent.keyboard('{Enter}');
};
export const Ready: Story = {};
export const Dark: Story = { args: { dark: true }, parameters: { mode: 'dark' } };
export const Validation: Story = {
  args: { scenario: 'validation' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByRole('button', { name: 'Invite user' }));
    await expect(canvas.getByLabelText('Email (required)')).toHaveFocus();
    await expect(canvas.getByRole('alert')).toHaveTextContent('highlighted email');
  }
};
export const Pending: Story = { args: { scenario: 'pending' }, play: submit };
export const Conflict: Story = { args: { scenario: 'conflict' }, play: submit };
export const Throttled: Story = { args: { scenario: 'throttle' }, play: submit };
export const Unconfirmed: Story = { args: { scenario: 'unconfirmed' }, play: submit };
export const SystemFailure: Story = { args: { scenario: 'system' }, play: submit };
export const Success: Story = {
  args: { scenario: 'success' },
  play: async (context) => {
    await submit(context);
    const canvas = within(context.canvasElement);
    await expect(
      canvas.findByRole('heading', { name: 'Invitation accepted' })
    ).resolves.toBeVisible();
    await expect(canvas.getByRole('link', { name: 'View invitation status' })).toBeVisible();
  }
};
export const SuccessDark: Story = {
  ...Success,
  args: { scenario: 'success', dark: true },
  parameters: { mode: 'dark' }
};
export const InviteOnlySuccess: Story = {
  args: { scenario: 'invite-only' },
  play: async (context) => {
    await submit(context);
    const canvas = within(context.canvasElement);
    await expect(
      canvas.findByRole('heading', { name: 'Invitation accepted' })
    ).resolves.toBeVisible();
    await expect(
      canvas.queryByRole('link', { name: 'View invitation status' })
    ).not.toBeInTheDocument();
  }
};
export const LongUnicodeInput: Story = {
  args: { scenario: 'long' },
  play: async ({ canvasElement }) => {
    const input = await within(canvasElement).findByLabelText('Email (required)');
    await userEvent.click(input);
    await userEvent.paste(`${'équipe'.repeat(35)}@example.test`);
  }
};
