import { ContentState } from '@/components/ContentState';
import { SessionContinuity } from '@/features/auth/SessionContinuity';
import { useApplicationSession } from '@/features/auth/useApplicationSession';
import { LoginPage } from '@/pages/LoginPage';

import type { RuntimeConfiguration } from '@/runtimeConfiguration';

/** Standalone login composition; production frame uses the same sole session graph */
export function LoginJourney({
  runtime,
  onAuthenticated
}: {
  runtime: RuntimeConfiguration;
  onAuthenticated?: (() => void) | undefined;
}) {
  const { session, retired } = useApplicationSession(runtime);
  if (retired)
    return (
      <ContentState
        kind="empty"
        title="Sign-in cleared"
        message="Reload this page to sign in again. Browser history does not restore credentials."
      />
    );
  return (
    <SessionContinuity coordinator={session.coordinator}>
      <LoginPage
        service={session.coordinator}
        schemas={session.schemas}
        onAuthenticated={onAuthenticated}
      />
    </SessionContinuity>
  );
}
