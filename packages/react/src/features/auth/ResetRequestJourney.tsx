import { useEffect, useState } from 'react';

import { ApiClient } from '../../api/ApiClient.js';
import { ResetRequestPage } from '../../pages/ResetRequestPage.js';
import { PublicSchemaService } from '../validation/PublicSchemaService.js';
import { CsrfProofService } from './CsrfProofService.js';
import { ResetRequestService } from './ResetRequestService.js';

import type { ApiConfiguration } from '../../ApiConfiguration.js';

export function ResetRequestJourney({ runtime }: { runtime: ApiConfiguration }) {
  const [dependencies] = useState(() => {
    const api = new ApiClient(runtime);
    return {
      service: new ResetRequestService(api, new CsrfProofService(api)),
      schemas: new PublicSchemaService(api)
    };
  });
  useEffect(
    () => () => {
      dependencies.service.dispose();
      dependencies.schemas.clear();
    },
    [dependencies]
  );
  return <ResetRequestPage {...dependencies} />;
}
