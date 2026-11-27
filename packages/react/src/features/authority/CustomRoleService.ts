import { apiFailure, type ApiResult } from '../../api/ApiResult.js';
import { decodeCatalogRole } from './CatalogService.js';

import type { ApiClient } from '../../api/ApiClient.js';
import type { ResponseDecoder } from '../../api/decodeResponse.js';
import type { AuthorityCache } from './AuthorityCache.js';
import type { CatalogPermission, CatalogRole } from './CatalogService.js';

export function eligibleRolePermission(permission: CatalogPermission): boolean {
  return permission.managed && permission.tier === 'ADMIN_SAFE';
}

/** Sends explicit role intent to the registered Actions and refreshes authority after each attempt. */
export class CustomRoleService {
  constructor(
    private readonly api: Pick<ApiClient, 'mutate'>,
    private readonly authority: AuthorityCache,
    private readonly refresh?: (result: ApiResult<unknown>) => void
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
    if (displayed.managed || !this.validId(displayed.roleId))
      return Promise.resolve(apiFailure('protocol'));
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
    if (!confirmed || displayed.managed || !this.validId(displayed.roleId))
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

  changePermission(
    role: CatalogRole,
    permission: CatalogPermission,
    action: 'grant' | 'revoke',
    confirmed: boolean
  ): Promise<ApiResult<true>> {
    if (
      !confirmed ||
      role.managed ||
      !eligibleRolePermission(permission) ||
      !this.validId(role.roleId) ||
      !this.validId(permission.permissionId)
    )
      return Promise.resolve(apiFailure('protocol'));
    return this.refreshAfter(
      this.api.mutate(
        action === 'grant' ? 'PUT' : 'DELETE',
        `/roles/${role.roleId}/permissions/${permission.permissionId}`,
        { confirm: true },
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
    const current = this.authority.captureAuthenticationContext();
    const result = await request;
    // A lost response/publication failure may still have committed. Never replay stale intent.
    if (current()) {
      if (this.refresh) this.refresh(result);
      else await this.authority.signal('authority-changed');
    }
    return result;
  }
}
