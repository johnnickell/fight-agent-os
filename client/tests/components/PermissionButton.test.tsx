import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { PermissionButton } from '@/components/PermissionButton';
import { AuthorityCache } from '@/features/authority/AuthorityCache';

import { deferred } from '../apiFixtures';
import { principal } from '../authorityFixtures';

import type { AuthorityScope, PrincipalLoadResult } from '@/features/authority/AuthorityCache';

describe('permission-dependent actions', () => {
  it('hides or natively disables denied actions despite the SuperAdmin role', async () => {
    const cache = new AuthorityCache(() =>
      Promise.resolve({ status: 'principal', principal: { ...principal, permissions: [] } })
    );
    await cache.load();
    const action = vi.fn();
    const { rerender, unmount } = render(
      <PermissionButton
        cache={cache}
        permissions={['EDIT_REPORTS']}
        denied="hide"
        onAction={action}
      >
        Edit
      </PermissionButton>
    );
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    rerender(
      <PermissionButton
        cache={cache}
        permissions={['EDIT_REPORTS']}
        denied="disable"
        onAction={action}
      >
        Edit
      </PermissionButton>
    );
    expect(screen.getByRole('button', { name: 'Edit' })).toBeDisabled();
    await userEvent.click(screen.getByRole('button'));
    expect(action).not.toHaveBeenCalled();
    unmount();
    cache.dispose();
  });

  it('allows exact grants and carries a data fence; activation rechecks expiry before its delayed render/timer', async () => {
    let now = 0;
    const pending = deferred<PrincipalLoadResult>();
    const loader = vi
      .fn()
      .mockResolvedValueOnce({ status: 'principal', principal })
      .mockReturnValueOnce(pending.promise);
    const cache = new AuthorityCache(loader, () => now);
    await cache.load();
    const action = vi.fn<(scope: AuthorityScope) => void>();
    const { unmount } = render(
      <PermissionButton
        cache={cache}
        permissions={['EDIT_REPORTS']}
        denied="disable"
        onAction={action}
      >
        Edit
      </PermissionButton>
    );
    const button = screen.getByRole('button');
    await userEvent.click(button);
    expect(action).toHaveBeenCalledOnce();
    expect(action.mock.calls[0]?.[0].isCurrent()).toBe(true);
    now = 60_000;
    fireEvent.click(button);
    await act(async () => {
      await Promise.resolve();
    });
    expect(action).toHaveBeenCalledOnce();
    expect(button).toBeDisabled();
    expect(action.mock.calls[0]?.[0].signal.aborted).toBe(true);
    await act(async () => {
      pending.resolve({ status: 'principal', principal });
      await cache.load();
    });
    expect(button).toBeEnabled();
    expect(action).toHaveBeenCalledOnce();
    unmount();
    cache.dispose();
  });
});
