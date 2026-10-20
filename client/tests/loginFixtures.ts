import { ApiClient } from '@/api/ApiClient';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { LoginService } from '@/features/auth/LoginService';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { PublicSchemaService } from '@/features/validation/PublicSchemaService';

import { jsonResponse } from './activationFixtures';

import type { ApiTransport } from '@/api/ApiClient';

const rule = (type: string, args: string[], message: string) => ({
  type,
  args,
  message,
  depends_on: []
});
export const loginSchemaSource = {
  schema_version: 1,
  revision: 'a'.repeat(64),
  form_name: 'login',
  fields: {
    email: {
      client_field: 'email',
      rules: [
        rule('Required', [], 'Email is required'),
        rule('Type', ['string'], 'Email must be a value of type: string'),
        rule('MinLength', ['1'], 'Email must contain no less than 1 characters'),
        rule('MaxLength', ['254'], 'Email must contain no more than 254 characters')
      ]
    },
    password: {
      client_field: 'password',
      rules: [
        rule('Required', [], 'Password is required'),
        rule('Type', ['string'], 'Password must be a value of type: string'),
        rule('MinLength', ['1'], 'Password must contain no less than 1 characters'),
        rule('MaxLength', ['128'], 'Password must contain no more than 128 characters')
      ]
    }
  }
};

export const loginSuccess = () =>
  jsonResponse({ access_token: 'header.payload.signature', issued_at: 1000, expires_at: 1900 });
export const loginRejected = () =>
  jsonResponse({ status: 'error', message: 'Unauthorized.' }, 401, false);

export function loginDependencies(
  post: ApiTransport = () => Promise.resolve(loginRejected()),
  schema?: () => Response | Promise<Response>
) {
  const tokens = new MemoryAccessToken();
  const csrfCalls: string[] = [];
  const api = new ApiClient(
    { schemaVersion: 1, apiBasePath: '/api/v1' },
    {
      fetch: (url, init) => {
        if (url.endsWith('/validations/login'))
          return Promise.resolve(schema?.() ?? jsonResponse(loginSchemaSource));
        if (url.endsWith('/auth/csrf')) {
          csrfCalls.push('bootstrap');
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
    service: new LoginService(api, new CsrfProofService(api), tokens),
    schemas: new PublicSchemaService(api),
    tokens,
    csrfCalls
  };
}
