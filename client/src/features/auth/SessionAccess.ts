import { hasExactKeys } from '@/api/decodeResponse';

type SessionAccess = Readonly<{ accessToken: string; issuedAt: number; expiresAt: number }>;

/** Decodes only the explicit memory-access response, never cookie material or authority claims. */
export function decodeSessionAccess(data: unknown): SessionAccess | null {
  if (
    !hasExactKeys(data, ['access_token', 'issued_at', 'expires_at']) ||
    typeof data.access_token !== 'string' ||
    data.access_token.length > 2048 ||
    !/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/.test(data.access_token) ||
    typeof data.issued_at !== 'number' ||
    typeof data.expires_at !== 'number' ||
    !Number.isSafeInteger(data.issued_at) ||
    !Number.isSafeInteger(data.expires_at) ||
    data.issued_at < 1 ||
    data.expires_at - data.issued_at !== 900
  )
    return null;
  return { accessToken: data.access_token, issuedAt: data.issued_at, expiresAt: data.expires_at };
}
