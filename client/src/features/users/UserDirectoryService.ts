import { apiFailure } from '@/api/ApiResult';
import { hasExactKeys } from '@/api/decodeResponse';

import type { ApiClient } from '@/api/ApiClient';
import type { ApiResult } from '@/api/ApiResult';
import type { ResponseDecoder } from '@/api/decodeResponse';

export type DirectoryRole = Readonly<{ name: string; managed: boolean }>;
export type DirectoryUser = Readonly<{
  userId: string;
  email: string;
  state: 'pending_activation' | 'active' | 'disabled' | 'deleted';
  roles: readonly DirectoryRole[];
  createdAt: string;
  updatedAt: string;
}>;
export type DirectoryPage = Readonly<{
  page: number;
  perPage: number;
  totalPages: number;
  totalRecords: number;
  records: readonly DirectoryUser[];
}>;
const integer = (value: unknown, min: number, max = Number.MAX_SAFE_INTEGER): value is number =>
  typeof value === 'number' && Number.isSafeInteger(value) && value >= min && value <= max;
const timestamp = (value: unknown): value is string =>
  typeof value === 'string' &&
  /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/.test(value) &&
  Number.isFinite(Date.parse(value)) &&
  new Date(value).toISOString().replace('.000Z', '+00:00') === value;
const lifecycle = (value: unknown): value is DirectoryUser['state'] =>
  typeof value === 'string' &&
  ['pending_activation', 'active', 'disabled', 'deleted'].includes(value);
const decodeUser: ResponseDecoder<DirectoryUser> = (value) => {
  if (
    !hasExactKeys(value, ['user_id', 'email', 'state', 'roles', 'created_at', 'updated_at']) ||
    typeof value.user_id !== 'string' ||
    !/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(
      value.user_id
    ) ||
    // The server/package owns email syntax; decode bounded canonical display text, not another RFC policy.
    typeof value.email !== 'string' ||
    value.email.length === 0 ||
    value.email.length > 254 ||
    value.email !== value.email.toLowerCase() ||
    [...value.email].some((character) => character.charCodeAt(0) < 32 || character === '\u007f') ||
    !lifecycle(value.state) ||
    !timestamp(value.created_at) ||
    !timestamp(value.updated_at) ||
    value.updated_at < value.created_at ||
    !Array.isArray(value.roles)
  )
    return null;
  const roles: DirectoryRole[] = [];
  for (const role of value.roles) {
    if (
      !hasExactKeys(role, ['name', 'managed']) ||
      typeof role.name !== 'string' ||
      role.name.length > 255 ||
      !/^ROLE_[A-Z_]+(?![\s\S])/.test(role.name) ||
      typeof role.managed !== 'boolean' ||
      (roles.length > 0 && (roles.at(-1)?.name ?? '') >= role.name)
    )
      return null;
    roles.push(Object.freeze({ name: role.name, managed: role.managed }));
  }
  return Object.freeze({
    userId: value.user_id,
    email: value.email,
    state: value.state,
    roles: Object.freeze(roles),
    createdAt: value.created_at,
    updatedAt: value.updated_at
  });
};

/** Reads only the protected fixed-order directory; retains no list or principal. */
export class UserDirectoryService {
  readonly #api: ApiClient;
  readonly #lifecycle: { accessRejected?: () => void; authorityRejected?: () => void };
  #blocked: 'authentication' | 'authorization' | null = null;

  constructor(
    api: ApiClient,
    lifecycle: { accessRejected?: () => void; authorityRejected?: () => void } = {}
  ) {
    this.#api = api;
    this.#lifecycle = lifecycle;
  }

  /** Requires explicit recovery; a guard remount cannot replay a refused request. */
  retry(): void {
    this.#blocked = null;
  }

  async load(page = 1, perPage = 25, signal?: AbortSignal): Promise<ApiResult<DirectoryPage>> {
    if (this.#blocked) return apiFailure(this.#blocked);
    const result = await this.#api.get<DirectoryPage>(
      '/users',
      (value) => {
        if (
          !hasExactKeys(value, ['page', 'per_page', 'total_pages', 'total_records', 'records']) ||
          !integer(value.page, 1, 1000) ||
          value.page !== page ||
          !integer(value.per_page, 1, 100) ||
          value.per_page !== perPage ||
          !integer(value.total_pages, 1) ||
          !integer(value.total_records, 0) ||
          value.total_pages !== Math.max(1, Math.ceil(value.total_records / perPage)) ||
          !Array.isArray(value.records) ||
          value.records.length > perPage ||
          value.records.length > value.total_records
        )
          return null;
        const records: DirectoryUser[] = [];
        const ids = new Set<string>();
        for (const item of value.records) {
          const user = decodeUser(item);
          if (!user || ids.has(user.userId)) return null;
          const previous = records.at(-1);
          // Persisted ordering has finer time precision than the safe second-precision wire.
          if (previous && previous.createdAt > user.createdAt) return null;
          ids.add(user.userId);
          records.push(user);
        }
        return Object.freeze({
          page,
          perPage,
          totalPages: value.total_pages,
          totalRecords: value.total_records,
          records: Object.freeze(records)
        });
      },
      { access: 'required', pagination: { page, perPage }, ...(signal ? { signal } : {}) }
    );
    if (signal?.aborted) return apiFailure('cancelled');
    if (
      !result.ok &&
      (result.error.kind === 'authentication' || result.error.kind === 'authorization')
    ) {
      this.#blocked = result.error.kind;
      if (result.error.kind === 'authentication') this.#lifecycle.accessRejected?.();
      else this.#lifecycle.authorityRejected?.();
    }
    return result;
  }
}
