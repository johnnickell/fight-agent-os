import { apiFailure, type ApiFailureKind, type ApiResult } from './ApiResult';

export type ResponseDecoder<T> = (data: unknown) => T | null;

/**
 * Accepts only JSON object shapes with the exact own keys selected by the codec
 */
export function hasExactKeys(
  value: unknown,
  keys: readonly string[],
): value is Record<string, unknown> {
  return (
    typeof value === 'object' &&
    value !== null &&
    !Array.isArray(value) &&
    Object.keys(value).length === keys.length &&
    keys.every((key) => Object.hasOwn(value, key))
  );
}

const errors: Readonly<Record<number, readonly [ApiFailureKind, string]>> = {
  400: ['bad-request', 'Bad request.'],
  401: ['authentication', 'Unauthorized.'],
  403: ['authorization', 'Forbidden.'],
  404: ['not-found', 'Not found.'],
  405: ['method-not-allowed', 'Method not allowed.'],
  409: ['conflict', 'Conflict.'],
  410: ['gone', 'Gone.'],
  429: ['rate-limited', 'Too many requests.'],
};

/**
 * Validates the common failure shape without returning arbitrary field messages
 */
function isValidationData(data: unknown): boolean {
  if (!hasExactKeys(data, ['fields'])) return false;
  const fields = data.fields;
  if (typeof fields !== 'object' || fields === null || Array.isArray(fields)) {
    return false;
  }
  const entries = Object.entries(fields);
  return (
    entries.length > 0 &&
    entries.every(
      ([field, messages]) =>
        /^[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)*(?![\s\S])/.test(field) &&
        Array.isArray(messages) &&
        messages.length > 0 &&
        messages.every(
          (message: unknown) =>
            typeof message === 'string' && message.length > 0,
        ) &&
        new Set(messages).size === messages.length,
    )
  );
}

/**
 * Maps JSend and HTTP together, rejecting contradictory or unrecognized envelopes
 */
export function decodeResponse<T>(
  status: number,
  envelope: unknown,
  decode: ResponseDecoder<T>,
  correlationId: string | null,
): ApiResult<T> {
  if (hasExactKeys(envelope, ['status', 'data'])) {
    if (status === 200 && envelope.status === 'success') {
      const value = decode(envelope.data);
      return value === null
        ? apiFailure('protocol', correlationId)
        : Object.freeze({ ok: true, value, correlationId });
    }
    if (
      (status === 400 || status === 422) &&
      envelope.status === 'fail' &&
      isValidationData(envelope.data)
    ) {
      return apiFailure('validation', correlationId);
    }
  }
  if (
    hasExactKeys(envelope, ['status', 'message']) &&
    envelope.status === 'error'
  ) {
    const expected =
      status >= 500 && status <= 599
        ? (['system', 'Internal server error.'] as const)
        : errors[status];
    if (expected && envelope.message === expected[1]) {
      return apiFailure(expected[0], correlationId);
    }
  }
  return apiFailure('protocol', correlationId);
}
