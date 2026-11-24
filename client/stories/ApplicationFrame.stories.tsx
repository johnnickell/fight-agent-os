import { useEffect, useState, useSyncExternalStore } from 'react';

import { apiFailure } from '@fight/react/account';
import { MemoryAccessToken } from '@fight/react/account';
import { PasswordChangeService } from '@fight/react/account';
import { SessionCoordinator } from '@fight/react/account';
import { AuthorityCache } from '@fight/react/account';
import { InvitationIssuanceService } from '@fight/react/account';
import { InvitationService } from '@fight/react/account';
import { OwnedSessionsService } from '@fight/react/account';
import { UserAdministrationService } from '@fight/react/account';
import { UserDirectoryService } from '@fight/react/account';
import { PublicSchemaService } from '@fight/react/account';
import { BrandProvider, ThemePreferenceStore } from '@fight/react/controls';
import { ApiClient } from '@fixtures/transport';
import { expect, userEvent, waitFor, within } from 'storybook/test';

import { SwaggerService } from '@/features/swagger/SwaggerService';
import { ApplicationView } from '@/routes/ApplicationJourney';
import { applicationRoutes } from '@/routes/applicationRoutes';
import { IntendedRoute } from '@/routes/IntendedRoute';
import { useApplicationNavigation } from '@/routes/useApplicationNavigation';

import { expectHarborTheme } from './expectHarborTheme';
import { harborBrand } from './HarborBrand';

import type { ThemePreference } from '@fight/react/controls';
import type { Meta, StoryObj } from '@storybook/react-vite';

