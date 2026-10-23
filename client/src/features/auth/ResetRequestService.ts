import { apiFailure, type ApiResult } from '@/api/ApiResult';

import type { ApiClient } from '@/api/ApiClient';
import type { CsrfProofService } from '@/features/auth/CsrfProofService';

/** One explicit public mutation, without identity reads, automatic replay or retained email. */
export class ResetRequestService {
  readonly #api: ApiClient;
  readonly #csrf: CsrfProofService;
  #active = true;
  #pending = false;

  constructor(api: ApiClient, csrf: CsrfProofService) {
    this.#api = api;
    this.#csrf = csrf;
  }

  async submit(values: Readonly<Record<string, string>>): Promise<ApiResult<true>> {
    if (!this.#active || this.#pending) return apiFailure('cancelled');
    this.#pending = true;
    try {
      const current = this.#csrf.current();
      const proof = current ? { ok: true as const, value: current } : await this.#csrf.bootstrap();
      if (!proof.ok) return proof;
      if (!this.#active) return apiFailure('cancelled');
      const result = await this.#api.post(
        '/auth/password-reset',
        { email: (values.email ?? '').replace(/[A-Z]/g, (letter) => letter.toLowerCase()) },
        proof.value.proof,
        (data) => {
          if (
            typeof data !== 'object' ||
            data === null ||
            Array.isArray(data) ||
            Object.keys(data).length !== 1 ||
            !('accepted' in data) ||
            data.accepted !== true
          )
            return null;
          return true;
        }
      );
      if (!result.ok && result.error.kind === 'authorization') this.#csrf.clear();
      // Observed success remains success: edits/disposal cannot cancel committed server work.
      return result;
    } finally {
      this.#pending = false;
    }
  }

  dispose(): void {
    this.#active = false;
    this.#csrf.clear();
  }
}
