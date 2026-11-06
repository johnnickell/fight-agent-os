import { describe, expect, it } from 'vitest';

import { decodeResponse } from '@/api/decodeResponse';

const decode = (value: unknown) => (value === null ? true : null);
const partial = () => ({
  user_id: crypto.randomUUID(),
  assignment: 'unconfirmed',
  audit: 'unconfirmed',
  delivery: null
});

describe('deliberate Common failure cutover', () => {
  it.each([
    [401, { status: 'error', message: 'Unauthorized.' }],
    [409, { status: 'error', message: 'Conflict.' }],
    [409, { status: 'error', message: 'Use Logout for the current session.', code: 5801 }],
    [
      409,
      { status: 'fail', data: { message: 'Use Logout for the current session.', intent: 'retry' } }
    ],
    [500, { status: 'fail', data: { message: 'State conflict.' } }],
    [500, { status: 'error', message: 'Service unavailable.' }],
    [503, { status: 'error', message: 'Internal server error.' }],
    [501, { status: 'error', message: 'Internal server error.' }],
    [503, { status: 'error', message: 'private failure', data: partial() }],
    [422, { status: 'fail', data: { message: 'Rule violation.', private: 'secret' } }]
  ] as const)('rejects legacy, contradictory or undeclared payload %#', (status, envelope) => {
    expect(decodeResponse(status, envelope, decode, null)).toEqual({
      ok: false,
      error: { kind: 'protocol', correlationId: null }
    });
  });

  it('uses only the declared current-session intent and retains no server display text', () => {
    const result = decodeResponse(
      409,
      {
        status: 'fail',
        data: { message: 'Use Logout for the current session.', intent: 'use_logout' }
      },
      decode,
      null
    );
    expect(result).toEqual({ ok: false, error: { kind: 'current-session', correlationId: null } });
    expect(JSON.stringify(result)).not.toContain('Use Logout');
  });

  it('retains secretless inspected partial facts without declaring rollback or retry authority', () => {
    const data = partial();
    const result = decodeResponse(
      503,
      {
        status: 'error',
        message:
          'Invitation completion is unconfirmed. Read invitation status before another operation.',
        data
      },
      decode,
      null
    );
    expect(result).toEqual({
      ok: false,
      error: {
        kind: 'uncertain',
        correlationId: null,
        partialInvitation: {
          userId: data.user_id,
          assignment: 'unconfirmed',
          audit: 'unconfirmed',
          delivery: null
        }
      }
    });
    expect(JSON.stringify(result)).not.toContain('message');
  });

  it.each([
    { ...partial(), private: 'credential' },
    { ...partial(), assignment: 'committed' },
    { ...partial(), user_id: '/private/identity' },
    { ...partial(), delivery: { secret: 'credential' } }
  ])('rejects malformed partial data %# rather than salvaging it', (data) => {
    const result = decodeResponse(
      503,
      {
        status: 'error',
        message:
          'Invitation completion is unconfirmed. Read invitation status before another operation.',
        data
      },
      decode,
      null
    );
    expect(result).toEqual({ ok: false, error: { kind: 'protocol', correlationId: null } });
  });
});
