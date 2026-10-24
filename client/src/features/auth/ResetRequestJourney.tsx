import { useEffect, useState } from 'react';

import { ApiClient } from '@/api/ApiClient';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { ResetRequestService } from '@/features/auth/ResetRequestService';
import { PublicSchemaService } from '@/features/validation/PublicSchemaService';
import { ResetRequestPage } from '@/pages/ResetRequestPage';

import type { RuntimeConfiguration } from '@/runtimeConfiguration';

export function ResetRequestJourney({ runtime }: { runtime: RuntimeConfiguration }) {
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
