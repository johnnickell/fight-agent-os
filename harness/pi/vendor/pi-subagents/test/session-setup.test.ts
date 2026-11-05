import { EventEmitter } from "node:events";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { fauxAssistantMessage, fauxText } from "@earendil-works/pi-ai";
import { type AgentSession, type ExtensionAPI, type ExtensionContext, SessionManager } from "@earendil-works/pi-coding-agent";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { AgentManager } from "../src/agent-manager.js";
import subagentsExtension from "../src/index.js";
import { ctx, flush, hermeticDir, makePi } from "./helpers/boot-extension.js";
import { fauxModelBackend } from "./helpers/faux-model-backend.js";
import { registerFauxProvider } from "./helpers/pi-ai.js";

const MANAGER_KEY = Symbol.for("pi-subagents:manager");
const FIXTURE = fileURLToPath(new URL("./fixtures/session-setup-ext.mjs", import.meta.url));
type Setup = (session: AgentSession, signal?: AbortSignal) => void | Promise<void>;
type Options = Parameters<AgentManager["spawn"]>[4] & { beforeSessionStart?: Setup };
type Registry = Pick<AgentManager, "spawn" | "getRecord" | "waitForAll"> & {
  capabilities?: { beforeSessionStart: boolean; boundRootSession: boolean };
};
const registry = () => Reflect.get(globalThis, MANAGER_KEY) as Registry;