function FrameExample({
  outcome = 'ready',
  initial = '/',
  longEmail = false,
  preference = 'system'
}: {
  outcome?: 'ready' | 'denied' | 'pending' | 'protocol' | 'network' | 'terminal' | 'background';
  initial?: string;
  longEmail?: boolean;
  preference?: ThemePreference;
}) {
  const navigation = useApplicationNavigation(initial);
  const [session] = useState(() => {
    const tokens = new MemoryAccessToken();
    const identity = crypto.randomUUID();
    let reads = 0;
    const authority = new AuthorityCache(() =>
      outcome === 'background' && ++reads > 1
        ? Promise.resolve({ status: 'network' })
        : outcome === 'pending'
          ? new Promise(() => {})
          : Promise.resolve(
              outcome === 'protocol'
                ? { status: 'protocol' }
                : {
                    status: 'principal',
                    principal: {
                      userId: identity,
                      email: longEmail
                        ? `${'reader'.repeat(10)}@example.test`
                        : 'reader@example.test',
                      roles: ['ROLE_SUPER_ADMIN'],
                      permissions:
                        outcome === 'denied'
                          ? []
                          : [
                              'VIEW_DASHBOARD',
                              'READ_OWN_SESSIONS',
                              'READ_USERS',
                              'INVITE_USERS',
                              'ASSIGN_SUPER_ADMIN',
                              'MANAGE_USER_INVITATIONS'
                            ]
                    }
                  }
            )
    );
    const intent = new IntendedRoute(authority, () => applicationRoutes, window.location.origin);
    const coordinator = new SessionCoordinator({
      tokens,
      authority,
      clearIntent: () => intent.clear(),
      session: {
        refresh: () => {
          if (outcome === 'network' || outcome === 'terminal')
            return Promise.resolve(
              apiFailure(outcome === 'network' ? 'network' : 'authentication')
            );
          tokens.accept('catalog.access.signature', performance.now());
          return Promise.resolve({ ok: true, value: true, correlationId: null });
        },
        logout: () => Promise.resolve({ ok: true, value: true, correlationId: null }),
        invalidate: () => tokens.clear(),
        dispose: () => tokens.clear()
      },
      login: {
        submit: () => Promise.resolve(apiFailure('authentication')),
        dispose: () => tokens.clear()
      }
    });
    return {
      authority,
      intent,
      coordinator,
      passwordChange: new PasswordChangeService(
        new ApiClient({
          schemaVersion: 2,
          apiBasePath: '/api/v1'
        }),
        authority,
        coordinator.terminal
      ),
      invitationIssuance: new InvitationIssuanceService(
        new ApiClient({
          schemaVersion: 2,
          apiBasePath: '/api/v1'
        }),
        authority,
        { invalidated: () => {}, accessRejected: () => {}, authorityRejected: () => {} }
      ),
      invitations: new InvitationService(
        new ApiClient({
          schemaVersion: 2,
          apiBasePath: '/api/v1'
        }),
        authority,
        {
          invalidated: () => {},
          accessRejected: () => {},
          authorityRejected: () => {}
        }
      ),
      users: new UserDirectoryService(
        new ApiClient({
          schemaVersion: 2,
          apiBasePath: '/api/v1'
        })
      ),
      userAdministration: new UserAdministrationService(
        new ApiClient({ schemaVersion: 2, apiBasePath: '/api/v1' }),
        authority,
        () => {}
      ),
      sessions: new OwnedSessionsService(
        new ApiClient({
          schemaVersion: 2,
          apiBasePath: '/api/v1'
        })
      ),
      swagger: new SwaggerService(
        new ApiClient({
          schemaVersion: 2,
          apiBasePath: '/api/v1'
        }),
        authority,
        false,
        { accessRejected: () => {}, authorityChanged: () => {} },
        window.location.origin
      ),
      schemas: new PublicSchemaService(
        new ApiClient(
          { schemaVersion: 2, apiBasePath: '/api/v1' },
          { fetch: () => Promise.resolve(new Response('', { status: 503 })) }
        )
      )
    };
  });
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
  useEffect(() => {
    void session.coordinator.restore().then(async () => {
      await session.authority.load();
      if (outcome === 'background') session.authority.resume();
    });
    return () => {
      session.intent.dispose();
      session.coordinator.dispose();
      session.authority.dispose();
      session.schemas.clear();
    };
  }, [session, outcome]);
  return (
    <div className="catalog-preview catalog-frame-preview" data-bs-theme={appearance.effective}>
      <ApplicationView session={session} {...navigation} theme={theme} />
    </div>
  );
}
const meta = {
  title: 'Application/Frame',
  component: FrameExample,
  parameters: { layout: 'fullscreen', applicationFrame: true }
} satisfies Meta<typeof FrameExample>;
export default meta;
type Story = StoryObj<typeof meta>;
export const WideLight: Story = {};
export const WideDark: Story = { args: { preference: 'dark' }, parameters: { mode: 'dark' } };
export const NarrowDark: Story = {
  args: { preference: 'dark' },
  parameters: { mode: 'dark' },
  globals: { viewport: { value: 'narrow', isRotated: false } }
};
export const LongEmail: Story = { args: { longEmail: true } };
export const Forbidden: Story = { args: { outcome: 'denied' } };
export const RestoringAuthority: Story = { args: { outcome: 'pending' } };
export const MalformedAuthority: Story = { args: { outcome: 'protocol' } };
export const SessionNetworkFailure: Story = {
  args: { outcome: 'network' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(await canvas.findByText('Session connection unavailable')).toBeVisible();
    await canvasElement.ownerDocument.fonts.ready;
    const header = canvas.getByRole('banner');
    const brand = within(header).getByRole('link', { name: 'Fight Agent OS' });
    const appearance = within(header).getByRole('group', { name: 'Appearance' });
    // Observe the real public header, not its CSS implementation or the authenticated menu.
    if (canvasElement.clientWidth >= 768) {
      await expect(header.getBoundingClientRect().height).toBeLessThanOrEqual(64);
      await expect(brand.getBoundingClientRect().right).toBeLessThan(
        appearance.getBoundingClientRect().left
      );
    }
    for (const radio of within(appearance).getAllByRole('radio')) {
      const target = radio.closest('label')?.getBoundingClientRect();
      await expect(target?.width).toBeGreaterThanOrEqual(44);
      await expect(target?.height).toBeGreaterThanOrEqual(44);
    }
    await expect(canvas.queryByRole('button', { name: 'User Menu' })).not.toBeInTheDocument();
  }
};
export const SessionExpired: Story = { args: { outcome: 'terminal' } };
export const BackgroundCheckUnavailable: Story = {
  args: { outcome: 'background' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(await canvas.findByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible();
    await expect(await canvas.findByRole('button', { name: 'Retry access check' })).toBeVisible();
    await expect(canvas.getByText(/Current access could not be verified/)).toBeVisible();
  }
};
export const DashboardRouteReturn: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const view = canvasElement.ownerDocument.defaultView;
    if (!view) throw new Error('Missing browser window');
    await canvas.findByRole('heading', { name: 'Dashboard', level: 1 });
    await canvasElement.ownerDocument.fonts.ready;
    view.scrollTo(0, 0);
    const primary = async () => {
      const open = canvas.queryByRole('button', { name: 'Open navigation' });
      if (open) await userEvent.click(open);
      return within(await canvas.findByRole('navigation', { name: 'Primary' }));
    };
    await userEvent.click((await primary()).getByRole('link', { name: 'Account & Security' }));
    await canvas.findByRole('heading', { name: 'Account & Security', level: 1 });
    await expect(view.scrollY).toBe(0);
    await userEvent.click((await primary()).getByRole('link', { name: 'Dashboard' }));
    await canvas.findByRole('heading', { name: 'Dashboard', level: 1 });
    // Observe the browser's settled layout, not jsdom's geometry-free focus behavior.
    await new Promise<void>((resolve) => view.requestAnimationFrame(() => resolve()));
    await expect(canvas.getByRole('main', { name: 'Dashboard' })).toHaveFocus();
    await expect(view.scrollY).toBe(0);
    await expect(canvas.getByRole('banner', { name: 'Application utilities' })).toBeVisible();
  }
};
export const AccountSeam: Story = { args: { initial: '/account' } };
export const InvitationSeam: Story = { args: { initial: '/admin/invitations' } };

