import { ApiClient } from '@/api/ApiClient';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { PasswordChangeService } from '@/features/auth/PasswordChangeService';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { PublicSchemaService } from '@/features/validation/PublicSchemaService';

import { activationSchemaSource, jsonResponse } from './activationFixtures';
import { principal } from './authorityFixtures';

import type { ApiTransport } from '@/api/ApiClient';
import type { PrincipalLoader } from '@/features/authority/AuthorityCache';

export const passwordChangeSchema = {
  ...activationSchemaSource,
  form_name: 'password_change',
  fields: {
    current_password: {
      client_field: 'currentPassword',
      rules: [
        { type: 'Required', args: [], message: 'Current password is required', depends_on: [] },
        {
          type: 'Type',
          args: ['string'],
          message: 'Current password must be a value of type: string',
          depends_on: []
        },
        {
          type: 'MinLength',
          args: ['1'],
          message: 'Current password must contain no less than 1 characters',
          depends_on: []
        },
        {
          type: 'MaxLength',
          args: ['128'],
          message: 'Current password must contain no more than 128 characters',
          depends_on: []
        }
      ]
    },
    ...activationSchemaSource.fields
  }
};

export function passwordChangeDependencies(
  post: ApiTransport,
  schema?: () => Response | Promise<Response>,
  terminal?: () => void,
  loadPrincipal: PrincipalLoader = () => Promise.resolve({ status: 'principal', principal })
) {
  const tokens = new MemoryAccessToken();
  tokens.accept('synthetic.access.signature', performance.now());
  const authority = new AuthorityCache(loadPrincipal);
  const api = new ApiClient(
    { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
    {
      accessTokens: tokens,
      fetch: (url, init) =>
        url.endsWith('/validations/password_change')
          ? Promise.resolve(schema?.() ?? jsonResponse(passwordChangeSchema))
          : post(url, init)
    }
  );
  return {
    tokens,
    authority,
    api,
    service: new PasswordChangeService(api, authority, () => {
      tokens.clear();
      void authority.signal('terminal');
      terminal?.();
    }),
    schemas: new PublicSchemaService(api)
  };
}
