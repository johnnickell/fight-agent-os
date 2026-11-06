export type InvitationPartial = Readonly<{
  userId: string | null;
  assignment: 'confirmed' | 'unconfirmed' | 'unknown';
  audit: 'confirmed' | 'unconfirmed';
  delivery: Readonly<{
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
  }> | null;
}>;

export type ApiFailureKind =
  | 'validation'
  | 'authentication'
  | 'authorization'
  | 'conflict'
  | 'current-session'
  | 'not-found'
  | 'bad-request'
  | 'method-not-allowed'
  | 'gone'
  | 'rate-limited'
  | 'precondition'
  | 'rule-violation'
  | 'unavailable'
  | 'uncertain'
  | 'system'
  | 'protocol'
  | 'network'
  | 'cancelled';

/**
 * Carries only safe diagnostic metadata, never transport objects or server messages
 */
export type ApiFailure = Readonly<{
  ok: false;
  error: Readonly<{
    kind: ApiFailureKind;
    correlationId: string | null;
    /** Untrusted bounded validation detail; only a feature with a matching public schema may display it */
    fields?: Readonly<Record<string, readonly string[]>>;
    partialInvitation?: InvitationPartial;
  }>;
}>;

/**
 * Separates decoded feature data from failures and silent cancellation
 */
export type ApiResult<T> =
  Readonly<{ ok: true; value: T; correlationId: string | null }> | ApiFailure;

export function apiFailure(
  kind: ApiFailureKind,
  correlationId: string | null = null,
  fields?: Readonly<Record<string, readonly string[]>>,
  partialInvitation?: InvitationPartial
): ApiFailure {
  return Object.freeze({
    ok: false,
    error: Object.freeze({
      kind,
      correlationId,
      ...(fields ? { fields } : {}),
      ...(partialInvitation ? { partialInvitation } : {})
    })
  });
}
