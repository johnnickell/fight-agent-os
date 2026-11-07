import { useEffect, useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { ApplicationFrame } from '@/layouts/ApplicationFrame';
import UserDetail from '@/pages/UserDetail';

import {
  directoryDependencies,
  directoryResponse,
  directoryUser
} from '../tests/userDirectoryFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario = 'ready' | 'empty-roles' | 'loading' | 'forbidden' | 'missing' | 'error';
function Example({ scenario = 'ready' }: { scenario?: Scenario }) {
  const [d] = useState(() => {
    const user = directoryUser({
      email: `${'équipe'.repeat(20)}@example.test`,
      roles:
        scenario === 'empty-roles'
          ? []
          : [
              { name: 'ROLE_CUSTOM', managed: false },
              { name: 'ROLE_USER', managed: true }
            ]
    });
    return {
      ...directoryDependencies(() => {
        if (scenario === 'loading') return new Promise<Response>(() => {});
        if (scenario === 'forbidden') return Promise.resolve(directoryResponse(null, 403));
        if (scenario === 'missing')
          return Promise.resolve(
            new Response(
              JSON.stringify({ status: 'fail', data: { message: 'Resource not found.' } }),
              {
                status: 404,
                headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
              }
            )
          );
        if (scenario === 'error') return Promise.resolve(new Response('', { status: 503 }));
        return Promise.resolve(directoryResponse(user));
      }),
      user
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
      pathname="/app/admin/users"
      title="Users"
      onLogout={() => {
        void d.cache.signal('logout');
      }}
    >
      <UserDetail
        userId={d.user.user_id}
        cache={d.cache}
        service={d.service}
        onBack={() => {
          /* The containing directory owns navigation. */
        }}
      />
    </ApplicationFrame>
  ) : (
    <p>Preparing example</p>
  );
}
const meta = {
  title: 'Administration/User detail',
  component: Example,
  parameters: { layout: 'fullscreen', applicationFrame: true }
} satisfies Meta<typeof Example>;
export default meta;
type Story = StoryObj<typeof meta>;
export const SafeDetail: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await canvas.findByText('ROLE_CUSTOM (Custom)');
    await expect(canvas.getByRole('heading', { name: 'User detail' })).toHaveFocus();
    await userEvent.click(canvas.getByRole('button', { name: 'Reload user detail' }));
    await canvas.findByText('ROLE_CUSTOM (Custom)');
  }
};
export const Dark: Story = { parameters: { mode: 'dark' } };
export const NoRoles: Story = { args: { scenario: 'empty-roles' } };
export const Loading: Story = { args: { scenario: 'loading' } };
export const Forbidden: Story = { args: { scenario: 'forbidden' } };
export const Missing: Story = { args: { scenario: 'missing' } };
export const Unavailable: Story = { args: { scenario: 'error' } };
