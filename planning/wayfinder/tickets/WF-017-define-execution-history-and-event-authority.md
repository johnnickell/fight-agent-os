# Define execution history and event authority

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-010](WF-010-define-authoritative-planning-domain-and-lifecycle.md), [WF-015](WF-015-define-coordinated-task-authority-protocol.md), [WF-016](WF-016-define-runner-dispatch-and-recovery.md)

## Question

What PostgreSQL execution model cleanly relates one coordinated TASK attempt, its agent conversations and phase attempts, authoritative transitions, observational events, evidence, Git/PR outcomes, usage, and recovery state?

## Must decide

- Whether one top-level workflow session represents one coordinated attempt to carry one TASK through PR handoff, and how retries, resumptions, cancellations, and later attempts relate.
- Identities and relationships among repository, TASK revision, workflow session, claim/job, agent conversation, phase attempt, event, finding, verdict, evidence/artifact, branch, commit, and PR.
- Which application commands own authoritative state transitions and which events are observations that may be delayed, duplicated, missing, or reordered.
- Event identifiers, producer sequence/order, timestamps, idempotency, deduplication, transactional capture/outbox boundaries, delivery failure, replay, and reconciliation.
- Commit-specific review acceptance and finding dispositions in the durable model, including invalidation after implementation changes.
- Usage, duration, interruption, failure, and model/skill/policy revision capture when available without inventing unavailable precision.
- Artifact metadata versus external/blob storage, bounded previews, sensitive-content/redaction rules, retention, deletion, and audit needs.
- Separation between dashboard history and Pi's persisted conversation/session tree required to resume agent context; agents call application APIs/tools rather than writing SQL.

## Resolution boundary

This decision defines execution aggregates, events, projections, reconciliation, and retention policy categories. It must not create production tables, import Pi JSONL, start event capture, or claim that every Pi transcript event is authoritative workflow state.

## Preferences required

John must choose retention/privacy expectations, how much raw tool/prompt content may be retained, and whether a resumed failed workflow remains the same top-level attempt. The recommendation should use one workflow session per coordinated TASK attempt, explicit phase attempts beneath it, application-owned transitions, and separately retained resumable Pi conversation references.

## Resolution

### Workflow and related identities

One event-sourced `Workflow` represents one explicit human grant to carry one approved TASK revision toward one PR handoff. Queueing, runner takeover, process retries, safe Pi resumption, pause, Needs Human continuation, Builder revision, independent review, acceptance, and publication remain within that Workflow. Cancellation, terminal failure, and PR handoff close it permanently. Later human-authorized work creates a new Workflow linked to its predecessor and the same TASK rather than reopening or rewriting history. A recoverable limit extension appends a new grant to the existing Workflow.

The Workflow owns the repository, TASK and revision, initiating user when present, Coordinator Agent, grant history, policy and context snapshots, claim relationship, state and current phase, budget consumption, revision and process-attempt limits, recovery decisions, accepted Review reference, publication result, and terminal outcome. Its event stream records meaningful decisions, not raw process traffic.

Use separately identified durable records where their lifecycle or ownership differs:

- A `PhaseAttempt` identifies one logical Coordinator, Builder, Reviewer, or Publisher invocation; a `ProcessAttempt` identifies one technical Pi launch or safe resumption beneath it.
- An `AgentConversation` binds one Workflow role and managed Agent/profile snapshot to one protected Pi session lineage.
- A `Review` owns one immutable reviewed subject, criterion dispositions, findings, evidence, verdict, and append-only corrections or annotations.
- An `Artifact` owns immutable content metadata, provenance, sensitivity, retention holds, and later purge state independently of any one Workflow link.
- Runner identity and operational availability remain the separate model settled by WF-016.

Branches, worktrees, commits, changed-tree manifests, checks, Git remotes, PRs, provider observations, usage facts, and evidence links are typed records or values related through stable internal identities. Names, paths, branch heads, PR URLs, and Pi file locations are evidence, never identity or authority.

### Application-owned authority

