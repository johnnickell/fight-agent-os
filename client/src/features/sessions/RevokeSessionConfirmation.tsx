import { type KeyboardEvent, useEffect, useRef, useState } from 'react';
import BootstrapButton from 'react-bootstrap/Button';
import Modal from 'react-bootstrap/Modal';

import { Button } from '@/components/Button';
import { evaluateAccess } from '@/features/authority/evaluateAccess';

import type { ApiResult } from '@/api/ApiResult';
import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type { OwnedSession } from '@/features/sessions/OwnedSessionsService';

export const revokeRequirements = [{ kind: 'permissions', all: ['DELETE_OWN_SESSIONS'] }] as const;

/** Names only closed coarse presentation; no opaque identifier is rendered into HTML. */
export function sessionDescription(session: OwnedSession): string {
  const device = session.deviceClass === 'unknown' ? 'Unknown device' : session.deviceClass;
  const browser = session.browserFamily === 'Unknown' ? 'Unknown browser' : session.browserFamily;
  return `${device} · ${browser}`;
}

/** Explicit focus-trapped confirmation; pending cannot duplicate or pretend cancellation undoes a mutation. */
export function RevokeSessionConfirmation({
  target,
  cache,
  revoke,
  onClose,
  onResult
}: {
  target: OwnedSession;
  cache: AuthorityCache;
  revoke: (id: string) => Promise<ApiResult<true>>;
  onClose: () => void;
  onResult: (result: ApiResult<true>) => void;
}) {
  const [pending, setPending] = useState(false);
  const busy = useRef(false);
  const cancel = useRef<HTMLButtonElement>(null);
  const active = useRef(true);
  useEffect(() => {
    active.current = true;
    const scope = cache.captureScope();
    const retire = () => {
      active.current = false;
      onClose();
    };
    scope.signal.addEventListener('abort', retire, { once: true });
    return () => {
      active.current = false;
      scope.signal.removeEventListener('abort', retire);
    };
  }, [cache, onClose]);
  const confirm = async () => {
    if (
      busy.current ||
      target.current ||
      evaluateAccess(cache.getSnapshot(), revokeRequirements) !== 'allowed'
    )
      return;
    const scope = cache.captureScope();
    if (!scope.isCurrent()) return;
    busy.current = true;
    setPending(true);
    const result = await revoke(target.sessionId);
    if (!active.current || !scope.isCurrent()) return;
    onResult(result);
  };
  return (
    <Modal
      show
      animation={false}
      onHide={() => {
        if (!busy.current) onClose();
      }}
      onShow={() => cancel.current?.focus()}
      onKeyDown={(event: KeyboardEvent<HTMLElement>) => {
        if (event.key !== 'Tab') return;
        // The library enforces containment; retain a usable cyclic order at our two endpoints too.
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
      aria-labelledby="revoke-session-title"
      aria-describedby="revoke-session-description"
      centered
    >
      <Modal.Header>
        <Modal.Title id="revoke-session-title">Revoke this other session?</Modal.Title>
      </Modal.Header>
      <Modal.Body id="revoke-session-description">
        <p>{sessionDescription(target)}</p>
        <p>Created {target.createdAt.replace('T', ' ').replace('+00:00', ' UTC')}.</p>
        <p>
          Last refresh activity {target.lastActivityAt.replace('T', ' ').replace('+00:00', ' UTC')}.
        </p>
        <p>
          This ends only this sign-in. Your current session stays signed in. The other device must
          sign in again. Device hints are approximate.
        </p>
        {pending && <p role="status">Revoking session. Waiting for the server.</p>}
      </Modal.Body>
      <Modal.Footer>
        <BootstrapButton
          ref={cancel}
          type="button"
          variant=""
          className="catalog-button"
          disabled={pending}
          onClick={onClose}
        >
          Cancel
        </BootstrapButton>
        <Button
          busy={pending}
          busyLabel="Revoking session"
          onClick={() => {
            void confirm();
          }}
        >
          Confirm revocation
        </Button>
      </Modal.Footer>
    </Modal>
  );
}
