import { AccountLink } from './AccountLinks.js';
import { useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';

import { ApiClient } from '../../api/ApiClient.js';
import { ContentState } from '../../components/ContentState.js';
import { CsrfProofService } from './CsrfProofService.js';
import { ResetCompletionService } from './ResetCompletionService.js';
import { ResetCredential } from './ResetCredential.js';
import { broadcastPasswordReset } from './SessionChannel.js';
import { PublicSchemaService } from '../validation/PublicSchemaService.js';
import { ResetCompletionPage } from '../../pages/ResetCompletionPage.js';

import type { ApiConfiguration } from '../../ApiConfiguration.js';

/** Public possession journey: no cookie restoration, principal cache or parallel session graph. */
export function ResetCompletionJourney({
  runtime,
  credential
}: {
  runtime: ApiConfiguration;
  credential?: ResetCredential | undefined;
}) {
  const [retired, setRetired] = useState(false);
  const [dependencies] = useState(() => {
    const api = new ApiClient(runtime);
    return {
      service: new ResetCompletionService(
        api,
        new CsrfProofService(api),
        credential ?? new ResetCredential(null),
        broadcastPasswordReset
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
      flushSync(() => setRetired(true));
    };
    window.addEventListener('pagehide', retire);
    window.addEventListener('fight:reset-retired', retire);
    return () => {
      window.removeEventListener('pagehide', retire);
      window.removeEventListener('fight:reset-retired', retire);
      clear();
    };
  }, [dependencies]);
  if (retired)
    return (
      <section className="activation-page" aria-label="Password reset cleared">
        <h1>Password reset cleared</h1>
        <ContentState
          kind="empty"
          title="Open a new reset link"
          message="For your security this page no longer holds a reset link. Browser history cannot restore it."
        />
        <AccountLink to="requestReset">Request a new link</AccountLink>
      </section>
    );
  return <ResetCompletionPage {...dependencies} />;
}
