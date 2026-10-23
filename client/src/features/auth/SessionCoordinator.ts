import { apiFailure, type ApiResult } from '@/api/ApiResult';
import {
  decodeSessionMessage,
  type SessionChannel,
  sessionLockName,
  type SessionMessage
} from '@/features/auth/SessionChannel';

import type { LoginService } from '@/features/auth/LoginService';
import type { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import type { SessionService } from '@/features/auth/SessionService';
import type { AuthorityCache, AuthoritySignal } from '@/features/authority/AuthorityCache';

export type SessionState = Readonly<{
  status:
    | 'unknown'
    | 'restoring'
    | 'authenticated'
    | 'anonymous'
    | 'conflict'
    | 'network-degraded'
    | 'terminal'
    | 'logout';
  signOutUnconfirmed?: boolean;
}>;
export type SessionLocks = {
  request: (
    name: string,
    options: { mode: 'exclusive'; signal: AbortSignal },
    work: () => Promise<void>
  ) => Promise<void>;
};
type SignalBody =
  | { kind: 'request'; round: string }
  | { kind: 'token'; round: string; token: string; remaining: number }
  | { kind: 'invalidate' | 'authority-changed' | 'logout' | 'terminal' };
type Round = { id: string; generation: number; started: number; abort: AbortController };
const success = (): ApiResult<true> => ({ ok: true, value: true, correlationId: null });

/** Owns volatile credential progress, not a principal/permission cache or mutation replay interceptor. */
export class SessionCoordinator {
  readonly #session: Pick<SessionService, 'refresh' | 'logout' | 'dispose' | 'invalidate'>;
  readonly #login: Pick<LoginService, 'submit' | 'dispose'>;
  readonly #tokens: MemoryAccessToken;
  readonly #locks: SessionLocks | undefined;
  readonly #channel: SessionChannel | undefined;
  readonly #authority: AuthorityCache | undefined;
  readonly #clearIntent: (() => void) | undefined;
  readonly #coldAnonymous: boolean;
  #established = false;
  readonly #unsubscribeAuthority: (() => void) | undefined;
  readonly #now: () => number;
  readonly #id: () => string;
  readonly #sender: string;
  readonly #listeners = new Set<() => void>();
  readonly #peers = new Map<string, number>();
  readonly #requests = new Set<string>();
  #state: SessionState = Object.freeze({ status: 'unknown' });
  #generation = 0;
  #sequence = 0;
  #active = true;
  #barrier = false;
  #round: Round | null = null;
  #pending: Promise<ApiResult<true>> | null = null;
  #expiry: ReturnType<typeof setTimeout> | undefined;
  #lastClock: number;
  #foreground = true;

  constructor(dependencies: {
    session: Pick<SessionService, 'refresh' | 'logout' | 'dispose' | 'invalidate'>;
    login: Pick<LoginService, 'submit' | 'dispose'>;
    tokens: MemoryAccessToken;
    locks?: SessionLocks;
    channel?: SessionChannel;
    authority?: AuthorityCache;
    clearIntent?: () => void;
    coldAnonymous?: boolean;
    now?: () => number;
    id?: () => string;
  }) {
    this.#session = dependencies.session;
    this.#login = dependencies.login;
    this.#tokens = dependencies.tokens;
    this.#locks = dependencies.locks;
    this.#channel = dependencies.channel;
    this.#authority = dependencies.authority;
    this.#unsubscribeAuthority = this.#authority?.subscribe(() => {
      // The cache fences results first. A superseded /me rejection must not clear new credentials.
      if (this.#authority?.getSnapshot().status === 'terminal' && !this.#barrier) this.terminal();
    });
    this.#clearIntent = dependencies.clearIntent;
    this.#coldAnonymous = dependencies.coldAnonymous ?? false;
    this.#now = dependencies.now ?? (() => performance.now());
    this.#id = dependencies.id ?? (() => crypto.randomUUID().replaceAll('-', ''));
    this.#sender = this.#id();
    this.#lastClock = this.#now();
    if (this.#channel) this.#channel.onmessage = (event) => this.#receive(event.data);
  }

  subscribe = (listener: () => void): (() => void) => {
    this.#listeners.add(listener);
    return () => this.#listeners.delete(listener);
  };
  getSnapshot = (): SessionState => this.#state;

  /** One cold restoration or explicit retry; terminal/logout barriers require explicit login. */
  restore = (): Promise<ApiResult<true>> => {
    if (!this.#active || this.#barrier) return Promise.resolve(apiFailure('cancelled'));
    if (this.#pending) return this.#pending;
    const round: Round = {
      id: this.#id(),
      generation: ++this.#generation,
      started: this.#now(),
      abort: new AbortController()
    };
    this.#round = round;
    this.#tokens.clear();
    clearTimeout(this.#expiry);
    this.#signal('refresh-started');
    this.#publish('restoring');
    const pending = this.#restore(round).finally(() => {
      if (this.#pending === pending) this.#pending = null;
      if (this.#round === round) this.#round = null;
      round.abort.abort();
    });
    this.#pending = pending;
    return pending;
  };

  async #restore(round: Round): Promise<ApiResult<true>> {
    this.#send({ kind: 'request', round: round.id });
    // Give an already-current sibling one bounded chance to answer the fresh challenge.
    if (this.#channel) await this.#pause(50, round.abort.signal);
    if (this.#tokens.getAccessToken()) return success();
    let result = apiFailure('network') as ApiResult<true>;
    // Only lock acquisition loss permits one successor election. A dispatched mutation is not replayed.
    for (let election = 0; election < 2 && this.#current(round); election++) {
      let entered = false;
      const acquire = new AbortController();
      const abort = () => acquire.abort();
      round.abort.signal.addEventListener('abort', abort, { once: true });
      const timer = setTimeout(abort, 2_000);
      const work = async () => {
        entered = true;
        clearTimeout(timer);
        try {
          // A winner posts before releasing its lock, but cross-agent message delivery may lag it.
          if (this.#channel && this.#locks) await this.#pause(50, round.abort.signal);
          if (!this.#current(round) || this.#tokens.getAccessToken()) return;
          result = await this.#rotate(round);
        } finally {
          // Returning releases Web Locks even after timeout/terminal/disposal.
          acquire.abort();
        }
      };
      try {
        if (this.#locks)
          await this.#locks.request(
            sessionLockName,
            { mode: 'exclusive', signal: acquire.signal },
            work
          );
        else await work();
      } catch {
        result = apiFailure('network');
      } finally {
        clearTimeout(timer);
        round.abort.signal.removeEventListener('abort', abort);
      }
      if (entered || this.#tokens.getAccessToken()) break;
    }
    if (this.#tokens.getAccessToken() && this.#state.status === 'authenticated') return success();
    if (!this.#current(round)) return apiFailure('cancelled');
    if (result.ok) {
      if (this.#tokens.remaining() <= 0) result = apiFailure('network');
      else {
        this.#adopt();
        this.#answerRequests();
        return result;
      }
    }
    this.#tokens.clear();
    if (result.error.kind === 'authentication' && this.#coldAnonymous && !this.#established) {
      // A cold browser cannot distinguish an absent from an expired cookie. Fence all
      // work, require explicit login, and retain only its classified navigation intent.
      this.#retire();
      this.#barrier = true;
      this.#session.invalidate();
      this.#signal('terminal');
      this.#publish('anonymous');
    } else if (result.error.kind === 'authentication' || result.error.kind === 'conflict') {
      this.terminal();
    } else {
      this.#signal('refresh-failed');
      this.#publish('network-degraded');
    }
    return result;
  }

  async #rotate(round: Round): Promise<ApiResult<true>> {
    this.#send({ kind: 'invalidate' });
    let result = await this.#bounded(this.#session.refresh(), 8_000, round.abort.signal);
    if (!this.#current(round)) return apiFailure('cancelled');
    if (!result.ok && result.error.kind === 'conflict') {
      this.#publish('conflict');
      this.#send({ kind: 'request', round: round.id });
      await this.#pause(250, round.abort.signal);
      if (this.#tokens.getAccessToken()) return success();
      if (!this.#current(round)) return apiFailure('cancelled');
      // Exactly one package-conflict recovery, using only the browser's current HttpOnly cookie.
      result = await this.#bounded(this.#session.refresh(), 8_000, round.abort.signal);
      if (!result.ok && this.#current(round)) this.terminal();
    }
    return result;
  }

  /** Explicit login crosses a terminal barrier with its own local generation. No form values retained. */
  submit: LoginService['submit'] = async (values, remember) => {
    if (!this.#active || this.#state.status === 'restoring' || this.#state.status === 'logout')
      return apiFailure('cancelled');
    this.#retire();
    // Retire deferred service mutations too, before a replacement login can change the cookie.
    this.#session.invalidate();
    this.#barrier = true;
    const generation = this.#generation;
    const attempt = this.#authority?.beginAuthentication();
    this.#send({ kind: 'invalidate' });
    const abort = new AbortController();
    const result = await this.#bounded(this.#login.submit(values, remember), 8_000, abort.signal);
    if (!this.#active || generation !== this.#generation) return apiFailure('cancelled');
    if (result.ok && this.#tokens.getAccessToken()) {
      this.#barrier = false;
      this.#adopt();
      void attempt?.acceptCredentials();
    } else {
      this.#tokens.clear();
      this.#barrier = false;
      attempt?.fail();
      this.#publish('anonymous');
    }
    return result;
  };

  logout = async (): Promise<ApiResult<true>> => {
    if (!this.#active || this.#state.status === 'logout') return apiFailure('cancelled');
    this.#end('logout');
    this.#send({ kind: 'logout' });
    const generation = this.#generation;
    const result = await this.#withLogoutLock(generation);
    if (!this.#active || generation !== this.#generation) return apiFailure('cancelled');
    this.#state = Object.freeze({ status: 'anonymous', signOutUnconfirmed: !result.ok });
    this.#notify();
    return result;
  };

  async #withLogoutLock(generation: number): Promise<ApiResult<true>> {
    let result: ApiResult<true> = apiFailure('network');
    const abort = new AbortController();
    const timer = setTimeout(() => abort.abort(), 2_000);
    const work = async () => {
      clearTimeout(timer);
      // A peer session end/new login can retire this queued operation before lock entry.
      // Fence the service call itself: logout clears memory and selects the current browser cookie.
      if (!this.#active || generation !== this.#generation) {
        result = apiFailure('cancelled');
        return;
      }
      result = await this.#bounded(this.#session.logout(), 8_000, new AbortController().signal);
    };
    try {
      if (this.#locks)
        await this.#locks.request(
          sessionLockName,
          { mode: 'exclusive', signal: abort.signal },
          work
        );
      else await work();
    } catch {
      // Failure to acquire is uncertainty, never proof of server revocation.
    } finally {
      clearTimeout(timer);
    }
    return result;
  }

  /** Retires current authority locally and asks peers to refetch their own /me, without token exchange. */
  authorityChanged = (): void => {
    if (!this.#active || this.#barrier) return;
    this.#signal(this.#tokens.getAccessToken() ? 'authority-changed' : 'refresh-started');
    this.#send({ kind: 'authority-changed' });
  };

  terminal = (): void => {
    if (!this.#active) return;
    this.#end('terminal');
    this.#send({ kind: 'terminal' });
  };

  /** Pauses routine renewal while hidden; conservative memory expiry still applies at dispatch */
  pause = (): void => {
    this.#foreground = false;
    clearTimeout(this.#expiry);
  };

  /** Rechecks the volatile budget; visibility alone does not discard usable credentials */
  resume = (): void => {
    this.#foreground = true;
    const now = this.#now();
    const discontinuous = now < this.#lastClock || !Number.isFinite(now);
    this.#lastClock = now;
    if (!this.#active || this.#barrier || this.#pending) return;
    if (this.#state.status === 'authenticated' && !discontinuous && this.#tokens.getAccessToken()) {
      this.#authority?.resume();
      this.#schedule();
    } else if (this.#state.status === 'authenticated' || discontinuous) {
      this.#retire();
      void this.restore();
    }
  };

  showLogin = (): void => {
    if (!this.#active) return;
    this.#retire();
    // Showing a form does not admit peer tokens or start refresh across a terminal barrier.
    this.#publish('anonymous');
  };

  #receive(value: unknown): void {
    const message = decodeSessionMessage(value);
    if (!this.#active || !message || message.sender === this.#sender) return;
    const prior = this.#peers.get(message.sender) ?? 0;
    if (message.sequence <= prior) return;
    if (!this.#peers.has(message.sender) && this.#peers.size >= 64) {
      // Bound replay bookkeeping, not fail-closed safety signals from later tabs.
      // Untracked senders can remove authority, but cannot supply credentials or progress.
      if (
        message.kind !== 'invalidate' &&
        message.kind !== 'authority-changed' &&
        message.kind !== 'logout' &&
        message.kind !== 'terminal'
      )
        return;
    } else this.#peers.set(message.sender, message.sequence);
    if (message.kind === 'logout' || message.kind === 'terminal') {
      this.#end(message.kind);
      if (message.kind === 'logout') {
        this.#state = Object.freeze({ status: 'anonymous', signOutUnconfirmed: false });
        this.#notify();
      }
      return;
    }
    if (message.kind === 'authority-changed') {
      this.#signal(this.#tokens.getAccessToken() ? 'authority-changed' : 'refresh-started');
      return;
    }
    if (message.kind === 'invalidate') {
      this.#tokens.clear();
      // Peer context changes must fence deferred cookie mutations, not only token adoption.
      this.#session.invalidate();
      this.#signal('refresh-started');
      const round = this.#round;
      if (round && this.#current(round)) {
        // Retire pre-invalidation peer replies without restarting the bounded mutation/retry flow.
        round.id = this.#id();
        round.started = this.#now();
        this.#send({ kind: 'request', round: round.id });
      }
      if (this.#state.status === 'authenticated') {
        this.#retire();
        this.#publish('unknown');
        if (!this.#barrier) void this.restore();
      }
      return;
    }
    if (message.kind === 'request') {
      if (this.#requests.size < 64) this.#requests.add(message.round);
      this.#answerRequests();
      return;
    }
    if (message.kind !== 'token') return;
    const round = this.#round;
    if (this.#barrier || !round || !this.#current(round) || message.round !== round.id) return;
    const elapsed = this.#now() - round.started;
    if (!Number.isFinite(elapsed) || elapsed < 0) return;
    // Subtract the full challenge elapsed time; delayed delivery never renews the leader's budget.
    this.#tokens.clear();
    if (
      !this.#tokens.accept(
        message.token,
        this.#now(),
        this.#tokens.generation,
        message.remaining - elapsed
      )
    )
      return;
    this.#adopt();
    round.abort.abort();
  }

  #answerRequests(): void {
    if (this.#barrier || this.#state.status !== 'authenticated') return;
    const token = this.#tokens.getAccessToken();
    if (!token) return;
    for (const round of this.#requests)
      this.#send({ kind: 'token', round, token, remaining: this.#tokens.remaining() });
    this.#requests.clear();
  }
  #adopt(): void {
    this.#established = true;
    this.#signal('credentials-changed');
    this.#publish('authenticated');
    this.#schedule();
  }
  #schedule(): void {
    clearTimeout(this.#expiry);
    if (this.#foreground)
      this.#expiry = setTimeout(() => {
        if (this.#active && !this.#barrier) void this.restore();
      }, this.#tokens.remaining());
  }
  #send(body: SignalBody): void {
    const message: SessionMessage = {
      version: 1,
      sender: this.#sender,
      sequence: ++this.#sequence,
      ...body
    };
    try {
      this.#channel?.postMessage(message);
    } catch {
      /* Best-effort, no persistent queue. */
    }
  }
  #current(round: Round): boolean {
    return (
      this.#active &&
      !this.#barrier &&
      round.generation === this.#generation &&
      !round.abort.signal.aborted
    );
  }
  #retire(): void {
    ++this.#generation;
    this.#round?.abort.abort();
    this.#round = null;
    this.#pending = null;
    this.#tokens.clear();
    this.#requests.clear();
    clearTimeout(this.#expiry);
  }
  #end(status: 'logout' | 'terminal'): void {
    this.#retire();
    this.#barrier = true;
    this.#session.invalidate();
    this.#clearIntent?.();
    this.#signal(status);
    this.#publish(status);
  }
  #signal(signal: AuthoritySignal): void {
    void this.#authority?.signal(signal);
  }
  #publish(status: SessionState['status']): void {
    this.#state = Object.freeze({ status });
    this.#notify();
  }
  #notify(): void {
    for (const listener of this.#listeners) listener();
  }
  #pause(ms: number, signal: AbortSignal): Promise<void> {
    return this.#bounded(new Promise<ApiResult<true>>(() => {}), ms, signal).then(() => {});
  }
  #bounded(
    promise: Promise<ApiResult<true>>,
    ms: number,
    signal: AbortSignal
  ): Promise<ApiResult<true>> {
    return new Promise((resolve) => {
      let settled = false;
      const finish = (result: ApiResult<true>) => {
        if (settled) return;
        settled = true;
        clearTimeout(timer);
        signal.removeEventListener('abort', abort);
        resolve(result);
      };
      const abort = () => finish(apiFailure('cancelled'));
      const timer = setTimeout(() => finish(apiFailure('network')), ms);
      signal.addEventListener('abort', abort, { once: true });
      if (signal.aborted) abort();
      void promise.then(finish, () => finish(apiFailure('network')));
    });
  }
  dispose = (): void => {
    if (!this.#active) return;
    this.#active = false;
    this.#unsubscribeAuthority?.();
    this.#retire();
    if (this.#channel) {
      this.#channel.onmessage = null;
      this.#channel.close();
    }
    this.#session.dispose();
    this.#login.dispose();
    this.#listeners.clear();
  };
}
