import { AsyncLocalStorage } from "node:async_hooks";

/**
 * Marks resource loading/session construction performed for a subagent. This is
 * async-context-local so concurrent top-level extension work is unaffected.
 */
// Pi can evaluate separate copies through its extension loaders. Keep one
// async-local marker across those copies, not process.env or a global boolean.
const key = Symbol.for("pi-subagents:child-session-context");
const globals = globalThis as typeof globalThis & { [key: symbol]: AsyncLocalStorage<boolean> | undefined };
globals[key] ??= new AsyncLocalStorage<boolean>();
const childSessionContext = globals[key];

export function inChildSessionContext(): boolean {
  return childSessionContext.getStore() === true;
}

export function runInChildSessionContext<T>(fn: () => Promise<T>): Promise<T> {
  return childSessionContext.run(true, fn);
}
