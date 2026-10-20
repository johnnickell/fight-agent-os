import { useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';

import { ApiClient } from '@/api/ApiClient';
import { ContentState } from '@/components/ContentState';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { LoginService } from '@/features/auth/LoginService';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { SessionService } from '@/features/auth/SessionService';
import { PublicSchemaService } from '@/features/validation/PublicSchemaService';
import { LoginPage } from '@/pages/LoginPage';

import type { RuntimeConfiguration } from '@/runtimeConfiguration';

export function LoginJourney({
  runtime,
  onAuthenticated
}: {
  runtime: RuntimeConfiguration;
  onAuthenticated?: (() => void) | undefined;
}) {
  const [retired, setRetired] = useState(false);
  const [dependencies] = useState(() => {
    const tokens = new MemoryAccessToken();
    const api = new ApiClient(runtime, { accessTokens: tokens });
    const csrf = new CsrfProofService(api);
    return {
      service: new LoginService(api, csrf, tokens),
      session: new SessionService(api, csrf, tokens),
      schemas: new PublicSchemaService(api)
    };
  });
  useLayoutEffect(() => {
    const clear = () => {
      dependencies.service.dispose();
      dependencies.session.dispose();
      dependencies.schemas.clear();
    };
    const retire = () => {
      clear();
      flushSync(() => setRetired(true));
    };
    window.addEventListener('pagehide', retire);
    return () => {
      window.removeEventListener('pagehide', retire);
      clear();
    };
  }, [dependencies]);
  if (retired)
    return (
      <ContentState
        kind="empty"
        title="Sign-in cleared"
        message="Reload this page to sign in again. Browser history does not restore credentials."
      />
    );
  return <LoginPage {...dependencies} onAuthenticated={onAuthenticated} />;
}
