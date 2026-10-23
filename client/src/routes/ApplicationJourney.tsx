import { lazy, Suspense, useEffect, useLayoutEffect, useRef, useSyncExternalStore } from 'react';

import { AuthorityStatus } from '@/components/AuthorityStatus';
import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { useApplicationSession } from '@/features/auth/useApplicationSession';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import { useAuthority, useAuthorityPresentation } from '@/features/authority/useAuthority';
import { ApplicationFrame } from '@/layouts/ApplicationFrame';
import { ShellLayout } from '@/layouts/ShellLayout';
import { LoadingPage } from '@/pages/LoadingPage';
import { LoginPage } from '@/pages/LoginPage';
import { applicationRoutes, matchApplicationRoute } from '@/routes/applicationRoutes';
import { GuardedRoute } from '@/routes/GuardedRoute';

import type { ApplicationSession } from '@/features/auth/useApplicationSession';
import type { ThemePreferenceStore } from '@/features/theme/ThemePreference';
import type { RuntimeConfiguration } from '@/runtimeConfiguration';

const LandingPage = lazy(() => import('@/pages/LandingPage'));
const DashboardPage = lazy(() => import('@/pages/DashboardPage'));

export function ApplicationJourney({
  runtime,
  ...props
}: {
  runtime: RuntimeConfiguration;
  pathname: string;
  theme?: ThemePreferenceStore | undefined;
  navigate: (target: string, replace?: boolean) => void;
  onAuthenticated?: (() => void) | undefined;
}) {
  const { session, retired } = useApplicationSession(runtime);
  return retired ? (
    <ShellLayout theme={props.theme} publicJourney>
      <ContentState
        kind="empty"
        title="Sign-in cleared"
        message="Reload this page to sign in again. Browser history does not restore credentials."
      />
      <a href="/app/login" data-reload-application>
        Sign in
      </a>
    </ShellLayout>
  ) : (
    <ApplicationView session={session} {...props} />
  );
}

