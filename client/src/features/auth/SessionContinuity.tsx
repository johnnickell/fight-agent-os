import { useEffect, useRef, useSyncExternalStore } from 'react';

import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { Notice } from '@/components/Notice';

import type { SessionCoordinator } from '@/features/auth/SessionCoordinator';
import type { ReactNode } from 'react';

/** Credential readiness is not a principal, dashboard grant or server authorization. */
export function SessionContinuity({
  coordinator,
  children
}: {
  coordinator: SessionCoordinator;
  children: ReactNode;
}) {
  const state = useSyncExternalStore(coordinator.subscribe, coordinator.getSnapshot);
  const heading = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    if (
      ['authenticated', 'terminal', 'network-degraded', 'logout'].includes(state.status) ||
      state.signOutUnconfirmed !== undefined
    )
      heading.current?.focus();
  }, [state]);
  if (state.status === 'anonymous' && state.signOutUnconfirmed === undefined) return children;
  const busy = ['unknown', 'restoring', 'conflict'].includes(state.status);
  const title = busy
    ? 'Restoring your session'
    : state.status === 'authenticated'
      ? 'You’re signed in'
      : state.status === 'network-degraded'
        ? 'Session connection unavailable'
        : state.status === 'terminal'
          ? 'Your session has expired or ended'
          : 'Signed out of this page';
  return (
    <section className="activation-page" aria-labelledby="continuity-heading" aria-busy={busy}>
      <h1 id="continuity-heading" ref={heading} tabIndex={-1}>
        {title}
      </h1>
      {busy ? (
        <ContentState
          kind="loading"
          title="Checking this browser’s session"
          message="Protected work stays unavailable while restoration completes."
        />
      ) : state.status === 'authenticated' ? (
        <>
          <Notice title="Session established" tone="success">
            Access stays only in volatile memory. The authenticated dashboard and account controls
            are not available yet. This does not establish roles or permissions.
          </Notice>
          <Button
            onClick={() => {
              void coordinator.logout();
            }}
          >
            Sign out of this session
          </Button>
        </>
      ) : state.status === 'network-degraded' ? (
        <>
          <Notice title="Access cleared" tone="warning">
            The session could not be checked. This is not confirmation of revocation. Retry once
            when your connection is ready, or sign in explicitly.
          </Notice>
          <Button
            onClick={() => {
              void coordinator.restore();
            }}
          >
            Retry session restoration
          </Button>
          <Button onClick={coordinator.showLogin}>Use sign in instead</Button>
        </>
      ) : (
        <>
          <Notice
            title={state.signOutUnconfirmed ? 'Server sign-out unconfirmed' : 'Access cleared'}
            tone="warning"
          >
            {state.signOutUnconfirmed
              ? 'Local access is cleared, but server revocation could not be confirmed. Close this page on a shared device.'
              : 'This page no longer has access. Sign in explicitly to begin a new session.'}
          </Notice>
          {state.status !== 'logout' && (
            <Button onClick={coordinator.showLogin}>Sign in again</Button>
          )}
        </>
      )}
    </section>
  );
}
