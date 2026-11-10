import { useEffect, useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { ApplicationFrame } from '@/layouts/ApplicationFrame';
import InviteSuperAdminPage from '@/pages/InviteSuperAdminPage';

import { jsonResponse } from '../tests/apiFixtures';
import {
  issuanceData,
  issuanceResponse,
  ordinaryInvitationDependencies
} from '../tests/ordinaryInvitationFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario =
  | 'ready'
  | 'confirm'
  | 'pending'
  | 'success'
  | 'unconfirmed'
  | 'system'
  | 'conflict'
  | 'throttle'
  | 'forbidden'
  | 'no-read'
  | 'long';
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
                data: { ...issuanceData(), assignment: 'unknown', audit: 'unconfirmed' }
              },
              503
            )
          );
        return Promise.resolve(issuanceResponse());
      },
      scenario === 'forbidden'
        ? ['INVITE_USERS']
        : scenario === 'no-read'
          ? ['INVITE_USERS', 'ASSIGN_SUPER_ADMIN', 'MANAGE_USER_INVITATIONS']
          : ['INVITE_USERS', 'ASSIGN_SUPER_ADMIN', 'READ_USERS', 'VIEW_DASHBOARD']
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
    // Match production's document-root theme so body-portaled dialogs inherit it too.
    const previous = document.documentElement.getAttribute('data-bs-theme');
    document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    return () => {
      if (previous === null) document.documentElement.removeAttribute('data-bs-theme');
      else document.documentElement.setAttribute('data-bs-theme', previous);
    };
  }, [dark]);
  useEffect(() => {
    void d.cache.load().then(() => setReady(true));
    return () => d.cache.dispose();
  }, [d]);
  return (
    <div data-bs-theme={dark ? 'dark' : 'light'} className="catalog-preview catalog-frame-preview">
      {ready ? (
        <ApplicationFrame
          cache={d.cache}
          pathname="/admin/invite-super-admin"
          title="Invite Super Admin"
          onLogout={() => {
            void d.cache.signal('logout');
          }}
        >
          <InviteSuperAdminPage service={d.service} cache={d.cache} />
        </ApplicationFrame>
      ) : (
        <p>Preparing example</p>
      )}
    </div>
  );
}
const meta = {
  title: 'Administration/Invite Super Admin',
  component: Example,
  parameters: { layout: 'fullscreen', applicationFrame: true }
} satisfies Meta<typeof Example>;
export default meta;
type Story = StoryObj<typeof meta>;
const review: NonNullable<Story['play']> = async ({ canvasElement }) => {
  const canvas = within(canvasElement);
  const email = await canvas.findByLabelText('Email (required)');
  await userEvent.click(email);
  await userEvent.paste('Person@EXAMPLE.test');
  await userEvent.keyboard('{Enter}');
  const dialog = await within(document.body).findByRole('dialog');
  await expect(within(dialog).getByText('person@example.test')).toBeVisible();
  await expect(within(dialog).getByRole('button', { name: 'Cancel' })).toHaveFocus();
};
const submit: NonNullable<Story['play']> = async (context) => {
  await review(context);
  await userEvent.click(
    within(document.body).getByRole('button', { name: 'Confirm Super Admin invitation' })
  );
};
export const Ready: Story = {};
export const Dark: Story = { args: { dark: true }, parameters: { mode: 'dark' } };
export const Confirmation: Story = { args: { scenario: 'confirm' }, play: review };
export const ConfirmationDark: Story = {
  ...Confirmation,
  args: { scenario: 'confirm', dark: true },
  parameters: { mode: 'dark' }
};
export const CancelAndReturn: Story = {
  play: async (context) => {
    await review(context);
    await userEvent.keyboard('{Escape}');
    await expect(
      within(context.canvasElement).getByRole('button', { name: 'Review Super Admin invitation' })
    ).toHaveFocus();
  }
};
export const Validation: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(
      await canvas.findByRole('button', { name: 'Review Super Admin invitation' })
    );
    await expect(canvas.getByLabelText('Email (required)')).toHaveFocus();
    await expect(canvas.getByRole('alert')).toHaveTextContent('highlighted email');
  }
};
export const Pending: Story = { args: { scenario: 'pending' }, play: submit };
export const Conflict: Story = { args: { scenario: 'conflict' }, play: submit };
export const Throttled: Story = { args: { scenario: 'throttle' }, play: submit };
export const Unconfirmed: Story = { args: { scenario: 'unconfirmed' }, play: submit };
export const SystemFailure: Story = { args: { scenario: 'system' }, play: submit };
export const Forbidden: Story = { args: { scenario: 'forbidden' } };
export const Success: Story = {
  args: { scenario: 'success' },
  play: async (context) => {
    await submit(context);
    const canvas = within(context.canvasElement);
    await expect(
      canvas.findByRole('heading', { name: 'Super Admin invitation accepted' })
    ).resolves.toBeVisible();
    await expect(canvas.getByRole('link', { name: 'Read invitation status' })).toBeVisible();
  }
};
export const ManageWithoutRead: Story = {
  args: { scenario: 'no-read' },
  play: async (context) => {
    await submit(context);
    const canvas = within(context.canvasElement);
    await expect(
      canvas.findByRole('heading', { name: 'Super Admin invitation accepted' })
    ).resolves.toBeVisible();
    await expect(
      canvas.queryByRole('link', { name: 'Read invitation status' })
    ).not.toBeInTheDocument();
  }
};
export const LongUnicodeConfirmation: Story = {
  args: { scenario: 'long' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByLabelText('Email (required)'));
    await userEvent.paste(`${'équipe'.repeat(35)}@example.test`);
    await userEvent.keyboard('{Enter}');
    await expect(within(document.body).findByRole('dialog')).resolves.toBeVisible();
  }
};
