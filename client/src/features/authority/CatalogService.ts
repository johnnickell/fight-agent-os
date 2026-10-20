import { apiFailure, type ApiResult } from '@/api/ApiResult';
import { hasExactKeys } from '@/api/decodeResponse';

import type { ApiClient } from '@/api/ApiClient';
import type { ResponseDecoder } from '@/api/decodeResponse';

export type CatalogRole = Readonly<{ roleId: string; name: string; managed: boolean }>;
export type CatalogPermission = Readonly<{
  permissionId: string;
  name: string;
  tier: 'ADMIN_SAFE' | 'SUPER_ADMIN_ONLY';
  managed: boolean;
}>;
export type CatalogPage<T> = Readonly<{
  page: number;
  perPage: number;
  totalPages: number;
  totalRecords: number;
  records: readonly T[];
}>;
export type CatalogPagination = Readonly<{ page: number; perPage: number }>;

const uuid = (value: unknown): value is string =>
  typeof value === 'string' && /^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}(?![\s\S])/.test(value);
const integer = (value: unknown, min: number, max = Number.MAX_SAFE_INTEGER): value is number =>
  typeof value === 'number' && Number.isSafeInteger(value) && value >= min && value <= max;

const role: ResponseDecoder<CatalogRole> = (value) => {
  if (
    !hasExactKeys(value, ['role_id', 'name', 'managed']) ||
    !uuid(value.role_id) ||
    typeof value.name !== 'string' ||
    value.name.length > 255 ||
    !/^ROLE_[A-Z_]+(?![\s\S])/.test(value.name) ||
    typeof value.managed !== 'boolean'
  )
    return null;
  return Object.freeze({ roleId: value.role_id, name: value.name, managed: value.managed });
};
const permission: ResponseDecoder<CatalogPermission> = (value) => {
  if (
    !hasExactKeys(value, ['permission_id', 'name', 'tier', 'managed']) ||
    !uuid(value.permission_id) ||
    typeof value.name !== 'string' ||
    value.name.length > 255 ||
    !/^[A-Z]+(?:_[A-Z]+)*(?![\s\S])/.test(value.name) ||
    (value.tier !== 'ADMIN_SAFE' && value.tier !== 'SUPER_ADMIN_ONLY') ||
    typeof value.managed !== 'boolean'
  )
    return null;
  return Object.freeze({
    permissionId: value.permission_id,
    name: value.name,
    tier: value.tier,
    managed: value.managed
  });
};

function page<T>(
  decode: ResponseDecoder<T>,
  expected: CatalogPagination
): ResponseDecoder<CatalogPage<T>> {
  return (value) => {
    if (
      !hasExactKeys(value, ['page', 'per_page', 'total_pages', 'total_records', 'records']) ||
      !integer(value.page, 1, 1000) ||
      !integer(value.per_page, 1, 100) ||
      value.page !== expected.page ||
      value.per_page !== expected.perPage ||
      !integer(value.total_pages, 1) ||
      !integer(value.total_records, 0) ||
      value.total_pages !== Math.max(1, Math.ceil(value.total_records / value.per_page)) ||
      !Array.isArray(value.records) ||
      value.records.length > value.per_page ||
      value.records.length > value.total_records
    )
      return null;
    const records: T[] = [];
    for (const item of value.records) {
      const decoded = decode(item);
      if (decoded === null) return null;
      records.push(decoded);
    }
    return Object.freeze({
      page: value.page,
      perPage: value.per_page,
      totalPages: value.total_pages,
      totalRecords: value.total_records,
      records: Object.freeze(records)
    });
  };
}

/** Reads protected catalogs without retaining authority, caching results or exposing mutations. */
export class CatalogService {
  readonly #api: ApiClient;

  constructor(api: ApiClient) {
    this.#api = api;
  }

  roles(
    pagination: CatalogPagination = { page: 1, perPage: 25 },
    signal?: AbortSignal
  ): Promise<ApiResult<CatalogPage<CatalogRole>>> {
    return this.#api.get('/roles', page(role, pagination), {
      access: 'required',
      pagination,
      ...(signal ? { signal } : {})
    });
  }

  permissions(
    pagination: CatalogPagination = { page: 1, perPage: 25 },
    signal?: AbortSignal
  ): Promise<ApiResult<CatalogPage<CatalogPermission>>> {
    return this.#api.get('/permissions', page(permission, pagination), {
      access: 'required',
      pagination,
      ...(signal ? { signal } : {})
    });
  }

  role(id: string, signal?: AbortSignal): Promise<ApiResult<CatalogRole>> {
    if (!uuid(id)) return Promise.resolve(apiFailure('protocol'));
    return this.#api.get(
      `/roles/${id}`,
      (value) => {
        const result = role(value);
        return result?.roleId === id ? result : null;
      },
      { access: 'required', ...(signal ? { signal } : {}) }
    );
  }

  permission(id: string, signal?: AbortSignal): Promise<ApiResult<CatalogPermission>> {
    if (!uuid(id)) return Promise.resolve(apiFailure('protocol'));
    return this.#api.get(
      `/permissions/${id}`,
      (value) => {
        const result = permission(value);
        return result?.permissionId === id ? result : null;
      },
      { access: 'required', ...(signal ? { signal } : {}) }
    );
  }
}
