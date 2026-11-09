import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import InviteUserPage from '@/pages/InviteUserPage';

import { deferred } from '../apiFixtures';
import { authenticatedCache, principal } from '../authorityFixtures';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type { InvitationIssuanceOutcome as OrdinaryInvitationOutcome } from '@/features/invitations/InvitationIssuanceService';

const caches: AuthorityCache[] = [];
afterEach(() => {
  caches.splice(0).forEach((cache) => cache.dispose());
});
async function setup(
  permissions = ['INVITE_USERS', 'READ_USERS'],
  outcome: OrdinaryInvitationOutcome = { kind: 'accepted', userId: crypto.randomUUID() }
) {
  const cache = authenticatedCache({ ...principal, permissions });
  caches.push(cache);
  await cache.load();
  const submit = vi.fn<(value: string) => Promise<OrdinaryInvitationOutcome>>(() =>
    Promise.resolve(outcome)
  );
  const rendered = render(<InviteUserPage service={{ submit }} cache={cache} />);
  return { ...rendered, cache, submit };
}
const emailInput = () => screen.getByLabelText('Email (required)');
async function submitEmail(value = ' Person@EXAMPLE.test ') {
  const user = userEvent.setup();
  await user.click(emailInput());
  await user.paste(value);
  await user.keyboard('{Enter}');
}

