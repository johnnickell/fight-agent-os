import { useLayoutEffect } from 'react';

import { AccountLinksProvider } from '@fight/react/account';
import { ActivationJourney } from '@fight/react/account';
import { ResetCompletionJourney } from '@fight/react/account';
import { ResetRequestJourney } from '@fight/react/account';
import { BrandProvider } from '@fight/react/controls';
import { SystemErrorBoundary } from '@fight/react/controls';
import { ShellLayout } from '@fight/react/controls';
import { NotFoundPage } from '@fight/react/controls';
import { SystemErrorPage } from '@fight/react/controls';

import { agentOsBrand } from '@/agentOsBrand';
import { ApplicationJourney } from '@/routes/ApplicationJourney';
import { matchApplicationRoute, resolveAccountLink } from '@/routes/applicationRoutes';
import { useApplicationNavigation } from '@/routes/useApplicationNavigation';
import { decodeRuntimeConfiguration } from '@/runtimeConfiguration';

import type { ActivationCredential } from '@fight/react/account';
import type { ResetCredential } from '@fight/react/account';
import type { Brand } from '@fight/react/controls';
import type { ThemePreferenceStore } from '@fight/react/controls';

export function ShellApplication({
  configuration,
  pathname: initialPathname,
  theme,
  activation,
  reset,
  brand = agentOsBrand,
  onAuthenticated
}: {
  configuration: string | null;
  pathname: string;
  theme?: ThemePreferenceStore;
  activation?: ActivationCredential;
  reset?: ResetCredential;
  brand?: Brand;
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
      window.history.replaceState(null, '', '/');
    } else if (
      inertPublic &&
      route?.destination !== 'activation' &&
      route?.destination !== 'reset-completion'
    ) {
      if (
        window.location.pathname === pathname &&
        (!route || window.location.search || window.location.hash)
      )
        window.history.replaceState(null, '', route ? pathname : '/not-found');
    }
  }, [validRuntime, inertPublic, pathname, route, reset]);
  return (
    <BrandProvider brand={brand}>
      <AccountLinksProvider resolve={resolveAccountLink}>
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
      </AccountLinksProvider>
    </BrandProvider>
  );
}
