import { apiFailure, type ApiResult } from '@/api/ApiResult';
import { decodeSessionAccess } from '@/features/auth/SessionAccess';

import type { ApiClient } from '@/api/ApiClient';
import type { CsrfProofService } from '@/features/auth/CsrfProofService';
import type { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';

/** Owns one explicit login at a time, with no saved credentials, retry or principal fabrication. */
export class LoginService {
  readonly #api: ApiClient;
  readonly #csrf: CsrfProofService;
  readonly #tokens: MemoryAccessToken;
  #active = true;
  #pending = false;

  constructor(api: ApiClient, csrf: CsrfProofService, tokens: MemoryAccessToken) {
    this.#api = api;
    this.#csrf = csrf;
    this.#tokens = tokens;
  }

  async submit(
    values: Readonly<Record<string, string>>,
    remember: boolean
  ): Promise<ApiResult<true>> {
    if (!this.#active || this.#pending) return apiFailure('cancelled');
    this.#pending = true;
    try {
      const current = this.#csrf.current();
      const proof = current ? { ok: true as const, value: current } : await this.#csrf.bootstrap();
      if (!proof.ok) return proof;
      if (!this.#active) return apiFailure('cancelled');
      const startedAt = performance.now();
      const result = await this.#api.post(
        '/auth/login',
        {
          // PHP EmailAddress::canonical uses ASCII lowercase, not locale/Unicode folding.
          email: (values.email ?? '').replace(/[A-Z]/g, (letter) => letter.toLowerCase()),
          password: values.password ?? '',
          remember
        },
        proof.value.proof,
        decodeSessionAccess
      );
      if (result.ok) {
        this.#csrf.clear();
        if (!this.#active) return apiFailure('cancelled');
        this.#tokens.accept(result.value.accessToken, startedAt);
        return { ok: true, value: true, correlationId: result.correlationId };
      }
      if (result.error.kind === 'authorization') this.#csrf.clear();
      return result;
    } finally {
      this.#pending = false;
    }
  }

  dispose(): void {
    this.#active = false;
    this.#csrf.clear();
    this.#tokens.clear();
  }
}
