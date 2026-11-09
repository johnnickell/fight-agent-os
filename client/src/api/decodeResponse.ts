import {
  apiFailure,
  type ApiFailureKind,
  type ApiResult,
  type InvitationPartial
} from '@/api/ApiResult';

export type ResponseDecoder<T> = (data: unknown) => T | null;

/**
 * Accepts only JSON object shapes with the exact own keys selected by the codec
 */
export function hasExactKeys(
  value: unknown,
  keys: readonly string[]
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
  400: ['bad-request', 'Invalid input.'],
  401: ['authentication', 'Authentication required.'],
  403: ['authorization', 'Permission denied.'],
  404: ['not-found', 'Resource not found.'],
  405: ['method-not-allowed', 'Method not allowed.'],
  406: ['bad-request', 'Not acceptable.'],
  409: ['conflict', 'State conflict.'],
  410: ['gone', 'Resource gone.'],
  412: ['precondition', 'Precondition failed.'],
  413: ['bad-request', 'Request too large.'],
  415: ['bad-request', 'Unsupported media type.'],
  422: ['rule-violation', 'Rule violation.'],
  429: ['rate-limited', 'Too many requests.']
};

/**
 * Retains only bounded structured validation detail for explicit feature-level safe mapping
 */
function validationFields(data: unknown): Readonly<Record<string, readonly string[]>> | null {
  if (!hasExactKeys(data, ['fields'])) return null;
  const fields: unknown = data.fields;
  if (typeof fields !== 'object' || fields === null || Array.isArray(fields)) {
    return null;
  }
  const entries = Object.entries(fields as Record<string, unknown>);
  if (entries.length === 0 || entries.length > 64) return null;
  const safe: Record<string, readonly string[]> = {};
  for (const [field, messages] of entries) {
    if (
      field.length > 196 ||
      !/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(field) ||
      !Array.isArray(messages) ||
      messages.length === 0 ||
      messages.length > 16 ||
      !messages.every(
        (message: unknown) =>
          typeof message === 'string' &&
          message.length > 0 &&
          message.length <= 256 &&
          ![...message].some(
            (char) => char.charCodeAt(0) < 32 || char.charCodeAt(0) === 127 || '<>'.includes(char)
          )
      ) ||
      new Set(messages).size !== messages.length
    )
      return null;
    safe[field] = Object.freeze([...(messages as string[])]);
  }
  return Object.freeze(safe);
}

const uuid = (value: unknown): value is string =>
  typeof value === 'string' &&
  /^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(value);

/** Retains only the closed, secretless partial-outcome contract, never retry authority. */
export function decodeInvitationPartial(value: unknown): InvitationPartial | null {
  if (
    !hasExactKeys(value, ['user_id', 'assignment', 'audit', 'delivery']) ||
    !(value.user_id === null || uuid(value.user_id)) ||
    typeof value.assignment !== 'string' ||
    !['confirmed', 'unconfirmed', 'unknown'].includes(value.assignment) ||
    typeof value.audit !== 'string' ||
    !['confirmed', 'unconfirmed'].includes(value.audit)
  )
    return null;
  let delivery: InvitationPartial['delivery'] = null;
  if (value.delivery !== null) {
    const item: unknown = value.delivery;
    if (
      !hasExactKeys(item, ['user_id', 'activation_delivery_id', 'status', 'expires_at']) ||
      !uuid(item.user_id) ||
      item.user_id !== value.user_id ||
      !uuid(item.activation_delivery_id) ||
      typeof item.status !== 'string' ||
      ![
        'pending',
        'claimed',
        'retry_pending',
        'delivered',
        'permanent_failure',
        'expired',
        'invalidated'
      ].includes(item.status) ||
      typeof item.expires_at !== 'string' ||
      !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/.test(item.expires_at) ||
      !Number.isFinite(Date.parse(item.expires_at)) ||
      new Date(item.expires_at).toISOString().replace('.000Z', '+00:00') !== item.expires_at
    )
      return null;
    delivery = Object.freeze({
      userId: item.user_id,
      generation: item.activation_delivery_id,
      status: item.status as NonNullable<InvitationPartial['delivery']>['status'],
      expiresAt: item.expires_at
    });
  }
  return Object.freeze({
    userId: value.user_id,
    assignment: value.assignment as InvitationPartial['assignment'],
    audit: value.audit as InvitationPartial['audit'],
    delivery
  });
}

/**
 * Maps JSend and HTTP together, rejecting contradictory or unrecognized envelopes
 */
export function decodeResponse<T>(
  status: number,
  envelope: unknown,
  decode: ResponseDecoder<T>,
  correlationId: string | null,
  successStatus: 200 | 201 = 200
): ApiResult<T> {
  if (hasExactKeys(envelope, ['status', 'data'])) {
    if (status === successStatus && envelope.status === 'success') {
      const value = decode(envelope.data);
      return value === null
        ? apiFailure('protocol', correlationId)
        : Object.freeze({ ok: true, value, correlationId });
    }
    if ((status === 400 || status === 422) && envelope.status === 'fail') {
      const fields = validationFields(envelope.data);
      if (fields) return apiFailure('validation', correlationId, fields);
    }
    if (
      status === 409 &&
      envelope.status === 'fail' &&
      hasExactKeys(envelope.data, ['message', 'intent']) &&
      envelope.data.intent === 'use_logout' &&
      envelope.data.message === 'Use Logout for the current session.'
    ) {
      return apiFailure('current-session', correlationId);
    }
    const expected = errors[status];
    if (
      envelope.status === 'fail' &&
      expected &&
      hasExactKeys(envelope.data, ['message']) &&
      envelope.data.message === expected[1]
    ) {
      return apiFailure(expected[0], correlationId);
    }
  }
  if (
    status === 503 &&
    hasExactKeys(envelope, ['status', 'message', 'data']) &&
    envelope.status === 'error' &&
    envelope.message ===
      'Invitation completion is unconfirmed. Read invitation status before another operation.'
  ) {
    const partial = decodeInvitationPartial(envelope.data);
    return partial
      ? apiFailure('uncertain', correlationId, undefined, partial)
      : apiFailure('protocol', correlationId);
  }
  if (hasExactKeys(envelope, ['status', 'message']) && envelope.status === 'error') {
    if (status === 503 && envelope.message === 'Service unavailable.') {
      return apiFailure('unavailable', correlationId);
    }
    if (
      status === 503 &&
      envelope.message ===
        'Recovery outcome uncertain. Read invitation status before another operation.'
    ) {
      return apiFailure('uncertain', correlationId);
    }
    if ([500, 502, 504].includes(status) && envelope.message === 'Internal server error.') {
      return apiFailure('system', correlationId);
    }
  }
  return apiFailure('protocol', correlationId);
}
