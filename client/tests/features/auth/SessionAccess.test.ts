import { describe, expect, it } from 'vitest';

import { decodeSessionAccess } from '@/features/auth/SessionAccess';

const valid = { access_token: 'header.payload.signature', issued_at: 1000, expires_at: 1900 };
describe('shared session access response', () => {
  it('maps exactly the public token and timing fields', () => {
    expect(decodeSessionAccess(valid)).toEqual({
      accessToken: valid.access_token,
      issuedAt: 1000,
      expiresAt: 1900
    });
  });
  it.each([
    { ...valid, refresh: 'private' },
    { ...valid, expires_at: 1901 },
    { ...valid, access_token: 'invalid' },
    { ...valid, access_token: 'header.payload.signature\n' },
    { ...valid, access_token: 'a'.repeat(2049) },
    { ...valid, issued_at: 0, expires_at: 900 },
    { ...valid, issued_at: 1000.5, expires_at: 1900.5 },
    { ...valid, expires_at: '1900' },
    { ...valid, expires_at: Number.MAX_SAFE_INTEGER + 1 }
  ])('rejects extra cookie material and invalid token/timing shape (%#)', (source) => {
    expect(decodeSessionAccess(source)).toBeNull();
  });
});
