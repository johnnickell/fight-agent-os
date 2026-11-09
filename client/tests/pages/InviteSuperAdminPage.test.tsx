import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import InviteSuperAdminPage from '@/pages/InviteSuperAdminPage';

import { deferred } from '../apiFixtures';
import { authenticatedCache, principal } from '../authorityFixtures';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type { InvitationIssuanceOutcome } from '@/features/invitations/InvitationIssuanceService';

const caches: AuthorityCache[] = [];
afterEach(() => caches.splice(0).forEach((cache) => cache.dispose()));
async function setup(
  permissions = ['INVITE_USERS', 'ASSIGN_SUPER_ADMIN', 'READ_USERS'],
  result: InvitationIssuanceOutcome = { kind: 'accepted', userId: crypto.randomUUID() }
) {
  const cache = authenticatedCache({ ...principal, permissions });
  caches.push(cache);
  await cache.load();
  const submitElevated = vi.fn(() => Promise.resolve(result));
  const rendered = render(<InviteSuperAdminPage service={{ submitElevated }} cache={cache} />);
  return { ...rendered, cache, submitElevated };
}
async function review(email = ' Person@EXAMPLE.test ') {
  const user = userEvent.setup();
  await user.click(screen.getByLabelText('Email (required)'));
  await user.paste(email);
  await user.keyboard('{Enter}');
  return screen.findByRole('dialog');
}
async function confirm() {
  await userEvent.click(screen.getByRole('button', { name: 'Confirm Super Admin invitation' }));
}

describe('InviteSuperAdminPage', () => {
  it('requires explicit canonical-target confirmation, starts Cancel-focused, traps Tab and returns focus on Escape', async () => {
    const d = await setup();
    const dialog = await review();
    expect(d.submitElevated).not.toHaveBeenCalled();
    expect(within(dialog).getByText('person@example.test')).toBeVisible();
    expect(within(dialog).getByText(/ROLE_SUPER_ADMIN/)).toBeVisible();
    expect(within(dialog).getByText(/installation-wide administrative authority/)).toBeVisible();
    const cancel = within(dialog).getByRole('button', { name: 'Cancel' });
    await waitFor(() => expect(cancel).toHaveFocus());
    await userEvent.tab({ shift: true });
    expect(
      within(dialog).getByRole('button', { name: 'Confirm Super Admin invitation' })
    ).toHaveFocus();
    await userEvent.tab();
    expect(cancel).toHaveFocus();
    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(screen.getByRole('button', { name: 'Review Super Admin invitation' })).toHaveFocus();
    expect(d.submitElevated).not.toHaveBeenCalled();
  });
  it('submits only after confirmation, clears terminal input and focuses safe success', async () => {
    const d = await setup();
    await review();
    await confirm();
    expect(d.submitElevated).toHaveBeenCalledExactlyOnceWith('person@example.test', {
      email: 'person@example.test',
      confirmed: true
    });
    expect(
      await screen.findByRole('heading', { name: 'Super Admin invitation accepted' })
    ).toHaveFocus();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Read invitation status' })).toBeVisible();
    expect(screen.getByText(/not proof of inbox receipt/)).toBeVisible();
  });
  it.each([[], ['INVITE_USERS'], ['ASSIGN_SUPER_ADMIN'], ['ROLE_SUPER_ADMIN']])(
    'refuses misleading role or incomplete exact permission combination %j',
    async (...permissions) => {
      const d = await setup(permissions);
      expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
      expect(screen.getByText(/Both current INVITE_USERS/)).toBeVisible();
      expect(d.submitElevated).not.toHaveBeenCalled();
    }
  );
  it('invalidates an open confirmation on edits and requires fresh review of the changed target', async () => {
    const d = await setup();
    await review();
    fireEvent.change(screen.getByLabelText('Email (required)'), {
      target: { value: 'other@example.test' }
    });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(d.submitElevated).not.toHaveBeenCalled();
    await userEvent.click(screen.getByRole('button', { name: 'Review Super Admin invitation' }));
    expect(within(await screen.findByRole('dialog')).getByText('other@example.test')).toBeVisible();
    await confirm();
    expect(d.submitElevated).toHaveBeenCalledWith('other@example.test', {
      email: 'other@example.test',
      confirmed: true
    });
  });
  it('removes confirmation on authority loss and never dispatches with stale authority', async () => {
    const d = await setup();
    await review();
    await act(() => d.cache.signal('refresh-started'));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(d.submitElevated).not.toHaveBeenCalled();
  });
  it('suppresses duplicate confirmation and ignores late completion after unmount', async () => {
    const d = await setup();
    const work = deferred<InvitationIssuanceOutcome>();
    d.submitElevated.mockImplementation(() => work.promise);
    await review();
    await confirm();
    await userEvent.click(screen.getByRole('button', { name: 'Submitting elevated invitation' }));
    expect(d.submitElevated).toHaveBeenCalledOnce();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
    d.unmount();
    await act(async () => {
      work.resolve({ kind: 'accepted', userId: crypto.randomUUID() });
      await work.promise;
    });
    expect(screen.queryByText('Super Admin invitation accepted')).not.toBeInTheDocument();
  });
  it.each([
    ['INVITE_USERS', 'ASSIGN_SUPER_ADMIN'],
    ['INVITE_USERS', 'ASSIGN_SUPER_ADMIN', 'MANAGE_USER_INVITATIONS']
  ])('grants no reading from issuance or management %j', async (...permissions) => {
    await setup(permissions);
    await review();
    await confirm();
    await screen.findByRole('heading', { name: 'Super Admin invitation accepted' });
    expect(screen.queryByRole('link', { name: 'Read invitation status' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Read current Users' })).not.toBeInTheDocument();
  });
  it('stops unconfirmed work with operator investigation, no reinvite or assignment-recovery control', async () => {
    await setup(undefined, { kind: 'unconfirmed', userId: crypto.randomUUID() });
    await review();
    await confirm();
    expect(await screen.findByRole('heading', { name: /stop and investigate/ })).toHaveFocus();
    expect(screen.getByText(/Retry or resend is not assignment repair/)).toBeVisible();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Read invitation status' })).toBeVisible();
  });
  it.each(['conflict', 'rate-limited', 'authorization', 'validation'] as const)(
    'keeps safe rejected %s input but never replays',
    async (reason) => {
      const d = await setup(undefined, { kind: 'rejected', reason });
      await review();
      await confirm();
      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
      expect(screen.getByLabelText('Email (required)')).toHaveValue('person@example.test');
      expect(d.submitElevated).toHaveBeenCalledOnce();
      expect(screen.getByRole('button', { name: 'Review Super Admin invitation' })).toBeVisible();
    }
  );
  it('focuses invalid email and renders Unicode as text without persistence or diagnostic payloads', async () => {
    const d = await setup();
    await userEvent.click(screen.getByRole('button', { name: 'Review Super Admin invitation' }));
    expect(screen.getByLabelText('Email (required)')).toHaveFocus();
    expect(screen.getByRole('alert')).toBeVisible();
    await review('équipe@example.test');
    expect(
      within(await screen.findByRole('dialog')).getByText('équipe@example.test')
    ).toBeVisible();
    expect(d.submitElevated).not.toHaveBeenCalled();
    expect(window.location.hash).toBe('');
    expect(window.history.state).toBeNull();
  });
});
