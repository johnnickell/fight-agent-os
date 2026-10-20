import type { AccessTokenProvider } from '@/api/ApiClient';

/** Shared volatile token seam; expiry is conservative request-start monotonic time. */
export class MemoryAccessToken implements AccessTokenProvider {
  #token: string | null = null;
  #deadline = 0;
  #generation = 0;
  readonly #now: () => number;

  constructor(now: () => number = () => performance.now()) {
    this.#now = now;
  }

  get generation(): number {
    return this.#generation;
  }

  accept(token: string, startedAt: number, generation = this.#generation): boolean {
    if (generation !== this.#generation) return false;
    this.#token = token;
    this.#deadline = startedAt + 840_000;
    return true;
  }

  getAccessToken(): string | null {
    if (this.#token !== null && this.#now() >= this.#deadline) this.clear();
    return this.#token;
  }

  clear(): void {
    ++this.#generation;
    this.#token = null;
    this.#deadline = 0;
  }
}
