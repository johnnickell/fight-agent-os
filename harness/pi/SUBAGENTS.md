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

## Removal inventory

The native observation extension, custodian, registered-child selection and renderer are reused. No second pane
renderer or session engine was added. `run-stage.py` and its ancestry helpers still serve Work/Review/QA/Land;
their migration is not part of this first bridge and TASK-00544 continues to depend on that route. They are not
dead code. Recheck callers after integration and remove any actually superseded paths and obsolete tests/docs.

MCP client options are deferred in [TASK-00555](../../planning/tasks/00555-TASK.md).
