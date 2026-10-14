---
id: TICKET-00094
epic: EPIC-00007
title: Retain and protect immutable execution evidence
status: ready-for-agent
---

# Retain and protect immutable execution evidence

## Problem statement

A moving branch, chat message or vanished process cannot establish what was implemented or checked. Execution needs
immutable inspectable evidence that survives recovery while preventing private output, credentials and obsolete
resume material from leaking through history or storage.

## Solution and boundaries

Provide exact execution evidence and private Artifact capabilities for the existing TICKET-00032 handoff. Bind Git
and check receipts to Workflow, approved TASK revision, Repository/Checkout/Worktree, role/Agent/session, context and
Harness snapshots, configured base, full base/head commit IDs and complete changed-tree/status manifests. Preserve
actual command/check identity, exit/result, fresh verification, warnings, limitations and selected ignored Artifact
digests. A branch name, current working diff, model claim or future PR is not an immutable subject.

Preserve attributable design/build/evidence contribution facts needed by existing later independence consumers,
without issuing a Review verdict here. Missing contribution or source provenance is unknown, not proof of
independence. TASK-00141 remains the owner of validating the complete current handoff and authoritatively entering
Awaiting review; this capability supplies its exact subjects/receipts, not another handoff or acceptance engine.

PostgreSQL owns immutable Artifact identity, digest, size, media type, safe display name, provenance, sensitivity,
links, holds and purge tombstones. Store non-trivial bytes behind the private S3-compatible capability backed locally
by SeaweedFS, extending TICKET-00023's existing infrastructure rather than starting another object-storage stack.
Use durable intent, bounded idempotent transfer, digest/size verification and original-outcome reconciliation;
external storage effects stay outside business transactions. An upload acknowledgement alone is not verified evidence.
Replacement creates another Artifact, never changed bytes beneath an accepted digest.

Retain a digest-verified patch or equivalent immutable Git snapshot where needed for independent evidence. Make all
changed files discoverable, with bounded safe metadata, lazy file bodies and explicit binary/oversized/unavailable
states. Distinguish mutable live-worktree observations from retained base…head evidence. Only authorized bounded
previews/downloads expose private bytes; do not expose public storage, arbitrary object keys or unsanitized active
content. Browser rendering remains TICKET-00097's consumer, not a prerequisite for evidence capture.

Retain authoritative Workflow/grant/handoff history, Git/check summaries, usage totals and Artifact metadata as
project history. Hold bytes while linked active Planning or other accepted evidence/recovery/security requirements
need them. Preserve active, paused, recoverable and Needs Human material. Apply the configurable initial 30-day
post-terminal grace to resume-only Pi material and bulky transient observations/logs, subject to explicit holds and
narrowly authorized early deletion. Coordinate protected Pi content with TICKET-00090 and existing checkpoint owners,
not a parallel transcript store. Awaiting review is not terminal cleanup eligibility.

Detected credentials and prohibited content require controlled redaction even when ordinary retention allows
history. Account for projections, indexes, previews, exports, object bytes and actual backup capabilities. Preserve
secret-free tombstone identity/digest, actor or policy, time and reason, plus attributed append-only correction or
redaction placeholders; never preserve the prohibited payload merely to keep an audit copy. Distinguish logical
inaccessibility, confirmed deletion and backup expiry rather than promise impossible instantaneous physical erasure.

Exclude Review/QA verdicts, acceptance, publication, merge observation, post-publication worktree cleanup, arbitrary
file browsing, public object storage and transcript ingestion. Evidence preservation does not authorize running a
check against an unsafe target or passing content to a provider.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Retain exact implementation/check evidence | Record verified source/check receipt under existing handoff intent | Exact current subject, scope, result and prior operation | Committed owned evidence/provenance facts | Immutable attributable receipt, not review acceptance |
| Store an Artifact | Admit/transfer/verify original Artifact operation | Expected digest/size, private storage outcome and current authority | Confirmed Artifact metadata/lifecycle facts | Verified private bytes and immutable references or an explicit unresolved state |
| Inspect or retrieve evidence | N/A — bounded authorized reads | Subject/manifest, authorized metadata, preview/download and hold state | N/A — reads cannot certify or change evidence | Safe exact content or denied/partial/purged/unavailable result |
| Retain, redact or purge eligible content | Explicit hold/release/redaction/eligible cleanup operation | Current references, categories, terminal state, policy and backups | Secret-free hold/tombstone/redaction facts | Authorized bounded cleanup; active evidence/recovery remains protected |

## Validation and permissions

Authenticate actual producers and viewers; check current Workflow/Repository/assignment and sensitivity scope on
both metadata and bytes. Templates, URLs and possession of an Artifact ID grant no access. Verify full subject,
expected revision, source/content digest, ownership and bounds; reject stale/mismatched/partial evidence rather than
quietly accept it. Recheck current references and holds at cleanup under owned transaction/fencing contracts.

