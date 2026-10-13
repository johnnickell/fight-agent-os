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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 2 of EPIC-00007's remaining six-TICKET split. Applies
[WF-015](../wayfinder/tickets/WF-015-define-coordinated-task-authority-protocol.md),
[WF-017](../wayfinder/tickets/WF-017-define-execution-history-and-event-authority.md),
[WF-023](../wayfinder/tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md)/
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Early subject/receipt/Artifact contracts supply [TICKET-00032](00032-TICKET.md),
[TICKET-00096](00096-TICKET.md) and later Review consumers without waiting for those completed journeys.
TICKET-00064 retains independence decisions and live Team checks; TICKET-00083 retains managed Review/QA/landing.
Map exact early contribution/subject suppliers separately during TASK decomposition, not a blanket dependency on
this entire TICKET. Existing needs-info and qualified-host/current-authority holds remain. No evidence upload,
redaction, purge, live storage or implementation TASK is authorized through this approval.
