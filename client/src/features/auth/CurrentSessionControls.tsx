import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/Button';
import { Notice } from '@/components/Notice';

import type { SessionService } from '@/features/auth/SessionService';
import type { ReactNode } from 'react';

/** Presents only current-session logout, never a principal or account-management surface. */
export function CurrentSessionControls({
  service,
  children
}: {
  service?: SessionService | undefined;
  children: ReactNode;
}) {
  const [state, setState] = useState<'ready' | 'pending' | 'ended' | 'uncertain'>('ready');
  const active = useRef(true);
  const heading = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    active.current = true;
    return () => {
      active.current = false;
    };
  }, []);
  useEffect(() => {
    if (state === 'ended' || state === 'uncertain') heading.current?.focus();
  }, [state]);
  if (state === 'ended' || state === 'uncertain')
    return (
      <section className="current-session" aria-labelledby="session-ended-heading">
        <h2 id="session-ended-heading" ref={heading} tabIndex={-1}>
          Signed out of this page
        </h2>
        <Notice
          title={state === 'ended' ? 'Current session ended' : 'Server sign-out unconfirmed'}
          tone={state === 'ended' ? 'success' : 'warning'}
        >
          {state === 'ended'
            ? 'Access has been cleared from this page. Other sessions are unchanged.'
            : 'Access has been cleared from this page, but server revocation could not be confirmed. Close this page if you are using a shared device.'}
        </Notice>
        <a href="/app/login">Return to sign in</a>
      </section>
    );
  return (
    <>
      {children}
      {service && (
        <Button
          busy={state === 'pending'}
          busyLabel="Signing out"
          onClick={() => {
            setState('pending');
            void service.logout().then((result) => {
              if (active.current) setState(result.ok ? 'ended' : 'uncertain');
            });
          }}
        >
          Sign out of this session
        </Button>
      )}
    </>
  );
}
