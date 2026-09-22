# Define runner dispatch and recovery

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-008](WF-008-establish-repository-identity-and-registration-boundaries.md), [WF-015](WF-015-define-coordinated-task-authority-protocol.md)

## Question

How should a browser-created durable workflow job be claimed and executed by an authorized local runner that owns repository, Git, Docker, Pi, and tool access independently of the browser tab?

## Must decide

- Runner identity, enrollment, authentication, capabilities, health/availability, disable/revoke behavior, and least-privilege permissions.
- Repository-to-checkout mapping using WF-008 identities, including validation, multiple checkouts, worktree roots, branch ownership, and unavailable/moved checkout behavior.
- Durable job creation, dispatch, atomic claim/lease, heartbeat, progress, cancellation, deadline, retry, reconnection, restart, and orphan recovery semantics.
- Ownership split among application workflow, runner process, coordinator, Pi subprocess/session, and browser conversation.
- Safe command/tool environment, credential scope, network authority, Docker/service ownership, secret redaction, and evidence/artifact upload boundaries.
- Exactly what browser closure, runner shutdown, host reboot, and Pi crash do to authoritative job and claim state.
- Human intervention, pause, takeover, resume, and terminal-failure behavior without duplicate work.

## Resolution boundary

This decision defines the job/runner protocol and operational authority. It must not enroll a runner, start a daemon, map a live checkout, change host/global configuration, create containers, or dispatch a workflow. Detailed execution-event storage belongs to WF-017.

## Preferences required

John must choose the initial trust model for a personal local runner, whether enrollment is one-time or frequently confirmed, and what survives host restarts. The recommendation should begin with one explicitly enrolled local runner, durable application-owned jobs, leased claims with heartbeats, and no browser-process ownership of execution.

## Resolution

Write this only when runner identity, dispatch, recovery, cancellation, and checkout ownership are approved.
