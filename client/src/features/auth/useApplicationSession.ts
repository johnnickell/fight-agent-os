import { createAccountSession, useAccountSession } from '@fight/react/account';

import { SwaggerService } from '@/features/swagger/SwaggerService';
import { applicationRoutes } from '@/routes/applicationRoutes';
import { IntendedRoute } from '@/routes/IntendedRoute';
import { safeDiagnostics } from '@/SafeDiagnostics';

import type { RuntimeConfiguration } from '@/runtimeConfiguration';

export function createApplicationSession(runtime: RuntimeConfiguration) {
  const account = createAccountSession(
    runtime,
    (authority) => new IntendedRoute(authority, () => applicationRoutes, window.location.origin)
  );
  const { transport, ...journeys } = account;
  return {
    ...journeys,
    swagger: new SwaggerService(
      transport,
      account.authority,
      runtime.swaggerAvailable,
      {
        accessRejected: () => {
          void account.coordinator.restore();
        },
        authorityChanged: () => {
          void account.coordinator.authorityChanged();
        }
      },
      window.location.origin,
      safeDiagnostics(runtime)
    )
  };
}
export type ApplicationSession = ReturnType<typeof createApplicationSession>;

export function useApplicationSession(runtime: RuntimeConfiguration) {
  return useAccountSession(() => createApplicationSession(runtime));
}
