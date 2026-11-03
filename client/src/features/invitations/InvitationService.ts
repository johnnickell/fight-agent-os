import { apiFailure } from '@/api/ApiResult';
import { hasExactKeys } from '@/api/decodeResponse';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import { decodeDirectoryUser } from '@/features/users/UserDirectoryService';

import type { ApiClient } from '@/api/ApiClient';
import type { ApiResult } from '@/api/ApiResult';
import type { ResponseDecoder } from '@/api/decodeResponse';
import type { AuthorityCache } from '@/features/authority/AuthorityCache';

export const invitationRead = [{ kind: 'permissions', all: ['READ_USERS'] }] as const;
export const invitationMutation = [
  { kind: 'permissions', all: ['MANAGE_USER_INVITATIONS'] }
] as const;
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
    const delivery = await this.#api.get<Readonly<{ delivery: InvitationDelivery | null }>>(
      `/invitations/${userId}`,
      (value) => {
        if (value === null) return Object.freeze({ delivery: null });
        const status = decodeDelivery(value);
        return status?.userId === userId ? Object.freeze({ delivery: status }) : null;
      },
      { access: 'required', signal: controller.signal }
    );
    // Both are authoritative, separately admitted reads, not a cross-request snapshot.
    // Never run an identity read after refusal or retirement of the status request.
    const user = delivery.ok
      ? await this.#api.get(
          `/users/${userId}`,
          (value) => {
            const view = decodeDirectoryUser(value);
            return view?.userId === userId ? view : null;
          },
          { access: 'required', signal: controller.signal }
        )
      : delivery;
    const result: ApiResult<InvitationTarget> = !user.ok
      ? user
      : !delivery.ok
        ? delivery
        : {
            ok: true,
            value: Object.freeze({
              userId,
              email: user.value.email,
              state: user.value.state,
              delivery: delivery.value.delivery
            }),
            correlationId: user.correlationId
          };
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
      evaluateAccess(this.#authority.getSnapshot(), invitationMutation) !== 'allowed'
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
          (value) => (value === null ? (true as const) : null)
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
