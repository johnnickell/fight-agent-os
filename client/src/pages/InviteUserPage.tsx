import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { TextField } from '@/components/forms/TextField';
import { Notice } from '@/components/Notice';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import { useAuthority } from '@/features/authority/useAuthority';
import {
  canonicalInvitationEmail,
  ordinaryInvitationAccess
} from '@/features/invitations/OrdinaryInvitationService';
import { matchApplicationRoute } from '@/routes/applicationRoutes';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type {
  OrdinaryInvitationOutcome,
  OrdinaryInvitationService
} from '@/features/invitations/OrdinaryInvitationService';

/**
 * Presents ordinary issuance only, with volatile input and generation-fenced outcomes
 */
export default function InviteUserPage({
  service,
  cache
}: {
  service: Pick<OrdinaryInvitationService, 'submit'>;
  cache: AuthorityCache;
}) {
  const authority = useAuthority(cache);
  const allowed = evaluateAccess(authority, ordinaryInvitationAccess) === 'allowed';
  const [email, setEmail] = useState('');
  const [invalid, setInvalid] = useState(false);
  const [pending, setPending] = useState(false);
  const [outcome, setOutcome] = useState<OrdinaryInvitationOutcome | null>(null);
  const generation = useRef(0);
  const busy = useRef(false);
  const focusEmail = useRef(false);
  const input = useRef<HTMLInputElement>(null);
  const resultHeading = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    const scope = cache.captureScope();
    const retire = () => {
      ++generation.current;
      busy.current = false;
      setEmail('');
      setInvalid(false);
      setPending(false);
      setOutcome(null);
    };
    scope.signal.addEventListener('abort', retire, { once: true });
    const invalidate = () => {
      ++generation.current;
    };
    return () => {
      invalidate();
      scope.signal.removeEventListener('abort', retire);
    };
  }, [cache, allowed]);
  useEffect(() => {
    if (outcome) resultHeading.current?.focus({ preventScroll: true });
    else if (focusEmail.current) {
      focusEmail.current = false;
      input.current?.focus();
    }
  }, [outcome]);
  const permitted = (path: string) => {
    const route = matchApplicationRoute(path);
    return route && evaluateAccess(authority, route.requirements) === 'allowed';
  };
  const complete = outcome?.kind === 'accepted' || outcome?.kind === 'unconfirmed';
  const target = complete ? outcome.userId : null;
  const statusPath = target ? `/app/admin/invitations/${target}` : null;
  const error = outcome?.kind === 'rejected' ? outcome.reason : null;
  return (
    <section className="invite-user-page" aria-labelledby="invite-user-heading">
      <h1 id="invite-user-heading">Invite User</h1>
      <p>
        Create an invitation for one ordinary user. No password or elevated authority is chosen
        here.
      </p>
      <div className="invite-user-panel">
        {!allowed ? (
          <ContentState
            kind="error"
            title="Invitation unavailable"
            message="Current INVITE_USERS permission is required. No invitation can be submitted."
          />
        ) : (
          <>
            <p>
              The server assigns the managed User role (ROLE_USER). Activation invitations last
              seven days. Acceptance does not confirm provider delivery or recipient receipt.
            </p>
            {outcome && outcome.kind !== 'cancelled' && (
              <div>
                <h2 ref={resultHeading} tabIndex={-1}>
                  {outcome.kind === 'accepted'
                    ? 'Invitation accepted'
                    : outcome.kind === 'unconfirmed'
                      ? 'Invitation outcome unconfirmed'
                      : error === 'conflict'
                        ? 'Invitation conflicts with current state'
                        : error === 'rate-limited'
                          ? 'Invitation temporarily limited'
                          : error === 'validation' ||
                              error === 'bad-request' ||
                              error === 'rule-violation'
                            ? 'Check the email address'
                            : 'Invitation not accepted'}
                </h2>
                <Notice
                  title="Invitation update"
                  tone={outcome.kind === 'accepted' ? 'success' : 'warning'}
                >
                  {outcome.kind === 'accepted' ? (
                    <p>
                      The server confirmed the invitation, ordinary role assignment and recorded
                      audit steps. Delivery work may still be pending or failed; this is not proof
                      of inbox receipt or activation.
                    </p>
                  ) : outcome.kind === 'unconfirmed' ? (
                    <p>
                      Some work may have committed. Do not repeat this invitation automatically. Ask
                      an authorized operator to inspect current users and invitation state before
                      another attempt. Leaving this page cannot undo a submitted invitation.
                    </p>
                  ) : error === 'conflict' ? (
                    <p>
                      This address may already be registered or reserved, or installation policy may
                      need reconciliation. Check the address or ask an authorized operator to
                      inspect current state. No partial success is claimed.
                    </p>
                  ) : error === 'rate-limited' ? (
                    <p>Wait before another deliberate attempt. No automatic retry will be made.</p>
                  ) : error === 'validation' ||
                    error === 'bad-request' ||
                    error === 'rule-violation' ? (
                    <p>
                      Enter one valid email address of at most 254 characters. The server validates
                      it.
                    </p>
                  ) : (
                    <p>
                      Current authority could not admit this invitation. Recheck access before
                      trying again.
                    </p>
                  )}
                </Notice>
                {statusPath && permitted(statusPath) && (
                  <p>
                    <a href={statusPath}>View invitation status</a>
                  </p>
                )}
              </div>
            )}
            {!complete && (
              <form
                noValidate
                aria-busy={pending}
                onSubmit={(event) => {
                  event.preventDefault();
                  if (
                    busy.current ||
                    evaluateAccess(cache.getSnapshot(), ordinaryInvitationAccess) !== 'allowed'
                  )
                    return;
                  const canonical = canonicalInvitationEmail(email);
                  if (!canonical) {
                    setInvalid(true);
                    input.current?.focus();
                    return;
                  }
                  const scope = cache.captureScope();
                  const request = ++generation.current;
                  busy.current = true;
                  setPending(true);
                  setOutcome(null);
                  setEmail(canonical);
                  void service.submit(canonical).then((result) => {
                    if (request !== generation.current || !scope.isCurrent()) return;
                    busy.current = false;
                    setPending(false);
                    if (result.kind === 'cancelled') return;
                    setOutcome(result);
                    if (result.kind === 'accepted' || result.kind === 'unconfirmed') setEmail('');
                  });
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
                  readOnly={pending}
                  value={email}
                  description="Enter one email address (at most 254 characters). Paste is welcome. Surrounding spaces are removed and letters are lowercased on submission."
                  errors={
                    invalid ? ['Enter one valid email address (at most 254 characters).'] : []
                  }
                  onChange={(event) => {
                    setEmail(event.target.value);
                    setInvalid(false);
                    setOutcome(null);
                  }}
                />
                {invalid && <p role="alert">Check the highlighted email field.</p>}
                {pending && (
                  <p role="status">Submitting invitation. Leaving cannot undo server work.</p>
                )}
                <div className="invite-user-actions">
                  <Button type="submit" busy={pending} busyLabel="Submitting invitation">
                    Invite user
                  </Button>
                  <Button
                    disabled={pending}
                    onClick={() => {
                      ++generation.current;
                      setEmail('');
                      setInvalid(false);
                      setOutcome(null);
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
                Invite another user
              </Button>
            )}
          </>
        )}
      </div>
      <p>
        {permitted('/app/admin/users') ? (
          <a href="/app/admin/users">Back to Users</a>
        ) : permitted('/app/dashboard') ? (
          <a href="/app/dashboard">Back to Dashboard</a>
        ) : (
          <a href="/app/account">Back to Account &amp; Security</a>
        )}
      </p>
    </section>
  );
}
