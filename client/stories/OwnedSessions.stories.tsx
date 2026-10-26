import { useEffect, useState } from 'react';

import { expect, userEvent, within } from 'storybook/test';

import { Button } from '@/components/Button';
import OwnedSessionsPage from '@/pages/OwnedSessionsPage';

import {
  sessionDependencies,
  sessionPage,
  sessionsResponse,
  sessionWire
} from '../tests/sessionFixtures';

import type { Meta, StoryObj } from '@storybook/react-vite';

type Scenario = 'ready' | 'loading' | 'error' | 'empty' | 'unknown' | 'pagination';
function Example({ scenario = 'ready' }: { scenario?: Scenario }) {
  const [d] = useState(() =>
    sessionDependencies((url) => {
      if (scenario === 'loading') return new Promise<Response>(() => {});
      if (scenario === 'error') return Promise.resolve(new Response('', { status: 503 }));
      const rows =
        scenario === 'empty'
          ? []
          : scenario === 'unknown'
            ? [sessionWire({ browser_family: 'Unknown', device_class: 'unknown' })]
            : [
                sessionWire(),
                sessionWire({
                  current: false,
                  remembered: true,
                  device_class: 'mobile',
                  browser_family: 'Safari',
                  created_at: '2026-10-25T10:30:00+00:00'
                })
              ];
      if (scenario === 'pagination') {
        const second = url.includes('page=2&');
        return Promise.resolve(
          sessionsResponse(
            sessionPage(
              second
                ? [sessionWire({ current: false })]
                : Array.from({ length: 25 }, (_, index) =>
                    sessionWire({
                      current: index === 0,
                      created_at: `2026-10-25T09:${String(index).padStart(2, '0')}:00+00:00`
                    })
                  ),
              { page: second ? 2 : 1, total_records: 26, total_pages: 2 }
            )
          )
        );
      }
      return Promise.resolve(sessionsResponse(sessionPage(rows)));
    })
  );
  const [ready, setReady] = useState(false);
  useEffect(() => {
    void d.cache.load().then(() => setReady(true));
    return () => d.cache.dispose();
  }, [d]);
  return ready ? (
    <>
      <OwnedSessionsPage service={d.service} cache={d.cache} />
      <Button
        onClick={() => {
          void d.cache.signal('logout');
        }}
      >
        End catalog session
      </Button>
    </>
  ) : (
    <p>Preparing example</p>
  );
}
const meta = { title: 'Security/OwnedSessions', component: Example } satisfies Meta<typeof Example>;
export default meta;
type Story = StoryObj<typeof meta>;
export const CurrentAndRemembered: Story = {};
export const Dark: Story = { parameters: { mode: 'dark' } };
export const Loading: Story = { args: { scenario: 'loading' } };
export const Empty: Story = { args: { scenario: 'empty' } };
export const Failure: Story = { args: { scenario: 'error' } };
export const UnknownDevice: Story = { args: { scenario: 'unknown' } };
export const Pagination: Story = {
  args: { scenario: 'pagination' },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const next = await canvas.findByRole('button', { name: 'Next page' });
    await userEvent.click(next);
    await expect(canvas.findByText('Page 2 of 2. 26 active sessions.')).resolves.toBeVisible();
    await expect(canvas.getByRole('heading', { name: 'Active Sessions' })).toHaveFocus();
  }
};
export const SessionEnd: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await canvas.findByText('Current session — this request');
    await userEvent.click(canvas.getByRole('button', { name: 'End catalog session' }));
    await expect(canvas.findByText('Session view cleared')).resolves.toBeVisible();
    await expect(canvas.queryByRole('list')).not.toBeInTheDocument();
  }
};
