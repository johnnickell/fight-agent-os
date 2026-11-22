import { ContentState } from '../../components/ContentState.js';
import { LoginPage } from '../../pages/LoginPage.js';
import { createAccountSession, useAccountSession } from './AccountSession.js';
import { SessionContinuity } from './SessionContinuity.js';

import type { ApiConfiguration } from '../../ApiConfiguration.js';

/** Mounts a standalone sign-in when the host does not keep a session above its routes */
export function LoginJourney({
  runtime,
  onAuthenticated
}: {
  runtime: ApiConfiguration;
  onAuthenticated?: (() => void) | undefined;
}) {
  const { session, retired } = useAccountSession(() =>
    createAccountSession(runtime, () => ({ clear() {}, dispose() {} }))
  );
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
