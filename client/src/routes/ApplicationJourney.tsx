import {
  lazy,
  Suspense,
  useEffect,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
  useSyncExternalStore
} from 'react';

import { evaluateAccess } from '@fight/react/account';
import { useAuthority, useAuthorityPresentation } from '@fight/react/account';
import { LoginPage } from '@fight/react/account';
import {
  AuthorityStatus,
  BrandProvider,
  Button,
  ContentState,
  LoadingPage,
  ShellLayout,
  useBrand
} from '@fight/react/controls';

import { agentOsBrand } from '@/agentOsBrand';
import { useApplicationSession } from '@/features/auth/useApplicationSession';
import { ApplicationFrame } from '@/layouts/ApplicationFrame';
import { applicationRoutes, matchApplicationRoute } from '@/routes/applicationRoutes';
import { GuardedRoute } from '@/routes/GuardedRoute';

import type { ApplicationSession } from '@/features/auth/useApplicationSession';
import type { ApplicationNavigation } from '@/routes/useApplicationNavigation';
import type { RuntimeConfiguration } from '@/runtimeConfiguration';
import type { AdministrationNotice } from '@fight/react/account';
import type { ThemePreferenceStore } from '@fight/react/controls';

const LandingPage = lazy(() => import('@/pages/LandingPage'));
const DashboardPage = lazy(() => import('@/pages/DashboardPage'));
const SwaggerPage = lazy(() => import('@/pages/SwaggerPage'));
const PasswordChangePage = lazy(() =>
  import('@fight/react/account').then((module) => ({ default: module.PasswordChangePage }))
);
const OwnedSessionsPage = lazy(() =>
  import('@fight/react/account').then((module) => ({ default: module.OwnedSessionsPage }))
);
const UsersPage = lazy(() =>
  import('@fight/react/account').then((module) => ({ default: module.UsersPage }))
);
const RolesPage = lazy(() =>
  import('@fight/react/account').then((module) => ({ default: module.RolesPage }))
);
const PermissionsPage = lazy(() =>
  import('@fight/react/account').then((module) => ({ default: module.PermissionsPage }))
);
const InvitationPage = lazy(() =>
  import('@fight/react/account').then((module) => ({ default: module.InvitationPage }))
);
const InviteUserPage = lazy(() =>
  import('@fight/react/account').then((module) => ({ default: module.InviteUserPage }))
);
const InviteSuperAdminPage = lazy(() =>
  import('@fight/react/account').then((module) => ({ default: module.InviteSuperAdminPage }))
);

export function ApplicationJourney({
  runtime,
  ...props
}: {
  runtime: RuntimeConfiguration;
  pathname: string;
  tableRestoration?: Readonly<{ invalid: boolean }> | undefined;
  theme?: ThemePreferenceStore | undefined;
  navigate: ApplicationNavigation;
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
      <a href="/login" data-reload-application>
        Sign in
      </a>
    </ShellLayout>
  ) : session ? (
    <ApplicationView session={session} {...props} />
  ) : null;
}

