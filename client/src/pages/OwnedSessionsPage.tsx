import { useCallback, useEffect, useRef, useState } from 'react';

import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { Notice } from '@/components/Notice';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import { useAuthority } from '@/features/authority/useAuthority';
import {
  revokeRequirements,
  RevokeSessionConfirmation
} from '@/features/sessions/RevokeSessionConfirmation';

import type { ApiResult } from '@/api/ApiResult';
import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type {
  OwnedSession,
  OwnedSessionsPage as SessionPage,
  OwnedSessionsService
} from '@/features/sessions/OwnedSessionsService';

const requirements = [
  { kind: 'authenticated' },
  { kind: 'permissions', all: ['READ_OWN_SESSIONS'] }
] as const;
type State =
  | { kind: 'loading' }
  | { kind: 'error'; forbidden: boolean }
  | { kind: 'retired' }
  | { kind: 'ready'; data: SessionPage };

export default function OwnedSessionsPage({
  service,
  cache,
  logout
}: {
  service: Pick<OwnedSessionsService, 'load'> &
    Partial<Pick<OwnedSessionsService, 'retry' | 'revoke' | 'subscribe'>>;
  cache: AuthorityCache;
  logout?: () => void;
}) {
  const authority = useAuthority(cache);
  const allowed = evaluateAccess(authority, requirements) === 'allowed';
  const canRevoke = allowed && evaluateAccess(authority, revokeRequirements) === 'allowed';
  const [target, setTarget] = useState<OwnedSession | null>(null);
  const [outcome, setOutcome] = useState<string | null>(null);
  const close = useCallback(() => setTarget(null), []);
  const [page, setPage] = useState(1);
  const [attempt, setAttempt] = useState(0);
  const [retainedState, setState] = useState<State>({ kind: 'loading' });
  const state: State = allowed ? retainedState : { kind: 'retired' };
  const heading = useRef<HTMLHeadingElement>(null);
  const focusAfterRead = useRef(false);
  useEffect(() => {
    const scope = cache.captureScope();
    const controller = new AbortController();
    let active = true;
    const retire = () => {
      setTarget(null);
      setOutcome(null);
      controller.abort();
      if (active) setState({ kind: 'retired' });
    };
    if (!scope.isCurrent() || evaluateAccess(cache.getSnapshot(), requirements) !== 'allowed')
      return;
    scope.signal.addEventListener('abort', retire, { once: true });
    void service.load(page, 25, controller.signal).then((result) => {
      if (!active || controller.signal.aborted || !scope.isCurrent()) return;
      if (result.ok) setState({ kind: 'ready', data: result.value });
      else if (result.error.kind !== 'cancelled') {
        setState({ kind: 'error', forbidden: result.error.kind === 'authorization' });
        // A denied read stays failed. Invalidating the guard here could remount and replay it.
      }
      if (focusAfterRead.current) {
        focusAfterRead.current = false;
        heading.current?.focus({ preventScroll: true });
      }
    });
    return () => {
      active = false;
      scope.signal.removeEventListener('abort', retire);
      controller.abort();
    };
  }, [service, cache, page, attempt]);
  useEffect(
    () =>
      service.subscribe?.(() => {
        if (evaluateAccess(cache.getSnapshot(), requirements) !== 'allowed') return;
        setState({ kind: 'loading' });
        setAttempt((value) => value + 1);
      }),
    [cache, service]
  );
  const completed = (result: ApiResult<true>) => {
    setTarget(null);
    if (!result.ok && result.error.kind === 'cancelled') return;
    setOutcome(
      result.ok
        ? 'Session revoked. The session list is being checked again.'
        : result.error.kind === 'current-session'
          ? 'Use Sign out (Logout) for the current session.'
          : result.error.kind === 'conflict'
            ? 'That session is no longer available for this action. The session list is being checked again.'
            : 'Revocation was not confirmed. Reload the list before selecting and confirming another attempt.'
    );
    heading.current?.focus({ preventScroll: true });
  };
  const reload = () => {
    if (evaluateAccess(cache.getSnapshot(), requirements) !== 'allowed') return;
    service.retry?.();
    focusAfterRead.current = true;
    setState({ kind: 'loading' });
    setAttempt((value) => value + 1);
  };
  const move = (next: number) => {
    if (evaluateAccess(cache.getSnapshot(), requirements) !== 'allowed') return;
    focusAfterRead.current = true;
    setState({ kind: 'loading' });
    setPage(next);
  };
  return (
    <section className="owned-sessions-page" aria-labelledby="sessions-heading">
      <h1 id="sessions-heading" ref={heading} tabIndex={-1}>
        Active Sessions
      </h1>
      <p>
        Review the usable sign-ins for your account. Device descriptions are approximate, supplied
        by the browser at sign-in, and may be unknown or inaccurate.
      </p>
      <p>
        Activity means session creation or the last successful refresh, not page views or online
        presence. All times are UTC. An ordinary session does not survive a browser restart; a
        remembered session may. Both end at their server deadlines.
      </p>
      <p>
        Sign out (Logout) ends only your current session. Confirm revocation to end another sign-in.
        Same-origin tabs receive best-effort updates; other browsers and devices are rejected by the
        server on their next request, not by guaranteed immediate UI push.
      </p>
      {allowed && outcome && (
        <Notice tone="info" title="Session action result">
          {outcome}
        </Notice>
      )}
      {canRevoke && target && service.revoke && (
        <RevokeSessionConfirmation
          target={target}
          cache={cache}
          revoke={(id) => service.revoke!(id)}
          onClose={close}
          onResult={completed}
        />
      )}
      {state.kind === 'loading' ? (
        <ContentState
          kind="loading"
          title="Loading sessions"
          message="Checking your usable refresh sessions."
        />
      ) : state.kind === 'error' || state.kind === 'retired' ? (
        <ContentState
          kind="error"
          title={
            state.kind === 'retired'
              ? 'Session view cleared'
              : state.forbidden
                ? 'Access forbidden'
                : 'Sessions unavailable'
          }
          message="No session rows are retained. Retry explicitly after current access is verified."
        />
      ) : (
        <>
          <p role="status" aria-atomic="true">
            Page {state.data.page} of {state.data.totalPages}. {state.data.totalRecords} active{' '}
            {state.data.totalRecords === 1 ? 'session' : 'sessions'}.
          </p>
          {state.data.records.length === 0 ? (
            <ContentState
              kind="empty"
              title="No sessions on this page"
              message="Sessions may have expired or changed. Return to the first page or reload to check again."
            />
          ) : (
            <ul className="owned-sessions-list" aria-label="Owned active sessions">
              {state.data.records.map((session) => (
                <SessionCard key={session.sessionId} session={session}>
                  {session.current
                    ? logout && <Button onClick={logout}>Sign out (Logout)</Button>
                    : canRevoke &&
                      service.revoke && (
                        <Button
                          onClick={() => {
                            if (
                              evaluateAccess(cache.getSnapshot(), revokeRequirements) === 'allowed'
                            ) {
                              setOutcome(null);
                              setTarget(session);
                            }
                          }}
                        >
                          Revoke session
                        </Button>
                      )}
                </SessionCard>
              ))}
            </ul>
          )}
          <nav aria-label="Session pages" className="session-pagination">
            <Button disabled={!allowed || page === 1} onClick={() => move(page - 1)}>
              Previous page
            </Button>
            <Button
              disabled={!allowed || page >= Math.min(state.data.totalPages, 1000)}
              onClick={() => move(page + 1)}
            >
              Next page
            </Button>
            {page !== 1 && (
              <Button disabled={!allowed} onClick={() => move(1)}>
                First page
              </Button>
            )}
          </nav>
          {state.data.totalPages > 1000 && (
            <p>Only the first 1,000 pages are available through this bounded view.</p>
          )}
        </>
      )}
      <Button disabled={!allowed || state.kind === 'loading'} onClick={reload}>
        {state.kind === 'error' || state.kind === 'retired'
          ? 'Retry session list'
          : 'Reload sessions'}
      </Button>
      <p>
        <a href="/app/account">Back to Account &amp; Security</a>
      </p>
    </section>
  );
}

function SessionCard({ session, children }: { session: OwnedSession; children: React.ReactNode }) {
  const device =
    session.deviceClass === 'unknown'
      ? 'Unknown device'
      : session.deviceClass[0]?.toUpperCase() + session.deviceClass.slice(1);
  return (
    <li className="session-card">
      <h2>
        {device} · {session.browserFamily === 'Unknown' ? 'Unknown browser' : session.browserFamily}
      </h2>
      {session.current && (
        <p className="session-current">
          <strong>Current session — this request</strong>
        </p>
      )}
      <p>{session.remembered ? 'Remembered session' : 'Ordinary session'}</p>
      <dl>
        {(
          [
            ['Created', session.createdAt],
            ['Last refresh activity', session.lastActivityAt],
            ['Idle deadline', session.idleExpiresAt],
            ['Absolute deadline', session.absoluteExpiresAt]
          ] as const
        ).map(([label, timestamp]) => (
          <div key={label}>
            <dt>{label}</dt>
            <dd>
              <time dateTime={timestamp}>
                {timestamp.replace('T', ' ').replace('+00:00', ' UTC')}
              </time>
            </dd>
          </div>
        ))}
      </dl>
      {children}
    </li>
  );
}
