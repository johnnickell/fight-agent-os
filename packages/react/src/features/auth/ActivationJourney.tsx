import { useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';

import { ApiClient } from '../../api/ApiClient.js';
import { ContentState } from '../../components/ContentState.js';
import { ActivationCredential } from './ActivationCredential.js';
import { ActivationService } from './ActivationService.js';
import { CsrfProofService } from './CsrfProofService.js';
import { MemoryAccessToken } from './MemoryAccessToken.js';
import { PublicSchemaService } from '../validation/PublicSchemaService.js';
import { ActivationPage } from '../../pages/ActivationPage.js';

import type { ApiConfiguration } from '../../ApiConfiguration.js';

export function ActivationJourney({
  runtime,
  credential
}: {
  runtime: ApiConfiguration;
  credential?: ActivationCredential | undefined;
}) {
  const [retired, setRetired] = useState(false);
  const [dependencies] = useState(() => {
    const tokens = new MemoryAccessToken();
    const api = new ApiClient(runtime, { accessTokens: tokens });
    return {
      service: new ActivationService(
        api,
        new CsrfProofService(api),
        credential ?? new ActivationCredential(null),
        tokens
      ),
      schemas: new PublicSchemaService(api)
    };
  });
  useLayoutEffect(() => {
    const clear = () => {
      dependencies.service.dispose();
      dependencies.schemas.clear();
    };
    const retire = () => {
      clear();
      // A history document can freeze immediately after pagehide. Remove its secret-bearing
      // form synchronously rather than waiting for a later render that may never run.
      flushSync(() => setRetired(true));
    };
    window.addEventListener('pagehide', retire);
    window.addEventListener('fight:activation-retired', retire);
    return () => {
      window.removeEventListener('pagehide', retire);
      window.removeEventListener('fight:activation-retired', retire);
      clear();
    };
  }, [dependencies]);
  if (retired)
    return (
      <section className="activation-page" aria-labelledby="activation-retired-title">
        <h1 id="activation-retired-title">Invitation cleared</h1>
        <ContentState
          kind="error"
          title="Open your invitation in a new tab"
          message="For your security this page no longer holds an invitation. Open the invitation in a new tab or ask your administrator for a new link."
        />
      </section>
    );
  return <ActivationPage {...dependencies} />;
}
