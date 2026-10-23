import { ApiClient } from '@/api/ApiClient';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { ResetRequestService } from '@/features/auth/ResetRequestService';
import { PublicSchemaService } from '@/features/validation/PublicSchemaService';

import { jsonResponse } from './activationFixtures';
import { loginSchemaSource } from './loginFixtures';

import type { ApiTransport } from '@/api/ApiClient';

export const resetSchemaSource = {
  ...loginSchemaSource,
  form_name: 'password_reset_request',
  fields: { email: loginSchemaSource.fields.email }
};
export const resetAccepted = () => jsonResponse({ accepted: true });
export function resetDependencies(
  post: ApiTransport = () => Promise.resolve(resetAccepted()),
  schema: () => Response | Promise<Response> = () => jsonResponse(resetSchemaSource)
) {
  const calls: string[] = [];
  const api = new ApiClient(
    { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
    {
      fetch: (url, init) => {
        calls.push(url);
        if (url.endsWith('/validations/password_reset_request')) return Promise.resolve(schema());
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
    service: new ResetRequestService(api, new CsrfProofService(api)),
    schemas: new PublicSchemaService(api),
    calls
  };
}
