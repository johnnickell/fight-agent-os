import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { ApplicationView } from '@/routes/ApplicationJourney';

import { authenticatedCache, principal } from '../authorityFixtures';

import type { ApplicationSession } from '@/features/auth/useApplicationSession';
import type { UsersPage } from '@fight/react/account';
import type { ComponentProps, ReactNode } from 'react';

vi.mock('@/layouts/ApplicationFrame', () => ({
  ApplicationFrame: ({ children }: { children: ReactNode }) => <main>{children}</main>
}));
vi.mock(import('@fight/react/account'), async (original) => ({
  ...(await original()),
  UsersPage: ({
    onOpenDetail,
    onCloseDetail,
    onOutcome,
    notice
  }: ComponentProps<typeof UsersPage>) => (
    <>
      <button type="button" onClick={() => onOpenDetail?.(targetId)}>
        Open detail
      </button>
      <button type="button" onClick={onCloseDetail}>
        Close detail
      </button>
      <button
        type="button"
        onClick={() => onOutcome?.({ message: 'Change confirmed', error: false })}
      >
        Report outcome
      </button>
      {notice && <p>{notice.message}</p>}
    </>
  )
}));

const targetId = crypto.randomUUID();
const listPath = '/admin/users';
const detailPath = `${listPath}/${targetId}`;
const query = '?page=2&per_page=25&sort=Created&order=asc';

afterEach(() => window.history.replaceState(null, '', '/'));

describe('ApplicationView Users host callbacks', () => {
  it('keeps list options in both directions and discards a detail notice after leaving', async () => {
    const authority = authenticatedCache({ ...principal, permissions: ['READ_USERS'] });
    await authority.load();
    const credential = { status: 'authenticated' } as const;
    // Only the ports read by this host are supplied. No account session or transport is created.
    const session = {
      authority,
      coordinator: { subscribe: () => () => {}, getSnapshot: () => credential },
      passwordChange: { completed: () => false, clearCompletion: vi.fn() },
      intent: { consume: () => undefined }
    } as unknown as ApplicationSession;
    const navigate = vi.fn();
    const view = (pathname: string) => (
      <ApplicationView session={session} pathname={pathname} navigate={navigate} />
    );
    window.history.replaceState(null, '', listPath + query);
    const { rerender, unmount } = render(view(listPath));
    try {
      fireEvent.click(await screen.findByRole('button', { name: 'Open detail' }));
      expect(navigate).toHaveBeenLastCalledWith(detailPath + query);
      window.history.replaceState(null, '', detailPath + query);
      rerender(view(detailPath));
      fireEvent.click(screen.getByRole('button', { name: 'Report outcome' }));
      expect(screen.getByText('Change confirmed')).toBeVisible();
      fireEvent.click(screen.getByRole('button', { name: 'Close detail' }));
      expect(navigate).toHaveBeenLastCalledWith(listPath + query);
      window.history.replaceState(null, '', listPath + query);
      await act(() => {
        rerender(view(listPath));
        return Promise.resolve();
      });
      expect(screen.queryByText('Change confirmed')).not.toBeInTheDocument();
      rerender(view(detailPath));
      expect(screen.queryByText('Change confirmed')).not.toBeInTheDocument();
    } finally {
      unmount();
      authority.dispose();
    }
  });
});
