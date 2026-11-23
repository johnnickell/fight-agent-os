import { apiFailure, type ApiResult } from '../../api/ApiResult.js';
import { hasExactKeys } from '../../api/decodeResponse.js';

import type { ApiClient } from '../../api/ApiClient.js';
import type { CsrfProofService } from './CsrfProofService.js';
import type { ResetCredential } from './ResetCredential.js';

function decodeCompletion(value: unknown): true | null {
  return hasExactKeys(value, ['completed']) && value.completed === true ? true : null;
}

/** Holds no session authority, candidate cache or retry queue. */
export class ResetCompletionService {
  readonly #api: ApiClient;
  readonly #csrf: CsrfProofService;
  readonly #credential: ResetCredential;
  readonly #completed: (userId: string) => void;
  #active = true;
  constructor(
    api: ApiClient,
    csrf: CsrfProofService,
    credential: ResetCredential,
    completed: (userId: string) => void
  ) {
    this.#api = api;
    this.#csrf = csrf;
    this.#credential = credential;
    this.#completed = completed;
  }
  usable(): boolean {
    return this.#active && this.#credential.current() !== null;
  }
  async submit(values: Readonly<Record<string, string>>): Promise<ApiResult<true>> {
    if (!this.usable()) return apiFailure('conflict');
    const current = this.#csrf.current();
    const proof = current ? { ok: true as const, value: current } : await this.#csrf.bootstrap();
    if (!proof.ok) return proof;
    const material = this.#credential.current();
    if (!this.#active || !material) return apiFailure('cancelled');
    const result = await this.#api.post(
      '/auth/password-reset/complete',
      {
        user_id: material.userId,
        credential: material.credential,
        password: values.password ?? '',
        password_confirmation: values.passwordConfirmation ?? ''
      },
      proof.value.proof,
      decodeCompletion
    );
    if (result.ok) {
      this.#credential.clear();
      this.#csrf.clear();
      // Even an edited/unmounted form cannot undo committed reset or required peer invalidation.
      this.#completed(material.userId);
    } else {
      if (['conflict', 'gone', 'not-found'].includes(result.error.kind)) this.#credential.clear();
      if (result.error.kind === 'authorization') this.#csrf.clear();
    }
    return result;
  }
  dispose(): void {
    this.#active = false;
    this.#credential.clear();
    this.#csrf.clear();
  }
}
