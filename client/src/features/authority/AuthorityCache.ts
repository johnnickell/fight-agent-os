import { decodeCurrentPrincipal } from '@/features/authority/CurrentPrincipal';

import type { CurrentPrincipal } from '@/features/authority/CurrentPrincipal';

export type AuthorityState =
  | Readonly<{ status: 'unknown' | 'loading' | 'stale' | 'terminal' | 'refresh-failed' }>
  | Readonly<{ status: 'anonymous'; reason: 'absent' | 'logout' }>
  | Readonly<{ status: 'error'; reason: 'network' | 'protocol' }>
  | Readonly<{ status: 'authenticated'; principal: CurrentPrincipal }>;

export type PrincipalLoadResult =
  | Readonly<{ status: 'principal'; principal: unknown }>
  | Readonly<{ status: 'anonymous' | 'terminal' | 'network' | 'protocol' }>;

/**
 * Leaves HTTP mapping to the future feature service; no production loader exists yet
 */
export type PrincipalLoader = (signal: AbortSignal) => Promise<PrincipalLoadResult>;
export type AuthoritySignal =
  | 'refresh-started'
  | 'credentials-changed'
  | 'refresh-failed'
  | 'authority-changed'
  | 'logout'
  | 'terminal';

/**
 * Fences protected data; owners retire on abort and check isCurrent before accepting any result
 */
export type AuthorityScope = Readonly<{
  signal: AbortSignal;
  isCurrent: () => boolean;
}>;

/**
 * Owns one volatile principal, freshness and request/context fences per authentication context
 */
export class AuthorityCache {
  readonly #loader: PrincipalLoader;
  readonly #now: () => number;
  readonly #listeners = new Set<() => void>();
  #state: AuthorityState = Object.freeze({ status: 'unknown' });
  #context = 0;
  #generation = 0;
  #barrier = false;
  #canLoad = true;
  #identity: string | null = null;
  #pending: Promise<void> | null = null;
  #request: AbortController | null = null;
  #scope = new AbortController();
  #scopeView: AuthorityScope;
  #startedAt: number | null = null;
  #lastClock: number;
  #expiry: ReturnType<typeof setTimeout> | undefined;
  #disposed = false;
  #retiring = false;

  constructor(loader: PrincipalLoader, now: () => number = () => performance.now()) {
    this.#loader = loader;
    this.#now = now;
    this.#lastClock = now();
    this.#scopeView = this.#makeScope();
  }

  subscribe = (listener: () => void): (() => void) => {
    this.#listeners.add(listener);
    return () => {
      this.#listeners.delete(listener);
    };
  };