Only intent-specific application Commands may change authoritative Workflow or Review state. A Command authenticates its user, Agent, or system actor; loads current aggregates; verifies its grant, expected revisions, claim and lease, immutable Git/evidence subject, limits, and relevant observations; then appends semantic events. Runner reports, Pi JSON/RPC events, tool calls, shell output, Git inspection, provider webhooks, Mercure delivery, and Dashboard projections cannot transition a Workflow by themselves.

Use Fight Common's event-sourcing contracts and DBAL `EventStore`: stable aggregate and event names, schema versions and upcasters, generated message identities, expected stream versions, prefix-stable global positions, exact append retry, and at-least-once projections. Event metadata carries available actor, Command, causation, correlation, Workflow, and delegated-user identities without duplicating those facts in every payload. Fight Common's current `EventSourcedRepository` creates envelopes with empty metadata, so downstream implementation must add a small policy-neutral envelope seam or an application repository adapter while continuing to use the existing store; it must not invent another event-store protocol.

Fight Common does not itself provide Command-result idempotency, an atomic application outbox, arbitrary command-critical constraint writes, observation storage, redaction, artifact storage, or Mercure policy. Agent OS owns those concerns and must not claim they arrive automatically with event sourcing. Idempotent Commands retain their Command identity and original result. Active-claim constraints, dispatch intents, and similar command-critical records are updated with the authoritative decision under an application-owned PostgreSQL transaction design; exact schema and integration mechanics remain downstream.

External effects use durable intent and verified result rather than fictional atomicity. Record the authorized intent, perform an idempotent process, Git, provider, or artifact operation, observe and reconcile its actual result, and only then append the authoritative completion. An uncertain outcome pauses or enters Needs Human; it never becomes success because a queue message or process disappeared.

### Reviews, evidence, and browser detail

A finalized Review remains append-only and identifies the exact TASK revision, base and implementation commits, changed tree, checks, selected artifacts and digests, Reviewer Agent and fresh conversation, criteria, findings, dispositions, and verdict. The Workflow separately records whether that Review satisfies WF-015 acceptance. Later annotations preserve the original report. A material subject change or fact establishing a blocker appends acceptance invalidation; it does not mutate the Review or transfer its verdict to a new commit.

The Dashboard presents a structured operational dossier rather than a chat transcript. It may show current state and next action, grants and actors, phases and attempts, durations and interruptions, commands and bounded sanitized output, changed files, commits, checks, findings, criteria, artifacts, usage, recovery, publication, and external effects. Authoritative decisions and observations must be visually distinguishable, and unavailable detail must remain unknown rather than inferred.

A first-class code-review surface shows a bounded, syntax-highlighted `base…head` patch for the exact immutable submitted or accepted commit, with related criteria, findings, checks, and artifacts. Persist a digest-verified Git patch or equivalent immutable snapshot as an Artifact when required for independent evidence. A live worktree diff may also be shown but is explicitly mutable and observational. Browser review adds no mandatory publication gate to WF-015; Pause or Halt provides the intentional intervention boundary.

Large histories use server pagination, lazy bounded detail, and artifact-backed full output rather than arbitrary silent display caps. WF-018 owns the disposable interaction prototype and final information hierarchy.

### Pi conversations and observations

Pi's persisted JSONL session tree remains authoritative only for reconstructing Pi model context. Agent OS stores an opaque runner-resolvable session reference and the Workflow, role, Agent, profile/Harness/policy revisions, Pi/session format version, lifecycle, predecessor when replaced, and integrity facts needed for recovery. It does not import Pi entries into the Workflow stream or ingest, index, search, or project raw system prompts, user/assistant conversation, hidden thinking, provider signatures, or complete transcripts into the Dashboard.

Coordinator keeps one recoverable conversation lineage. Builder normally resumes one lineage across implementation, blocking-finding revisions, pauses, and safe recovery. Each independent Review attempt starts a fresh Reviewer conversation and Pi session, even when it uses the same managed Reviewer identity. Publisher remains isolated. A missing, corrupt, incompatible, or intentionally abandoned Pi session produces a linked successor conversation with a recorded reason; no role resumes another role's conversation.

