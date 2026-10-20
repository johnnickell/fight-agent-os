import { SystemErrorBoundary } from '@/components/SystemErrorBoundary';
import { ActivationJourney } from '@/features/auth/ActivationJourney';
import { LoginJourney } from '@/features/auth/LoginJourney';
import { ShellLayout } from '@/layouts/ShellLayout';
import { LoginRecoveryPage } from '@/pages/LoginPage';
import { SystemErrorPage } from '@/pages/SystemErrorPage';
import { ShellRoutes } from '@/routes/ShellRoutes';
import { decodeRuntimeConfiguration } from '@/runtimeConfiguration';

import type { ActivationCredential } from '@/features/auth/ActivationCredential';
import type { ThemePreferenceStore } from '@/features/theme/ThemePreference';

export function ShellApplication({
  configuration,
  pathname,
  theme,
  activation,
  onAuthenticated
}: {
  configuration: string | null;
  pathname: string;
  theme?: ThemePreferenceStore;
  activation?: ActivationCredential;
  onAuthenticated?: () => void;
}) {
  const runtime = decodeRuntimeConfiguration(configuration);
  // Only validated public configuration reaches the activation composition.
  // Protected routes and principal restoration remain separately owned.
  return (
    <ShellLayout
      theme={theme}
      publicJourney={pathname === '/app/activate' || pathname.startsWith('/app/login')}
    >
      <SystemErrorBoundary>
        {runtime === null ? (
          <SystemErrorPage />
        ) : pathname === '/app/login' ? (
          <LoginJourney runtime={runtime} onAuthenticated={onAuthenticated} />
        ) : pathname === '/app/login/recovery' ? (
          <LoginRecoveryPage />
        ) : pathname === '/app/activate' ? (
          <ActivationJourney runtime={runtime} credential={activation} />
        ) : (
          <ShellRoutes pathname={pathname} />
        )}
      </SystemErrorBoundary>
    </ShellLayout>
  );
}
