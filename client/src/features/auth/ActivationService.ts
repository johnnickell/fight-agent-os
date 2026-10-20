import { apiFailure, type ApiResult } from '@/api/ApiResult';
import { decodeSessionAccess } from '@/features/auth/SessionAccess';

import type { ApiClient } from '@/api/ApiClient';
import type { ActivationCredential } from '@/features/auth/ActivationCredential';
import type { CsrfProofService } from '@/features/auth/CsrfProofService';
import type { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';

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
