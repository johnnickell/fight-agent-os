import { useLayoutEffect } from 'react';

import { SystemErrorBoundary } from '@/components/SystemErrorBoundary';
import { ActivationJourney } from '@/features/auth/ActivationJourney';
import { ResetCompletionJourney } from '@/features/auth/ResetCompletionJourney';
import { ResetRequestJourney } from '@/features/auth/ResetRequestJourney';
import { ShellLayout } from '@/layouts/ShellLayout';
import { NotFoundPage } from '@/pages/NotFoundPage';
import { SystemErrorPage } from '@/pages/SystemErrorPage';
import { ApplicationJourney } from '@/routes/ApplicationJourney';
import { matchApplicationRoute } from '@/routes/applicationRoutes';
import { useApplicationNavigation } from '@/routes/useApplicationNavigation';
import { decodeRuntimeConfiguration } from '@/runtimeConfiguration';

import type { ActivationCredential } from '@/features/auth/ActivationCredential';
import type { ResetCredential } from '@/features/auth/ResetCredential';
import type { ThemePreferenceStore } from '@/features/theme/ThemePreference';

export function ShellApplication({
  configuration,
  pathname: initialPathname,
  theme,
  activation,
  reset,
  onAuthenticated
}: {
  configuration: string | null;
  pathname: string;
  theme?: ThemePreferenceStore;
  activation?: ActivationCredential;
  reset?: ResetCredential;
  onAuthenticated?: () => void;
}) {
  const runtime = decodeRuntimeConfiguration(configuration);
  const validRuntime = runtime !== null;
  const { pathname, navigate } = useApplicationNavigation(initialPathname);
  const route = matchApplicationRoute(pathname);
  const inertPublic =
    route?.destination === 'activation' ||
    route?.destination === 'recovery' ||
    route?.destination === 'reset-completion' ||
    !route;
  useLayoutEffect(() => {
    if (!validRuntime || route?.destination !== 'reset-completion') reset?.clear();
    if (!validRuntime) {
      window.history.replaceState(null, '', '/app');
    } else if (
      inertPublic &&
      route?.destination !== 'activation' &&
      route?.destination !== 'reset-completion'
    ) {
      if (
        window.location.pathname === pathname &&
        (!route || window.location.search || window.location.hash)
      )
        window.history.replaceState(null, '', route ? pathname : '/app/not-found');
    }
  }, [validRuntime, inertPublic, pathname, route, reset]);
  return (
    <SystemErrorBoundary
      fallback={
        <ShellLayout theme={theme} publicJourney>
          <SystemErrorPage />
        </ShellLayout>
      }
    >
      {runtime === null ? (
        <ShellLayout theme={theme}>
          <SystemErrorPage />
        </ShellLayout>
      ) : inertPublic ? (
        <ShellLayout theme={theme} publicJourney>
          {route?.destination === 'activation' ? (
            <ActivationJourney runtime={runtime} credential={activation} />
          ) : route?.destination === 'reset-completion' ? (
            <ResetCompletionJourney runtime={runtime} credential={reset} />
          ) : route?.destination === 'recovery' ? (
            <ResetRequestJourney runtime={runtime} />
          ) : (
            <NotFoundPage />
          )}
        </ShellLayout>
      ) : (
        <ApplicationJourney
          runtime={runtime}
          pathname={pathname}
          navigate={navigate}
          theme={theme}
          onAuthenticated={onAuthenticated}
        />
      )}
    </SystemErrorBoundary>
  );
}
