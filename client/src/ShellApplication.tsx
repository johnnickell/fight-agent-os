import { SystemErrorBoundary } from '@/components/SystemErrorBoundary';
import { ShellLayout } from '@/layouts/ShellLayout';
import { SystemErrorPage } from '@/pages/SystemErrorPage';
import { ShellRoutes } from '@/routes/ShellRoutes';
import { decodeRuntimeConfiguration } from '@/runtimeConfiguration';

import type { ThemePreferenceStore } from '@/features/theme/ThemePreference';

export function ShellApplication({
  configuration,
  pathname,
  theme
}: {
  configuration: string | null;
  pathname: string;
  theme?: ThemePreferenceStore;
}) {
  const runtime = decodeRuntimeConfiguration(configuration);
  // Future application dependencies receive only the validated immutable model.
  // There is no API client, authentication restoration, or authority context yet.
  return (
    <ShellLayout theme={theme}>
      <SystemErrorBoundary>
        {runtime === null ? <SystemErrorPage /> : <ShellRoutes pathname={pathname} />}
      </SystemErrorBoundary>
    </ShellLayout>
  );
}
