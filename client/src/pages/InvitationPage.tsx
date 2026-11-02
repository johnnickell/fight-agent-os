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
  invitationChange,
  invitationRead,
  isUserId
} from '@/features/invitations/InvitationService';

import type { ApiFailureKind, ApiResult } from '@/api/ApiResult';
import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type {
  InvitationOperation,
  InvitationService,
  InvitationTarget
} from '@/features/invitations/InvitationService';
import type { KeyboardEvent } from 'react';

type View =
  | { kind: 'loading' }
  | { kind: 'invalidated' }
  | { kind: 'error'; reason: ApiFailureKind }
  | { kind: 'ready'; target: InvitationTarget };
const deliveryLabels = {
  pending: 'Awaiting delivery attempt',
  claimed: 'Provider attempt in progress',
  retry_pending: 'Retryable delivery failure',
  delivered: 'Accepted by provider',
  permanent_failure: 'Delivery failed permanently',
  expired: 'Delivery expired',
  invalidated: 'Delivery invalidated'
};
const lifecycleLabels = {
  pending_activation: 'Pending activation',
  active: 'Active',
  disabled: 'Disabled',
  deleted: 'Deleted'
};
const operationLabels = {
  retry: 'Retry delivery',
  resend: 'Resend invitation',
  correct: 'Correct email'
};
const validEmail = (value: string) =>
  value.length <= 254 && /^(?:"(?:[^"\\]|\\.)+"|[^\s@"\\]+)@[^\s@]+\.[^\s@]+$/.test(value);