/** Routes only after bounded credential restoration and a fresh sole-cache projection */
export function ApplicationView({
  session,
  pathname,
  navigate,
  theme,
  onAuthenticated
}: {
  session: ApplicationSession;
  pathname: string;
  navigate: (target: string, replace?: boolean) => void;
  theme?: ThemePreferenceStore | undefined;
  onAuthenticated?: (() => void) | undefined;
}) {
  const dispatch = useAuthority(session.authority);
  const authority = useAuthorityPresentation(session.authority);
  const credential = useSyncExternalStore(
    session.coordinator.subscribe,
    session.coordinator.getSnapshot
  );
  const route = matchApplicationRoute(pathname);
  const pendingTarget = useRef<string | null>(null);
  const capturedPath = useRef<string | null>(null);
  const recoveryFocus = useRef<HTMLButtonElement | null>(null);
  useLayoutEffect(() => {
    const control = recoveryFocus.current;
    if (!control || control.isConnected) return;
    recoveryFocus.current = null;
    // Only hand off a disappearing explicit control's focus; routine checks and other focus are untouched.
    if (document.activeElement === document.body)
      document.getElementById('main-content')?.focus({ preventScroll: true });
  });
  // Capture before any URL sanitation, once per attempted location. Never retain raw URL/form state.
  useLayoutEffect(() => {
    const firstAttempt = capturedPath.current !== pathname;
    capturedPath.current = pathname;
    if (
      firstAttempt &&
      route?.access === 'protected' &&
      authority.status !== 'authenticated' &&
      credential.status !== 'logout' &&
      credential.signOutUnconfirmed === undefined
    ) {
      const target =
        window.location.pathname === pathname
          ? pathname + window.location.search + window.location.hash
          : pathname;
      session.intent.capture(target);
    }
    if (window.location.pathname === pathname && (window.location.search || window.location.hash))
      window.history.replaceState(null, '', route ? pathname : '/app/not-found');
  }, [
    pathname,
    route,
    authority.status,
    credential.status,
    credential.signOutUnconfirmed,
    session
  ]);
  useEffect(() => {
    if (
      credential.status === 'terminal' ||
      credential.status === 'logout' ||
      credential.signOutUnconfirmed !== undefined
    )
      session.intent.clear();
  }, [credential, session]);
  useEffect(() => {
    if (authority.status !== 'authenticated' || credential.status !== 'authenticated') return;
    const intended = session.intent.consume();
    if (route?.destination === 'root' || route?.destination === 'login') {
      const target = intended ?? '/app/dashboard';
      // One replace, never a redirect-to-login cycle for a resolved denied principal.
      if (pendingTarget.current !== target) {
        pendingTarget.current = target;
        navigate(target, true);
      }
    } else pendingTarget.current = null;
  }, [authority, credential.status, route, navigate, session]);
  useEffect(() => {
    // Navigation demand can refresh stale projection once. Errors require explicit recovery.
    if (
      session.coordinator.getSnapshot().status === 'authenticated' &&
      session.authority.getSnapshot().status === 'stale'
    )
      void session.authority.load();
  }, [pathname, session]);
  const retained =
    authority.status === 'authenticated' &&
    !['terminal', 'logout', 'anonymous'].includes(credential.status);
  const busy = !retained && ['unknown', 'restoring', 'conflict'].includes(credential.status);
  const denied =
    authority.status === 'authenticated' &&
    route?.access === 'protected' &&
    evaluateAccess(authority, route.requirements) === 'forbidden';
  const title = busy
    ? 'Restoring your session'
    : credential.status === 'terminal'
      ? 'Session ended'
      : credential.status === 'logout' || credential.signOutUnconfirmed !== undefined
        ? 'Signed out'
        : credential.status === 'network-degraded' && !retained
          ? 'Session connection unavailable'
          : credential.status === 'anonymous'
            ? 'Sign in'
            : authority.status !== 'authenticated'
              ? 'Checking current access'
              : denied
                ? 'Access forbidden'
                : (route?.title ?? 'Application');
  useEffect(() => {
    document.title = `${title} — Fight Agent OS`;
    document.getElementById('main-content')?.focus();
  }, [pathname, title]);
  let content;
  if (busy)
    content = (
      <ContentState
        kind="loading"
        title="Restoring your session"
        message="Protected content stays unavailable while this browser’s session is checked."
      />
    );
  else if (credential.status === 'network-degraded' && !retained)
    content = (
      <>
        <ContentState
          kind="error"
          title="Session connection unavailable"
          message="Access is cleared. Server revocation is not confirmed."
          onRetry={() => {
            void session.coordinator.restore();
          }}
        />
        <Button onClick={session.coordinator.showLogin}>Use sign in instead</Button>
      </>
    );
  else if (
    credential.status === 'terminal' ||
    credential.status === 'logout' ||
    credential.signOutUnconfirmed !== undefined
  )
    content = (
      <>
        <ContentState
          kind="empty"
          title={
            credential.status === 'terminal'
              ? 'Your session has expired or ended'
              : 'Signed out of this page'
          }
          message={
            credential.signOutUnconfirmed
              ? 'Local access is cleared. Server sign-out is unconfirmed. Close this page on a shared device.'
              : 'This page no longer has access. Sign in explicitly to begin a new session.'
          }
        />
        {credential.status !== 'logout' && (
          <Button
            onClick={() => {
              session.coordinator.showLogin();
              navigate('/app/login', true);
            }}
          >
            Sign in again
          </Button>
        )}
      </>
    );
  else if (credential.status === 'anonymous') {
    content = (
      <LoginPage
        service={session.coordinator}
        schemas={session.schemas}
        onAuthenticated={onAuthenticated}
      />
    );
  } else if (route && authority.status === 'authenticated') {
    if (route.destination === 'root' || route.destination === 'login') {
      const dashboard = applicationRoutes.find((entry) => entry.pathname === '/app/dashboard');
      content =
        dashboard && evaluateAccess(authority, dashboard.requirements) === 'forbidden' ? (
          <AuthorityStatus state={authority} decision="forbidden" />
        ) : (
          <ContentState
            kind="loading"
            title="Opening your destination"
            message="Current access has been checked."
          />
        );
    } else
      content = (
        <GuardedRoute cache={session.authority} route={route}>
          <Suspense fallback={<LoadingPage />}>
            {route.destination === 'dashboard' ? (
              <DashboardPage cache={session.authority} />
            ) : (
              <LandingPage route={route} />
            )}
          </Suspense>
        </GuardedRoute>
      );
  } else
    content = (
      <AuthorityStatus
        state={authority}
        decision="unavailable"
        {...(session.authority.canRetry()
          ? {
              onRetry: () => {
                void session.authority.load();
              }
            }
          : {})}
      />
    );
  useEffect(() => {
    if (
      credential.status === 'anonymous' &&
      credential.signOutUnconfirmed === undefined &&
      route?.destination !== 'login'
    )
      navigate('/app/login', true);
  }, [credential, route, navigate]);
  const statusOnly =
    credential.status !== 'anonymous' &&
    ((!retained && credential.status !== 'authenticated') ||
      authority.status !== 'authenticated' ||
      route?.destination === 'root' ||
      route?.destination === 'login' ||
      evaluateAccess(authority, route?.requirements) !== 'allowed');
  const outlet = (
    <>
      {statusOnly && <h1>Application access</h1>}
      {content}
    </>
  );
  // Initial admission needs both owners; routine checks retain only the cache-owned same-context display.
  const frame =
    retained && authority.status === 'authenticated' ? (
      <ApplicationFrame
        key={authority.principal.userId}
        cache={session.authority}
        pathname={pathname}
        title={title}
        theme={theme}
        onLogout={() => {
          void session.coordinator.logout();
        }}
      >
        {dispatch.status !== 'authenticated' && (
          <div className="application-verification" role="status" aria-live="polite">
            {dispatch.status === 'loading' ||
            credential.status === 'restoring' ||
            credential.status === 'conflict'
              ? 'Checking current access in the background. Protected actions are paused.'
              : 'Current access could not be verified. Protected actions are unavailable.'}
            {session.authority.canRetry() && (
              <Button
                onClick={(event) => {
                  if (document.activeElement === event.currentTarget)
                    recoveryFocus.current = event.currentTarget;
                  void session.authority.load();
                }}
                onBlur={() => {
                  recoveryFocus.current = null;
                }}
              >
                Retry access check
              </Button>
            )}
            {credential.status === 'network-degraded' && (
              <Button
                onClick={(event) => {
                  if (document.activeElement === event.currentTarget)
                    recoveryFocus.current = event.currentTarget;
                  void session.coordinator.restore();
                }}
                onBlur={() => {
                  recoveryFocus.current = null;
                }}
              >
                Retry session connection
              </Button>
            )}
          </div>
        )}
        {outlet}
      </ApplicationFrame>
    ) : (
      <ShellLayout theme={theme} publicJourney>
        {outlet}
      </ShellLayout>
    );
  return (
    <>
      <p
        className="visually-hidden"
        role="status"
        aria-atomic="true"
        aria-label="Application navigation status"
      >
        {title} — Fight Agent OS
      </p>
      {frame}
    </>
  );
}
