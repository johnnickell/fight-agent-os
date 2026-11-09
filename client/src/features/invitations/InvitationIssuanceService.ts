import { decodeInvitationPartial } from '@/api/decodeResponse';
import { evaluateAccess } from '@/features/authority/evaluateAccess';

import type { ApiClient } from '@/api/ApiClient';
import type { ApiFailureKind } from '@/api/ApiResult';
import type { AuthorityCache } from '@/features/authority/AuthorityCache';

export const ordinaryInvitationAccess = [
  { kind: 'authenticated' },
  { kind: 'permissions', all: ['INVITE_USERS'] }
] as const;
export const elevatedInvitationAccess = [
  { kind: 'authenticated' },
  { kind: 'permissions', all: ['INVITE_USERS', 'ASSIGN_SUPER_ADMIN'] }
] as const;
export type InvitationIssuanceOutcome =
  | Readonly<{ kind: 'accepted'; userId: string }>
  | Readonly<{ kind: 'unconfirmed'; userId: string | null }>
  | Readonly<{ kind: 'rejected'; reason: ApiFailureKind }>
  | Readonly<{ kind: 'cancelled' }>;

/**
 * Supplies bounded usability feedback only; server/package email and conflict policy remain authoritative
 */
export function canonicalInvitationEmail(value: string): string | null {
  const email = value.trim().toLowerCase();
  return email.length <= 254 &&
    /^(?:"(?:[^"\\]|\\.)+"|[^\s@"\\]+)@[^\s@]+$/.test(email) &&
    ![...email].some((character) => character.charCodeAt(0) < 32 || character.charCodeAt(0) === 127)
    ? email
    : null;
}

type InvitationLifecycle = {
  invalidated: (userId: string | null) => void;
  accessRejected: () => void;
  authorityRejected: () => void;
};

/**
 * Issues fixed ordinary or explicitly confirmed elevated requests without retaining private form state
 */
export class InvitationIssuanceService {
  readonly #api: ApiClient;
  readonly #authority: AuthorityCache;
  readonly #lifecycle: InvitationLifecycle;
  #pending = false;
  #generation = 0;

  constructor(api: ApiClient, authority: AuthorityCache, lifecycle: InvitationLifecycle) {
    this.#api = api;
    this.#authority = authority;
    this.#lifecycle = lifecycle;
  }

  async submit(value: string): Promise<InvitationIssuanceOutcome> {
    return this.#issue(value, 'ordinary');
  }

  async submitElevated(
    value: string,
    confirmation: Readonly<{ email: string; confirmed: true }>
  ): Promise<InvitationIssuanceOutcome> {
    const email = canonicalInvitationEmail(value);
    if (!email || confirmation?.confirmed !== true || confirmation.email !== email)
      return { kind: 'rejected', reason: 'validation' };
    return this.#issue(email, 'super_admin');
  }

  async #issue(
    value: string,
    mode: 'ordinary' | 'super_admin'
  ): Promise<InvitationIssuanceOutcome> {
    const scope = this.#authority.captureScope();
    if (
      !scope.isCurrent() ||
      evaluateAccess(
        this.#authority.getSnapshot(),
        mode === 'ordinary' ? ordinaryInvitationAccess : elevatedInvitationAccess
      ) !== 'allowed'
    )
      return { kind: 'rejected', reason: 'authorization' };
    const email = canonicalInvitationEmail(value);
    if (!email) return { kind: 'rejected', reason: 'validation' };
    if (this.#pending) return { kind: 'cancelled' };
    this.#pending = true;
    const generation = ++this.#generation;
    let timer: ReturnType<typeof setTimeout> | undefined;
    const work = this.#api
      .mutate(
        'POST',
        '/invitations',
        mode === 'ordinary' ? { email, mode } : { email, mode, confirm_super_admin: true },
        (data) => {
          const issuance = decodeInvitationPartial(data);
          return issuance?.assignment === 'confirmed' &&
            issuance.audit === 'confirmed' &&
            issuance.delivery !== null &&
            issuance.userId
            ? Object.freeze({ userId: issuance.userId })
            : null;
        },
        201
      )
      .then((result): InvitationIssuanceOutcome => {
        // Even a late or retired completion can have committed. Retire directory/status reads,
        // but never adopt its payload as a user/status View or automatically replay creation.
        const userId = result.ok
          ? result.value.userId
          : (result.error.partialInvitation?.userId ?? null);
        this.#lifecycle.invalidated(userId);
        this.#pending = false;
        if (!scope.isCurrent() || generation !== this.#generation) return { kind: 'cancelled' };
        if (result.ok) return { kind: 'accepted', userId: result.value.userId };
        const reason = result.error.kind;
        if (reason === 'authentication') this.#lifecycle.accessRejected();
        if (reason === 'authorization') this.#lifecycle.authorityRejected();
        return ['uncertain', 'network', 'system', 'protocol', 'unavailable'].includes(reason)
          ? { kind: 'unconfirmed', userId }
          : { kind: 'rejected', reason };
      });
    const result = await Promise.race([
      work,
      new Promise<InvitationIssuanceOutcome>((resolve) => {
        timer = setTimeout(() => {
          this.#lifecycle.invalidated(null);
          resolve({ kind: 'unconfirmed', userId: null });
        }, 8_000);
      })
    ]);
    clearTimeout(timer);
    // The transport continues after this deadline: a local bound is not server cancellation.
    return scope.isCurrent() && generation === this.#generation ? result : { kind: 'cancelled' };
  }
}
