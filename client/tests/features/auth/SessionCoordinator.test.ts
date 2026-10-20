import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { apiFailure } from '@/api/ApiResult';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { decodeSessionMessage } from '@/features/auth/SessionChannel';
import { SessionCoordinator } from '@/features/auth/SessionCoordinator';

import { deferred } from '../../apiFixtures';

import type { ApiResult } from '@/api/ApiResult';
import type { SessionChannel } from '@/features/auth/SessionChannel';
import type { SessionLocks } from '@/features/auth/SessionCoordinator';

let sequence = 0;
const id = () => (++sequence).toString(16).padStart(32, '0');
const ok = (): ApiResult<true> => ({ ok: true, value: true, correlationId: null });
const owners: SessionCoordinator[] = [];

class Channels {
  peers = new Set<SessionChannel>();
  messages: unknown[] = [];
  open(): SessionChannel {
    const channel: SessionChannel = {
      onmessage: null,
      close: vi.fn(() => this.peers.delete(channel)),
      postMessage: (value: unknown) => {
        this.messages.push(value);
        for (const peer of this.peers) {
          if (peer !== channel)
            queueMicrotask(() => peer.onmessage?.({ data: value } as MessageEvent<unknown>));
        }
      }
    };
    this.peers.add(channel);
    return channel;
  }
}
class Locks implements SessionLocks {
  busy = false;
  queue: (() => void)[] = [];
  count = 0;
  request: SessionLocks['request'] = async (_name, options, work) => {
    this.count++;
    await new Promise<void>((resolve, reject) => {
      const abort = () => reject(new Error('aborted'));
      options.signal.addEventListener('abort', abort, { once: true });
      const enter = () => {
        options.signal.removeEventListener('abort', abort);
        if (options.signal.aborted) {
          reject(new Error('aborted'));
          return;
        }
        this.busy = true;
        resolve();
      };
      if (this.busy) this.queue.push(enter);
      else enter();
    });
    try {
      await work();
    } finally {
      this.busy = false;
      this.queue.shift()?.();
    }
  };
}
function setup(
  options: {
    channels?: Channels;
    locks?: SessionLocks;
    outcome?: () => Promise<ApiResult<true>>;
  } = {}
) {
  const tokens = new MemoryAccessToken();
  const session = {
    refresh: vi.fn(async () => {
      const generation = tokens.generation;
      const start = performance.now();
      const result = await (options.outcome?.() ?? Promise.resolve(ok()));
      if (result.ok) tokens.accept('access.current.signature', start, generation);
      return result;
    }),
    logout: vi.fn(() => Promise.resolve(ok())),
    invalidate: vi.fn(() => tokens.clear()),
    dispose: vi.fn()
  };
  const login = {
    submit: vi.fn(() => {
      tokens.accept('access.login.signature', performance.now());
      return Promise.resolve(ok());
    }),
    dispose: vi.fn()
  };
  const channel = options.channels?.open();
  const clearIntent = vi.fn();
  const coordinator = new SessionCoordinator({
    session,
    login,
    tokens,
    clearIntent,
    id,
    ...(channel ? { channel } : {}),
    ...(options.locks ? { locks: options.locks } : {})
  });
  owners.push(coordinator);
  return { coordinator, tokens, session, login, channel, clearIntent };
}
async function tick(ms = 0) {
  await vi.advanceTimersByTimeAsync(ms);
}

beforeEach(() => {
  vi.useFakeTimers();
});
afterEach(() => {
  for (const owner of owners.splice(0)) owner.dispose();
  vi.useRealTimers();
});

