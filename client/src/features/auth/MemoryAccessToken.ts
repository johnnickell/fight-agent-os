import type { AccessTokenProvider } from '@/api/ApiClient';

/** Shared volatile token seam; expiry is conservative request-start monotonic time. */
export class MemoryAccessToken implements AccessTokenProvider {
  #token: string | null = null;
  #deadline = 0;
  #generation = 0;
  #lastClock: number;
  readonly #now: () => number;

  constructor(now: () => number = () => performance.now()) {
    this.#now = now;
    this.#lastClock = now();
  }

  get generation(): number {
    return this.#generation;
  }

  accept(
    token: string,
    startedAt: number,
    generation = this.#generation,
    budget = 840_000
  ): boolean {
    if (generation !== this.#generation) return false;
    const deadline = startedAt + Math.min(840_000, budget);
    if (!Number.isFinite(deadline) || deadline <= this.#now()) return false;
    this.#token = token;
    this.#deadline = deadline;
    return true;
  }

  /** Exposes only a conservative monotonic handoff budget, never an issuer-clock offset. */
  remaining(): number {
    return this.getAccessToken() === null ? 0 : Math.max(0, this.#deadline - this.#now());
  }

  getAccessToken(): string | null {
    const now = this.#now();
    if (!Number.isFinite(now) || now < this.#lastClock || now >= this.#deadline) {
      if (this.#token !== null) this.clear();
    }
    this.#lastClock = now;
    return this.#token;
  }

  clear(): void {
    ++this.#generation;
    this.#token = null;
    this.#deadline = 0;
  }
}
