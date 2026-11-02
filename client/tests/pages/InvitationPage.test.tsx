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
  const d = invitationDependencies(transport, permissions);
  caches.push(d.cache);
  await d.cache.load();
  const rendered = render(<InvitationPage userId={target.user_id} {...d} />);
  return { ...d, ...rendered, transport, target };
}
afterEach(() => {
  caches.splice(0).forEach((cache) => cache.dispose());
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
  it('registers only canonical UUID target paths without accepting query or authority fields', () => {
    const id = crypto.randomUUID();
    const route = matchApplicationRoute(`/app/admin/invitations/${id}`);
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
      expect(matchApplicationRoute(`/app/admin/invitations/${suffix}`)).toBeUndefined();
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
    const d = await mount(target, undefined, (_url, init) =>
      init.method === 'GET'
        ? ++reads === 1
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
