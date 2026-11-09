import { useEffect, useRef, useState } from 'react';
import BootstrapButton from 'react-bootstrap/Button';
import Modal from 'react-bootstrap/Modal';

import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { TextField } from '@/components/forms/TextField';
import { Notice } from '@/components/Notice';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import { useAuthority } from '@/features/authority/useAuthority';
import {
  canonicalInvitationEmail,
  elevatedInvitationAccess
} from '@/features/invitations/InvitationIssuanceService';
import { matchApplicationRoute } from '@/routes/applicationRoutes';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type {
  InvitationIssuanceOutcome,
  InvitationIssuanceService
} from '@/features/invitations/InvitationIssuanceService';
import type { KeyboardEvent } from 'react';

/** Presents a separate, explicitly confirmed pending Super Admin invitation */
export default function InviteSuperAdminPage({
  service,
  cache
}: {
  service: Pick<InvitationIssuanceService, 'submitElevated'>;
  cache: AuthorityCache;
}) {
  const authority = useAuthority(cache);
  const allowed = evaluateAccess(authority, elevatedInvitationAccess) === 'allowed';
  const [email, setEmail] = useState('');
  const [invalid, setInvalid] = useState(false);
  const [confirmation, setConfirmation] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [outcome, setOutcome] = useState<InvitationIssuanceOutcome | null>(null);
  const generation = useRef(0);
  const busy = useRef(false);
  const input = useRef<HTMLInputElement>(null);
  const review = useRef<HTMLButtonElement>(null);
  const cancel = useRef<HTMLButtonElement>(null);
  const heading = useRef<HTMLHeadingElement>(null);
  const returnFocus = useRef(false);
  const focusEmail = useRef(false);
  useEffect(() => {
    let isCurrentAuthentication = cache.captureAuthenticationContext();
    let scope = cache.captureScope();
    const retire = (preserveUnresolved: boolean) => {
      const submitted = busy.current;
      ++generation.current;
      busy.current = false;
      setEmail('');
      setInvalid(false);
      setConfirmation(null);
      setPending(false);
      // Freshness cannot resolve submitted work. Keep only its safe investigation
      // disposition, never the target form or a late response from the retired scope.
      setOutcome((previous) =>
        preserveUnresolved && (submitted || previous?.kind === 'unconfirmed')
          ? {
              kind: 'unconfirmed',
              userId: previous?.kind === 'unconfirmed' ? previous.userId : null
            }
          : null
      );
    };
    const retireScope = () => retire(isCurrentAuthentication());
    scope.signal.addEventListener('abort', retireScope, { once: true });
    const unsubscribe = cache.subscribe(() => {
      const state = cache.getSnapshot();
      if (
        !isCurrentAuthentication() ||
        (state.status === 'authenticated' &&
          evaluateAccess(state, elevatedInvitationAccess) !== 'allowed')
      ) {
        retire(false);
        isCurrentAuthentication = cache.captureAuthenticationContext();
      }
      const current = cache.captureScope();
      if (current !== scope) {
        scope.signal.removeEventListener('abort', retireScope);
        scope = current;
        scope.signal.addEventListener('abort', retireScope, { once: true });
      }
    });
    const invalidate = () => {
      ++generation.current;
    };
    return () => {
      invalidate();
      unsubscribe();
      scope.signal.removeEventListener('abort', retireScope);
    };
  }, [cache]);
  useEffect(() => {
    if (outcome) heading.current?.focus({ preventScroll: true });
    else if (focusEmail.current) {
      focusEmail.current = false;
      input.current?.focus();
    } else if (!confirmation && returnFocus.current) {
      returnFocus.current = false;
      review.current?.focus({ preventScroll: true });
    }
  }, [outcome, confirmation]);
  const permitted = (path: string) => {
    const route = matchApplicationRoute(path);
    return route && evaluateAccess(authority, route.requirements) === 'allowed';
  };
  const complete = outcome?.kind === 'accepted' || outcome?.kind === 'unconfirmed';
  const target = complete ? outcome.userId : null;
  const statusPath = target ? `/app/admin/invitations/${target}` : null;
  const reason = outcome?.kind === 'rejected' ? outcome.reason : null;
  const close = () => {
    if (busy.current) return;
    returnFocus.current = true;
    setConfirmation(null);
  };
  return (
    <section className="invite-user-page" aria-labelledby="elevated-invite-heading">
      <h1 id="elevated-invite-heading">Invite Super Admin</h1>
      <div className="invite-user-panel">
        <Notice title="High-risk installation-wide authority" tone="warning" announce="off">
          <p>
            This separate journey invites a new pending identity with the managed Super Admin role
            (ROLE_SUPER_ADMIN). It grants installation-wide administrative authority, not ordinary
            User access. Your authenticated actor and the target are recorded in durable audit.
          </p>
          <p>
            The recipient must activate the seven-day invitation and choose their own password. This
            does not promote an existing active user or bypass bootstrap restrictions.
          </p>
        </Notice>
        {!allowed && outcome?.kind !== 'unconfirmed' ? (
          <ContentState
            kind="error"
            title="Elevated invitation unavailable"
            message="Both current INVITE_USERS and ASSIGN_SUPER_ADMIN permissions are required. Role names alone grant nothing."
          />
        ) : (
          <>
            {outcome && outcome.kind !== 'cancelled' && (
              <div>
                <h2 ref={heading} tabIndex={-1}>
                  {outcome.kind === 'accepted'
                    ? 'Super Admin invitation accepted'
                    : outcome.kind === 'unconfirmed'
                      ? 'Elevated outcome unconfirmed — stop and investigate'
                      : reason === 'conflict'
                        ? 'Invitation conflicts with current state'
                        : reason === 'rate-limited'
                          ? 'Invitation temporarily limited'
                          : reason === 'validation' ||
                              reason === 'bad-request' ||
                              reason === 'rule-violation'
                            ? 'Check the email and confirmation'
                            : 'Elevated invitation not accepted'}
                </h2>
                <Notice
                  title="Elevated invitation update"
                  tone={outcome.kind === 'accepted' ? 'success' : 'warning'}
                >
                  {outcome.kind === 'accepted' ? (
                    <p>
                      The server confirmed the pending invitation, managed Super Admin assignment
                      and required audit steps. Delivery may still be pending or failed. This is not
                      proof of inbox receipt or activation.
                    </p>
                  ) : outcome.kind === 'unconfirmed' ? (
                    <p>
                      Invitation, membership and confirmation audit commit independently. An
                      invitation, sent mail or membership may remain without confirmed audit. Do not
                      reinvite, compensate or replay assignment. Ask a trusted operator to
                      investigate identity, membership, audit and delivery under the invitation
                      runbook. A safe status read cannot establish audit completion. Retry or resend
                      is not assignment repair. Leaving cannot undo submitted work.
                    </p>
                  ) : reason === 'conflict' ? (
                    <p>
                      The canonical address may already exist or be reserved, or managed policy may
                      have changed. Inspect current state with an authorized operator. No partial
                      success is claimed.
                    </p>
                  ) : reason === 'rate-limited' ? (
                    <p>Wait before another deliberate attempt. Nothing is retried automatically.</p>
                  ) : reason === 'validation' ||
                    reason === 'bad-request' ||
                    reason === 'rule-violation' ? (
                    <p>Enter one valid email address and review a fresh explicit confirmation.</p>
                  ) : (
                    <p>
                      Current authority could not admit this request. Recheck access before trying
                      again.
                    </p>
                  )}
                </Notice>
                {statusPath && permitted(statusPath) && (
                  <p>
                    <a href={statusPath}>Read invitation status</a> — safe observation only, not
                    audit confirmation or elevation repair.
                  </p>
                )}
              </div>
            )}
            {allowed && !complete && (
              <form
                noValidate
                aria-busy={pending}
                onSubmit={(event) => {
                  event.preventDefault();
                  if (
                    busy.current ||
                    evaluateAccess(cache.getSnapshot(), elevatedInvitationAccess) !== 'allowed'
                  )
                    return;
                  const canonical = canonicalInvitationEmail(email);
                  if (!canonical) {
                    setInvalid(true);
                    input.current?.focus();
                    return;
                  }
                  setEmail(canonical);
                  setOutcome(null);
                  setConfirmation(canonical);
                }}
              >
                <TextField
                  ref={input}
                  label="Email"
                  name="email"
                  type="text"
                  inputMode="email"
                  autoComplete="email"
                  autoCapitalize="none"
                  spellCheck={false}
                  required
                  maxLength={254}
                  readOnly={pending || confirmation !== null}
                  value={email}
                  description="Enter one email address (at most 254 characters). Paste is welcome. Spaces are trimmed and letters lowercased before confirmation."
                  errors={
                    invalid ? ['Enter one valid email address (at most 254 characters).'] : []
                  }
                  onChange={(event) => {
                    setEmail(event.target.value);
                    setInvalid(false);
                    setConfirmation(null);
                    setOutcome(null);
                  }}
                />
                {invalid && <p role="alert">Check the highlighted email field.</p>}
                {pending && (
                  <p role="status">
                    Submitting elevated invitation. Leaving cannot undo server work.
                  </p>
                )}
                <div className="invite-user-actions">
                  <BootstrapButton
                    ref={review}
                    type="submit"
                    variant=""
                    className="catalog-button"
                    disabled={pending}
                  >
                    Review Super Admin invitation
                  </BootstrapButton>
                  <Button
                    disabled={pending}
                    onClick={() => {
                      setEmail('');
                      setInvalid(false);
                      setOutcome(null);
                      setConfirmation(null);
                      input.current?.focus();
                    }}
                  >
                    Clear email
                  </Button>
                </div>
              </form>
            )}
            {outcome?.kind === 'accepted' && (
              <Button
                onClick={() => {
                  ++generation.current;
                  focusEmail.current = true;
                  setOutcome(null);
                }}
              >
                Start a separate invitation
              </Button>
            )}
          </>
        )}
      </div>
      <p>
        {permitted('/app/admin/users') ? (
          <a href="/app/admin/users">Read current Users</a>
        ) : permitted('/app/dashboard') ? (
          <a href="/app/dashboard">Back to Dashboard</a>
        ) : (
          <a href="/app/account">Back to Account &amp; Security</a>
        )}
      </p>
      {allowed && confirmation !== null && (
        <Modal
          className="invitation-confirm"
          show
          animation={false}
          centered
          onHide={close}
          onEscapeKeyDown={(event: Event) => event.stopPropagation()}
          onShow={() => cancel.current?.focus()}
          onKeyDown={(event: KeyboardEvent<HTMLElement>) => {
            if (event.key !== 'Tab') return;
            const controls =
              event.currentTarget.querySelectorAll<HTMLButtonElement>('button:not(:disabled)');
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
              event.preventDefault();
              last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
              event.preventDefault();
              first?.focus();
            }
          }}
          aria-labelledby="elevated-confirm-title"
          aria-describedby="elevated-confirm-description"
        >
          <Modal.Header>
            <Modal.Title id="elevated-confirm-title">
              Confirm installation-wide Super Admin invitation
            </Modal.Title>
          </Modal.Header>
          <Modal.Body id="elevated-confirm-description">
            <p>
              Canonical target: <strong>{confirmation}</strong>
            </p>
            <p>
              Managed role: <strong>ROLE_SUPER_ADMIN</strong>. This grants installation-wide
              administrative authority after invite-only activation. The invitation lasts seven
              days; you do not choose a password.
            </p>
            <p>
              Your authenticated actor, target and elevated action are audited. Steps commit
              independently; later failure may retain an invitation, delivered mail or membership
              without complete audit. No automatic retry or browser assignment repair is available.
            </p>
            {pending && (
              <p role="status">
                Waiting for the server. This cannot be undone by closing the page.
              </p>
            )}
          </Modal.Body>
          <Modal.Footer>
            <BootstrapButton
              ref={cancel}
              variant=""
              className="catalog-button"
              disabled={pending}
              onClick={close}
            >
              Cancel
            </BootstrapButton>
            <Button
              busy={pending}
              busyLabel="Submitting elevated invitation"
              onClick={() => {
                if (
                  busy.current ||
                  confirmation !== canonicalInvitationEmail(email) ||
                  evaluateAccess(cache.getSnapshot(), elevatedInvitationAccess) !== 'allowed'
                )
                  return;
                const scope = cache.captureScope();
                const request = ++generation.current;
                busy.current = true;
                setPending(true);
                void service
                  .submitElevated(email, { email: confirmation, confirmed: true })
                  .then((result) => {
                    if (request !== generation.current || !scope.isCurrent()) return;
                    busy.current = false;
                    setPending(false);
                    setConfirmation(null);
                    if (result.kind === 'cancelled') return;
                    setOutcome(result);
                    if (result.kind === 'accepted' || result.kind === 'unconfirmed') setEmail('');
                  });
              }}
            >
              Confirm Super Admin invitation
            </Button>
          </Modal.Footer>
        </Modal>
      )}
    </section>
  );
}