describe('InviteUserPage', () => {
  it('labels one paste-friendly email field, submits by keyboard, clears success input and offers only read-permitted status', async () => {
    const d = await setup();
    expect(emailInput()).toHaveAttribute('autocomplete', 'email');
    expect(emailInput()).toHaveAttribute('maxlength', '254');
    expect(screen.getAllByRole('textbox')).toHaveLength(1);
    await submitEmail();
    expect(d.submit).toHaveBeenCalledWith('person@example.test');
    expect(await screen.findByRole('heading', { name: 'Invitation accepted' })).toHaveFocus();
    expect(screen.getByText(/not proof of inbox/)).toBeVisible();
    expect(screen.getByRole('link', { name: 'View invitation status' })).toHaveAttribute(
      'href',
      expect.stringMatching(/^\/app\/admin\/invitations\//)
    );
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Invite another user' }));
    expect(emailInput()).toHaveValue('');
  });
  it('submits a supported address literal by paste and keyboard for server admission', async () => {
    const d = await setup();
    await submitEmail(' Person@[IPv6:2001:db8::1] ');
    expect(d.submit).toHaveBeenCalledExactlyOnceWith('person@[ipv6:2001:db8::1]');
    expect(await screen.findByRole('heading', { name: 'Invitation accepted' })).toHaveFocus();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
  it.each([
    ['INVITE_USERS'],
    ['INVITE_USERS', 'MANAGE_USER_INVITATIONS'],
    ['INVITE_USERS', 'VIEW_DASHBOARD']
  ])(
    'never infers status reading from invitation or management $permissions',
    async (...permissions) => {
      await setup(permissions);
      await submitEmail();
      expect(await screen.findByRole('heading', { name: 'Invitation accepted' })).toBeVisible();
      expect(
        screen.queryByRole('link', { name: 'View invitation status' })
      ).not.toBeInTheDocument();
      expect(screen.queryByRole('link', { name: 'Back to Users' })).not.toBeInTheDocument();
      expect(
        screen.getByRole('link', {
          name: permissions.includes('VIEW_DASHBOARD')
            ? 'Back to Dashboard'
            : 'Back to Account & Security'
        })
      ).toBeVisible();
    }
  );
  it('announces and focuses invalid email without dispatch, then clears explicitly', async () => {
    const d = await setup();
    await submitEmail('not-email');
    expect(emailInput()).toHaveFocus();
    expect(emailInput()).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('alert')).toHaveTextContent('Check the highlighted email');
    expect(d.submit).not.toHaveBeenCalled();
    await userEvent.click(screen.getByRole('button', { name: 'Clear email' }));
    expect(emailInput()).toHaveValue('');
    expect(emailInput()).toHaveFocus();
  });
  it.each([
    'conflict',
    'rate-limited',
    'validation',
    'bad-request',
    'rule-violation',
    'authorization'
  ] as const)('retains controlled correction/retry for %s without raw messages', async (reason) => {
    await setup(undefined, { kind: 'rejected', reason });
    await submitEmail();
    await waitFor(() => expect(screen.getByRole('heading', { level: 2 })).toHaveFocus());
    expect(emailInput()).toHaveValue('person@example.test');
    fireEvent.change(emailInput(), { target: { value: 'different@example.test' } });
    expect(screen.queryByRole('heading', { level: 2 })).not.toBeInTheDocument();
  });
  it('shows partial uncertainty without retry controls, success claim or invented target', async () => {
    await setup(undefined, { kind: 'unconfirmed', userId: null });
    await submitEmail();
    expect(
      await screen.findByRole('heading', { name: 'Invitation outcome unconfirmed' })
    ).toHaveFocus();
    expect(screen.getByText(/Some work may have committed/)).toBeVisible();
    expect(screen.queryByRole('link', { name: 'View invitation status' })).not.toBeInTheDocument();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Invite another user' })).not.toBeInTheDocument();
  });
  it('suppresses duplicate submit while pending and makes email read-only', async () => {
    const pending = deferred<OrdinaryInvitationOutcome>();
    const d = await setup();
    d.submit.mockReturnValue(pending.promise);
    await submitEmail('pending@example.test');
    const form = emailInput().closest('form')!;
    fireEvent.submit(form);
    fireEvent.submit(form);
    expect(d.submit).toHaveBeenCalledOnce();
    expect(emailInput()).toHaveAttribute('readonly');
    expect(screen.getByRole('button', { name: 'Submitting invitation' })).toHaveAttribute(
      'aria-busy',
      'true'
    );
    expect(screen.getByRole('button', { name: 'Clear email' })).toBeDisabled();
    await act(async () => {
      pending.resolve({ kind: 'accepted', userId: crypto.randomUUID() });
      await pending.promise;
    });
  });
  it.each(['accepted', 'rejected'] as const)(
    'retires old %s after authority replacement without overwriting a newer invitation',
    async (kind) => {
      const old = deferred<OrdinaryInvitationOutcome>();
      const d = await setup();
      d.submit.mockReturnValueOnce(old.promise);
      await submitEmail('old@example.test');
      await act(async () => {
        await d.cache.signal('authority-changed');
        await d.cache.load();
      });
      expect(emailInput()).toHaveValue('');
      d.submit.mockResolvedValueOnce({ kind: 'accepted', userId: crypto.randomUUID() });
      await submitEmail('new@example.test');
      expect(await screen.findByRole('heading', { name: 'Invitation accepted' })).toBeVisible();
      await act(async () => {
        old.resolve(
          kind === 'accepted' ? { kind, userId: crypto.randomUUID() } : { kind, reason: 'conflict' }
        );
        await old.promise;
      });
      expect(screen.queryByRole('heading', { name: /conflicts/ })).not.toBeInTheDocument();
    }
  );
  it('clears volatile fields and ignores late success on logout and unmount', async () => {
    const pending = deferred<OrdinaryInvitationOutcome>();
    const d = await setup();
    d.submit.mockReturnValue(pending.promise);
    await submitEmail('private@example.test');
    await act(async () => {
      await d.cache.signal('logout');
    });
    expect(screen.queryByDisplayValue('private@example.test')).not.toBeInTheDocument();
    d.unmount();
    await act(async () => {
      pending.resolve({ kind: 'accepted', userId: crypto.randomUUID() });
      await pending.promise;
    });
    expect(screen.queryByRole('link', { name: 'View invitation status' })).not.toBeInTheDocument();
  });
  it('clears private form input on repeated same-permission authority generations', async () => {
    const d = await setup();
    for (const email of ['first@example.test', 'second@example.test']) {
      fireEvent.change(emailInput(), { target: { value: email } });
      await act(async () => {
        await d.cache.signal('authority-changed');
        await d.cache.load();
      });
      expect(emailInput()).toHaveValue('');
    }
  });
  it('has no form or dispatch for unknown authority despite a Super Admin role label', async () => {
    const d = await setup([]);
    expect(screen.getByText(/Current INVITE_USERS permission/)).toBeVisible();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(d.submit).not.toHaveBeenCalled();
  });
});
