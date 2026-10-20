import { apiFailure, type ApiResult } from '@/api/ApiResult';
import { decodeSessionAccess } from '@/features/auth/SessionAccess';

import type { ApiClient } from '@/api/ApiClient';
import type { CsrfProofService } from '@/features/auth/CsrfProofService';
import type { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';

/** Owns explicit single-tab refresh/logout. Scheduling and cross-tab recovery belong to TASK-00048. */
export class SessionService {
  readonly #api: ApiClient;
  readonly #csrf: CsrfProofService;
  readonly #tokens: MemoryAccessToken;
  #active = true;
  #generation = 0;
  #refresh: Promise<ApiResult<true>> | null = null;
  #logout: Promise<ApiResult<true>> | null = null;

  constructor(api: ApiClient, csrf: CsrfProofService, tokens: MemoryAccessToken) {
    this.#api = api;
    this.#csrf = csrf;
    this.#tokens = tokens;
  }

  refresh(): Promise<ApiResult<true>> {
    if (!this.#active || this.#logout) return Promise.resolve(apiFailure('cancelled'));
    if (this.#refresh) return this.#refresh;
    this.#tokens.clear();
    const generation = ++this.#generation;
    const tokenGeneration = this.#tokens.generation;
    this.#refresh = this.#rotate(generation, tokenGeneration).finally(() => {
      this.#refresh = null;
    });
    return this.#refresh;
  }

  async #rotate(generation: number, tokenGeneration: number): Promise<ApiResult<true>> {
    const proof = await this.#proof();
    if (!this.#current(generation)) return apiFailure('cancelled');
    if (!proof.ok) return proof;
    const startedAt = performance.now();
    const result = await this.#api.post(
      '/auth/refresh',
      {},
      proof.value.proof,
      decodeSessionAccess
    );
    if (!this.#current(generation)) return apiFailure('cancelled');
    if (result.ok) {
      if (!this.#tokens.accept(result.value.accessToken, startedAt, tokenGeneration))
        return apiFailure('cancelled');
      return { ok: true, value: true, correlationId: result.correlationId };
    }
    // Conflict remains a typed safe signal, never a hidden retry or retained old access token.
    if (result.error.kind !== 'conflict') this.#csrf.clear();
    return result;
  }

  logout(): Promise<ApiResult<true>> {
    if (!this.#active) return Promise.resolve(apiFailure('cancelled'));
    if (this.#logout) return this.#logout;
    this.#tokens.clear();
    const generation = ++this.#generation;
    // A dispatched refresh cannot be undone by AbortSignal. Let its browser cookie land before
    // revoking the current cookie, while its stale access result is fenced out immediately.
    this.#logout = this.#end(generation, this.#refresh).finally(() => {
      this.#logout = null;
    });
    return this.#logout;
  }

  async #end(
    generation: number,
    pending: Promise<ApiResult<true>> | null
  ): Promise<ApiResult<true>> {
    await pending;
    if (!this.#current(generation)) return apiFailure('cancelled');
    try {
      const proof = await this.#proof();
      if (!this.#current(generation)) return apiFailure('cancelled');
      if (!proof.ok) return proof;
      const result = await this.#api.post('/auth/logout', {}, proof.value.proof, (value) =>
        value === null ? (true as const) : null
      );
      if (!this.#current(generation)) return apiFailure('cancelled');
      return result;
    } finally {
      this.#csrf.clear();
    }
  }

  #proof() {
    const proof = this.#csrf.current();
    return proof ? Promise.resolve({ ok: true as const, value: proof }) : this.#csrf.bootstrap();
  }

  #current(generation: number): boolean {
    return this.#active && this.#generation === generation;
  }

  dispose(): void {
    this.#active = false;
    ++this.#generation;
    this.#tokens.clear();
    this.#csrf.clear();
  }
}
