import { SystemErrorBoundary } from '@/components/SystemErrorBoundary';
import { ActivationJourney } from '@/features/auth/ActivationJourney';
import { ShellLayout } from '@/layouts/ShellLayout';
import { SystemErrorPage } from '@/pages/SystemErrorPage';
import { ShellRoutes } from '@/routes/ShellRoutes';
import { decodeRuntimeConfiguration } from '@/runtimeConfiguration';

import type { ActivationCredential } from '@/features/auth/ActivationCredential';
import type { ThemePreferenceStore } from '@/features/theme/ThemePreference';

export function ShellApplication({
  configuration,
  pathname,
  theme,
  activation
}: {
  configuration: string | null;
  pathname: string;
  theme?: ThemePreferenceStore;
  activation?: ActivationCredential;
}) {
  const runtime = decodeRuntimeConfiguration(configuration);
  // Only validated public configuration reaches the activation composition.
  // Protected routes and principal restoration remain separately owned.
  return (
    <ShellLayout theme={theme} publicJourney={pathname === '/app/activate'}>
      <SystemErrorBoundary>
        {runtime === null ? (
          <SystemErrorPage />
        ) : pathname === '/app/activate' ? (
          <ActivationJourney runtime={runtime} credential={activation} />
        ) : (
          <ShellRoutes pathname={pathname} />
        )}
      </SystemErrorBoundary>
    </ShellLayout>
  );
}
