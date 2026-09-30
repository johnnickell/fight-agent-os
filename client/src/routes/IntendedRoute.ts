import { evaluateAccess } from '@/features/authority/evaluateAccess';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type { RegisteredRoute } from '@/routes/GuardedRoute';

/**
 * Retains at most one classified navigation target in memory, never form or history state
 */
export class IntendedRoute {
  readonly #cache: AuthorityCache;
  readonly #routes: () => readonly RegisteredRoute[];
  readonly #origin: string;
  readonly #now: () => number;
  readonly #unsubscribe: () => void;
  #intent: Readonly<{ target: string; capturedAt: number }> | null = null;
  #lastClock: number;

  constructor(
    cache: AuthorityCache,
    routes: () => readonly RegisteredRoute[],
    origin: string,
    now: () => number = () => performance.now()
  ) {
    const base = new URL(origin);
    if (!['http:', 'https:'].includes(base.protocol) || base.origin !== origin) {
      throw new Error('Invalid application origin');
    }
    this.#cache = cache;
    this.#routes = routes;
    this.#origin = origin;
    this.#now = now;
    this.#lastClock = now();
    this.#unsubscribe = cache.subscribe(() => {
      const state = cache.getSnapshot();
      if (state.status === 'anonymous' && state.reason === 'logout') this.clear();
    });
  }

  capture(target: string): boolean {
    if (!this.#validClock()) return false;
    if (this.#intent) return false; // Repeated redirects neither replace nor renew intent
    const resolved = this.#resolve(target);
    if (!resolved) return false;
    this.#intent = Object.freeze({ target: resolved.target, capturedAt: this.#lastClock });
    return true;
  }

  /**
   * Waits for fresh authority, then consumes once and resolves current route requirements again
   */
  consume(): string | null {
    if (!this.#validClock() || !this.#intent) return null;
    if (this.#cache.getSnapshot().status !== 'authenticated') return null;
    const intent = this.#intent;
    this.clear();
    const resolved = this.#resolve(intent.target);
    return resolved &&
      evaluateAccess(this.#cache.getSnapshot(), resolved.route.requirements) === 'allowed'
      ? resolved.target
      : null;
  }

  clear(): void {
    this.#intent = null;
  }
  dispose(): void {
    this.clear();
    this.#unsubscribe();
  }

  #validClock(): boolean {
    const now = this.#now();
    const invalid = !Number.isFinite(now) || now < this.#lastClock;
    this.#lastClock = now;
    if (invalid || (this.#intent && now - this.#intent.capturedAt >= 600_000)) {
      this.clear();
      return false;
    }
    return true;
  }

  #resolve(target: string): { target: string; route: RegisteredRoute } | null {
    if (
      target.length > 2048 ||
      !target.startsWith('/app/') ||
      /[\\\s\p{Cc}]/u.test(target) ||
      /%(?![0-9a-f]{2})/i.test(target)
    )
      return null;
    const path = target.split(/[?#]/)[0];
    // No parameterized paths, encodings, dot normalization or ambiguous separators yet.
    if (!path || !/^\/app(?:\/[A-Za-z0-9_-]+)+(?![\s\S])/.test(path)) return null;
    try {
      const url = new URL(target, this.#origin);
      if (url.origin !== this.#origin || url.pathname !== path || url.username || url.password)
        return null;
      const matches = this.#routes().filter((route) => route.pathname === path);
      if (matches.length !== 1) return null;
      const route = matches[0];
      if (
        !route?.intendedRoute ||
        evaluateAccess({ status: 'unknown' }, route.requirements) !== 'unavailable'
      )
        return null;
      // Authentication/grant-link paths are never eligible, even if mistakenly opted in.
      if (
        /\/(?:auth|login|logout|activate|activation|invite|invitation|reset|password)(?:\/|-|$)/i.test(
          path
        )
      )
        return null;
      const query = new URLSearchParams();
      for (const [key, value] of url.searchParams) {
        if (
          !['page', 'sort', 'view'].includes(key) ||
          query.has(key) ||
          !/^[A-Za-z0-9_-]{1,64}(?![\s\S])/.test(value) ||
          !Object.hasOwn(route.intendedRoute, key) ||
          !route.intendedRoute[key]?.includes(value)
        )
          return null;
        query.set(key, value);
      }
      query.sort();
      const serialized = query.toString();
      // Fragments are deliberately discarded, never retained for later navigation.
      return { target: route.pathname + (serialized ? `?${serialized}` : ''), route };
    } catch {
      return null;
    }
  }
}
