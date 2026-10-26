import { useEffect, useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { Button } from '@/components/Button';
import { ApplicationFrame } from '@/layouts/ApplicationFrame';
import UsersPage from '@/pages/UsersPage';
import { matchApplicationRoute } from '@/routes/applicationRoutes';
import { GuardedRoute } from '@/routes/GuardedRoute';

import {
  directoryDependencies,
  directoryPage,
  directoryPermissions,
  directoryResponse,
  directoryUser
} from '../tests/userDirectoryFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario =
  | 'ready'
  | 'loading'
  | 'empty'
  | 'error'
  | 'forbidden'
  | 'denied'
  | 'long'
  | 'pagination'
  | 'read-only';
function Example({ scenario = 'ready' }: { scenario?: Scenario }) {
  const [d] = useState(() =>
    directoryDependencies(
      (url) => {
        if (scenario === 'loading') return new Promise<Response>(() => {});
        if (scenario === 'error') return Promise.resolve(new Response('', { status: 503 }));
        if (scenario === 'forbidden') return Promise.resolve(directoryResponse(null, 403));
        if (scenario === 'pagination') {
          const second = url.includes('page=2&');
          return Promise.resolve(
            directoryResponse(
              directoryPage(
                second
                  ? [directoryUser({ email: 'last@example.test', state: 'active' })]
                  : Array.from({ length: 25 }, (_, i) =>
                      directoryUser({ email: `person${i}@example.test` })
                    ),
                { page: second ? 2 : 1, total_records: 26, total_pages: 2 }
              )
            )
          );
        }
        return Promise.resolve(
          directoryResponse(
            directoryPage(
              scenario === 'empty'
                ? []
                : [
                    directoryUser({
                      email:
                        scenario === 'long'
                          ? `${'équipe'.repeat(25)}@example.test`
                          : 'pending@example.test',
                      roles: [
                        { name: 'ROLE_CUSTOM', managed: false },
                        { name: 'ROLE_USER', managed: true }
                      ]
                    }),
                    directoryUser({
                      email: 'active@example.test',
                      state: 'active',
                      roles: [{ name: 'ROLE_SUPER_ADMIN', managed: true }]
                    }),
                    directoryUser({ email: 'disabled@example.test', state: 'disabled', roles: [] }),
                    directoryUser({ email: 'deleted@example.test', state: 'deleted' })
                  ]
            )
          )
        );
      },
      scenario === 'denied'
        ? ['INVITE_USERS']
        : scenario === 'read-only'
          ? ['READ_USERS']
          : directoryPermissions
    )
  );
  const [ready, setReady] = useState(false);
  useEffect(() => {
    void d.cache.load().then(() => setReady(true));
    return () => d.cache.dispose();
  }, [d]);
  const route = matchApplicationRoute('/app/admin/users');
  return ready && route ? (
    <ApplicationFrame
      cache={d.cache}
      pathname="/app/admin/users"
      title="Users"
      onLogout={() => {
        void d.cache.signal('logout');
      }}
    >
      <GuardedRoute cache={d.cache} route={route}>
        <UsersPage service={d.service} cache={d.cache} />
      </GuardedRoute>
      <Button
        onClick={() => {
          void d.cache.signal('logout');
        }}
      >
        End catalog session
      </Button>
    </ApplicationFrame>
  ) : (
    <p>Preparing example</p>
  );
}
const meta = {
  title: 'Administration/Users',
  component: Example,
  parameters: { layout: 'fullscreen', applicationFrame: true }
} satisfies Meta<typeof Example>;
export default meta;
type Story = StoryObj<typeof meta>;
export const Directory: Story = {};
export const Dark: Story = { parameters: { mode: 'dark' } };
export const Narrow: Story = {
  globals: { viewport: { value: 'narrow', isRotated: false } },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const table = await canvas.findByRole('table');
    const caption = canvas.getByText('Installation users — current page');
    await expect(canvas.getAllByRole('columnheader')).toHaveLength(5);
    // Browser geometry protects readable card reflow, not CSS source spelling.
    await expect(caption.getBoundingClientRect().width).toBeGreaterThan(200);
    await expect(caption.getBoundingClientRect().height).toBeLessThan(100);
    await expect(table.getBoundingClientRect().width).toBeLessThanOrEqual(
      canvasElement.ownerDocument.defaultView?.innerWidth ?? 0
    );
  }
};
export const LongEmail: Story = { args: { scenario: 'long' } };
export const Loading: Story = { args: { scenario: 'loading' } };
export const Empty: Story = { args: { scenario: 'empty' } };
export const SystemFailure: Story = { args: { scenario: 'error' } };
export const ServerForbidden: Story = { args: { scenario: 'forbidden' } };
export const RoleNameWithoutRead: Story = { args: { scenario: 'denied' } };
export const ReadOnly: Story = { args: { scenario: 'read-only' } };
export const Pagination: Story = {
  args: { scenario: 'pagination' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByRole('button', { name: 'Next page' }));
    await expect(canvas.findByText('last@example.test')).resolves.toBeVisible();
    await expect(canvas.getByRole('heading', { name: 'Users', level: 1 })).toHaveFocus();
    await expect(canvas.getByRole('button', { name: 'Next page' })).toBeDisabled();
    await userEvent.click(canvas.getByRole('button', { name: 'First page' }));
    await expect(canvas.findByText('person0@example.test')).resolves.toBeVisible();
  }
};
export const Retirement: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await canvas.findByRole('table');
    await userEvent.click(canvas.getByRole('button', { name: 'End catalog session' }));
    await expect(canvas.queryByRole('table')).not.toBeInTheDocument();
    await expect(canvas.queryByText('pending@example.test')).not.toBeInTheDocument();
  }
};
