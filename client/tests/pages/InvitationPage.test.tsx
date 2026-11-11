import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import InvitationPage from '@/pages/InvitationPage';
import { matchApplicationRoute } from '@/routes/applicationRoutes';

import { deferred } from '../apiFixtures';
import {
  invitationDependencies,
  invitationResponse,
  invitationTarget
} from '../invitationFixtures';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';

async function submitRecovery(operation: 'retry' | 'resend' | 'correct') {
  if (operation === 'retry') {
    fireEvent.click(await screen.findByRole('button', { name: 'Retry delivery' }));
  } else if (operation === 'resend') {
    fireEvent.click(await screen.findByRole('button', { name: 'Resend invitation' }));
    fireEvent.click(screen.getByRole('button', { name: 'Confirm resend' }));
  } else {
    fireEvent.click(await screen.findByRole('button', { name: 'Correct email' }));
    fireEvent.change(screen.getByLabelText('New email (required)'), {
      target: { value: 'new@example.test' }
    });
    fireEvent.click(screen.getByRole('button', { name: 'Review email correction' }));
    fireEvent.click(screen.getByRole('button', { name: 'Confirm correction' }));
  }
}

const caches: AuthorityCache[] = [];
async function mount(
  target = invitationTarget(),
  permissions = ['READ_USERS', 'MANAGE_USER_INVITATIONS'],
  fetch?: (url: string, init: RequestInit) => Promise<Response>
) {
  const transport = vi.fn(
    fetch ??
      ((_url, init) =>
        Promise.resolve(invitationResponse(init.method === 'GET' ? target : target.delivery)))
  );
  // Existing lifecycle assertions count invitation-endpoint requests, not the separate detail GET.
  const detailTransport = fetch ?? (() => Promise.resolve(invitationResponse(target)));
  const d = invitationDependencies(
    (url, init) => (url.includes('/users/') ? detailTransport(url, init) : transport(url, init)),
    permissions
  );
  caches.push(d.cache);
  await d.cache.load();
  const rendered = render(<InvitationPage userId={target.user_id} {...d} />);
  return { ...d, ...rendered, transport, target };
}
afterEach(() => {
  caches.splice(0).forEach((cache) => cache.dispose());
  vi.useRealTimers();
});