  /**
   * Checks freshness at render/action time even if the browser delayed its timer
   */
  getSnapshot = (): AuthorityState => {
    const now = this.#now();
    if (
      (this.#state.status === 'authenticated' || this.#state.status === 'loading') &&
      (!Number.isFinite(now) ||
        !Number.isFinite(this.#lastClock) ||
        now < this.#lastClock ||
        (this.#startedAt !== null && now - this.#startedAt >= 60_000))
    ) {
      // React may read during render. Retire authority immediately; notify other
      // subscribers outside that render rather than updating a sibling mid-render.
      this.#retire();
      queueMicrotask(() => this.#emit());
    }
    this.#lastClock = now;
    return this.#state;
  };

  canRetry(): boolean {
    const state = this.getSnapshot();
    return (
      !this.#disposed &&
      !this.#barrier &&
      this.#canLoad &&
      (state.status === 'stale' || state.status === 'error')
    );
  }

  captureScope(): AuthorityScope {
    this.getSnapshot();
    return this.#scopeView;
  }

  /**
   * Coalesces demand; failures require an explicit subsequent call, never a retry loop
   */
  load = (): Promise<void> => {
    const state = this.getSnapshot();
    if (
      this.#disposed ||
      this.#retiring ||
      this.#barrier ||
      !this.#canLoad ||
      state.status === 'authenticated'
    ) {
      return Promise.resolve();
    }
    if (this.#pending) return this.#pending;
    if (!this.#retire()) return Promise.resolve();
    const context = this.#context;
    const generation = this.#generation;
    const request = new AbortController();
    this.#request = request;
    this.#startedAt = this.#now();
    this.#lastClock = this.#startedAt;
    // Install the promise before notifying subscribers, so reentrant demands coalesce.
    const pending = Promise.resolve().then(async () => {
      try {
        if (!this.#matches(context, generation)) return;
        const result = await this.#loader(request.signal);
        if (!this.#matches(context, generation)) return;
        this.getSnapshot();
        if (!this.#matches(context, generation)) return;
        this.#apply(result);
      } catch {
        if (this.#matches(context, generation))
          this.#publish({ status: 'error', reason: 'network' });
      } finally {
        if (this.#pending === pending) {
          this.#pending = null;
          this.#request = null;
        }
      }
    });
    this.#pending = pending;
    this.#publish({ status: 'loading' });
    return pending;
  };

  /**
   * Receives coordination intent, not evidence of identity or permissions
   */
  signal(signal: AuthoritySignal): Promise<void> {
    if (this.#disposed) return Promise.resolve();
    if (signal === 'logout' || signal === 'terminal') {
      this.#context++;
      this.#barrier = true;
      this.#canLoad = false;
      this.#identity = null;
      if (!this.#retire()) return Promise.resolve();
      this.#publish(
        signal === 'logout' ? { status: 'anonymous', reason: 'logout' } : { status: 'terminal' }
      );
      return Promise.resolve();
    }
    if (this.#barrier) return Promise.resolve();
    if (!this.#retire()) return Promise.resolve();
    if (signal === 'refresh-started' || signal === 'refresh-failed') this.#canLoad = false;
    if (signal === 'credentials-changed') this.#canLoad = true;
    this.#publish({ status: signal === 'refresh-failed' ? 'refresh-failed' : 'stale' });
    return signal === 'credentials-changed' || signal === 'authority-changed'
      ? this.load()
      : Promise.resolve();
  }

  /**
   * Crosses a logout/terminal barrier only through an explicit new authentication attempt
   */
  beginAuthentication(): Readonly<{ acceptCredentials: () => Promise<void>; fail: () => void }> {
    // Capture the attempt before retirement invokes consumer callbacks. A context
    // ended by one of those callbacks cannot lend its generation to this attempt.
    const context = this.#disposed ? this.#context : ++this.#context;
    if (!this.#disposed) {
      this.#barrier = true;
      this.#canLoad = false;
      this.#identity = null;
      if (this.#retire()) this.#publish({ status: 'unknown' });
    }
    let completed = false;
    const current = () => !completed && !this.#disposed && context === this.#context;
    return Object.freeze({
      acceptCredentials: () => {
        if (!current()) return Promise.resolve();
        completed = true;
        this.#barrier = false;
        this.#canLoad = true;
        return this.load();
      },
      fail: () => {
        if (!current()) return;
        completed = true;
        this.#publish({ status: 'refresh-failed' });
      }
    });
  }

  /**
   * Removes presentation on foreground return without polling or starting restoration
   */
  resume = (): void => {
    const state = this.getSnapshot();
    if (state.status === 'authenticated' || state.status === 'loading') {
      if (this.#retire()) this.#publish({ status: 'stale' });
    }
  };

  dispose(): void {
    if (this.#disposed) return;
    this.#disposed = true;
    this.#context++;
    this.#barrier = true;
    this.#retire();
    this.#publish({ status: 'terminal' });
    this.#listeners.clear();
  }

  #apply(result: PrincipalLoadResult): void {
    if (typeof result !== 'object' || result === null) {
      this.#publish({ status: 'error', reason: 'protocol' });
      return;
    }
    switch (result.status) {
      case 'principal': {
        const principal = decodeCurrentPrincipal(result.principal);
        if (!principal) {
          this.#publish({ status: 'error', reason: 'protocol' });
          return;
        }
        if (this.#identity !== null && this.#identity !== principal.userId) {
          const context = ++this.#context;
          const generation = this.#generation;
          this.#renewScope();
          // Identity replacement deliberately advances context, but consumer abort
          // callbacks may advance it again or retire this result's request.
          this.getSnapshot();
          if (!this.#matches(context, generation)) return;
        }
        this.#identity = principal.userId;
        const remaining = 60_000 - (this.#now() - (this.#startedAt ?? 0));
        this.#expiry = setTimeout(
          () => {
            this.getSnapshot();
          },
          Math.max(0, Math.ceil(remaining))
        );
        this.#publish({ status: 'authenticated', principal });
        return;
      }
      case 'anonymous':
        this.#canLoad = false;
        this.#context++;
        this.#identity = null;
        if (this.#retire()) this.#publish({ status: 'anonymous', reason: 'absent' });
        return;
      case 'terminal':
        void this.signal('terminal');
        return;
      case 'network':
        this.#publish({ status: 'error', reason: 'network' });
        return;
      default:
        this.#publish({ status: 'error', reason: 'protocol' });
    }
  }

  #matches(context: number, generation: number): boolean {
    return (
      !this.#disposed &&
      !this.#barrier &&
      this.#canLoad &&
      this.#context === context &&
      this.#generation === generation
    );
  }

  #makeScope(): AuthorityScope {
    const context = this.#context;
    const generation = this.#generation;
    const signal = this.#scope.signal;
    return Object.freeze({
      signal,
      isCurrent: () => {
        const state = this.getSnapshot();
        return (
          !signal.aborted && this.#matches(context, generation) && state.status === 'authenticated'
        );
      }
    });
  }

  #renewScope(): void {
    const previous = this.#scope;
    this.#scope = new AbortController();
    this.#scopeView = this.#makeScope();
    previous.abort();
  }

  /**
   * Retires owned work and reports whether callbacks left this transition current
   */
  #retire(): boolean {
    // Detach owned state before any external callback. Nested transitions own their
    // replacements; this continuation may only abort its captured old request.
    this.#state = Object.freeze({ status: 'stale' });
    const context = this.#context;
    const generation = ++this.#generation;
    const request = this.#request;
    clearTimeout(this.#expiry);
    this.#request = null;
    this.#pending = null;
    this.#startedAt = null;
    const retiring = this.#retiring;
    this.#retiring = true;
    try {
      this.#renewScope();
      request?.abort();
      return context === this.#context && generation === this.#generation;
    } finally {
      this.#retiring = retiring;
    }
  }

  #publish(state: AuthorityState): void {
    this.#state = Object.freeze(state);
    this.#emit();
  }

  #emit(): void {
    for (const listener of this.#listeners) listener();
  }
}

/**
 * Observes foreground return once per tab owner, not once per route or component
 */
export function observeAuthorityResume(
  cache: AuthorityCache,
  document: Document,
  window: Window
): () => void {
  let hidden = document.visibilityState === 'hidden';
  const resume = () => {
    if (hidden && document.visibilityState === 'visible') {
      hidden = false;
      cache.resume();
    }
  };
  const visibility = () => {
    if (document.visibilityState === 'hidden') hidden = true;
    else resume();
  };
  document.addEventListener('visibilitychange', visibility);
  window.addEventListener('focus', resume);
  return () => {
    document.removeEventListener('visibilitychange', visibility);
    window.removeEventListener('focus', resume);
  };
}