Selected sanitized operational activity may enter a small application-owned observation ledger for live and historical browser detail. An observation has its own identity and relates to the Workflow and available phase, process, conversation, tool, or external-operation identities; it records kind, available producer and receipt times, bounded safe detail, sensitivity, and an optional Artifact reference. Observations may be duplicated, delayed, missing, or reordered. Start with ordinary idempotency when a stable source identity exists; do not mandate a custom global sequence, runner spool, gap protocol, or telemetry platform without demonstrated need.

Recovery never depends on complete observations. It uses authoritative Workflow, claim, lease and process-attempt state plus reconciled Git, worktree, Pi session, provider, and Artifact evidence. Completed application handoffs and verified check/Git snapshots matter more than reconstructing execution from streaming deltas. Missing telemetry is displayed as incomplete, not repaired with invented facts.

### Usage, time, and projections

Record provider, requested and reported model, profile/Harness/policy revisions, Pi version, available response or session-entry identity, and provider-reported input, output, cache, reasoning, token, and cost facts when available. Identify each usage fact so projections do not sum cumulative streaming values repeatedly. Preserve the source and currency when supplied; distinguish unavailable from zero and never estimate precision the provider did not report. Compaction, cache warming, and nested tool-model usage remain separately attributed when Pi exposes them.

Record server-observed phase, process, interruption, recovery, and external-operation boundaries. Present wall elapsed, known active duration, queue time, and interrupted time separately where available rather than manufacturing one precise duration from incomplete events.

Fight Common projectors build idempotent, rebuildable PostgreSQL views for Workflow lists, details, review/evidence status, usage rollups, and live summaries. Projection delivery is at least once. Mercure broadcasts only committed projection updates; reconnecting clients reload durable queries before following live changes. Publication, projection, or Mercure failure never changes aggregate truth.

### Artifact storage, privacy, and retention

PostgreSQL stores Artifact identity and metadata: digest, media type, size, safe display name, provenance, producing Workflow/phase/Agent, sensitivity, links, retention holds, and lifecycle. Store non-trivial bytes behind an application-owned S3-compatible object capability and place only references in Commands and events. WF-023 selects and deploys the concrete local service. Objects remain private; Agent OS authorizes bounded previews and downloads. Replacing content creates a new immutable Artifact rather than changing bytes under an accepted digest.

Durably retain authoritative Workflow and Review history, grants, findings, acceptance and invalidation, usage totals, Git/commit/PR references, verification summaries, and Artifact metadata as project history. Evidence bytes remain held while linked active Planning records require them under WF-010. Pi sessions, transient observations, and bulky logs remain available while work is active, paused, recoverable, or Needs Human. After terminal completion, cancellation, or failure, resume-only Pi material and bulky transient data receive a configurable cleanup grace period initially defaulting to 30 days. Authorized users may retain eligible material longer or delete it early when no evidence, recovery, security, or other explicit hold applies.

Detected credentials and prohibited sensitive content are never preserved merely because another category permits retention. Redaction and purge apply to projections, indexes, previews, exports, and object bytes within their storage and backup capabilities. Purged evidence leaves a tombstone with identity, digest, actor or policy, time, and reason. Append-only corrections or emergency-redaction placeholders preserve that history changed without retaining the prohibited payload. Exact periods, quotas, backup expiry, redaction detectors, and safe preview formats are installation and implementation policy, not permanent domain invariants.

### Boundary

This decision defines semantic authority and minimum reliability, not a frozen telemetry protocol. Observation columns, batching, sequencing, transport, OpenTelemetry use, search/index infrastructure, and richer diagnostics may evolve without reopening this decision so long as observations cannot grant authority and incomplete telemetry is not presented as verified fact.

It creates no schema, table, event mapping, outbox, projection, object bucket, Pi session, imported transcript, captured event, retained credential, browser component, or implementation record. Exact PHP classes, SQL layout, event names and payloads, Command and API names, projector topology, Mercure topics, S3 product, preview renderer, and UI layout remain downstream. WF-018 owns browser prototyping; WF-023 owns the runtime and concrete object-storage deployment.