function deferred() {
  let resolve!: () => void;
  let reject!: (error: Error) => void;
  const promise = new Promise<void>((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
}

// Real manager, runner, loader, child SDK sessions and extension lifecycle. Only
// the parent UI and model transport are controlled; no keys or network calls.
describe("trusted host setup for programmatic spawns", () => {
  let env: ReturnType<typeof hermeticDir>;
  let root: ReturnType<typeof makePi>;
  let parent: ExtensionContext;
  let faux: ReturnType<typeof registerFauxProvider>;
  let requests: number;
  let priorRegistry: unknown;

  beforeEach(() => {
    priorRegistry = Reflect.get(globalThis, MANAGER_KEY);
    Reflect.deleteProperty(globalThis, MANAGER_KEY);
    env = hermeticDir({
      settings: { schedulingEnabled: false, workflowsEnabled: false, maxConcurrent: 1 },
      agentFiles: {
        setup: `---\ntools: read\nextensions: [${JSON.stringify(FIXTURE)}]\nskills: false\npersist_session: false\n---\nAnswer briefly.`,
      },
    });
    faux = registerFauxProvider({ provider: "faux", models: [{ id: "setup", contextWindow: 200_000 }] });
    requests = 0;
    faux.setResponses(Array.from({ length: 10 }, () => () => {
      requests++;
      return fauxAssistantMessage([fauxText("done")]);
    }));
    const model = faux.getModel();
    const backend = fauxModelBackend(model);
    backend.modelRegistry.runtime = backend.modelRuntime;
    parent = ctx({
      cwd: env.dir, model, modelRegistry: backend.modelRegistry,
      isProjectTrusted: () => true, sessionManager: SessionManager.inMemory(env.dir),
    }) as ExtensionContext;
    root = makePi();
    subagentsExtension(root.pi);
  });

  afterEach(async () => {
    await root.lifecycle.get("session_shutdown")?.();
    faux.unregister();
    env.restore();
    if (priorRegistry === undefined) Reflect.deleteProperty(globalThis, MANAGER_KEY);
    else Reflect.set(globalThis, MANAGER_KEY, priorRegistry);
  });

  async function bind() {
    await root.lifecycle.get("session_start")({}, parent);
  }

  function spawn(options: Partial<Options> = {}, caller = parent) {
    return registry().spawn(root.pi as ExtensionAPI, caller, "setup", "say done", {
      description: "host setup", isBackground: true, ...options,
    });
  }

  it("advertises the setup and bound-root capabilities without relying on a version", () => {
    expect(registry().capabilities).toEqual({ beforeSessionStart: true, boundRootSession: true });
  });

  it("awaits setup before real session_start and prompting, using only the bound parent", async () => {
    await bind();
    const gate = deferred();
    const entered = deferred();
    let child: AgentSession | undefined;
    const setup = vi.fn<Setup>(async session => {
      child = session;
      session.sessionManager.appendCustomEntry("test:session-setup", "authorized");
      entered.resolve();
      await gate.promise;
    });
    // Neither an options field nor the registry's caller-supplied ctx can name
    // another root/config. The real session_start context is authoritative.
    const forged = ctx({ cwd: resolve(env.dir, "missing"), sessionManager: SessionManager.inMemory("/forged") });
    const id = spawn({ rootSessionId: "../../forged", beforeSessionStart: setup }, forged);
    try {
      await entered.promise;
      expect(child?.sessionManager.getCwd()).toBe(env.dir);
      expect(child?.model).toBe(parent.model);
      expect(requests).toBe(0);
      expect(child?.sessionManager.getEntries().some(e => e.type === "custom" && e.customType === "test:setup-observed")).toBe(false);
      expect(registry().getRecord(id)?.session).toBeUndefined();
    } finally {
      gate.resolve();
    }
    await registry().waitForAll();
    const record = registry().getRecord(id)!;
    expect(setup).toHaveBeenCalledTimes(1);
    expect(record.rootSessionId).toBe(parent.sessionManager.getSessionId());
    expect(record.session).toBe(child);
    expect(record.status).toBe("completed");
    expect(record.result).toBe("done");
    expect(requests).toBe(1);
    expect(child?.sessionManager.getEntries()).toContainEqual(expect.objectContaining({
      type: "custom", customType: "test:setup-observed", data: "authorized",
    }));
  });

  it("refuses registry spawn before binding and after shutdown", async () => {
    const entry = registry();
    expect(() => spawn()).toThrow("No active session");
    await bind();
    await root.lifecycle.get("session_shutdown")();
    expect(() => entry.spawn(root.pi, parent, "setup", "go", { description: "stale" })).toThrow("No active session");
    expect(requests).toBe(0);
  });

  it.each(["sync", "async"])("settles a %s setup rejection without binding, prompting or leaking a session", async mode => {
    await bind();
    let dispose: ReturnType<typeof vi.spyOn> | undefined;
    let bindExtensions: ReturnType<typeof vi.spyOn> | undefined;
    const setup: Setup = session => {
      dispose = vi.spyOn(session, "dispose");
      bindExtensions = vi.spyOn(session, "bindExtensions");
      if (mode === "sync") throw new Error("setup refused");
      return Promise.reject(new Error("setup refused"));
    };
    const id = spawn({ beforeSessionStart: setup });
    await registry().waitForAll();
    expect(registry().getRecord(id)).toMatchObject({ status: "error", error: "setup refused" });
    expect(registry().getRecord(id)?.session).toBeUndefined();
    expect(dispose).toHaveBeenCalledTimes(1);
    expect(bindExtensions).not.toHaveBeenCalled();
    expect(requests).toBe(0);
  });

  it("rejects a serialized setup value instead of silently ignoring it", async () => {
    await bind();
    expect(() => spawn({ beforeSessionStart: "not a function" as unknown as Setup })).toThrow("beforeSessionStart must be a function");
    expect(requests).toBe(0);
  });

  it("does not call setup or prompt when the caller is already aborted", async () => {
    await bind();
    const setup = vi.fn<Setup>();
    const id = spawn({ beforeSessionStart: setup, signal: AbortSignal.abort() });
    await registry().waitForAll();
    expect(registry().getRecord(id)?.status).toBe("stopped");
    expect(setup).not.toHaveBeenCalled();
    expect(requests).toBe(0);
  });

  it("cancels pending setup, frees the slot and never starts it after a late resolution", async () => {
    await bind();
    const gate = deferred();
    const entered = deferred();
    const caller = new AbortController();
    let bindExtensions: ReturnType<typeof vi.spyOn> | undefined;
    let dispose: ReturnType<typeof vi.spyOn> | undefined;
    let setupSignal: AbortSignal | undefined;
    const id = spawn({ signal: caller.signal, beforeSessionStart: async (session, signal) => {
      bindExtensions = vi.spyOn(session, "bindExtensions");
      dispose = vi.spyOn(session, "dispose");
      setupSignal = signal;
      entered.resolve();
      await gate.promise;
    } });
    const nextSetup = vi.fn<Setup>();
    const next = spawn({ beforeSessionStart: nextSetup });
    try {
      await entered.promise;
      expect(registry().getRecord(next)?.status).toBe("queued");
      expect(nextSetup).not.toHaveBeenCalled();
      caller.abort();
      await vi.waitFor(() => expect(nextSetup).toHaveBeenCalledTimes(1));
      await registry().waitForAll();
      expect(setupSignal?.aborted).toBe(true);
      expect(registry().getRecord(id)?.status).toBe("stopped");
      expect(registry().getRecord(next)?.status).toBe("completed");
      expect(dispose).toHaveBeenCalledTimes(1);
      expect(bindExtensions).not.toHaveBeenCalled();
      expect(requests).toBe(1);
    } finally {
      gate.resolve();
    }
    await flush();
    expect(bindExtensions).not.toHaveBeenCalled();
    expect(requests).toBe(1);
  });

  it("settles synchronous cancellation followed by a late setup rejection", async () => {
    await bind();
    const caller = new AbortController();
    const gate = deferred();
    const id = spawn({ signal: caller.signal, beforeSessionStart: () => {
      caller.abort();
      return gate.promise;
    } });
    await registry().waitForAll();
    gate.reject(new Error("late rejection"));
    await flush();
    expect(registry().getRecord(id)?.status).toBe("stopped");
    expect(requests).toBe(0);
  });

  it("refuses setup completion if the bound parent changes while it awaits", async () => {
    await bind();
    const gate = deferred();
    const entered = deferred();
    const id = spawn({ beforeSessionStart: async () => { entered.resolve(); await gate.promise; } });
    try {
      await entered.promise;
      parent.sessionManager.getSessionId = () => "replacement-session";
    } finally {
      gate.resolve();
    }
    await registry().waitForAll();
    expect(registry().getRecord(id)?.error).toBe("Spawning session is no longer active");
    expect(requests).toBe(0);
  });

  it("rejects a queued start whose parent changed before setup", async () => {
    await bind();
    const gate = deferred();
    const entered = deferred();
    const first = spawn({ beforeSessionStart: async () => { entered.resolve(); await gate.promise; } });
    const queuedSetup = vi.fn<Setup>();
    const queued = spawn({ beforeSessionStart: queuedSetup });
    try {
      await entered.promise;
      expect(registry().getRecord(queued)?.status).toBe("queued");
      parent.sessionManager.getSessionId = () => "replacement-session";
    } finally {
      gate.resolve();
    }
    await registry().waitForAll();
    expect(registry().getRecord(first)?.status).toBe("error");
    expect(registry().getRecord(queued)?.error).toBe("Spawning session is no longer active");
    expect(queuedSetup).not.toHaveBeenCalled();
    expect(requests).toBe(0);
  });

  it("releases the concurrency slot after setup rejects", async () => {
    await bind();
    const failed = spawn({ beforeSessionStart: async () => { throw new Error("setup refused"); } });
    const next = spawn({ beforeSessionStart: session => {
      session.sessionManager.appendCustomEntry("test:session-setup", "next");
    } });
    expect(registry().getRecord(next)?.status).toBe("queued");
    await registry().waitForAll();
    expect(registry().getRecord(failed)?.status).toBe("error");
    expect(registry().getRecord(next)?.status).toBe("completed");
    expect(requests).toBe(1);
  });

  it("applies setup and bound-root association through the actual RPC handler", async () => {
    const bus = new EventEmitter();
    root.pi.events = {
      on(event: string, listener: (data: unknown) => void) {
        bus.on(event, listener);
        return () => bus.off(event, listener);
      },
      emit(event: string, data: unknown) { bus.emit(event, data); },
    };
    await bind();
    const setup = vi.fn<Setup>(session => {
      session.sessionManager.appendCustomEntry("test:session-setup", "rpc");
    });
    const reply = new Promise<{ success: boolean; data: { id: string } }>(resolve => {
      bus.once("subagents:rpc:spawn:reply:setup-test", resolve);
    });
    bus.emit("subagents:rpc:spawn", {
      requestId: "setup-test", type: "setup", prompt: "say done",
      options: { description: "RPC setup", beforeSessionStart: setup, rootSessionId: "../../forged" },
    });
    const result = await reply;
    expect(result.success).toBe(true);
    await registry().waitForAll();
    const record = registry().getRecord(result.data.id)!;
    expect(record.rootSessionId).toBe(parent.sessionManager.getSessionId());
    expect(setup).toHaveBeenCalledTimes(1);
    expect(record.session?.sessionManager.getEntries()).toContainEqual(expect.objectContaining({
      type: "custom", customType: "test:setup-observed", data: "rpc",
    }));
    expect(requests).toBe(1);
  });

  it("keeps each queued setup with its own session and skips setup for a cancelled queue entry", async () => {
    await bind();
    const gate = deferred();
    const entered = deferred();
    const first = spawn({ beforeSessionStart: async session => {
      session.sessionManager.appendCustomEntry("test:session-setup", "first");
      entered.resolve();
      await gate.promise;
    } });
    const secondSetup = vi.fn<Setup>(session => {
      session.sessionManager.appendCustomEntry("test:session-setup", "second");
    });
    const second = spawn({ beforeSessionStart: secondSetup });
    const caller = new AbortController();
    const cancelledSetup = vi.fn<Setup>();
    const cancelled = spawn({ beforeSessionStart: cancelledSetup, signal: caller.signal });
    try {
      await entered.promise;
      expect(secondSetup).not.toHaveBeenCalled();
      caller.abort();
    } finally {
      gate.resolve();
    }
    await registry().waitForAll();
    for (const [id, marker] of [[first, "first"], [second, "second"]]) {
      expect(registry().getRecord(id)?.session?.sessionManager.getEntries()).toContainEqual(expect.objectContaining({
        type: "custom", customType: "test:setup-observed", data: marker,
      }));
    }
    expect(secondSetup).toHaveBeenCalledTimes(1);
    expect(cancelledSetup).not.toHaveBeenCalled();
    expect(registry().getRecord(cancelled)?.status).toBe("stopped");
    expect(requests).toBe(2);
  });
});
