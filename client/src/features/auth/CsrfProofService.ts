import type { ApiClient } from '../../api/ApiClient';
import { apiFailure, type ApiResult } from '../../api/ApiResult';
import { hasExactKeys } from '../../api/decodeResponse';

/**
 * Holds a non-authoritative proof and its UTC Unix-second deadline in memory only
 */
export type CsrfProof = Readonly<{ proof: string; expiresAt: number }>;

/**
 * Decodes the exact bootstrap data contract without accepting cookie or extra fields
 */
export function decodeCsrfProof(data: unknown): CsrfProof | null {
  if (
    !hasExactKeys(data, ['proof', 'expires_at']) ||
    typeof data.proof !== 'string' ||
    !/^[1-9][0-9]{0,9}\.[a-f0-9]{64}(?![\s\S])/.test(data.proof) ||
    typeof data.expires_at !== 'number' ||
    !Number.isSafeInteger(data.expires_at) ||
    data.expires_at < 1 ||
    data.expires_at > 9999999999 ||
    Number(data.proof.split('.')[0]) !== data.expires_at
  ) {
    return null;
  }
  return Object.freeze({ proof: data.proof, expiresAt: data.expires_at });
}

/**
 * Owns volatile bootstrap state and fences superseded results independently of abort
 */
export class CsrfProofService {
  readonly #client: ApiClient;
  readonly #now: () => number;
  #proof: CsrfProof | null = null;
  #generation = 0;
  #pending: AbortController | null = null;

  constructor(client: ApiClient, now: () => number = Date.now) {
    this.#client = client;
    this.#now = now;
  }

  /**
   * Returns only an unexpired proof, never a browser cookie or authentication claim
   */
  current(): CsrfProof | null {
    if (this.#proof && !this.#usable(this.#proof)) this.#proof = null;
    return this.#proof;
  }

  /**
   * Invalidates both stored proof and pending results on teardown or nonce changes
   */
  clear(): void {
    this.#generation += 1;
    this.#pending?.abort();
    this.#pending = null;
    this.#proof = null;
  }

  /**
   * Replaces the current request without allowing old success or failure to win
   */
  async bootstrap(signal?: AbortSignal): Promise<ApiResult<CsrfProof>> {
    this.clear();
    const generation = this.#generation;
    const controller = new AbortController();
    this.#pending = controller;
    const abort = () => controller.abort();
    if (signal?.aborted) abort();
    else signal?.addEventListener('abort', abort, { once: true });
    const result = await this.#client.get('/auth/csrf', decodeCsrfProof, {
      signal: controller.signal,
    });
    signal?.removeEventListener('abort', abort);
    if (generation !== this.#generation) return apiFailure('cancelled');
    this.#pending = null;
    if (controller.signal.aborted) return apiFailure('cancelled');
    if (!result.ok) return result;
    if (!this.#usable(result.value))
      return apiFailure('protocol', result.correlationId);
    this.#proof = result.value;
    return result;
  }

  #usable(proof: CsrfProof): boolean {
    const now = this.#now();
    return Number.isFinite(now) && now >= 0 && now < proof.expiresAt * 1000;
  }
}
