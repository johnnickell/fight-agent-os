import { apiFailure } from '@/api/ApiResult';
import { hasExactKeys } from '@/api/decodeResponse';
import { evaluateAccess } from '@/features/authority/evaluateAccess';

import type { ApiClient } from '@/api/ApiClient';
import type { ApiResult } from '@/api/ApiResult';
import type { ResponseDecoder } from '@/api/decodeResponse';
import type { AuthorityCache } from '@/features/authority/AuthorityCache';

export type OwnedSession = Readonly<{
  sessionId: string;
  browserFamily: 'Chrome' | 'Edge' | 'Firefox' | 'Safari' | 'Unknown';
  deviceClass: 'desktop' | 'mobile' | 'tablet' | 'unknown';
  createdAt: string;
  lastActivityAt: string;
  idleExpiresAt: string;
  absoluteExpiresAt: string;
  remembered: boolean;
  current: boolean;
}>;
export type OwnedSessionsPage = Readonly<{
  page: number;
  perPage: number;
  totalPages: number;
  totalRecords: number;
  records: readonly OwnedSession[];
}>;

type SessionLifecycle = {
  accessRejected?: () => void;
  authorityRejected?: () => void;
  currentSession?: (id: string) => void;
  revoked?: (id: string) => void;
  invalidated?: () => void;
};

const integer = (value: unknown, min: number, max = Number.MAX_SAFE_INTEGER): value is number =>
  typeof value === 'number' && Number.isSafeInteger(value) && value >= min && value <= max;
const timestamp = (value: unknown): value is string =>
  typeof value === 'string' &&
  /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/.test(value) &&
  Number.isFinite(Date.parse(value)) &&
  new Date(value).toISOString().replace('.000Z', '+00:00') === value;

const decodeSession: ResponseDecoder<OwnedSession> = (value) => {
  if (
    !hasExactKeys(value, [
      'session_id',
      'browser_family',
      'device_class',
      'created_at',
      'last_activity_at',
      'idle_expires_at',
      'absolute_expires_at',
      'remembered',
      'current'
    ]) ||
    typeof value.session_id !== 'string' ||
    !/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(
      value.session_id
    ) ||
    !['Chrome', 'Edge', 'Firefox', 'Safari', 'Unknown'].includes(String(value.browser_family)) ||
    typeof value.browser_family !== 'string' ||
    !['desktop', 'mobile', 'tablet', 'unknown'].includes(String(value.device_class)) ||
    typeof value.device_class !== 'string' ||
    !timestamp(value.created_at) ||
    !timestamp(value.last_activity_at) ||
    !timestamp(value.idle_expires_at) ||
    !timestamp(value.absolute_expires_at) ||
    value.created_at > value.last_activity_at ||
    // Public seconds can collapse distinct precise instants; only reversed lifetimes are invalid here.
    value.last_activity_at > value.idle_expires_at ||
    value.idle_expires_at > value.absolute_expires_at ||
    typeof value.remembered !== 'boolean' ||
    typeof value.current !== 'boolean'
  )
    return null;
  return Object.freeze({
    sessionId: value.session_id,
    browserFamily: value.browser_family as OwnedSession['browserFamily'],
    deviceClass: value.device_class as OwnedSession['deviceClass'],
    createdAt: value.created_at,
    lastActivityAt: value.last_activity_at,
    idleExpiresAt: value.idle_expires_at,
    absoluteExpiresAt: value.absolute_expires_at,
    remembered: value.remembered,
    current: value.current
  });
};

/** Reads a fixed protected endpoint; retains no rows, credentials or authority. */
export class OwnedSessionsService {
  readonly #api: ApiClient;
  readonly #lifecycle: SessionLifecycle;
  readonly #authority: AuthorityCache | undefined;
  readonly #listeners = new Set<() => void>();
  #blocked: 'authentication' | 'authorization' | null = null;

  constructor(api: ApiClient, lifecycle: SessionLifecycle = {}, authority?: AuthorityCache) {
    this.#api = api;
    this.#lifecycle = lifecycle;
    this.#authority = authority;
  }

  /** Permits a new read only after an explicit user retry, never a guard remount. */
  retry(): void {
    this.#blocked = null;
  }

  subscribe = (listener: () => void): (() => void) => {
    this.#listeners.add(listener);
    return () => this.#listeners.delete(listener);
  };

  invalidate = (): void => {
    for (const listener of this.#listeners) listener();
  };

  /** One explicit mutation; scope fences late outcomes and no denied/uncertain mutation is replayed. */
  async revoke(sessionId: string): Promise<ApiResult<true>> {
    const scope = this.#authority?.captureScope();
    if (
      !this.#authority ||
      !scope?.isCurrent() ||
      evaluateAccess(this.#authority.getSnapshot(), [
        { kind: 'permissions', all: ['DELETE_OWN_SESSIONS'] }
      ]) !== 'allowed'
    )
      return apiFailure('authorization');
    if (
      !/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(sessionId)
    )
      return apiFailure('protocol');
    let timer: ReturnType<typeof setTimeout> | undefined;
    const result = await Promise.race([
      this.#api.mutate('DELETE', `/me/sessions/${sessionId}`, {}, (value) =>
        value === null ? (true as const) : null
      ),
      new Promise<ApiResult<true>>((resolve) => {
        timer = setTimeout(() => resolve(apiFailure('network')), 8_000);
      })
    ]);
    clearTimeout(timer);
    if (!scope.isCurrent()) return apiFailure('cancelled');
    if (result.ok) this.#lifecycle.revoked?.(sessionId);
    else if (result.error.kind === 'conflict' || result.error.kind === 'current-session')
      this.#lifecycle.invalidated?.();
    // Conflicts and uncertainty also converge locally; only observed commits broadcast target identity.
    this.invalidate();
    if (!result.ok && result.error.kind === 'authentication') this.#lifecycle.accessRejected?.();
    if (!result.ok && result.error.kind === 'authorization') this.#lifecycle.authorityRejected?.();
    return result;
  }

  async load(page = 1, perPage = 25, signal?: AbortSignal): Promise<ApiResult<OwnedSessionsPage>> {
    const scope = this.#authority?.captureScope();
    if (this.#blocked) return apiFailure(this.#blocked);
    const result = await this.#api.get<OwnedSessionsPage>(
      '/me/sessions',
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
          value.records.length !==
            Math.min(perPage, Math.max(0, value.total_records - (page - 1) * perPage))
        )
          return null;
        const records: OwnedSession[] = [];
        const ids = new Set<string>();
        let currentCount = 0;
        for (const item of value.records) {
          const session = decodeSession(item);
          if (!session || ids.has(session.sessionId)) return null;
          const previous = records.at(-1);
          // Server ordering uses precise persisted creation time; public timestamps have second precision.
          if (previous && previous.createdAt > session.createdAt) return null;
          ids.add(session.sessionId);
          if (session.current && ++currentCount > 1) return null;
          records.push(session);
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
    if (signal?.aborted || (scope && !scope.isCurrent())) return apiFailure('cancelled');
    if (result.ok) {
      const current = result.value.records.find((record) => record.current);
      if (current) this.#lifecycle.currentSession?.(current.sessionId);
    }
    if (
      !result.ok &&
      (result.error.kind === 'authentication' || result.error.kind === 'authorization')
    ) {
      // Remember only a safe refusal, not authority or rows. Fresh /me cannot replay the denied operation.
      this.#blocked = result.error.kind;
      if (result.error.kind === 'authentication') this.#lifecycle.accessRejected?.();
      else this.#lifecycle.authorityRejected?.();
    }
    return result;
  }
}
