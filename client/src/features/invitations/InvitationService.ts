import { apiFailure } from '@/api/ApiResult';
import { hasExactKeys } from '@/api/decodeResponse';
import { evaluateAccess } from '@/features/authority/evaluateAccess';

import type { ApiClient } from '@/api/ApiClient';
import type { ApiResult } from '@/api/ApiResult';
import type { ResponseDecoder } from '@/api/decodeResponse';
import type { AuthorityCache } from '@/features/authority/AuthorityCache';

export const invitationRead = [{ kind: 'permissions', all: ['READ_USERS'] }] as const;
export const invitationChange = [
  { kind: 'permissions', all: ['READ_USERS', 'MANAGE_USER_INVITATIONS'] }
] as const;
export const isUserId = (value: string): boolean =>
  /^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(value);
export type InvitationDelivery = Readonly<{
  userId: string;
  generation: string;
  status:
    | 'pending'
    | 'claimed'
    | 'retry_pending'
    | 'delivered'
    | 'permanent_failure'
    | 'expired'
    | 'invalidated';
  expiresAt: string;
}>;
export type InvitationTarget = Readonly<{
  userId: string;
  email: string;
  state: 'pending_activation' | 'active' | 'disabled' | 'deleted';
  delivery: InvitationDelivery | null;
}>;
export type InvitationOperation = 'retry' | 'resend' | 'correct';
type InvitationLifecycle = {
  invalidated: () => void;
  accessRejected: () => void;
  authorityRejected: () => void;
};
const timestamp = (value: unknown): value is string =>
  typeof value === 'string' &&
  /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/.test(value) &&
  Number.isFinite(Date.parse(value)) &&
  new Date(value).toISOString().replace('.000Z', '+00:00') === value;
const decodeDelivery: ResponseDecoder<InvitationDelivery> = (value) => {
  if (
    !hasExactKeys(value, ['user_id', 'status', 'expires_at', 'activation_delivery_id']) ||
    typeof value.user_id !== 'string' ||
    !isUserId(value.user_id) ||
    typeof value.activation_delivery_id !== 'string' ||
    !isUserId(value.activation_delivery_id) ||
    typeof value.status !== 'string' ||
    ![
      'pending',
      'claimed',
      'retry_pending',
      'delivered',
      'permanent_failure',
      'expired',
      'invalidated'
    ].includes(value.status) ||
    !timestamp(value.expires_at)
  )
    return null;
  return Object.freeze({
    userId: value.user_id,
    generation: value.activation_delivery_id,
    status: value.status as InvitationDelivery['status'],
    expiresAt: value.expires_at
  });
};
const decodeTarget: ResponseDecoder<InvitationTarget> = (value) => {
  if (
    !hasExactKeys(value, ['user_id', 'email', 'state', 'delivery']) ||
    typeof value.user_id !== 'string' ||
    !isUserId(value.user_id) ||
    typeof value.email !== 'string' ||
    value.email.length === 0 ||
    value.email.length > 254 ||
    value.email !== value.email.toLowerCase() ||
    [...value.email].some((character) => character.charCodeAt(0) < 32 || character === '\u007f') ||
    typeof value.state !== 'string' ||
    !['pending_activation', 'active', 'disabled', 'deleted'].includes(value.state)
  )
    return null;
  const delivery = value.delivery === null ? null : decodeDelivery(value.delivery);
  if (value.delivery !== null && (!delivery || delivery.userId !== value.user_id)) return null;
  return Object.freeze({
    userId: value.user_id,
    email: value.email,
    state: value.state as InvitationTarget['state'],
    delivery
  });
};

/** Uses existing APIs only; keeps no target, form, credential or authority snapshot. */
export class InvitationService {
  readonly #api: ApiClient;
  readonly #authority: AuthorityCache;
  readonly #lifecycle: InvitationLifecycle;
  #generation = 0;
  #pending = false;
  #blocked = false;
  readonly #listeners = new Set<(userId: string) => void>();

  /** Observes target invalidation only; never publishes identity or mutation results. */
  subscribe(userId: string, listener: () => void): () => void {
    const notify = (changedUserId: string) => {
      if (changedUserId === userId) listener();
    };
    this.#listeners.add(notify);
    return () => this.#listeners.delete(notify);
  }
  #invalidate(userId: string): void {
    for (const listener of this.#listeners) listener(userId);
  }