const harborFrame: Story = {
  parameters: { harbor: true },
  decorators: [
    (Story) => (
      <BrandProvider brand={harborBrand}>
        <Story />
      </BrandProvider>
    )
  ],
  play: async ({ canvasElement, args }) => {
    const canvas = within(canvasElement);
    const utilities = await canvas.findByRole('banner', { name: 'Application utilities' });
    await expect(within(utilities).getByRole('img', { name: 'Harbor sailboat' })).toBeVisible();
    await expect(canvasElement.ownerDocument.title).toMatch(/ — Harbor Desk$/);
    await expectHarborTheme(canvasElement, args.preference === 'dark');
  }
};
export const HarborAccountLight: Story = {
  ...harborFrame,
  args: { initial: '/account', preference: 'light' },
  globals: { viewport: { value: 'wide' } }
};
export const HarborAdminDarkNarrow: Story = {
  ...harborFrame,
  args: { initial: '/admin/invitations', preference: 'dark' },
  globals: { viewport: { value: 'narrow' } },
  parameters: { ...harborFrame.parameters, mode: 'dark' }
};
export const HarborAccountDark: Story = {
  ...harborFrame,
  args: { initial: '/account', preference: 'dark' },
  parameters: { ...harborFrame.parameters, mode: 'dark' }
};
export const HarborAdminLight: Story = {
  ...harborFrame,
  args: { initial: '/admin/invitations', preference: 'light' }
};
export const AccountMenuKeyboard: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const toggle = await canvas.findByRole('button', { name: 'User Menu' });
    const account = within(canvas.getByRole('banner', { name: 'Application utilities' }));
    toggle.focus();
    await userEvent.keyboard('{ArrowDown}');
    await expect(account.getByRole('link', { name: 'Account & Security' })).toHaveFocus();
    await userEvent.keyboard('{ArrowDown}');
    await expect(account.getByRole('link', { name: 'Active Sessions' })).toHaveFocus();
    await userEvent.keyboard('{Escape}');
    await expect(toggle).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await userEvent.click(canvas.getByRole('radio', { name: 'Dark' }));
    await expect(canvas.getByRole('radio', { name: 'Dark' })).toBeChecked();
    await userEvent.click(canvas.getByRole('radio', { name: 'System' }));
    await expect(canvas.getByRole('radio', { name: 'System' })).toBeChecked();
  }
};
export const AccountMenuLongEmail: Story = {
  args: { longEmail: true },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await userEvent.click(await canvas.findByRole('button', { name: 'User Menu' }));
    await expect(canvas.getByRole('group', { name: 'Appearance' })).toBeVisible();
  }
};
export const AccountMenuNarrowDark: Story = {
  ...AccountMenuLongEmail,
  args: { longEmail: true, preference: 'dark' },
  parameters: { mode: 'dark' },
  globals: { viewport: { value: 'narrow', isRotated: false } }
};
export const DrawerKeyboard: Story = {
  globals: { viewport: { value: 'narrow', isRotated: false } },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const open = await canvas.findByRole('button', { name: 'Open navigation' });
    await userEvent.click(open);
    const dialog = await canvas.findByRole('dialog', { name: 'Application navigation' });
    await expect(dialog).toBeVisible();
    await expect(canvas.queryByRole('button', { name: 'Open navigation' })).not.toBeInTheDocument();
    await userEvent.keyboard('{Escape}');
    await waitFor(async () => {
      await expect(canvas.getByRole('button', { name: 'Open navigation' })).toHaveFocus();
    });
  }
};