/** Owns a volatile canonical target and explicit recovery, fenced by the sole principal and read generation. */
export default function InvitationPage({
  userId,
  service,
  cache
}: {
  userId: string;
  service: Pick<InvitationService, 'load' | 'change' | 'retry' | 'subscribe'>;
  cache: AuthorityCache;
}) {
  const authority = useAuthority(cache);
  const allowed = evaluateAccess(authority, invitationRead) === 'allowed';
  const canChange = evaluateAccess(authority, invitationChange) === 'allowed';
  const [view, setView] = useState<View>({ kind: 'loading' });
  const [revision, setRevision] = useState(0);
  const [email, setEmail] = useState('');
  const [editing, setEditing] = useState(false);
  const [invalid, setInvalid] = useState(false);
  const [confirm, setConfirm] = useState<'resend' | 'correct' | null>(null);
  const [pending, setPending] = useState<InvitationOperation | null>(null);
  const [announcement, setAnnouncement] = useState('');
  const [now, setNow] = useState(Date.now);
  const generation = useRef(0);
  const busy = useRef(false);
  const heading = useRef<HTMLHeadingElement>(null);
  const cancel = useRef<HTMLButtonElement>(null);
  const input = useRef<HTMLInputElement>(null);
  const returnFocus = useRef<HTMLElement | null>(null);
  const focusAfterClose = useRef(false);
  useEffect(() => {
    if (confirm !== null || !focusAfterClose.current) return;
    focusAfterClose.current = false;
    returnFocus.current?.focus({ preventScroll: true });
  }, [confirm, revision, editing]);
  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 30_000);
    return () => clearInterval(timer);
  }, []);
  useEffect(() => {
    const scope = cache.captureScope();
    const controller = new AbortController();
    const request = ++generation.current;
    const invalidate = () => {
      generation.current += 1;
    };
    const retire = () => {
      ++generation.current;
      controller.abort();
      setView({ kind: 'loading' });
      setEmail('');
      setEditing(false);
      setConfirm(null);
      setPending(null);
      busy.current = false;
      setAnnouncement('');
    };
    if (!allowed || !scope.isCurrent() || !isUserId(userId)) return;
    scope.signal.addEventListener('abort', retire, { once: true });
    const unsubscribe = service.subscribe(userId, () => {
      // The submitting page handles its own outcome once below. Other consumers must
      // retire even an already-adopted GET, without replaying or trusting mutation data.
      if (!scope.isCurrent() || busy.current) return;
      ++generation.current;
      controller.abort();
      setView({ kind: 'invalidated' });
      setEmail('');
      setEditing(false);
      setConfirm(null);
      setAnnouncement('');
      heading.current?.focus({ preventScroll: true });
    });
    void service.load(userId, controller.signal).then((result) => {
      if (request !== generation.current || controller.signal.aborted || !scope.isCurrent()) return;
      if (result.ok) setView({ kind: 'ready', target: result.value });
      // This page still owns the read, but other work superseded its result.
      // Keep the stale result discarded and expose deliberate recovery, not endless loading.
      else if (result.error.kind === 'cancelled') setView({ kind: 'invalidated' });
      else setView({ kind: 'error', reason: result.error.kind });
    });
    return () => {
      invalidate();
      unsubscribe();
      controller.abort();
      scope.signal.removeEventListener('abort', retire);
    };
  }, [service, cache, userId, revision, allowed]);
  const reload = () => {
    if (busy.current || evaluateAccess(cache.getSnapshot(), invitationRead) !== 'allowed') return;
    service.retry();
    setView({ kind: 'loading' });
    setEmail('');
    setEditing(false);
    setConfirm(null);
    setRevision((value) => value + 1);
  };
  const close = () => {
    if (!busy.current) {
      focusAfterClose.current = true;
      setConfirm(null);
      setEmail('');
      setEditing(false);
    }
  };
  const change = async (operation: InvitationOperation) => {
    if (busy.current || evaluateAccess(cache.getSnapshot(), invitationChange) !== 'allowed') return;
    const scope = cache.captureScope();
    const request = ++generation.current;
    busy.current = true;
    setPending(operation);
    const result: ApiResult<true> = await service.change(
      userId,
      operation,
      operation === 'correct' ? email.trim().toLowerCase() : undefined
    );
    if (!scope.isCurrent() || request !== generation.current) return;
    busy.current = false;
    returnFocus.current = heading.current;
    focusAfterClose.current = true;
    setPending(null);
    setConfirm(null);
    setEmail('');
    setEditing(false);
    setAnnouncement(
      result.ok
        ? `${operationLabels[operation]} request completed. Reading authoritative state; this is not proof of inbox receipt.`
        : !result.ok && result.error.kind === 'rate-limited'
          ? `${operationLabels[operation]} is temporarily limited. Status will be checked once; wait before another deliberate attempt.`
          : !result.ok && result.error.kind === 'conflict'
            ? 'Invitation changed or this operation is no longer eligible; the address may already be claimed. Reading current state.'
            : `${operationLabels[operation]} outcome is unconfirmed. Reading current state before another deliberate attempt; do not repeat automatically.`
    );
    setView({ kind: 'loading' });
    setRevision((value) => value + 1);
  };
  const target =
    allowed && view.kind === 'ready' && view.target.userId === userId ? view.target : null;
  const delivery = target?.delivery;
  const pendingUser = target?.state === 'pending_activation';
  const expired = delivery ? Date.parse(delivery.expiresAt) <= now : false;
  const recoverable = pendingUser && delivery && delivery.status !== 'invalidated';
  return (
    <section className="invitation-page" aria-labelledby="invitation-heading">
      <h1 id="invitation-heading" ref={heading} tabIndex={-1}>
        Pending invitation
      </h1>
      <p>Canonical user identity and invitation recovery. All times are UTC.</p>
      {allowed && <a href="/app/admin/users">Back to Users</a>}
      {!allowed || !isUserId(userId) ? (
        <ContentState
          kind="error"
          title="Invitation unavailable"
          message="Current READ_USERS permission and a valid target are required. No user information is shown."
        />
      ) : view.kind === 'loading' ? (
        <ContentState
          kind="loading"
          title="Reading invitation"
          message="Checking current user and delivery state."
        />
      ) : view.kind === 'invalidated' ? (
        <ContentState
          kind="error"
          title="Invitation status needs reloading"
          message="Invitation work may have changed this status. Reload invitation to read current state; no recovery action was replayed."
        />
      ) : view.kind === 'error' ? (
        <ContentState
          kind="error"
          title={
            view.reason === 'not-found'
              ? 'User not found'
              : view.reason === 'rate-limited'
                ? 'Status temporarily limited'
                : view.reason === 'authorization'
                  ? 'Access forbidden'
                  : 'Invitation unavailable'
          }
          message="No target information is retained. Retry explicitly; no recovery action was replayed."
        />
      ) : null}
      {announcement && allowed && (
        <Notice tone="info" title="Recovery update">
          <p>{announcement}</p>
        </Notice>
      )}
      {target && (
        <>
          <h2>User and invitation</h2>
          <dl className="invitation-details">
            <div>
              <dt>Canonical email</dt>
              <dd>{target.email}</dd>
            </div>
            <div>
              <dt>User lifecycle</dt>
              <dd>{lifecycleLabels[target.state]}</dd>
            </div>
            <div>
              <dt>Delivery status</dt>
              <dd>
                {delivery ? deliveryLabels[delivery.status] : 'No invitation delivery generation'}
              </dd>
            </div>
            {delivery && (
              <div>
                <dt>Invitation expires (UTC)</dt>
                <dd>
                  <time dateTime={delivery.expiresAt}>
                    {delivery.expiresAt.replace('T', ' ').replace('+00:00', ' UTC')}
                  </time>
                </dd>
              </div>
            )}
          </dl>
          <p>
            Provider acceptance is a delivery handoff, not recipient receipt or activation. Stored
            delivery status can lag the expiry deadline.
          </p>
          {!pendingUser ? (
            <Notice tone="info" title="Invitation recovery unavailable">
              <p>
                This user is {lifecycleLabels[target.state].toLowerCase()}. No pending-invitation
                changes are offered. Reload to inspect subsequent changes.
              </p>
            </Notice>
          ) : expired ? (
            <Notice tone="warning" title="Invitation deadline passed">
              <p>
                This link is no longer usable by time. Retry cannot extend it. An authorized
                operator may confirm a replacement; only the server establishes a new generation.
              </p>
            </Notice>
          ) : null}
          {!canChange ? (
            <Notice tone="info" title="Read-only invitation">
              <p>
                READ_USERS permits viewing. MANAGE_USER_INVITATIONS is additionally required to
                change invitation state.
              </p>
            </Notice>
          ) : recoverable ? (
            <section aria-labelledby="invitation-recovery-heading">
              <h2 id="invitation-recovery-heading">Recovery</h2>
              <p>
                Retry preserves the same invitation, link and generation. Resend replaces it with a
                fresh seven-day invitation and invalidates the old link. Delivery is at least once,
                not exactly once.
              </p>
              <div className="invitation-actions">
                {delivery.status === 'retry_pending' && !expired && (
                  <Button
                    busy={pending === 'retry'}
                    busyLabel="Retrying delivery"
                    onClick={() => {
                      void change('retry');
                    }}
                  >
                    Retry delivery
                  </Button>
                )}
                <Button
                  onClick={(event) => {
                    if (!busy.current) {
                      returnFocus.current = event.currentTarget;
                      setConfirm('resend');
                    }
                  }}
                >
                  Resend invitation
                </Button>
                <Button
                  onClick={(event) => {
                    if (!busy.current) {
                      returnFocus.current = event.currentTarget;
                      setEditing(true);
                      setInvalid(false);
                    }
                  }}
                >
                  Correct email
                </Button>
              </div>
              {editing && (
                <form
                  onSubmit={(event) => {
                    event.preventDefault();
                    if (busy.current) return;
                    const canonical = email.trim().toLowerCase();
                    if (!validEmail(canonical) || canonical === target.email) {
                      setInvalid(true);
                      input.current?.focus();
                      return;
                    }
                    setEmail(canonical);
                    setConfirm('correct');
                  }}
                >
                  <TextField
                    ref={input}
                    label="New email"
                    name="email"
                    type="text"
                    inputMode="email"
                    value={email}
                    autoComplete="off"
                    required
                    description="Enter a different canonical address. The server validates ownership and claims. Nothing is saved until confirmation."
                    errors={
                      invalid
                        ? ['Enter a different valid email address (at most 254 characters).']
                        : []
                    }
                    onChange={(event) => {
                      setEmail(event.target.value);
                      setInvalid(false);
                    }}
                  />
                  <Button type="submit">Review email correction</Button>
                  <Button onClick={close}>Cancel correction</Button>
                </form>
              )}
            </section>
          ) : (
            <p>
              No recovery action is currently available. Reload status or contact an authorized
              operator.
            </p>
          )}
        </>
      )}
      {allowed && (
        <Button disabled={view.kind === 'loading' || pending !== null} onClick={reload}>
          Reload invitation
        </Button>
      )}
      {confirm && target && canChange && (
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
          aria-labelledby="invitation-confirm-title"
          aria-describedby="invitation-confirm-description"
        >
          <Modal.Header>
            <Modal.Title id="invitation-confirm-title">
              {confirm === 'correct'
                ? 'Confirm email correction'
                : 'Confirm replacement invitation'}
            </Modal.Title>
          </Modal.Header>
          <Modal.Body id="invitation-confirm-description">
            <p>
              Current canonical email: <strong>{target.email}</strong>
            </p>
            {confirm === 'correct' && (
              <p>
                New canonical email: <strong>{email}</strong>
              </p>
            )}
            <p>
              {confirm === 'correct'
                ? 'The email and invitation generation replace atomically, or neither changes. '
                : ''}
              The old link becomes invalid. A fresh seven-day invitation is created. This does not
              confirm delivery.
            </p>
            {pending && (
              <p role="status">
                Waiting for the server. Closing this dialog cannot undo a submitted change.
              </p>
            )}
          </Modal.Body>
          <Modal.Footer>
            <BootstrapButton
              ref={cancel}
              variant=""
              className="catalog-button"
              disabled={pending !== null}
              onClick={close}
            >
              Cancel
            </BootstrapButton>
            <Button
              busy={pending !== null}
              busyLabel="Submitting recovery"
              onClick={() => {
                void change(confirm);
              }}
            >
              Confirm {confirm === 'correct' ? 'correction' : 'resend'}
            </Button>
          </Modal.Footer>
        </Modal>
      )}
    </section>
  );
}
