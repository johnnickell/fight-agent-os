import { useEffect, useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { Button } from '@/components/Button';
import { ContentPanel } from '@/components/ContentPanel';
import { PermissionButton } from '@/components/PermissionButton';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { GuardedRoute } from '@/routes/GuardedRoute';

import type { PrincipalLoadResult } from '@/features/authority/AuthorityCache';
import type { RegisteredRoute } from '@/routes/GuardedRoute';
import type { Meta, StoryObj } from '@storybook/react-vite';

const route: RegisteredRoute = {
  pathname: '/app/example',
  requirements: [{ kind: 'authenticated' }, { kind: 'permissions', all: ['VIEW_EXAMPLE'] }]
};
// Generated through the platform factory, not an invented UUID or an application identity.
const exampleUserId = crypto.randomUUID();
type Scenario =
  | 'unknown'
  | 'loading'
  | 'anonymous'
  | 'allowed'
  | 'forbidden'
  | 'stale'
  | 'terminal'
  | 'refresh-failed'
  | 'protocol'
  | 'network';

function ExampleOutlet() {
  return <p>Example protected outlet. Server authorization remains authoritative.</p>;
}
function AuthorityExample({ scenario }: { scenario: Scenario }) {
  const [cache] = useState(
    () =>
      new AuthorityCache(() => {
        if (scenario === 'loading') return new Promise<PrincipalLoadResult>(() => {});
        if (
          scenario === 'anonymous' ||
          scenario === 'terminal' ||
          scenario === 'protocol' ||
          scenario === 'network'
        ) {
          return Promise.resolve({ status: scenario });
        }
        return Promise.resolve({
          status: 'principal',
          principal: {
            userId: exampleUserId,
            email: 'example@example.test',
            roles: ['ROLE_EXAMPLE'],
            permissions: scenario === 'forbidden' ? [] : ['VIEW_EXAMPLE', 'EDIT_EXAMPLE']
          }
        });
      })
  );
  const [acted, setActed] = useState(false);
  useEffect(() => {
    const attempt = cache.beginAuthentication();
    if (scenario !== 'unknown') {
      void attempt.acceptCredentials().then(() => {
        if (scenario === 'stale') cache.resume();
        if (scenario === 'refresh-failed') void cache.signal('refresh-failed');
      });
    }
    return () => {
      void cache.signal('logout');
    };
  }, [cache, scenario]);
  return (
    <ContentPanel title="Client authority foundation">
      <p>Injected example only. No authentication endpoint or product route is implemented.</p>
      <div className="d-flex flex-column align-items-start gap-3">
        <GuardedRoute cache={cache} route={route} outlet={ExampleOutlet} />
        <PermissionButton
          cache={cache}
          permissions={['EDIT_EXAMPLE']}
          denied="disable"
          onAction={() => {
            setActed(true);
          }}
        >
          Example action
        </PermissionButton>
        {acted && <p role="status">Example action selected; no server operation was sent.</p>}
        {scenario === 'allowed' && (
          <Button
            onClick={() => {
              void cache.signal('terminal');
            }}
          >
            End example session
          </Button>
        )}
      </div>
    </ContentPanel>
  );
}

const meta = {
  title: 'Foundation/Authority',
  component: AuthorityExample,
  parameters: { controls: { disable: true } }
} satisfies Meta<typeof AuthorityExample>;
export default meta;
type Story = StoryObj<typeof meta>;
export const Unknown: Story = { args: { scenario: 'unknown' } };
export const Loading: Story = { args: { scenario: 'loading' } };
export const Anonymous: Story = { args: { scenario: 'anonymous' } };
export const Allowed: Story = {
  args: { scenario: 'allowed' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('button', { name: 'Example action' })).toBeEnabled();
    await userEvent.click(canvas.getByRole('button', { name: 'Example action' }));
    await expect(canvas.getByRole('status')).toHaveTextContent('no server operation');
  }
};
export const Forbidden: Story = {
  args: { scenario: 'forbidden' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByText('Access forbidden')).toBeVisible();
    await expect(canvas.getByRole('button', { name: 'Example action' })).toBeDisabled();
  }
};
export const Stale: Story = { args: { scenario: 'stale' } };
export const Terminal: Story = { args: { scenario: 'terminal' } };
export const RefreshFailed: Story = { args: { scenario: 'refresh-failed' } };
export const Malformed: Story = { args: { scenario: 'protocol' } };
export const NetworkFailure: Story = { args: { scenario: 'network' } };
export const SessionEnds: Story = {
  args: { scenario: 'allowed' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(canvas.getByRole('button', { name: 'End example session' }));
    await expect(canvas.getByText('Session ended')).toBeVisible();
    await expect(canvas.getByRole('button', { name: 'Example action' })).toBeDisabled();
    await expect(canvas.queryByText(/Example protected outlet/)).not.toBeInTheDocument();
  }
};