describe('Pending invitation', () => {
  it.each(
    [
      [],
      ['MANAGE_USER_INVITATIONS'],
      ['READ_USERS'],
      ['READ_USERS', 'MANAGE_USER_INVITATIONS']
    ].map((permissions) => ({ permissions }))
  )('keeps viewing and changes separate for $permissions', async ({ permissions }) => {
    const d = await mount(undefined, permissions);
    if (permissions.includes('READ_USERS')) {
      expect(await screen.findByText('pending@example.test')).toBeVisible();
      expect(Boolean(screen.queryByRole('button', { name: 'Resend invitation' }))).toBe(
        permissions.includes('MANAGE_USER_INVITATIONS')
      );
      if (!permissions.includes('MANAGE_USER_INVITATIONS'))
        expect(screen.getByText('Read-only invitation')).toBeVisible();
    } else {
      expect(d.transport).not.toHaveBeenCalled();
      expect(screen.queryByText('pending@example.test')).not.toBeInTheDocument();
    }
  });
  it.each(
    ([200, 500, 'network'] as const).flatMap((outcome) =>
      [false, true].map((readOnly) => ({ outcome, readOnly }))
    )
  )(
    'retains Reload keyboard focus through $outcome and recovery (read only: $readOnly)',
    async ({ outcome, readOnly }) => {
      const target = invitationTarget();
      const delayed = deferred<Response>();
      let reads = 0;
      const d = await mount(target, readOnly ? ['READ_USERS'] : undefined, (url) =>
        (url.includes('/invitations/') ? ++reads : reads) === 2
          ? delayed.promise
          : Promise.resolve(invitationResponse(target))
      );
      await screen.findByText(target.email);
      const reload = screen.getByRole('button', { name: 'Reload invitation' });
      reload.focus();
      await userEvent.keyboard('{Enter}');
      expect(screen.getByText('Reading invitation')).toBeVisible();
      // jsdom does not reproduce native blur on disable; require the focus-preserving
      // semantic busy contract as well as focus, and verify native behavior in Chrome.
      expect(reload).toBeEnabled();
      expect(reload).toHaveAttribute('aria-disabled', 'true');
      expect(reload).toHaveAttribute('aria-busy', 'true');
      expect(reload).toHaveFocus();
      await userEvent.keyboard('{Enter} ');
      fireEvent.click(reload);
      expect(d.transport).toHaveBeenCalledTimes(2);
      await act(async () => {
        if (outcome === 'network') delayed.reject(new TypeError('offline'));
        else delayed.resolve(invitationResponse(outcome === 200 ? target : null, outcome));
        await delayed.promise.catch(() => {});
      });
      if (outcome !== 200) {
        expect(await screen.findByText('Invitation unavailable')).toBeVisible();
        expect(screen.queryByText(target.email)).not.toBeInTheDocument();
      } else expect(await screen.findByText(target.email)).toBeVisible();
      expect(reload).toHaveFocus();
      expect(reload).toBeEnabled();
      expect(reload).toHaveAttribute('aria-busy', 'false');
      // Recover using the retained keyboard location, without an artificial refocus.
      await userEvent.keyboard('{Enter}');
      expect(await screen.findByText(target.email)).toBeVisible();
      expect(reload).toHaveFocus();
      expect(d.transport.mock.calls.map(([, init]) => init.method)).toEqual(['GET', 'GET', 'GET']);
    }
  );
  it('keeps Reload focus after its deadline and ignores the late read after explicit recovery', async () => {
    vi.useFakeTimers();
    const target = invitationTarget();
    const late = deferred<Response>();
    let reads = 0;
    const d = await mount(target, undefined, (url) =>
      (url.includes('/invitations/') ? ++reads : reads) === 2
        ? late.promise
        : Promise.resolve(invitationResponse(target))
    );
    await act(async () => {});
    const reload = screen.getByRole('button', { name: 'Reload invitation' });
    reload.focus();
    fireEvent.click(reload);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(8_000);
    });
    expect(screen.getByText('Invitation unavailable')).toBeVisible();
    expect(reload).toHaveFocus();
    expect(reload).toHaveAttribute('aria-busy', 'false');
    fireEvent.click(reload);
    await act(async () => {});
    expect(screen.getByText(target.email)).toBeVisible();
    await act(async () => {
      late.resolve(invitationResponse({ ...target, email: 'obsolete@example.test' }));
      await late.promise;
    });
    expect(screen.queryByText('obsolete@example.test')).not.toBeInTheDocument();
    expect(reload).toHaveFocus();
    expect(d.transport).toHaveBeenCalledTimes(3);
  });
  it('does not take focus on initial load or steal it after leaving a pending Reload', async () => {
    const target = invitationTarget();
    const initial = deferred<Response>();
    const delayed = deferred<Response>();
    let reads = 0;
    const d = await mount(target, undefined, (url) =>
      (url.includes('/invitations/') ? ++reads : reads) === 1 ? initial.promise : delayed.promise
    );
    const reload = screen.getByRole('button', { name: 'Reload invitation' });
    expect(reload).not.toHaveFocus();
    fireEvent.click(reload);
    expect(d.transport).toHaveBeenCalledOnce();
    await act(async () => {
      initial.resolve(invitationResponse(target));
      await initial.promise;
    });
    expect(reload).not.toHaveFocus();
    reload.focus();
    await userEvent.keyboard('{Enter}');
    await userEvent.tab({ shift: true });
    const back = screen.getByRole('link', { name: 'Back to Users' });
    expect(back).toHaveFocus();
    await act(async () => {
      delayed.resolve(invitationResponse(target));
      await delayed.promise;
    });
    await screen.findByText(target.email);
    expect(back).toHaveFocus();
    expect(d.transport).toHaveBeenCalledTimes(2);
  });
  it('registers only canonical UUID target paths without accepting query or authority fields', () => {
    const id = crypto.randomUUID();
    const route = matchApplicationRoute(`/admin/invitations/${id}`);
    expect(route).toMatchObject({ destination: 'invitation', targetUserId: id });
    expect(route?.requirements).toContainEqual({ kind: 'permissions', all: ['READ_USERS'] });
    expect(route?.intendedRoute).toBeUndefined();
    for (const suffix of [
      '../users',
      `${id}?actor=other`,
      `${id}#secret`,
      'invalid',
      `${id}/retry`
    ])
      expect(matchApplicationRoute(`/admin/invitations/${suffix}`)).toBeUndefined();
  });
  it('retries only failed live work once and refetches instead of fabricating delivery', async () => {
    const target = invitationTarget();
    const pending = deferred<Response>();
    const d = await mount(target, undefined, (_url, init) =>
      init.method === 'GET' ? Promise.resolve(invitationResponse(target)) : pending.promise
    );
    const retry = await screen.findByRole('button', { name: 'Retry delivery' });
    fireEvent.click(retry);
    fireEvent.click(retry);
    expect(d.transport).toHaveBeenCalledTimes(2);
    expect(screen.getByRole('button', { name: 'Retrying delivery' })).toHaveAttribute(
      'aria-busy',
      'true'
    );
    await act(async () => {
      pending.resolve(invitationResponse(target.delivery));
      await pending.promise;
    });
    expect(await screen.findByText('pending@example.test')).toBeVisible();
    expect(d.transport).toHaveBeenCalledTimes(3);
    expect(screen.getByText('Retryable delivery failure')).toBeVisible();
    expect(screen.getByText(/request completed/)).toHaveTextContent('not proof of inbox receipt');
  });
  it.each(
    (['retry', 'resend', 'correct'] as const).flatMap((operation) =>
      [200, 409, 429, 500].flatMap((status) =>
        [false, true].flatMap((sameTarget) =>
          [false, true].map((readFirst) => ({ operation, status, sameTarget, readFirst }))
        )
      )
    )
  )(
    'handles remounted $operation outcome $status (same target: $sameTarget, read first: $readFirst)',
    async ({ operation, status, sameTarget, readFirst }) => {
      const original = invitationTarget();
      const selected = sameTarget ? original : invitationTarget({ email: 'second@example.test' });
      const fresh = { ...selected, email: 'authoritative@example.test', state: 'active' };
      const mutation = deferred<Response>();
      const newRead = deferred<Response>();
      let reads = 0;
      const d = await mount(original, undefined, (url, init) => {
        if (init.method !== 'GET') return mutation.promise;
        if (url.includes('/invitations/')) ++reads;
        return reads === 1
          ? Promise.resolve(invitationResponse(original))
          : reads === 2
            ? newRead.promise
            : Promise.resolve(invitationResponse(fresh));
      });
      await submitRecovery(operation);
      expect(d.transport).toHaveBeenCalledTimes(2);
      d.unmount();
      render(<InvitationPage userId={selected.user_id} cache={d.cache} service={d.service} />);
      expect(screen.getByText('Reading invitation')).toBeVisible();
      expect(d.transport).toHaveBeenCalledTimes(3);
      const finishRead = async () => {
        await act(async () => {
          newRead.resolve(invitationResponse(selected));
          await newRead.promise;
        });
      };
      if (readFirst) {
        await finishRead();
        expect(screen.getByText(selected.email)).toBeVisible();
      }
      await act(async () => {
        mutation.resolve(invitationResponse(status === 200 ? original.delivery : null, status));
        await mutation.promise;
      });
      if (!readFirst) await finishRead();
      expect(screen.queryByText('Reading invitation')).not.toBeInTheDocument();
      if (!sameTarget) {
        // A completed operation on A does not retire or refresh B's authoritative data.
        expect(screen.getByText(selected.email)).toBeVisible();
        expect(screen.queryByText('Invitation status needs reloading')).not.toBeInTheDocument();
        expect(d.transport).toHaveBeenCalledTimes(3);
        expect(d.transport.mock.calls[2]?.[0]).toBe(`/api/v1/invitations/${selected.user_id}`);
        return;
      }
      expect(screen.queryByText(selected.email)).not.toBeInTheDocument();
      expect(screen.getByText('Invitation status needs reloading')).toBeVisible();
      expect(screen.queryByText(/request completed/)).not.toBeInTheDocument();
      expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      const reload = screen.getByRole('button', { name: 'Reload invitation' });
      expect(reload).toBeEnabled();
      expect(d.transport).toHaveBeenCalledTimes(3);
      await userEvent.click(reload);
      expect(await screen.findByText(fresh.email)).toBeVisible();
      expect(screen.getByText('Active')).toBeVisible();
      expect(screen.queryByRole('button', { name: 'Retry delivery' })).not.toBeInTheDocument();
      expect(d.transport.mock.calls.map(([, init]) => init.method)).toEqual([
        'GET',
        operation === 'correct' ? 'PATCH' : 'POST',
        'GET',
        'GET'
      ]);
      expect(d.transport.mock.calls[2]?.[0]).toBe(`/api/v1/invitations/${selected.user_id}`);
      expect(d.transport.mock.calls[3]?.[0]).toBe(`/api/v1/invitations/${selected.user_id}`);
    }
  );
  it.each(
    (['retry', 'resend', 'correct'] as const).flatMap((operation) =>
      [200, 409, 429, 500].map((status) => ({ operation, status }))
    )
  )(
    'reads current status when the old $operation completes before remount ($status)',
    async ({ operation, status }) => {
      const original = invitationTarget();
      const fresh = { ...original, email: 'authoritative@example.test', state: 'active' };
      const mutation = deferred<Response>();
      let reads = 0;
      const d = await mount(original, undefined, (url, init) =>
        init.method !== 'GET'
          ? mutation.promise
          : Promise.resolve(
              invitationResponse(
                (url.includes('/invitations/') ? ++reads : reads) === 1 ? original : fresh
              )
            )
      );
      await submitRecovery(operation);
      d.unmount();
      await act(async () => {
        mutation.resolve(invitationResponse(status === 200 ? original.delivery : null, status));
        await mutation.promise;
      });
      render(<InvitationPage userId={original.user_id} cache={d.cache} service={d.service} />);
      expect(await screen.findByText(fresh.email)).toBeVisible();
      expect(screen.getByText('Active')).toBeVisible();
      expect(screen.queryByText('Invitation status needs reloading')).not.toBeInTheDocument();
      expect(screen.queryByText(/request completed/)).not.toBeInTheDocument();
      expect(d.transport.mock.calls.map(([, init]) => init.method)).toEqual([
        'GET',
        operation === 'correct' ? 'PATCH' : 'POST',
        'GET'
      ]);
      expect(d.transport.mock.calls[2]?.[0]).toBe(`/api/v1/invitations/${original.user_id}`);
    }
  );
  it('retires a recovered ready read if an uncertain timed-out correction later completes', async () => {
    vi.useFakeTimers();
    const target = invitationTarget();
    const mutation = deferred<Response>();
    const d = await mount(target, undefined, (_url, init) =>
      init.method === 'GET' ? Promise.resolve(invitationResponse(target)) : mutation.promise
    );
    await act(async () => {});
    d.unmount();
    const operation = d.service.change(target.user_id, 'correct', 'new@example.test');
    await act(async () => {
      await vi.advanceTimersByTimeAsync(8_000);
      expect(await operation).toMatchObject({ ok: false, error: { kind: 'network' } });
    });
    render(<InvitationPage userId={target.user_id} cache={d.cache} service={d.service} />);
    await act(async () => {});
    expect(screen.getByText(target.email)).toBeVisible();
    await act(async () => {
      mutation.resolve(invitationResponse(target.delivery));
      await mutation.promise;
    });
    expect(screen.queryByText(target.email)).not.toBeInTheDocument();
    expect(screen.getByText('Invitation status needs reloading')).toBeVisible();
    expect(screen.getByRole('button', { name: 'Reload invitation' })).toBeEnabled();
    expect(d.transport.mock.calls.map(([, init]) => init.method)).toEqual(['GET', 'PATCH', 'GET']);
    vi.useRealTimers();
  });
  it.each(['editing', 'resend', 'correct'] as const)(
    'discards stale $0 interaction on relevant completion, handles repeated completions without reads',
    async (interaction) => {
      const target = invitationTarget();
      const mutation = deferred<Response>();
      const d = await mount(target, undefined, (_url, init) =>
        init.method === 'GET' ? Promise.resolve(invitationResponse(target)) : mutation.promise
      );
      // An operation begun on a previous page survives until after the current interaction opens.
      d.unmount();
      const operation = d.service.change(target.user_id, 'retry');
      render(<InvitationPage userId={target.user_id} cache={d.cache} service={d.service} />);
      await screen.findByText(target.email);
      if (interaction === 'resend') {
        await userEvent.click(screen.getByRole('button', { name: 'Resend invitation' }));
      } else {
        await userEvent.click(screen.getByRole('button', { name: 'Correct email' }));
        await userEvent.type(
          screen.getByLabelText('New email (required)'),
          'volatile@example.test'
        );
        if (interaction === 'correct')
          await userEvent.click(screen.getByRole('button', { name: 'Review email correction' }));
      }
      await act(async () => {
        mutation.resolve(invitationResponse(target.delivery));
        await operation;
      });
      expect(screen.getByText('Invitation status needs reloading')).toBeVisible();
      expect(screen.queryByText(target.email)).not.toBeInTheDocument();
      expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      expect(screen.getByRole('heading', { level: 1 })).toHaveFocus();
      await act(async () => {
        await d.service.change(target.user_id, 'resend');
        await d.service.change(target.user_id, 'correct', 'other@example.test');
      });
      expect(screen.getByRole('button', { name: 'Reload invitation' })).toBeEnabled();
      expect(d.transport.mock.calls.filter(([, init]) => init.method === 'GET')).toHaveLength(2);
      expect(d.transport.mock.calls.filter(([, init]) => init.method !== 'GET')).toHaveLength(3);
      await userEvent.click(screen.getByRole('button', { name: 'Reload invitation' }));
      await screen.findByText(target.email);
      await userEvent.click(screen.getByRole('button', { name: 'Correct email' }));
      expect(screen.getByLabelText('New email (required)')).toHaveValue('');
      expect(d.transport).toHaveBeenCalledTimes(6);
    }
  );
  it('keeps explicit recovery usable after in-place authority retirement during a mutation', async () => {
    const target = invitationTarget();
    const mutation = deferred<Response>();
    const d = await mount(target, undefined, (_url, init) =>
      init.method === 'GET' ? Promise.resolve(invitationResponse(target)) : mutation.promise
    );
    await submitRecovery('correct');
    await act(() => d.cache.signal('authority-changed'));
    await act(() => d.cache.load());
    await screen.findByText(target.email);
    await act(async () => {
      mutation.resolve(invitationResponse(target.delivery));
      await mutation.promise;
    });
    expect(screen.getByText('Invitation status needs reloading')).toBeVisible();
    expect(screen.queryByText(/request completed/)).not.toBeInTheDocument();
    const reload = screen.getByRole('button', { name: 'Reload invitation' });
    expect(reload).toBeEnabled();
    await userEvent.click(reload);
    expect(await screen.findByText(target.email)).toBeVisible();
    expect(d.transport.mock.calls.map(([, init]) => init.method)).toEqual([
      'GET',
      'PATCH',
      'GET',
      'GET'
    ]);
  });
  it('notifies a current read-only consumer even if the submitting authority was retired', async () => {
    const target = invitationTarget();
    const mutation = deferred<Response>();
    const permissions = ['READ_USERS', 'MANAGE_USER_INVITATIONS'];
    const d = await mount(target, permissions, (_url, init) =>
      init.method === 'GET' ? Promise.resolve(invitationResponse(target)) : mutation.promise
    );
    await submitRecovery('correct');
    d.unmount();
    permissions.pop();
    await act(() => d.cache.signal('authority-changed'));
    await d.cache.load();
    render(<InvitationPage userId={target.user_id} cache={d.cache} service={d.service} />);
    await screen.findByText(target.email);
    expect(screen.getByText('Read-only invitation')).toBeVisible();
    await act(async () => {
      mutation.resolve(invitationResponse(target.delivery));
      await mutation.promise;
    });
    expect(screen.queryByText(target.email)).not.toBeInTheDocument();
    expect(screen.getByText('Invitation status needs reloading')).toBeVisible();
    expect(screen.getByRole('button', { name: 'Reload invitation' })).toBeEnabled();
    expect(screen.queryByRole('button', { name: 'Correct email' })).not.toBeInTheDocument();
    expect(d.transport).toHaveBeenCalledTimes(3);
  });
  it.each(['retry', 'resend', 'correct'] as const)(
    'cannot restore retired or unmounted presentation on late %s completion',
    async (operation) => {
      const target = invitationTarget();
      const mutation = deferred<Response>();
      const d = await mount(target, undefined, (_url, init) =>
        init.method === 'GET' ? Promise.resolve(invitationResponse(target)) : mutation.promise
      );
      await submitRecovery(operation);
      d.unmount();
      const current = render(
        <InvitationPage userId={target.user_id} cache={d.cache} service={d.service} />
      );
      await screen.findByText(target.email);
      await act(() => d.cache.signal('logout'));
      await act(async () => {
        mutation.resolve(invitationResponse(target.delivery));
        await mutation.promise;
      });
      expect(screen.queryByText(target.email)).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Reload invitation' })).not.toBeInTheDocument();
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      expect(d.transport).toHaveBeenCalledTimes(3);
      current.unmount();
      expect(document.body).not.toHaveTextContent('request completed');
    }
  );
  it.each(
    (['retry', 'resend', 'correct'] as const).flatMap((operation) =>
      [200, 409, 429, 500].map((status) => ({ operation, status }))
    )
  )(
    'refetches exactly once for the mounted $operation outcome $status',
    async ({ operation, status }) => {
      const original = invitationTarget();
      const fresh = { ...original, email: 'authoritative@example.test', state: 'active' };
      let reads = 0;
      const d = await mount(original, undefined, (url, init) =>
        Promise.resolve(
          init.method === 'GET'
            ? invitationResponse(
                (url.includes('/invitations/') ? ++reads : reads) === 1 ? original : fresh
              )
            : invitationResponse(status === 200 ? original.delivery : null, status)
        )
      );
      await submitRecovery(operation);
      expect(await screen.findByText(fresh.email)).toBeVisible();
      expect(screen.queryByText(original.email)).not.toBeInTheDocument();
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
      expect(screen.getByRole('heading', { level: 1 })).toHaveFocus();
      expect(d.transport.mock.calls.map(([, init]) => init.method)).toEqual([
        'GET',
        operation === 'correct' ? 'PATCH' : 'POST',
        'GET'
      ]);
    }
  );
  it.each(['pending', 'claimed', 'delivered', 'permanent_failure', 'expired', 'invalidated'])(
    'does not offer retry for %s',
    async (status) => {
      const target = invitationTarget();
      target.delivery.status = status;
      await mount(target);
      await screen.findByText('pending@example.test');
      expect(screen.queryByRole('button', { name: 'Retry delivery' })).not.toBeInTheDocument();
    }
  );
  it('gives expiry priority without rewriting stored delivery state', async () => {
    const target = invitationTarget();
    target.delivery.expires_at = '2020-01-01T00:00:00+00:00';
    await mount(target);
    await screen.findByText('Invitation deadline passed');
    expect(screen.getByText('Retryable delivery failure')).toBeVisible();
    expect(screen.queryByRole('button', { name: 'Retry delivery' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Resend invitation' })).toBeVisible();
  });
  it.each(['active', 'disabled', 'deleted'])(
    'does not offer pending changes for %s',
    async (state) => {
      await mount(invitationTarget({ state }));
      await screen.findByText('Invitation recovery unavailable');
      expect(screen.queryByRole('button', { name: 'Correct email' })).not.toBeInTheDocument();
    }
  );
  it('names resend consequences, starts on Cancel, supports Escape and restores the trigger', async () => {
    const d = await mount();
    const trigger = await screen.findByRole('button', { name: 'Resend invitation' });
    await userEvent.click(trigger);
    const dialog = await screen.findByRole('dialog', { name: 'Confirm replacement invitation' });
    await waitFor(() =>
      expect(within(dialog).getByRole('button', { name: 'Cancel' })).toHaveFocus()
    );
    expect(dialog).toHaveTextContent('old link becomes invalid');
    expect(dialog).toHaveTextContent('seven-day');
    await userEvent.tab();
    expect(within(dialog).getByRole('button', { name: 'Confirm resend' })).toHaveFocus();
    await userEvent.tab();
    expect(within(dialog).getByRole('button', { name: 'Cancel' })).toHaveFocus();
    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(trigger).toHaveFocus();
    expect(d.transport).toHaveBeenCalledOnce();
  });
  it('keeps dialog Escape from triggering underlying frame or window shortcuts', async () => {
    await mount();
    await userEvent.click(await screen.findByRole('button', { name: 'Resend invitation' }));
    await screen.findByRole('dialog', { name: 'Confirm replacement invitation' });
    const backgroundEscape = vi.fn();
    window.addEventListener('keydown', backgroundEscape);
    try {
      await userEvent.keyboard('{Escape}');
      expect(backgroundEscape).not.toHaveBeenCalled();
    } finally {
      window.removeEventListener('keydown', backgroundEscape);
    }
  });

  it('clears cancelled correction and restores focus to the surviving correction action', async () => {
    await mount();
    const trigger = await screen.findByRole('button', { name: 'Correct email' });
    await userEvent.click(trigger);
    await userEvent.type(screen.getByLabelText('New email (required)'), 'new@example.test');
    await userEvent.click(screen.getByRole('button', { name: 'Review email correction' }));
    const dialog = await screen.findByRole('dialog', { name: 'Confirm email correction' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));
    await waitFor(() => expect(trigger).toHaveFocus());
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('clears inline correction and returns keyboard focus to its surviving trigger', async () => {
    const d = await mount();
    const trigger = await screen.findByRole('button', { name: 'Correct email' });
    await userEvent.click(trigger);
    await userEvent.type(screen.getByLabelText('New email (required)'), 'volatile@example.test');
    await userEvent.click(screen.getByRole('button', { name: 'Cancel correction' }));
    expect(trigger).toHaveFocus();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    await userEvent.click(trigger);
    expect(screen.getByLabelText('New email (required)')).toHaveValue('');
    expect(d.transport).toHaveBeenCalledOnce();
  });

  it('confirms old/new canonical correction, clears volatile input, and renders only the refetched email', async () => {
    const target = invitationTarget();
    let corrected = false;
    const d = await mount(target, undefined, (_url, init) => {
      if (init.method === 'PATCH') {
        corrected = true;
        return Promise.resolve(invitationResponse(target.delivery));
      }
      return Promise.resolve(
        invitationResponse(
          corrected ? { ...target, email: 'authoritative@example.test', state: 'active' } : target
        )
      );
    });
    await userEvent.click(await screen.findByRole('button', { name: 'Correct email' }));
    const input = screen.getByLabelText('New email (required)');
    await userEvent.type(input, 'NEW@EXAMPLE.test');
    await userEvent.click(screen.getByRole('button', { name: 'Review email correction' }));
    const dialog = await screen.findByRole('dialog', { name: 'Confirm email correction' });
    expect(dialog).toHaveTextContent('pending@example.test');
    expect(dialog).toHaveTextContent('new@example.test');
    expect(dialog).toHaveTextContent('atomically');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Confirm correction' }));
    expect(await screen.findByText('authoritative@example.test')).toBeVisible();
    expect(screen.queryByText('new@example.test')).not.toBeInTheDocument();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(d.transport.mock.calls.find(([, init]) => init.method === 'PATCH')?.[1].body).toBe(
      '{"email":"new@example.test"}'
    );
  });
  it('permits quoted canonical addresses for server qualification instead of imposing native email restrictions', async () => {
    await mount();
    await userEvent.click(await screen.findByRole('button', { name: 'Correct email' }));
    await userEvent.type(screen.getByLabelText('New email (required)'), '"a@b"@example.test');
    await userEvent.click(screen.getByRole('button', { name: 'Review email correction' }));
    expect(
      await screen.findByRole('dialog', { name: 'Confirm email correction' })
    ).toHaveTextContent('"a@b"@example.test');
  });

  it('rejects unchanged correction with associated error and focuses the input', async () => {
    const d = await mount();
    await userEvent.click(await screen.findByRole('button', { name: 'Correct email' }));
    const input = screen.getByLabelText('New email (required)');
    await userEvent.type(input, 'PENDING@example.test');
    await userEvent.click(screen.getByRole('button', { name: 'Review email correction' }));
    expect(input).toHaveFocus();
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAccessibleDescription(/different valid email/);
    expect(d.transport).toHaveBeenCalledOnce();
  });
  it('refetches on safe conflict and cannot restore old identity after terminal invalidation', async () => {
    const target = invitationTarget();
    const late = deferred<Response>();
    let reads = 0;
    const d = await mount(target, undefined, (url, init) =>
      init.method === 'GET'
        ? (url.includes('/invitations/') ? ++reads : reads) === 1
          ? Promise.resolve(invitationResponse(target))
          : late.promise
        : Promise.resolve(invitationResponse(null, 409))
    );
    await userEvent.click(await screen.findByRole('button', { name: 'Retry delivery' }));
    await screen.findByText(/no longer eligible/);
    expect(screen.queryByText(target.email)).not.toBeInTheDocument();
    await act(() => d.cache.signal('logout'));
    await act(async () => {
      late.resolve(invitationResponse(target));
      await late.promise;
    });
    expect(screen.queryByText(target.email)).not.toBeInTheDocument();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
  it('keeps long Unicode text escaped, private markers and correction outside persistence/history/console', async () => {
    const storage = vi.spyOn(Storage.prototype, 'setItem');
    const history = vi.spyOn(window.history, 'pushState');
    const log = vi.spyOn(console, 'log');
    const error = vi.spyOn(console, 'error');
    const email = `${'équipe'.repeat(25)}@example.test`;
    const d = await mount(invitationTarget({ email }));
    await screen.findByText(email);
    await userEvent.click(screen.getByRole('button', { name: 'Correct email' }));
    await userEvent.type(
      screen.getByLabelText('New email (required)'),
      'volatile-form-marker@example.test'
    );
    d.unmount();
    expect(storage).not.toHaveBeenCalled();
    expect(history).not.toHaveBeenCalled();
    expect(log).not.toHaveBeenCalled();
    expect(error).not.toHaveBeenCalled();
    expect(document.body).not.toHaveTextContent('volatile-form-marker');
    expect(window.location.href).not.toContain(email);
    storage.mockRestore();
    history.mockRestore();
    log.mockRestore();
    error.mockRestore();
  });
});
