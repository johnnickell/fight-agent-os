import { limits, Unavailable } from './readiness.mjs';

/** Share admission with readiness and retain slots until even uncooperative operations settle */
export class SessionRequests {
  #active = new Set();
  constructor(concurrent = limits.concurrent, onIdle = () => {}) {
    if (!Number.isSafeInteger(concurrent) || concurrent < 1 || concurrent > limits.concurrent) throw new Unavailable('invalid-setup');
    this.concurrent = concurrent;
    this.onIdle = onIdle;
  }
  get idle() { return this.#active.size === 0; }
  acquire(controller) {
    if (this.#active.size >= this.concurrent) throw new Unavailable('concurrency-limit');
    this.#active.add(controller);
  }
  release(controller) { if (this.#active.delete(controller) && this.idle) this.onIdle(); }
}

// A reload can leave an uncancellable Pi credential resolution in the old runtime. Preserve its slots.
// Entries exist only while a runtime owns the scope or an outstanding operation retains it.
const key = Symbol.for('@fight/agent-os-pi/jev-session-requests-v1');
export function retainSessionRequests(sessionId) {
  const registry = globalThis[key] ??= new Map();
  let entry = registry.get(sessionId);
  if (!entry) {
    entry = { scope: new SessionRequests(limits.concurrent, cleanup), owners: 0 };
    registry.set(sessionId, entry);
  }
  function cleanup() { if (entry.owners === 0 && entry.scope.idle) registry.delete(sessionId); }
  entry.owners++;
  let released = false;
  return { scope: entry.scope, release() { if (!released) { released = true; entry.owners--; cleanup(); } } };
}
