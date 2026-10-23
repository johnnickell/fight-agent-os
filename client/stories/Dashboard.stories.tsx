import { useEffect, useState, useSyncExternalStore } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { AuthorityStatus } from '@/components/AuthorityStatus';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { useAuthority } from '@/features/authority/useAuthority';
import { ThemePreferenceStore } from '@/features/theme/ThemePreference';
import { ApplicationFrame } from '@/layouts/ApplicationFrame';
import { ShellLayout } from '@/layouts/ShellLayout';
import DashboardPage from '@/pages/DashboardPage';
import { applicationRoutes } from '@/routes/applicationRoutes';
import { GuardedRoute } from '@/routes/GuardedRoute';

import type { ThemePreference } from '@/features/theme/ThemePreference';
import type { Meta, StoryObj } from '@storybook/react-vite';

const allPermissions = [
  'VIEW_DASHBOARD',
  'READ_OWN_SESSIONS',
  'READ_USERS',
  'INVITE_USERS',
  'MANAGE_USER_INVITATIONS'
];

function DashboardExample({
  preference = 'light',
  permissions = allPermissions,
  longEmail = false,
  outcome = 'ready'
}: {
  preference?: ThemePreference;
  permissions?: string[];
  longEmail?: boolean;
  outcome?: 'ready' | 'pending' | 'malformed';
}) {
  const [cache] = useState(
    () =>
      new AuthorityCache(() =>
        outcome === 'pending'
          ? new Promise(() => {})
          : Promise.resolve({
              status: 'principal',
              principal: {
                userId: crypto.randomUUID(),
                email: longEmail ? `${'reader'.repeat(10)}@example.test` : 'reader@example.test',
                roles: ['ROLE_USER'],
                permissions,
                ...(outcome === 'malformed' ? { private_field: 'rejected-private-marker' } : {})
              }
            })
      )
  );
  const [theme] = useState(
    () =>
      new ThemePreferenceStore({
        root: document.createElement('div'),
        storage: {
          getItem: () => (preference === 'system' ? null : preference),
          setItem: () => {},
          removeItem: () => {}
        },
        media: () => window.matchMedia('(prefers-color-scheme: dark)'),
        subscribeStorage: () => () => {}
      })
  );
  const appearance = useSyncExternalStore(theme.subscribe, theme.getSnapshot);
  const state = useAuthority(cache);
  useEffect(() => {
    void cache.load();
    return () => cache.dispose();
  }, [cache]);
  const route = applicationRoutes.find((route) => route.destination === 'dashboard');
  if (!route) return null;
  const outlet = (
    <GuardedRoute cache={cache} route={route} outlet={() => <DashboardPage cache={cache} />} />
  );
  return (
    <div data-bs-theme={appearance.effective}>
      {state.status === 'authenticated' ? (
        <ApplicationFrame
          cache={cache}
          pathname="/app/dashboard"
          title="Dashboard"
          theme={theme}
          onLogout={() => {
            void cache.signal('logout');
          }}
        >
          {outlet}
        </ApplicationFrame>
      ) : (
        <ShellLayout theme={theme} publicJourney>
          <h1>Application access</h1>
          <AuthorityStatus state={state} decision="unavailable" />
        </ShellLayout>
      )}
    </div>
  );
}

const meta = {
  title: 'Application/Dashboard',
  component: DashboardExample,
  parameters: { layout: 'fullscreen', applicationFrame: true }
} satisfies Meta<typeof DashboardExample>;
export default meta;
type Story = StoryObj<typeof meta>;
export const WideLight: Story = {};
export const WideDark: Story = { args: { preference: 'dark' }, parameters: { mode: 'dark' } };
export const System: Story = { args: { preference: 'system' } };
export const NarrowDark: Story = {
  args: { preference: 'dark' },
  parameters: { mode: 'dark' },
  globals: { viewport: { value: 'narrow', isRotated: false } }
};
export const LongEmail: Story = { args: { longEmail: true } };
export const AccountOnly: Story = { args: { permissions: ['VIEW_DASHBOARD'] } };
export const IndependentRecovery: Story = {
  args: { permissions: ['VIEW_DASHBOARD', 'MANAGE_USER_INVITATIONS'] }
};
export const Forbidden: Story = { args: { permissions: ['READ_USERS'] } };
export const PendingAuthority: Story = { args: { outcome: 'pending' } };
export const MalformedAuthority: Story = { args: { outcome: 'malformed' } };
export const SignOutRetiresDashboard: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await canvas.findByRole('heading', { name: 'Dashboard', level: 1 });
    await userEvent.click(canvas.getByRole('button', { name: 'User Menu' }));
    await userEvent.click(canvas.getByRole('button', { name: 'Sign Out' }));
    await expect(canvas.queryByRole('heading', { name: 'Dashboard' })).not.toBeInTheDocument();
    await expect(canvas.queryByText('reader@example.test')).not.toBeInTheDocument();
    await expect(canvas.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument();
  }
};
