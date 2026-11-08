import { writeFileSync } from "node:fs";
import { join } from "node:path";
import { afterEach, expect, it } from "vitest";
import subagentsExtension from "../src/index.js";
import { type Hermetic, hermeticDir, makePi } from "./helpers/boot-extension.js";

let hermetic: Hermetic | undefined;
let lifecycle: Map<string, any> | undefined;
afterEach(async () => {
  await lifecycle?.get("session_shutdown")?.();
  hermetic?.restore();
});

it("activates generic defaults without selecting a host's packaged policy", () => {
  hermetic = hermeticDir();
  const booted = makePi();
  lifecycle = booted.lifecycle;
  subagentsExtension(booted.pi);
  expect(booted.pi.events.emit).toHaveBeenCalledWith("subagents:settings_loaded", { settings: {} });
  expect(booted.tools.get("Agent").parameters.properties).toHaveProperty("isolation");
});

it("applies explicit packaged defaults beneath private and project settings", () => {
  hermetic = hermeticDir({ settings: { maxConcurrent: 3 } });
  const defaultsPath = join(hermetic.dir, "packaged.json");
  writeFileSync(defaultsPath, JSON.stringify({ worktreeIsolation: false, maxConcurrent: 1, defaultMaxTurns: 5 }));
  writeFileSync(join(process.env.PI_CODING_AGENT_DIR!, "subagents.json"), JSON.stringify({ maxConcurrent: 2, defaultMaxTurns: 7 }));
  const booted = makePi();
  lifecycle = booted.lifecycle;
  subagentsExtension(booted.pi, defaultsPath);
  expect(booted.pi.events.emit).toHaveBeenCalledWith("subagents:settings_loaded", {
    settings: { worktreeIsolation: false, maxConcurrent: 3, defaultMaxTurns: 7 },
  });
  expect(booted.tools.get("Agent").parameters.properties).not.toHaveProperty("isolation");
});
