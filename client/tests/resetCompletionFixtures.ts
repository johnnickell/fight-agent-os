import { ApiClient } from '@/api/ApiClient';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { ResetCompletionService } from '@/features/auth/ResetCompletionService';
import { ResetCredential } from '@/features/auth/ResetCredential';
import { PublicSchemaService } from '@/features/validation/PublicSchemaService';

import { activationSchemaSource, jsonResponse } from './activationFixtures';

import type { ApiTransport } from '@/api/ApiClient';

export const resetCompletionSchema = {
  ...activationSchemaSource,
  form_name: 'password_reset_completion'
};
export function resetCompletionDependencies(
  post: ApiTransport,
  schema?: () => Response | Promise<Response>,
  completed: (userId: string) => void = () => {}
) {
  const userId = crypto.randomUUID();
  const credential = new ResetCredential({ userId, credential: 'synthetic-reset-marker' });
  const api = new ApiClient(
    { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
    {
      fetch: (url, init) => {
        if (url.endsWith('/validations/password_reset_completion'))
          return Promise.resolve(schema?.() ?? jsonResponse(resetCompletionSchema));
        if (url.endsWith('/auth/csrf')) {
          const expiry = Math.floor(Date.now() / 1000) + 900;
          return Promise.resolve(
            jsonResponse({ proof: `${expiry}.${'c'.repeat(64)}`, expires_at: expiry })
          );
        }
        return post(url, init);
      }
    }
  );
  return {
    userId,
    credential,
    api,
    service: new ResetCompletionService(api, new CsrfProofService(api), credential, completed),
    schemas: new PublicSchemaService(api)
  };
}
