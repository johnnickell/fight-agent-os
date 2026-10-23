import { useEffect, useState } from 'react';

import { expect, userEvent, waitFor, within } from 'storybook/test';

import { ApiClient } from '@/api/ApiClient';
import { apiFailure } from '@/api/ApiResult';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { SessionCoordinator } from '@/features/auth/SessionCoordinator';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { SwaggerService } from '@/features/swagger/SwaggerService';
import { ThemePreferenceStore } from '@/features/theme/ThemePreference';
import { PublicSchemaService } from '@/features/validation/PublicSchemaService';
import { ApplicationView } from '@/routes/ApplicationJourney';
import { applicationRoutes } from '@/routes/applicationRoutes';
import { IntendedRoute } from '@/routes/IntendedRoute';

import type { Meta, StoryObj } from '@storybook/react-vite';

function FrameExample({
  outcome = 'ready',
  initial = '/app/dashboard',
  longEmail = false
}: {
  outcome?: 'ready' | 'denied' | 'pending' | 'protocol' | 'network' | 'terminal';
  initial?: string;
  longEmail?: boolean;
}) {
  const [pathname, setPathname] = useState(initial);
  const [session] = useState(() => {
    const tokens = new MemoryAccessToken();
    const authority = new AuthorityCache(() =>
      outcome === 'pending'
        ? new Promise(() => {})
        : Promise.resolve(
            outcome === 'protocol'
              ? { status: 'protocol' }
              : {
                  status: 'principal',
                  principal: {
                    userId: crypto.randomUUID(),
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
      swagger: new SwaggerService(
        new ApiClient({
          schemaVersion: 2,
          apiBasePath: '/api/v1',
          appDebug: false,
          swaggerAvailable: false
        }),
        authority,
        false,
        { accessRejected: () => {}, authorityChanged: () => {} },
        window.location.origin
      ),
      schemas: new PublicSchemaService(
        new ApiClient(
          { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
          { fetch: () => Promise.resolve(new Response('', { status: 503 })) }
        )
      )
    };
  });
  const [theme] = useState(
    () =>
      new ThemePreferenceStore({
        root: document.createElement('div'),
        storage: { getItem: () => null, setItem: () => {}, removeItem: () => {} },
        media: () => window.matchMedia('(prefers-color-scheme: dark)'),
        subscribeStorage: () => () => {}
      })
  );
  useEffect(() => {
    void session.coordinator.restore();
    return () => {
      session.intent.dispose();
      session.coordinator.dispose();
      session.authority.dispose();
      session.schemas.clear();
    };
  }, [session]);
  return (
    <ApplicationView session={session} pathname={pathname} navigate={setPathname} theme={theme} />
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
export const WideDark: Story = { parameters: { mode: 'dark' } };
export const NarrowDark: Story = {
  parameters: { mode: 'dark' },
  globals: { viewport: { value: 'narrow', isRotated: false } }
};
export const LongEmail: Story = { args: { longEmail: true } };
export const Forbidden: Story = { args: { outcome: 'denied' } };
export const RestoringAuthority: Story = { args: { outcome: 'pending' } };
export const MalformedAuthority: Story = { args: { outcome: 'protocol' } };
export const SessionNetworkFailure: Story = { args: { outcome: 'network' } };
export const SessionExpired: Story = { args: { outcome: 'terminal' } };
export const AccountSeam: Story = { args: { initial: '/app/account' } };
export const InvitationSeam: Story = { args: { initial: '/app/admin/invitations' } };
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
