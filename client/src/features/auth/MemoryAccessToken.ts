import type { AccessTokenProvider } from '@/api/ApiClient';

/** Shared volatile token seam; expiry is conservative request-start monotonic time. */
export class MemoryAccessToken implements AccessTokenProvider {
  #token: string | null = null;
  #deadline = 0;
  readonly #now: () => number;

  constructor(now: () => number = () => performance.now()) {
    this.#now = now;
  }

  accept(token: string, startedAt: number): void {
    this.#token = token;
    this.#deadline = startedAt + 840_000;
  }

  getAccessToken(): string | null {
    if (this.#now() >= this.#deadline) this.clear();
    return this.#token;
  }

  clear(): void {
    this.#token = null;
    this.#deadline = 0;
  }
}
