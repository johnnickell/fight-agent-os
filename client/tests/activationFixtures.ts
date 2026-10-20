import { ApiClient } from '@/api/ApiClient';
import { ActivationCredential } from '@/features/auth/ActivationCredential';
import { ActivationService } from '@/features/auth/ActivationService';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { PublicSchemaService } from '@/features/validation/PublicSchemaService';

import type { ApiTransport } from '@/api/ApiClient';

const rule = (type: string, args: string, message: string, depends_on: string[] = []) => ({
  type,
  args: args === '' ? [] : [args],
  message,
  depends_on
});
export const passwordGuidance =
  'Use 8–128 characters: uppercase, lowercase, number and symbol. Known-common passwords cannot be used.';
export const activationSchemaSource = {
  schema_version: 1,
  revision: 'e'.repeat(64),
  form_name: 'invitation_activation',
  fields: {
    password: {
      client_field: 'password',
      rules: [
        rule('Required', '', 'Password is required'),
        rule('Type', 'string', 'Password must be a value of type: string'),
        rule('MinLength', '8', passwordGuidance),
        rule('MaxLength', '128', 'Password must contain no more than 128 characters')
      ]
    },
    password_confirmation: {
      client_field: 'passwordConfirmation',
      rules: [
        rule('Required', '', 'Confirmation is required'),
        rule('Type', 'string', 'Confirmation must be a value of type: string'),
        rule('MinLength', '8', 'Confirmation must contain no less than 8 characters'),
        rule('MaxLength', '128', 'Confirmation must contain no more than 128 characters'),
        rule(
          'Same',
          'password',
          'Confirmation must be the same as the value in the password field',
          ['password']
        )
      ]
    }
  }
};

export function jsonResponse(data: unknown, status = 200, success = true) {
  return new Response(JSON.stringify(success ? { status: 'success', data } : data), {
    status,
    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
  });
}

export function activationDependencies(
  post: ApiTransport,
  schema?: () => Response | Promise<Response>
) {
  const credential = new ActivationCredential({
    userId: crypto.randomUUID(),
    credential: 'a'.repeat(64)
  });
  const tokens = new MemoryAccessToken();
  const api = new ApiClient(
    { schemaVersion: 1, apiBasePath: '/api/v1' },
    {
      fetch: (url, init) => {
        if (url.endsWith('/validations/invitation_activation'))
          return Promise.resolve(schema?.() ?? jsonResponse(activationSchemaSource));
        if (url.endsWith('/auth/csrf')) {
          const expiry = Math.floor(Date.now() / 1000) + 900;
          return Promise.resolve(
            jsonResponse({ proof: `${expiry}.${'c'.repeat(64)}`, expires_at: expiry })
          );
        }
        return post(url, init);
      },
      accessTokens: tokens
    }
  );
  return {
    service: new ActivationService(api, new CsrfProofService(api), credential, tokens),
    schemas: new PublicSchemaService(api),
    credential,
    tokens
  };
}
