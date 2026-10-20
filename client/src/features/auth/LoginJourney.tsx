import { useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';

import { ApiClient } from '@/api/ApiClient';
import { ContentState } from '@/components/ContentState';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { LoginService } from '@/features/auth/LoginService';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { browserSessionPlatform } from '@/features/auth/SessionChannel';
import { SessionContinuity } from '@/features/auth/SessionContinuity';
import { SessionCoordinator } from '@/features/auth/SessionCoordinator';
import { SessionService } from '@/features/auth/SessionService';
import { AuthorityCache, observeAuthorityResume } from '@/features/authority/AuthorityCache';
import { CurrentPrincipalService } from '@/features/authority/CurrentPrincipalService';
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
    const authority = new AuthorityCache(new CurrentPrincipalService(api).load);
    return {
      authority,
      coordinator: new SessionCoordinator({
        tokens,
        authority,
        session: new SessionService(api, csrf, tokens),
        login: new LoginService(api, csrf, tokens),
        ...browserSessionPlatform()
      }),
      schemas: new PublicSchemaService(api)
    };
  });
  useLayoutEffect(() => {
    const stopAuthorityResume = observeAuthorityResume(dependencies.authority, document, window);
    const clear = () => {
      stopAuthorityResume();
      dependencies.coordinator.dispose();
      dependencies.authority.dispose();
      dependencies.schemas.clear();
    };
    const retire = () => {
      clear();
      flushSync(() => setRetired(true));
    };
    let hidden = document.hidden;
    const visibility = () => {
      if (hidden && !document.hidden) dependencies.coordinator.resume();
      hidden = document.hidden;
    };
    const focus = () => {
      if (hidden) dependencies.coordinator.resume();
    };
    window.addEventListener('pagehide', retire);
    window.addEventListener('focus', focus);
    document.addEventListener('visibilitychange', visibility);
    void dependencies.coordinator.restore();
    return () => {
      window.removeEventListener('pagehide', retire);
      window.removeEventListener('focus', focus);
      document.removeEventListener('visibilitychange', visibility);
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
  return (
    <SessionContinuity coordinator={dependencies.coordinator}>
      <LoginPage
        service={dependencies.coordinator}
        schemas={dependencies.schemas}
        onAuthenticated={onAuthenticated}
      />
    </SessionContinuity>
  );
}
