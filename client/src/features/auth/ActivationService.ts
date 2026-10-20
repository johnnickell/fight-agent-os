import { apiFailure, type ApiResult } from '@/api/ApiResult';
import { hasExactKeys } from '@/api/decodeResponse';

import type { ApiClient } from '@/api/ApiClient';
import type { ActivationCredential } from '@/features/auth/ActivationCredential';
import type { CsrfProofService } from '@/features/auth/CsrfProofService';
import type { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';

type SessionAccess = Readonly<{ accessToken: string; issuedAt: number; expiresAt: number }>;

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

/** Keeps grant/session handling outside React state, errors and schema caches. */
export class ActivationService {
  readonly #api: ApiClient;
  readonly #csrf: CsrfProofService;
  readonly #credential: ActivationCredential;
  readonly #tokens: MemoryAccessToken;
  #active = true;

  constructor(
    api: ApiClient,
    csrf: CsrfProofService,
    credential: ActivationCredential,
    tokens: MemoryAccessToken
  ) {
    this.#api = api;
    this.#csrf = csrf;
    this.#credential = credential;
    this.#tokens = tokens;
  }

  usable(): boolean {
    return this.#credential.current() !== null;
  }

  async submit(values: Readonly<Record<string, string>>): Promise<ApiResult<true>> {
    const material = this.#credential.current();
    if (!material || !this.#active) return apiFailure('conflict');
    const bootstrap = this.#csrf.current() ?? null;
    const proof = bootstrap
      ? { ok: true as const, value: bootstrap }
      : await this.#csrf.bootstrap();
    if (!proof.ok) return proof;
    if (!this.#active) return apiFailure('cancelled');
    const startedAt = performance.now();
    const result = await this.#api.post(
      '/auth/activate',
      {
        user_id: material.userId,
        credential: material.credential,
        password: values.password ?? '',
        password_confirmation: values.passwordConfirmation ?? ''
      },
      proof.value.proof,
      decodeSessionAccess
    );
    if (result.ok) {
      this.#credential.clear();
      this.#csrf.clear();
      if (this.#active) this.#tokens.accept(result.value.accessToken, startedAt);
      return { ok: true, value: true, correlationId: result.correlationId };
    }
    if (['conflict', 'gone', 'not-found'].includes(result.error.kind)) this.#credential.clear();
    if (result.error.kind === 'authorization') this.#csrf.clear();
    return result;
  }

  dispose(): void {
    this.#active = false;
    this.#credential.clear();
    this.#csrf.clear();
    this.#tokens.clear();
  }
}
