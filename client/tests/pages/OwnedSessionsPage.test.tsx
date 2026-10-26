import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { apiFailure } from '@/api/ApiResult';
import OwnedSessionsPage from '@/pages/OwnedSessionsPage';

import {
  nearExpirySessionWire,
  sessionDependencies,
  sessionPage,
  sessionsResponse,
  sessionWire
} from '../sessionFixtures';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';

const caches: AuthorityCache[] = [];
afterEach(() => {
  for (const cache of caches.splice(0)) cache.dispose();
});
async function mount(
  fetch: (url: string, init: RequestInit) => Promise<Response> = () =>
    Promise.resolve(sessionsResponse(sessionPage()))
) {
  const d = sessionDependencies(fetch);
  caches.push(d.cache);
  await d.cache.load();
  render(<OwnedSessionsPage {...d} />);
  return d;
}

describe('OwnedSessionsPage', () => {
  it('R57-01 renders the complete list when a usable row has equal projected activity and deadlines', async () => {
    await mount(() =>
      Promise.resolve(sessionsResponse(sessionPage([nearExpirySessionWire(), sessionWire()])))
    );
    expect(await screen.findByRole('list', { name: 'Owned active sessions' })).toBeVisible();
    expect(screen.getAllByRole('listitem')).toHaveLength(2);
    expect(screen.getByRole('heading', { name: 'Unknown device · Unknown browser' })).toBeVisible();
    expect(screen.getByRole('status')).toHaveTextContent('2 active sessions');
    expect(screen.getAllByText('Current session — this request')).toHaveLength(1);
    expect(document.querySelectorAll('time[datetime="2026-10-25T12:00:00+00:00"]')).toHaveLength(3);
    expect(screen.queryByText('Sessions unavailable')).not.toBeInTheDocument();
  });

  it('keeps a denied read as an explicit failure without an authority-triggered remount/replay', async () => {
    const d = sessionDependencies(() => Promise.resolve(new Response('', { status: 403 })));
    caches.push(d.cache);
    await d.cache.load();
    const signal = vi.spyOn(d.cache, 'signal');
    const load = vi.fn(() => Promise.resolve(apiFailure('authorization')));
    render(<OwnedSessionsPage cache={d.cache} service={{ load }} />);
    expect(await screen.findByText('Access forbidden')).toBeVisible();
    expect(signal).not.toHaveBeenCalled();
    expect(load).toHaveBeenCalledTimes(1);
  });
  it('presents semantic current/ordinary/remembered cards and truthful UTC activity, never presence', async () => {
    const first = sessionWire({
      session_id: crypto.randomUUID(),
      created_at: '2026-10-25T09:00:00+00:00'
    });
    await mount(() =>
      Promise.resolve(
        sessionsResponse(
          sessionPage([
            first,
            sessionWire({
              current: false,
              remembered: true,
              browser_family: 'Unknown',
              device_class: 'unknown'
            })
          ])
        )
      )
    );
    expect(await screen.findByText('Current session — this request')).toBeVisible();
    expect(screen.getByText('Ordinary session')).toBeVisible();
    expect(screen.getByText('Remembered session')).toBeVisible();
    expect(screen.getByRole('heading', { name: 'Unknown device · Unknown browser' })).toBeVisible();
    expect(screen.getAllByText('Last refresh activity')).toHaveLength(2);
    expect(screen.getAllByText('Absolute deadline')).toHaveLength(2);
    expect(screen.getByRole('status')).toHaveTextContent('2 active sessions');
    expect(screen.getByText(/not page views or online presence/)).toBeVisible();
    expect(document.querySelectorAll('time')).toHaveLength(8);
    expect(screen.getByRole('button', { name: 'Previous page' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Next page' })).toBeDisabled();
  });

  it('announces loading, shows empty and permits explicit keyboard reload with a focus handoff', async () => {
    let finish: ((response: Response) => void) | undefined;
    const fetch = vi.fn<(url: string, init: RequestInit) => Promise<Response>>(
      () =>
        new Promise<Response>((resolve) => {
          finish = resolve;
        })
    );
    await mount(fetch);
    expect(screen.getByText('Loading sessions')).toBeVisible();
    await act(async () => {
      finish?.(sessionsResponse(sessionPage([])));
      await Promise.resolve();
    });
    expect(await screen.findByText('No sessions on this page')).toBeVisible();
    const reload = screen.getByRole('button', { name: 'Reload sessions' });
    reload.focus();
    await userEvent.keyboard('{Enter}');
    expect(fetch).toHaveBeenCalledTimes(2);
    await act(async () => {
      finish?.(sessionsResponse(sessionPage([])));
      await Promise.resolve();
    });
    await waitFor(() =>
      expect(screen.getByRole('heading', { name: 'Active Sessions' })).toHaveFocus()
    );
  });

  it('pages explicitly and fences late success when authority changes or the page unmounts', async () => {
    let late: ((response: Response) => void) | undefined;
    const fetch = vi.fn((url: string) =>
      url.includes('page=2&')
        ? new Promise<Response>((resolve) => {
            late = resolve;
          })
        : Promise.resolve(
            sessionsResponse(
              sessionPage(
                Array.from({ length: 25 }, (_, index) =>
                  sessionWire({
                    current: index === 0,
                    created_at: `2026-10-25T09:${String(index).padStart(2, '0')}:00+00:00`
                  })
                ),
                { total_records: 26, total_pages: 2 }
              )
            )
          )
    );
    const d = await mount(fetch);
    await screen.findByText('Current session — this request');
    await userEvent.click(screen.getByRole('button', { name: 'Next page' }));
    expect(screen.queryByText('Current session — this request')).not.toBeInTheDocument();
    await act(() => d.cache.signal('logout'));
    await act(async () => {
      late?.(
        sessionsResponse(
          sessionPage([sessionWire()], { page: 2, total_records: 26, total_pages: 2 })
        )
      );
      await Promise.resolve();
    });
    expect(screen.getByText('Session view cleared')).toBeVisible();
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Retry session list' })).toBeDisabled();
  });

  it('offers a safe explicit retry without exposing server prose or automatic replay', async () => {
    const d = sessionDependencies(() =>
      Promise.resolve(new Response('private-marker', { status: 500 }))
    );
    caches.push(d.cache);
    await d.cache.load();
    const load = vi.fn(() => Promise.resolve(apiFailure('network')));
    render(<OwnedSessionsPage cache={d.cache} service={{ load }} />);
    expect(await screen.findByText('Sessions unavailable')).toBeVisible();
    expect(screen.queryByText('private-marker')).not.toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Retry session list' }));
    await screen.findByText('Sessions unavailable');
    expect(load).toHaveBeenCalledTimes(2);
  });
});
