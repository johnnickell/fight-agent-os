import { useEffect, useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { ApiClient } from '@/api/ApiClient';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { OwnedSessionsService } from '@/features/sessions/OwnedSessionsService';
import OwnedSessionsPage from '@/pages/OwnedSessionsPage';

import { principal } from '../tests/authorityFixtures';
import { sessionPage, sessionsResponse, sessionWire } from '../tests/sessionFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario = 'confirmation' | 'pending' | 'success' | 'conflict' | 'error';
function Example({
  scenario = 'confirmation',
  dark = false
}: {
  scenario?: Scenario;
  dark?: boolean;
}) {
  useEffect(() => {
    // The real app themes its document. Mirror that catalog-only boundary for body-portaled overlays.
    const root = document.documentElement;
    const prior = root.getAttribute('data-bs-theme');
    root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    return () => {
      if (prior === null) root.removeAttribute('data-bs-theme');
      else root.setAttribute('data-bs-theme', prior);
    };
  }, [dark]);
  const [d] = useState(() => {
    const cache = new AuthorityCache(() =>
      Promise.resolve({
        status: 'principal',
        principal: { ...principal, permissions: ['READ_OWN_SESSIONS', 'DELETE_OWN_SESSIONS'] }
      })
    );
    const current = sessionWire();
    const target = sessionWire({
      current: false,
      remembered: true,
      created_at: '2026-10-25T10:30:00+00:00',
      device_class: 'mobile',
      browser_family: 'Safari'
    });
    let committed = false;
    const service = new OwnedSessionsService(
      new ApiClient(
        { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
        {
          accessTokens: { getAccessToken: () => 'synthetic.memory.only' },
          fetch: (_url, init) => {
            if (init.method === 'DELETE') {
              if (scenario === 'pending' || scenario === 'confirmation')
                return new Promise<Response>(() => {});
              if (scenario === 'success') {
                committed = true;
                return Promise.resolve(sessionsResponse(null));
              }
              return Promise.resolve(
                new Response(
                  JSON.stringify({
                    status: 'error',
                    message: scenario === 'conflict' ? 'Conflict.' : 'Internal server error.'
                  }),
                  {
                    status: scenario === 'conflict' ? 409 : 500,
                    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
                  }
                )
              );
            }
            return Promise.resolve(
              sessionsResponse(sessionPage(committed ? [current] : [current, target]))
            );
          }
        }
      ),
      {},
      cache
    );
    return { cache, service };
  });
  const [ready, setReady] = useState(false);
  useEffect(() => {
    void d.cache.load().then(() => setReady(true));
    return () => d.cache.dispose();
  }, [d]);
  return ready ? (
    <OwnedSessionsPage cache={d.cache} service={d.service} />
  ) : (
    <p>Preparing example</p>
  );
}
const meta = { title: 'Security/OwnedSessionRevocation', component: Example } satisfies Meta<
  typeof Example
>;
export default meta;
type Story = StoryObj<typeof meta>;
const confirm: NonNullable<Story['play']> = async ({ canvasElement, args }) => {
  const canvas = within(canvasElement);
  const trigger = await canvas.findByRole('button', { name: 'Revoke session' });
  await userEvent.click(trigger);
  const dialog = await within(document.body).findByRole('dialog', {
    name: 'Revoke this other session?'
  });
  await expect(within(dialog).getByRole('button', { name: 'Cancel' })).toHaveFocus();
  // The focus boundary must wrap backwards from its safe initial control to the final control.
  await userEvent.tab({ shift: true });
  await expect(within(dialog).getByRole('button', { name: 'Confirm revocation' })).toHaveFocus();
  if (args.scenario && args.scenario !== 'confirmation') {
    await userEvent.keyboard('{Enter}');
    if (args.scenario === 'pending') {
      await expect(
        within(dialog).getByRole('button', { name: 'Revoking session' })
      ).toHaveAttribute('aria-disabled', 'true');
    } else {
      await expect(
        canvas.findByText(
          args.scenario === 'success'
            ? /Session revoked\./
            : args.scenario === 'conflict'
              ? /That session is no longer available/
              : /Revocation was not confirmed/
        )
      ).resolves.toBeVisible();
    }
  }
};
export const Confirmation: Story = { play: confirm };
export const DarkConfirmation: Story = {
  args: { dark: true },
  parameters: { mode: 'dark' },
  play: confirm
};
export const Pending: Story = { args: { scenario: 'pending' }, play: confirm };
export const Success: Story = { args: { scenario: 'success' }, play: confirm };
export const Conflict: Story = { args: { scenario: 'conflict' }, play: confirm };
export const Failure: Story = { args: { scenario: 'error' }, play: confirm };
