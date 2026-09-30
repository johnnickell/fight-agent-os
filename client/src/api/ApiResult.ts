export type ApiFailureKind =
  | 'validation'
  | 'authentication'
  | 'authorization'
  | 'conflict'
  | 'not-found'
  | 'bad-request'
  | 'method-not-allowed'
  | 'gone'
  | 'rate-limited'
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
  }>;
}>;

/**
 * Separates decoded feature data from failures and silent cancellation
 */
export type ApiResult<T> =
  Readonly<{ ok: true; value: T; correlationId: string | null }> | ApiFailure;

export function apiFailure(kind: ApiFailureKind, correlationId: string | null = null): ApiFailure {
  return Object.freeze({
    ok: false,
    error: Object.freeze({ kind, correlationId })
  });
}
