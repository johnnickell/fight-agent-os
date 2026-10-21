import { hasExactKeys } from '@/api/decodeResponse';
import { decodeCurrentPrincipal } from '@/features/authority/CurrentPrincipal';

import type { ApiClient } from '@/api/ApiClient';
import type { PrincipalLoader } from '@/features/authority/AuthorityCache';
import type { CurrentPrincipal } from '@/features/authority/CurrentPrincipal';

/** Decodes only the server's safe View, never a token, catalog or peer projection. */
export function decodePrincipalResponse(value: unknown): CurrentPrincipal | null {
  if (!hasExactKeys(value, ['user_id', 'email', 'roles', 'permissions'])) return null;
  return decodeCurrentPrincipal({
    userId: value.user_id,
    email: value.email,
    roles: value.roles,
    permissions: value.permissions
  });
}

/** Reads current authority without retaining a second principal or performing authentication retries. */
export class CurrentPrincipalService {
  readonly #api: ApiClient;

  constructor(api: ApiClient) {
    this.#api = api;
  }

  load: PrincipalLoader = async (signal) => {
    const result = await this.#api.get('/me', decodePrincipalResponse, {
      access: 'required',
      signal
    });
    if (result.ok) return { status: 'principal', principal: result.value };
    // Only the cache may apply this result: an old 401 must not end a newer session.
    if (result.error.kind === 'authentication') return { status: 'terminal' };
    if (result.error.kind === 'network' || result.error.kind === 'system')
      return { status: 'network' };
    return { status: 'protocol' };
  };
}