Render untrusted content inert; validate media types and safe filenames without trusting extensions. Bound transfer,
preview and query sizes, concurrency and time; sanitize errors and logs. Raw credentials and hidden reasoning never
belong in ordinary evidence. No external effect or arbitrary filesystem access is hidden inside persistence adapters.
An uncertain storage/delete outcome remains unresolved until its original operation is reconciled.

## Acceptance and evidence

- Actual handoff evidence identifies exact immutable commits, manifests, fresh checks and Artifacts; moved branch
  heads, modified bytes, stale receipts and missing provenance cannot masquerade as the submitted subject.
- Real private storage supports authorized bounded reads, digest verification and interrupted transfer/recovery
  without public access or contradictory completion. Consumer UI failure does not destroy retained evidence.
- Holds and active/recoverable work prevent premature cleanup; redaction/tombstones retain attribution without
  secrets and report actual backup/deletion limits. Awaiting review keeps required evidence and session custody.
- Owned PostgreSQL/evidence/privacy/recovery tests, direct storage/retention tooling qualification, real production
  handoff evidence and `./bin/build` pass. No tests of object-server internals, migration text or generated patches.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00374](../tasks/00374-TASK.md) | Store and reconcile verified private Artifacts | ready-for-agent |
| [TASK-00375](../tasks/00375-TASK.md) | Record attributable contribution provenance | ready-for-agent |
| [TASK-00376](../tasks/00376-TASK.md) | Capture immutable Git evidence subjects | ready-for-agent |
| [TASK-00377](../tasks/00377-TASK.md) | Retain exact check receipts | ready-for-agent |
| [TASK-00378](../tasks/00378-TASK.md) | Inspect and retrieve protected evidence | ready-for-agent |
| [TASK-00379](../tasks/00379-TASK.md) | Retain held evidence and purge eligible transient content | ready-for-agent |
| [TASK-00380](../tasks/00380-TASK.md) | Redact prohibited content across retained copies | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved seven bounded delivery slices in the existing isolated planning worktree. Each owns
application behavior, actual operational qualification and guidance; no proof-only TASK is added.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00374](../tasks/00374-TASK.md) | Verified private Artifact storage/recovery and early reference/hold participants |
| B | [TASK-00375](../tasks/00375-TASK.md) | Attributable contribution/source lineage independently of completed roles |
| C | [TASK-00376](../tasks/00376-TASK.md) | Exact immutable Git subjects, complete manifests and retained snapshots |
| D | [TASK-00377](../tasks/00377-TASK.md) | Actual exact-subject check receipts, warnings and original outcomes |
| E | [TASK-00378](../tasks/00378-TASK.md) | Current-authorized bounded evidence inspection, previews and downloads |
| F | [TASK-00379](../tasks/00379-TASK.md) | Actual evidence/Planning/Pi hold participation and eligible transient cleanup |
| G | [TASK-00380](../tasks/00380-TASK.md) | Controlled cross-copy redaction, safe tombstones and honest backup limits |

TASK-00374 consumes TASK-00139/00234; TASK-00375 consumes TASK-00139/00348. TASK-00376 consumes
TASK-00374/00375/00140; TASK-00377 consumes TASK-00376. TASK-00378 consumes TASK-00377/00117;
TASK-00379 consumes TASK-00377/00087/00354; TASK-00380 consumes TASK-00378/00379. All seven are
`ready-for-agent` behind unfinished suppliers, with blank order/pr; allocation is not operational qualification.

TASK-00140 and TASK-00087 gain TASK-00374's early private Artifact/reference/hold contract. Storage integration
extends TASK-00072/TICKET-00023's one installation with only the SeaweedFS capability needed for terminal evidence,
not another stack or the full browser prerequisite chain. TASK-00087 retains Planning evidence/verdict/link ownership
and supplies TASK-00379's actual active references; it never reverse-depends on completed retention purge.

TASK-00250/00261/00264 gain TASK-00375's contribution/source-lineage participant, preserving `needs-info` for actual
source coverage, authority, configured-base inclusion, creation, host, phase/fresh-session composition as applicable.
No completed Team/role/Review journey reverse-blocks the primitive. An exact Git subject alone does not establish
prerequisite inclusion on the configured base. Missing contribution provenance remains unknown, not independent.

TASK-00141 and TASK-00373 gain TASK-00377, with exact subjects/contributions/Artifacts transitively. TASK-00141
retains complete current handoff validation and entering Awaiting review; its existing real production TASK provides
final handoff evidence, not another certification run. TASK-00445 gains TASK-00378 and retains its independent
session/review-phase admission holds. No new Review verdict, acceptance, phase engine or cleanup authority is added.
TASK-00139–00142 retain ownership and priority; the new TASK-00140/00141 edges name these precise early suppliers.

