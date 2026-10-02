import { decodePublicSchema, type PublicSchema } from '@/features/validation/PublicSchema';

import type { ApiClient } from '@/api/ApiClient';
import type { ApiFailureKind } from '@/api/ApiResult';

export type SchemaResult =
  | Readonly<{ ok: true; schema: PublicSchema }>
  | Readonly<{ ok: false; reason: ApiFailureKind | 'unsupported' }>;

/**
 * Keeps only bounded safe metadata in memory; the caller owns loading, retry and teardown
 */
export class PublicSchemaService {
  readonly #cache = new Map<string, PublicSchema>();
  readonly #api: ApiClient;
  #revision: string | null = null;

  constructor(api: ApiClient) {
    this.#api = api;
  }

  async load(name: string, signal?: AbortSignal): Promise<SchemaResult> {
    if (!/^[a-z][a-z0-9_]{0,63}$/.test(name)) return { ok: false, reason: 'unsupported' };
    if (signal?.aborted) return { ok: false, reason: 'cancelled' };
    const cached = this.#cache.get(name);
    if (cached) return { ok: true, schema: cached };
    const result = await this.#api.get(`/validations/${name}`, decodePublicSchema, {
      ...(signal ? { signal } : {})
    });
    if (signal?.aborted) return { ok: false, reason: 'cancelled' };
    if (!result.ok) return { ok: false, reason: result.error.kind };
    if (result.value.formName !== name) return { ok: false, reason: 'unsupported' };
    if (this.#revision !== result.value.revision) this.#cache.clear();
    this.#revision = result.value.revision;
    if (this.#cache.size >= 8) this.#cache.delete(this.#cache.keys().next().value!);
    this.#cache.set(name, result.value);
    return { ok: true, schema: result.value };
  }

  clear(): void {
    this.#cache.clear();
    this.#revision = null;
  }
}