  constructor(api: ApiClient, authority: AuthorityCache, lifecycle: InvitationLifecycle) {
    this.#api = api;
    this.#authority = authority;
    this.#lifecycle = lifecycle;
  }
  retry(): void {
    this.#blocked = false;
  }
  async load(userId: string, signal?: AbortSignal): Promise<ApiResult<InvitationTarget>> {
    const scope = this.#authority.captureScope();
    if (
      this.#blocked ||
      evaluateAccess(this.#authority.getSnapshot(), invitationRead) !== 'allowed'
    )
      return apiFailure('authorization');
    if (!isUserId(userId)) return apiFailure('protocol');
    const generation = ++this.#generation;
    const controller = new AbortController();
    let invalidated = false;
    const unsubscribe = this.subscribe(userId, () => {
      invalidated = true;
      controller.abort();
    });
    const abort = () => controller.abort();
    signal?.addEventListener('abort', abort, { once: true });
    scope.signal.addEventListener('abort', abort, { once: true });
    if (signal?.aborted || !scope.isCurrent()) controller.abort();
    const timer = setTimeout(abort, 8_000);
    const result = await this.#api.get(
      `/invitations/${userId}`,
      (value) => {
        const target = decodeTarget(value);
        return target?.userId === userId ? target : null;
      },
      { access: 'required', signal: controller.signal }
    );
    clearTimeout(timer);
    unsubscribe();
    signal?.removeEventListener('abort', abort);
    scope.signal.removeEventListener('abort', abort);
    if (invalidated || generation !== this.#generation || !scope.isCurrent() || signal?.aborted)
      return apiFailure('cancelled');
    if (controller.signal.aborted) return apiFailure('network');
    this.#denial(result);
    return result.ok ? result : apiFailure(result.error.kind, result.error.correlationId);
  }
  async change(
    userId: string,
    operation: InvitationOperation,
    email?: string
  ): Promise<ApiResult<true>> {
    const scope = this.#authority.captureScope();
    if (
      !scope.isCurrent() ||
      evaluateAccess(this.#authority.getSnapshot(), invitationChange) !== 'allowed'
    )
      return apiFailure('authorization');
    if (!isUserId(userId) || (operation === 'correct' && !email)) return apiFailure('protocol');
    if (this.#pending) return apiFailure('conflict');
    this.#pending = true;
    this.#invalidate(userId);
    let timer: ReturnType<typeof setTimeout> | undefined;
    let timedOut = false;
    const invalidateOutcome = () => {
      this.#lifecycle.invalidated();
      this.#invalidate(userId);
    };
    const result = await Promise.race([
      this.#api
        .mutate(
          operation === 'correct' ? 'PATCH' : 'POST',
          `/invitations/${userId}/${operation === 'correct' ? 'email' : operation}`,
          operation === 'correct' ? { email } : null,
          (value) => {
            const delivery = decodeDelivery(value);
            return delivery?.userId === userId ? (true as const) : null;
          }
        )
        .then((outcome) => {
          // A local deadline cannot cancel a server mutation. A later transport
          // completion must also retire any status adopted after that deadline.
          if (timedOut) invalidateOutcome();
          return outcome;
        }),
      new Promise<ApiResult<true>>((resolve) => {
        timer = setTimeout(() => {
          timedOut = true;
          resolve(apiFailure('network'));
        }, 8_000);
      })
    ]);
    clearTimeout(timer);
    this.#pending = false;
    invalidateOutcome();
    if (!scope.isCurrent()) return apiFailure('cancelled');
    this.#denial(result);
    return result.ok ? result : apiFailure(result.error.kind, result.error.correlationId);
  }
  #denial(result: ApiResult<unknown>): void {
    if (!result.ok && result.error.kind === 'authentication') {
      this.#blocked = true;
      this.#lifecycle.accessRejected();
    } else if (!result.ok && result.error.kind === 'authorization') {
      this.#blocked = true;
      this.#lifecycle.authorityRejected();
    }
  }
}