Safe admission and disclosure apply from TASK-00374; later cross-copy redaction is not permission to expose secrets
until TASK-00380 ships. TASK-00354 and TASK-00167 remain protected Pi/checkpoint owners, not an Artifact transcript
store. Active/recoverable/held material survives; Awaiting review is not terminal cleanup. The initial 30-day grace
applies only to eligible post-terminal transient/resume material, not durable project history. Missing reference,
copy-owner, storage or backup participation blocks the affected destructive route/complete-removal claim. Future
TICKET-00096/00097 writers must consume early restrictions/fences before exposure; whole journeys are not blockers.

TASK-00350/00364 remain `needs-info`: evidence storage does not implement protected host/broker or prototype execution.
TICKET-00095 now maps TASK-00381 exact-content Jev admission, independent TASK-00382/00383 search/retrieval and
TASK-00384 retained/cached/resumed web delivery. The last consumes TASK-00374/00378 and TASK-00353 recovery without
reverse-blocking their generic paths. Artifact read permission never authorizes model disclosure or waives current
screening. Generic queries remain provider-free: new screening requires a separate authorized operation. Ordinary
preview safety is not web-content screening qualification, and unqualified web-evidence routes stay unavailable.

TICKET-00096 now maps TASK-00386's safe activity/evidence consumer, TASK-00387 history queries, TASK-00388 actual
history-copy lifecycle participation and TASK-00389 live-publication fences. Existing evidence owners do not reverse-
depend on completed history; early safe admission and unknown-copy cleanup holds remain mandatory.
TICKET-00097 now maps TASK-00392's exact Changes/Evidence presentation and TASK-00393's safe Activity references,
consuming these owners through TASK-00391/00389 without a second read policy or reverse dependency.
This decomposition is complete, not implementation. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve terminal-first Board priority and Markdown authority
until separately approved verified cutover. Planning authorizes no storage/credential operation, source capture,
check, private-content read, retention/redaction/purge, runtime effect, commit or publication.

### Planning verification

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving prior approved
uncommitted planning. Before allocation, the live TASK ceiling was TASK-00373 and the archive contained no TASK
records. TASK-00374–00380 metadata and exact dependencies match the approved proposal; all are feature records,
ready behind unfinished suppliers, with blank order/pr.

`./bin/planning-check --write` and `./bin/planning-check` passed with 507 records, 415 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 72 new/untracked TASK
whitespace checks passed, including these seven. Explicit checks confirmed independent consumer/host/prototype
`needs-info` holds, unchanged TASK-00138/00139/00142/00226 records against this worktree's HEAD and preserved existing
producer priority/status. TASK-00140/00141/00087 gained only the documented early suppliers and coordination;
that historical split advanced the handoff to TICKET-00095. Active TASK-00138 and the terminal-first ordering remain unchanged.

These are planning receipts, not fresh storage, provenance, check, retention/redaction, provider or runtime evidence.
Full `./bin/build` was not run and remains required before commit/publication. All changes remain uncommitted;
independent review and publication are separate. No implementation, storage/credential operation, check, private
source/session read, upload, retention/redaction/purge, live Planning mutation, cutover, commit, push, PR or merge
occurred. Unrelated main-checkout work was left untouched, not claimed clean. Earlier receipts remain historical.

### Original requirement approval and supplier handoff

The earlier allocation requests below are resolved only by the precise mappings above; qualification holds persist.

Approved as area 2 of EPIC-00007's remaining six-TICKET split. Applies
[WF-015](../wayfinder/tickets/WF-015-define-coordinated-task-authority-protocol.md),
[WF-017](../wayfinder/tickets/WF-017-define-execution-history-and-event-authority.md),
[WF-023](../wayfinder/tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md)/
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Early subject/receipt/Artifact contracts supply [TICKET-00032](00032-TICKET.md),
[TICKET-00096](00096-TICKET.md) and later Review consumers without waiting for those completed journeys.
TICKET-00064 retains independence decisions and live Team checks; TICKET-00083 retains managed Review/QA/landing.
TICKET-00090 now maps TASK-00354's protected Pi retention/hold/redaction seam with TASK-00353 lifecycle and the
existing TASK-00167 checkpoint owner. Consume that seam without another transcript store; actual Artifact/reference/
hold writers must participate before affected cleanup is enabled. Missing participation preserves content; completed
evidence/browser journeys are not reverse prerequisites of the conversation primitives.
TICKET-00093 now maps TASK-00368–00373's Runner enrollment/admission/dispatch/consumer/control/recovery operations.
Supply exact early Artifact/evidence/reference participants to TASK-00140/00141 and TASK-00373 where required;
missing recovery evidence or retention/hold participation preserves content and blocks unsafe continuation/cleanup.
Completed Runner or browser journeys never reverse-block these early evidence contracts.
Map exact early contribution/subject suppliers separately during TASK decomposition, not a blanket dependency on
this entire TICKET. Existing needs-info and qualified-host/current-authority holds remain. No evidence upload,
redaction, purge, live storage or implementation TASK is authorized through this approval.