describe('bounded volatile session continuity', () => {
  it('starts unknown, coalesces a cold restore and schedules early by monotonic request start, not wall time', async () => {
    const d = setup();
    expect(d.coordinator.getSnapshot().status).toBe('unknown');
    expect(d.tokens.getAccessToken()).toBeNull();
    const first = d.coordinator.restore();
    expect(d.coordinator.restore()).toBe(first);
    expect(d.coordinator.getSnapshot().status).toBe('restoring');
    expect(await first).toMatchObject({ ok: true });
    vi.setSystemTime(new Date('2099-01-01'));
    expect(d.tokens.getAccessToken()).toBe('access.current.signature');
    await tick(839_999);
    expect(d.session.refresh).toHaveBeenCalledOnce();
    await tick(1);
    expect(d.session.refresh).toHaveBeenCalledTimes(2);
  });

  it('elects one leader, hands off only to a current follower round and releases the lock', async () => {
    const channels = new Channels();
    const locks = new Locks();
    const leader = setup({ channels, locks });
    const follower = setup({ channels, locks });
    const a = leader.coordinator.restore();
    const b = follower.coordinator.restore();
    await tick(200);
    await Promise.all([a, b]);
    expect(
      leader.session.refresh.mock.calls.length + follower.session.refresh.mock.calls.length
    ).toBe(1);
    expect(leader.coordinator.getSnapshot().status).toBe('authenticated');
    expect(follower.coordinator.getSnapshot().status).toBe('authenticated');
    expect(locks.busy).toBe(false);
    for (const value of channels.messages) expect(decodeSessionMessage(value)).not.toBeNull();
    expect(JSON.stringify(channels.messages)).not.toMatch(
      /password|csrf|refresh_token|roles|permissions|email/
    );
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);
    expect(JSON.stringify(leader.coordinator)).toBe('{}');
  });

  it('joins a current leader without rotating and closes listeners/channels on leave', async () => {
    const channels = new Channels();
    const leader = setup({ channels });
    const first = leader.coordinator.restore();
    await tick(50);
    await first;
    const follower = setup({ channels });
    const next = follower.coordinator.restore();
    await tick(50);
    await next;
    expect(follower.session.refresh).not.toHaveBeenCalled();
    follower.coordinator.dispose();
    expect(follower.channel?.onmessage).toBeNull();
    expect(follower.channel?.close).toHaveBeenCalledOnce();
  });

  it.each([false, true])(
    'allows exactly one conflict recovery (repeated conflict=%s), with bounded wait',
    async (repeated) => {
      let calls = 0;
      const d = setup({
        outcome: () => Promise.resolve(++calls === 1 || repeated ? apiFailure('conflict') : ok())
      });
      const result = d.coordinator.restore();
      await tick(250);
      expect((await result).ok).toBe(!repeated);
      expect(calls).toBe(2);
      expect(d.coordinator.getSnapshot().status).toBe(repeated ? 'terminal' : 'authenticated');
      if (repeated) {
        await d.coordinator.restore();
        expect(calls).toBe(2);
      }
    }
  );

  it('ends the context when the one conflict recovery fails, even with a network outcome', async () => {
    let calls = 0;
    const d = setup({
      outcome: () => Promise.resolve(apiFailure(++calls === 1 ? 'conflict' : 'network'))
    });
    const pending = d.coordinator.restore();
    await tick(250);
    await pending;
    expect(calls).toBe(2);
    expect(d.coordinator.getSnapshot().status).toBe('terminal');
  });

  it('accepts the winning peer during conflict wait rather than retrying', async () => {
    const channels = new Channels();
    const d = setup({ channels, outcome: () => Promise.resolve(apiFailure('conflict')) });
    const peer = channels.open();
    const result = d.coordinator.restore();
    await tick(50);
    const request = channels.messages.map(decodeSessionMessage).find((m) => m?.kind === 'request');
    if (!request || request.kind !== 'request') throw new Error('Expected demand');
    peer.postMessage({
      version: 1,
      sender: id(),
      sequence: 1,
      kind: 'token',
      round: request.round,
      token: 'access.winner.signature',
      remaining: 800_000
    });
    await tick();
    await result;
    expect(d.session.refresh).toHaveBeenCalledOnce();
    expect(d.coordinator.getSnapshot().status).toBe('authenticated');
  });

  it('times out acquisition, aborts the queue, makes at most one successor election and stops', async () => {
    const request = vi.fn<SessionLocks['request']>(
      (_n, { signal }) =>
        new Promise((_resolve, reject) =>
          signal.addEventListener('abort', () => reject(new Error('timeout')), { once: true })
        )
    );
    const d = setup({ locks: { request } });
    const result = d.coordinator.restore();
    await tick(4_000);
    await result;
    expect(request).toHaveBeenCalledTimes(2);
    expect(d.session.refresh).not.toHaveBeenCalled();
    expect(d.coordinator.getSnapshot().status).toBe('network-degraded');
    await tick(100_000);
    expect(request).toHaveBeenCalledTimes(2);
  });

  it('recovers once when the first leader disappears before acquisition', async () => {
    let calls = 0;
    const locks: SessionLocks = {
      request: async (_n, { signal }, work) => {
        if (++calls === 1)
          await new Promise<void>((_r, reject) =>
            signal.addEventListener('abort', () => reject(new Error('leader gone')))
          );
        else await work();
      }
    };
    const d = setup({ locks });
    const result = d.coordinator.restore();
    await tick(2_000);
    await result;
    expect(calls).toBe(2);
    expect(d.coordinator.getSnapshot().status).toBe('authenticated');
  });

  it('bounds a hung leader hold, releases the lock, and rejects its late access result', async () => {
    const delayed = deferred<ApiResult<true>>();
    const locks = new Locks();
    const d = setup({ locks, outcome: () => delayed.promise });
    const result = d.coordinator.restore();
    await tick(8_000);
    await result;
    expect(locks.busy).toBe(false);
    expect(d.coordinator.getSnapshot().status).toBe('network-degraded');
    delayed.resolve(ok());
    await tick();
    expect(d.tokens.getAccessToken()).toBeNull();
    expect(d.session.refresh).toHaveBeenCalledOnce();
  });

  it.each(['network', 'protocol', 'system'] as const)(
    'clears authority on %s without an automatic retry',
    async (kind) => {
      const d = setup({ outcome: () => Promise.resolve(apiFailure(kind)) });
      d.tokens.accept('old.access.signature', performance.now());
      await d.coordinator.restore();
      expect(d.tokens.getAccessToken()).toBeNull();
      expect(d.coordinator.getSnapshot().status).toBe('network-degraded');
      await tick(1_000_000);
      expect(d.session.refresh).toHaveBeenCalledOnce();
    }
  );

  it('propagates logout, clears only local intended route, and rejects delayed refresh/unsolicited tokens', async () => {
    const channels = new Channels();
    const delayed = deferred<ApiResult<true>>();
    const a = setup({ channels, outcome: () => delayed.promise });
    const b = setup({ channels });
    const pending = a.coordinator.restore();
    await tick(50);
    await b.coordinator.logout();
    await tick();
    delayed.resolve(ok());
    await tick();
    await pending;
    expect(a.tokens.getAccessToken()).toBeNull();
    expect(a.clearIntent).toHaveBeenCalledOnce();
    expect(a.coordinator.getSnapshot().status).toBe('anonymous');
    expect(a.session.logout).not.toHaveBeenCalled();
    expect(b.session.logout).toHaveBeenCalledOnce();
    expect((await a.coordinator.restore()).ok).toBe(false);
  });

  it('propagates terminal 401 and requires explicit reauthentication across the barrier', async () => {
    const channels = new Channels();
    const a = setup({ channels, outcome: () => Promise.resolve(apiFailure('authentication')) });
    const b = setup({ channels });
    const pending = a.coordinator.restore();
    await tick(50);
    await pending;
    expect(b.coordinator.getSnapshot().status).toBe('terminal');
    expect(b.tokens.getAccessToken()).toBeNull();
    expect(b.clearIntent).not.toHaveBeenCalled();
    b.coordinator.showLogin();
    expect((await b.coordinator.restore()).ok).toBe(false);
    await b.coordinator.submit({ email: 'synthetic@example.test', password: 'synthetic' }, false);
    expect(b.coordinator.getSnapshot().status).toBe('authenticated');
  });

  it('fences login completion after terminal/logout and reports logout uncertainty', async () => {
    const d = setup();
    const late = deferred<ApiResult<true>>();
    d.login.submit.mockImplementation(() => late.promise);
    const login = d.coordinator.submit({}, false);
    d.coordinator.terminal();
    late.resolve(ok());
    expect((await login).ok).toBe(false);
    expect(d.coordinator.getSnapshot().status).toBe('terminal');
    d.session.logout.mockResolvedValue(apiFailure('network'));
    await d.coordinator.logout();
    expect(d.coordinator.getSnapshot()).toEqual({ status: 'anonymous', signOutUnconfirmed: true });
  });

  it.each(['none', 'locks', 'channel'] as const)(
    'uses bounded independent fallback with %s available',
    async (available) => {
      const d = setup({
        ...(available === 'locks' ? { locks: new Locks() } : {}),
        ...(available === 'channel' ? { channels: new Channels() } : {}),
        outcome: () => Promise.resolve(apiFailure('conflict'))
      });
      const pending = d.coordinator.restore();
      await tick(350);
      await pending;
      expect(d.session.refresh).toHaveBeenCalledTimes(2);
      expect(d.coordinator.getSnapshot().status).toBe('terminal');
    }
  );

  it('retires a peer invalidation and starts one bounded fresh round instead of leaving loading stuck', async () => {
    const channels = new Channels();
    const d = setup({ channels });
    const initial = d.coordinator.restore();
    await tick(50);
    await initial;
    d.channel?.onmessage?.({
      data: { version: 1, sender: id(), sequence: 1, kind: 'invalidate' }
    } as MessageEvent<unknown>);
    expect(d.tokens.getAccessToken()).toBeNull();
    await tick(50);
    expect(d.session.refresh).toHaveBeenCalledTimes(2);
    expect(d.coordinator.getSnapshot().status).toBe('authenticated');
  });

  it.each([false, true])(
    'fences a delayed peer token across another sender’s invalidation (Locks=%s)',
    async (withLocks) => {
      const channels = new Channels();
      const d = setup({ channels, ...(withLocks ? { locks: new Locks() } : {}) });
      const pending = d.coordinator.restore();
      const request = channels.messages
        .map(decodeSessionMessage)
        .find((m) => m?.kind === 'request');
      if (!request || request.kind !== 'request') throw new Error('Expected demand');
      const send = (data: unknown) => d.channel?.onmessage?.({ data } as MessageEvent<unknown>);
      // B's reply was sent before C replaced the browser authentication context.
      send({ version: 1, sender: id(), sequence: 1, kind: 'invalidate' });
      send({
        version: 1,
        sender: id(),
        sequence: 1,
        kind: 'token',
        round: request.round,
        token: 'old.identity.signature',
        remaining: 800_000
      });
      expect(d.tokens.getAccessToken()).toBeNull();
      expect(d.coordinator.getSnapshot().status).toBe('restoring');
      const requests = channels.messages
        .map(decodeSessionMessage)
        .filter((m) => m?.kind === 'request');
      const current = requests.at(-1);
      if (!current || current.kind !== 'request') throw new Error('Expected current demand');
      expect(current.round).not.toBe(request.round);
      send({
        version: 1,
        sender: id(),
        sequence: 1,
        kind: 'token',
        round: current.round,
        token: 'current.identity.signature',
        remaining: 800_000
      });
      await tick(200);
      await pending;
      expect(d.tokens.getAccessToken()).toBe('current.identity.signature');
      expect(d.coordinator.getSnapshot().status).toBe('authenticated');
      expect(d.session.refresh).not.toHaveBeenCalled();
    }
  );

  it.each(['logout', 'terminal'] as const)(
    'honors a new sender’s %s after 64 historical senders',
    async (kind) => {
      const channels = new Channels();
      const d = setup({ channels });
      await d.coordinator.submit({}, false);
      const send = (data: unknown) => d.channel?.onmessage?.({ data } as MessageEvent<unknown>);
      for (let n = 0; n < 64; n++)
        send({ version: 1, sender: id(), sequence: 1, kind: 'request', round: id() });
      send({ version: 1, sender: id(), sequence: 1, kind });
      expect(d.tokens.getAccessToken()).toBeNull();
      expect(d.coordinator.getSnapshot().status).toBe(kind === 'logout' ? 'anonymous' : 'terminal');
      expect(d.session.logout).not.toHaveBeenCalled();
      expect(await d.coordinator.restore()).toMatchObject({
        ok: false,
        error: { kind: 'cancelled' }
      });
    }
  );

  it('honors new-sender invalidation at capacity without admitting its credentials', async () => {
    const channels = new Channels();
    const d = setup({ channels });
    await d.coordinator.submit({}, false);
    const send = (data: unknown) => d.channel?.onmessage?.({ data } as MessageEvent<unknown>);
    for (let n = 0; n < 64; n++)
      send({ version: 1, sender: id(), sequence: 1, kind: 'request', round: id() });
    const untracked = id();
    send({ version: 1, sender: untracked, sequence: 1, kind: 'invalidate' });
    expect(d.tokens.getAccessToken()).toBeNull();
    expect(d.coordinator.getSnapshot().status).toBe('restoring');
    const request = channels.messages
      .map(decodeSessionMessage)
      .filter((m) => m?.kind === 'request')
      .at(-1);
    if (!request || request.kind !== 'request') throw new Error('Expected fresh demand');
    send({
      version: 1,
      sender: untracked,
      sequence: 2,
      kind: 'token',
      round: request.round,
      token: 'untracked.identity.signature',
      remaining: 800_000
    });
    expect(d.tokens.getAccessToken()).toBeNull();
    await tick(50);
    expect(d.session.refresh).toHaveBeenCalledOnce();
    expect(d.tokens.getAccessToken()).toBe('access.current.signature');
  });

  it.each([false, true])(
    'fences outstanding handoffs on untracked invalidation at capacity (Locks=%s)',
    async (withLocks) => {
      const channels = new Channels();
      const d = setup({ channels, ...(withLocks ? { locks: new Locks() } : {}) });
      const send = (data: unknown) => d.channel?.onmessage?.({ data } as MessageEvent<unknown>);
      const tracked = id();
      send({ version: 1, sender: tracked, sequence: 1, kind: 'request', round: id() });
      for (let n = 1; n < 64; n++)
        send({ version: 1, sender: id(), sequence: 1, kind: 'request', round: id() });
      const pending = d.coordinator.restore();
      const request = () => {
        const message = channels.messages
          .map(decodeSessionMessage)
          .filter((m) => m?.kind === 'request')
          .at(-1);
        if (!message || message.kind !== 'request') throw new Error('Expected demand');
        return message.round;
      };
      const oldRound = request();
      const untracked = id();
      send({ version: 1, sender: untracked, sequence: 1, kind: 'invalidate' });
      const currentRound = request();
      expect(currentRound).not.toBe(oldRound);
      const reply = {
        version: 1,
        kind: 'token',
        token: 'old.identity.signature',
        remaining: 800_000
      };
      send({ ...reply, sender: tracked, sequence: 2, round: oldRound });
      send({ ...reply, sender: untracked, sequence: 2, round: currentRound });
      expect(d.tokens.getAccessToken()).toBeNull();
      expect(d.coordinator.getSnapshot().status).toBe('restoring');
      send({
        ...reply,
        sender: tracked,
        sequence: 3,
        round: currentRound,
        token: 'current.identity.signature'
      });
      await tick(200);
      await pending;
      expect(d.tokens.getAccessToken()).toBe('current.identity.signature');
      expect(d.session.refresh).not.toHaveBeenCalled();
    }
  );

  it('does not renew observation deadlines or admit new senders under invalidation churn at capacity', async () => {
    const channels = new Channels();
    const locks = new Locks();
    const d = setup({ channels, locks, outcome: () => new Promise<ApiResult<true>>(() => {}) });
    const send = (data: unknown) => d.channel?.onmessage?.({ data } as MessageEvent<unknown>);
    for (let n = 0; n < 64; n++)
      send({ version: 1, sender: id(), sequence: 1, kind: 'request', round: id() });
    const pending = d.coordinator.restore();
    await tick(100);
    for (let n = 0; n < 8; n++) {
      send({ version: 1, sender: id(), sequence: 1, kind: 'invalidate' });
      await tick(1_000);
    }
    await pending;
    expect(d.tokens.getAccessToken()).toBeNull();
    expect(d.coordinator.getSnapshot().status).toBe('network-degraded');
    expect(d.session.refresh).toHaveBeenCalledOnce();
    expect(locks.count).toBe(1);
    expect(locks.busy).toBe(false);
    await tick(100_000);
    expect(d.session.refresh).toHaveBeenCalledOnce();
  });

  it('fails closed and restores on wake without retaining stale access', async () => {
    const delayed = deferred<ApiResult<true>>();
    const d = setup();
    await d.coordinator.restore();
    d.session.refresh.mockImplementation(() => delayed.promise);
    d.coordinator.resume();
    expect(d.tokens.getAccessToken()).toBeNull();
    expect(d.coordinator.getSnapshot().status).toBe('restoring');
    d.coordinator.dispose();
    delayed.resolve(ok());
    await tick();
    expect(d.tokens.getAccessToken()).toBeNull();
  });

  it('ignores duplicate, stale-round, malformed, oversized and permission-bearing channel input', async () => {
    const channels = new Channels();
    const delayed = deferred<ApiResult<true>>();
    const d = setup({ channels, outcome: () => delayed.promise });
    const pending = d.coordinator.restore();
    await tick(50);
    const sender = id();
    const send = (data: unknown) => d.channel?.onmessage?.({ data } as MessageEvent<unknown>);
    const token = {
      version: 1,
      sender,
      sequence: 1,
      kind: 'token',
      round: id(),
      token: 'access.peer.signature',
      remaining: 800_000
    };
    send({ ...token, roles: ['ADMIN'] });
    send({ ...token, token: 'x'.repeat(3000) });
    send({ ...token, version: 2 });
    send(token);
    expect(d.tokens.getAccessToken()).toBeNull();
    send({ version: 1, sender, sequence: 2, kind: 'terminal' });
    send({ ...token, sequence: 1 });
    delayed.resolve(ok());
    await tick();
    await pending;
    expect(d.coordinator.getSnapshot().status).toBe('terminal');
  });
});
