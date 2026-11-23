import { apiFailure, type ApiResult } from '../../api/ApiResult.js';
import { decodeCatalogRole } from './CatalogService.js';

import type { ApiClient } from '../../api/ApiClient.js';
import type { ResponseDecoder } from '../../api/decodeResponse.js';
import type { AuthorityCache } from './AuthorityCache.js';
import type { CatalogRole } from './CatalogService.js';

/** Owns explicit revision intent and authority refetch; no Role UI or grant delegation. */
export class CustomRoleService {
  constructor(
    private readonly api: ApiClient,
    private readonly authority: AuthorityCache
  ) {}

  create(name: string): Promise<ApiResult<{ roleId: string }>> {
    return this.refreshAfter(
      this.api.mutate(
        'POST',
        '/roles',
        { name },
        (value) => {
          if (typeof value !== 'object' || value === null || Object.keys(value).length !== 1)
            return null;
          const roleId = (value as Record<string, unknown>).role_id;
          return typeof roleId === 'string' && this.validId(roleId) ? { roleId } : null;
        },
        201
      )
    );
  }

  rename(displayed: CatalogRole, name: string): Promise<ApiResult<CatalogRole>> {
    if (!this.validId(displayed.roleId)) return Promise.resolve(apiFailure('protocol'));
    return this.refreshAfter(
      this.api.mutate(
        'PATCH',
        `/roles/${displayed.roleId}`,
        { name, expected_revision: displayed.revision },
        this.roleFor(displayed.roleId)
      )
    );
  }

  remove(displayed: CatalogRole, confirmed: boolean): Promise<ApiResult<true>> {
    if (!confirmed || !this.validId(displayed.roleId))
      return Promise.resolve(apiFailure('protocol'));
    return this.refreshAfter(
      this.api.mutate(
        'DELETE',
        `/roles/${displayed.roleId}`,
        { expected_revision: displayed.revision, confirm: true },
        (value) => (value === null ? true : null)
      )
    );
  }

  private roleFor(id: string): ResponseDecoder<CatalogRole> {
    return (value) => {
      const decoded = decodeCatalogRole(value);
      return decoded?.roleId === id ? decoded : null;
    };
  }

  private validId(value: string): boolean {
    return /^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}(?![\s\S])/.test(value);
  }

  private async refreshAfter<T>(request: Promise<ApiResult<T>>): Promise<ApiResult<T>> {
    try {
      return await request;
    } finally {
      // A lost response/publication failure may still have committed. Never replay stale intent.
      await this.authority.signal('authority-changed');
    }
  }
}