/** Routes only after bounded credential restoration and a fresh sole-cache projection */
export function ApplicationView({
  session,
  pathname,
  navigate,
  tableRestoration,
  theme,
  onAuthenticated
}: {
  session: ApplicationSession;
  pathname: string;
  navigate: ApplicationNavigation;
  tableRestoration?: Readonly<{ invalid: boolean }> | undefined;
  theme?: ThemePreferenceStore | undefined;
  onAuthenticated?: (() => void) | undefined;
}) {
  const brand = useBrand(agentOsBrand);
  const productName = brand.name;
  const dispatch = useAuthority(session.authority);
  const authority = useAuthorityPresentation(session.authority);
  const credential = useSyncExternalStore(
    session.coordinator.subscribe,
    session.coordinator.getSnapshot
  );
  const route = matchApplicationRoute(pathname);
  // A visit outlives authority refreshes, but not navigation away from Roles.
  const roleVisit = useMemo(
    () => (route?.destination === 'roles' ? { pathname, session } : null),
    [pathname, route?.destination, session]
  );
  const currentRoleVisit = useRef<typeof roleVisit>(null);
  useLayoutEffect(() => {
    currentRoleVisit.current = roleVisit;
    return () => {
      currentRoleVisit.current = null;
    };
  }, [roleVisit]);
  const [adminOutcome, setAdminOutcome] = useState<{
    pathname: string;
    actorId: string;
    notice: AdministrationNotice;
    roleVisit?: NonNullable<typeof roleVisit>;
  }>();
  useEffect(() => {
    if (
      !adminOutcome ||
      (adminOutcome.pathname === pathname && credential.status === 'authenticated')
    )
      return;
    queueMicrotask(() => {
      setAdminOutcome((saved) => (saved === adminOutcome ? undefined : saved));
    });
  }, [adminOutcome, pathname, credential.status]);
  const passwordChanged = credential.passwordChanged === true || session.passwordChange.completed();
  useEffect(() => {
    if (credential.status === 'authenticated') session.passwordChange.clearCompletion();
  }, [credential.status, session]);
  useEffect(() => {
    if (passwordChanged && credential.status === 'terminal' && pathname !== '/login')
      navigate('/login', true);
  }, [passwordChanged, credential.status, pathname, navigate]);
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
      const atPath = window.location.pathname === pathname;
      const search = atPath ? window.location.search : '';
      // Project only bounded directory options before handing the target to the intent owner.
      if (typeof route.intendedRoute === 'function') {
        const projected = route.intendedRoute(search);
        session.intent.capture(`${pathname}?${projected.search}`, projected.invalid);
      } else session.intent.capture(pathname + search + (atPath ? window.location.hash : ''));
    }
    if (window.location.pathname !== pathname) return;
    // Table routes own their query strings, including while a reload is restoring the session.
    if (
      route?.normalizeSearch &&
      !['anonymous', 'logout', 'terminal'].includes(credential.status)
    ) {
      if (window.location.hash)
        window.history.replaceState(window.history.state, '', pathname + window.location.search);
    } else if (window.location.search || window.location.hash) {
      window.history.replaceState(null, '', route ? pathname : '/not-found');
    }
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
    if (route?.destination === 'login') {
      const target = intended?.target ?? '/';
      // One replace, never a redirect-to-login cycle for a resolved denied principal.
      if (pendingTarget.current !== target) {
        pendingTarget.current = target;
        if (intended?.invalid) navigate(target, true, true);
        else navigate(target, true);
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
    document.title = `${title} — ${productName}`;
    // Announce the destination without scrolling a tall main past the utility header.
    document.getElementById('main-content')?.focus({ preventScroll: true });
  }, [pathname, productName, title]);
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
            passwordChanged
              ? 'Password changed — sign in again'
              : credential.status === 'terminal'
                ? 'Your session has expired or ended'
                : 'Signed out of this page'
          }
          message={
            passwordChanged
              ? 'All sessions have ended. Password fields and local access are cleared. Sign in with your new password; no replacement session was created.'
              : credential.signOutUnconfirmed
                ? 'Local access is cleared. Server sign-out is unconfirmed. Close this page on a shared device.'
                : 'This page no longer has access. Sign in explicitly to begin a new session.'
          }
        />
        {credential.status !== 'logout' && (
          <Button
            onClick={() => {
              session.coordinator.showLogin();
              navigate('/login', true);
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
    if (route.destination === 'login') {
      const dashboard = applicationRoutes.find((entry) => entry.pathname === '/');
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
            {route.destination === 'swagger' ? (
              <SwaggerPage service={session.swagger} cache={session.authority} />
            ) : route.destination === 'password-change' ? (
              <PasswordChangePage service={session.passwordChange} schemas={session.schemas} />
            ) : route.destination === 'users' ? (
              <UsersPage
                service={session.users}
                cache={session.authority}
                locationRestoration={tableRestoration}
                administration={session.userAdministration}
                detailId={route.targetUserId}
                onOpenDetail={(id) => navigate(`/admin/users/${id}${window.location.search}`)}
                onCloseDetail={() => navigate(`/admin/users${window.location.search}`)}
                notice={
                  adminOutcome?.pathname === pathname &&
                  adminOutcome.actorId === authority.principal.userId
                    ? adminOutcome.notice
                    : undefined
                }
                onOutcome={(notice) =>
                  setAdminOutcome({ pathname, actorId: authority.principal.userId, notice })
                }
              />
            ) : route.destination === 'roles' ? (
              <RolesPage
                catalog={session.authorityCatalog}
                mutations={session.customRoles}
                cache={session.authority}
                locationRestoration={tableRestoration}
                notice={
                  adminOutcome?.roleVisit === roleVisit &&
                  adminOutcome.actorId === authority.principal.userId
                    ? adminOutcome.notice
                    : undefined
                }
                onOutcome={(notice) => {
                  if (roleVisit && currentRoleVisit.current === roleVisit)
                    setAdminOutcome({
                      pathname,
                      actorId: authority.principal.userId,
                      notice,
                      roleVisit
                    });
                }}
              />
            ) : route.destination === 'permissions' ? (
              <PermissionsPage
                catalog={session.authorityCatalog}
                cache={session.authority}
                locationRestoration={tableRestoration}
              />
            ) : route.destination === 'invite-user' ? (
              <InviteUserPage service={session.invitationIssuance} cache={session.authority} />
            ) : route.destination === 'invite-super-admin' ? (
              <InviteSuperAdminPage
                service={session.invitationIssuance}
                cache={session.authority}
              />
            ) : route.destination === 'invitation' && route.targetUserId ? (
              <InvitationPage
                key={route.targetUserId}
                userId={route.targetUserId}
                service={session.invitations}
                cache={session.authority}
              />
            ) : route.destination === 'sessions' ? (
              <OwnedSessionsPage
                service={session.sessions}
                cache={session.authority}
                logout={() => {
                  void session.coordinator.logout();
                }}
              />
            ) : route.destination === 'dashboard' ? (
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
      navigate('/login', true);
  }, [credential, route, navigate]);
  const statusOnly =
    credential.status !== 'anonymous' &&
    ((!retained && credential.status !== 'authenticated') ||
      authority.status !== 'authenticated' ||
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
    <BrandProvider brand={brand}>
      <p
        className="visually-hidden"
        role="status"
        aria-atomic="true"
        aria-label="Application navigation status"
      >
        {title} — {productName}
      </p>
      {frame}
    </BrandProvider>
  );
}
