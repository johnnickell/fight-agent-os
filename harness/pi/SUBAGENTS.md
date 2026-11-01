# Upstream subagents and Fight observation

[TASK-00554](../../planning/tasks/00554-TASK.md) is in progress. The bridge is an initial implementation,
not a qualified installed release. Do not treat these source changes as permission to update active sessions.

The selected companion is [`@tintinweb/pi-subagents`](https://github.com/tintinweb/pi-subagents).
Fight retains workflow rules, role/tool restrictions and worktree/Docker ownership. The companion owns
delegation, steering and resume. Its automatic worktree mode must remain disabled for the supported Fight
profile. Role packaging and installed/native qualification are still pending.

The bridge listens for `subagents:started`, uses the documented
[manager registry](https://github.com/tintinweb/pi-subagents/blob/master/docs/rpc.md#the-manager-registry)
to find a top-level record, checks `rootSessionId`, and subscribes to the live Pi session. It forwards bounded
assistant activity and tool names through the existing native parent's IPC channel into the existing signed
companion protocol. It reads no transcript files and offers no execution/cancellation API. The socket monitor
ancestry admission remains separate. Current limits are eight observed agents, a five-second session-creation
window, one-second sampling and bounded text; this is activity observation, not an archival transcript viewer.

## Qualification still required

- Actual upstream source/package identity and installed Pi/Herdr behavior, including early output before attachment,
  final output during shutdown, same-session resume, reloading and missing-capability diagnostics.
- Confirm root identity on every supported launch route. Source inspection found explicit root identity on the
  Agent tool path; a route without it is refused rather than guessed from cwd or a transcript filename.
- Configure and qualify Fight role definitions, inherited tool policy and upstream worktree-mode exclusion.
- Preserve truthful SDK lifecycle labels; the existing monitor display uses exit-code vocabulary and needs
  adaptation before SDK completion can be presented as a distinct upstream status.
- Full local build and independent Review/visual QA. Mock-session checks do not supply terminal acceptance.

## Installed diagnostic — not integration acceptance

The 2026-11-01 continuation privately installed upstream `0.22.1`, npm source
`fc089c4b7ab0f1185453b2e53027997c1f637e38`, with installed Pi `1.4.0` (tested identity, not a Pi pin).
Pi loaded upstream's workflow CLI flag. A native PTY startup with the packed Fight package produced a real
upstream RPC-start record without `rootSessionId`; the bridge refuses that route. The deliberately
credential-free profile then reported `Provider is not configured: openai-codex`. This is startup/unavailability
evidence only, not successful foreground/background/resume execution or an actual Herdr upper-right capture.
The operator's configured provider was not diagnosed as unavailable by this private-profile result.

Upstream `src/index.ts`'s `session_shutdown` handler aborts agents and disposes child sessions, including on
Pi reload. Closing Fight observation alone is passive; do not promise that upstream agents survive reloading
the entire extension runtime. Supported role packaging, early/final-output correctness, stable record identity,
all launch-route ownership and truthful SDK outcome labels still need implementation/qualification. Upstream's
`spawnTopLevel` strips caller-supplied root identity; supplying a guessed root is not a remedy.

The full local gate is blocked before phases by unavailable TASK-00553 shared ingress and the selected branch's
older resource wrapper. No global rollout, supplier startup, legacy ledger or unfinished-branch merge was made.
Exact commands, captures and cleanup are in TASK-00554's canonical ignored Work handoff.

## Removal inventory

No executable replacement/deletion occurred in the continuation. Replacement verification has not passed;
retaining a candidate temporarily does not establish that every existing module is live.

| Path | Surviving caller or disposition |
|---|---|
| `src/native-observation-extension.ts`, `src/observation/native-observation.mjs`, `custodian.mjs`, `custody.mjs` | Package manifest loads the native extension; it starts passive custody. Both SDK bridge and separate Build monitor reuse it |
| `src/observation/subagents.mjs` | Native extension's initial upstream bridge; owning `tests/subagents.test.mjs`; not a second execution engine |
| `src/observation/run-stage.py`, `automatic_observation.py`, `observation.py`, process/file birth helpers | `.pi/skills/build/scripts/run-stage.py` delegates to the selected package monitor. Work/Review/QA/Land and TASK-00544 still depend on this distinct descendant-process route; retain it |
| `src/workspace/registered-children.mjs`, `src/herdr/activity.mjs` | Live Herdr activity companion and verified log-selection metadata; reused rather than replaced |
| `src/workspace/concurrent-children.mjs`, `child-observation.mjs` | Only their owning tests reference these explicit-socket readers in the current source search; no production caller found. Removal candidates, not justified live-monitor dependencies; retire with obsolete tests/docs after replacement verification |

Recheck callers on the eventual replacement candidate. Do not delete the separate Build route to meet a deletion
count, or add a compatibility/session engine to keep an obsolete reader alive.

MCP client options are deferred in [TASK-00555](../../planning/tasks/00555-TASK.md).
