---
id: TICKET-00117
epic: EPIC-00019
title: Operate and recover the observability installation
status: ready-for-agent
---

# Operate and recover the observability installation

## Problem statement

Operators need to enroll, inspect and recover the complete diagnosis capability without guessing privileges,
capacity or which bytes survived. A working backend probe or UI screenshot does not establish safe installation,
physical limits, recovery or the full live-and-archived failure diagnosis journey.

## Solution and boundaries

Deliver the real operator path for [EPIC-00019](../epics/00019-EPIC.md), extending
[TICKET-00023](00023-TICKET.md)'s existing installation topology, task-based guidance and capability reporting.
Consume [WF-042](../wayfinder/tickets/WF-042-bound-observability-delivery-and-acceptance.md)'s workload, objectives,
ownership and mandatory gates, not a separate proof-only infrastructure demonstration or generic monitoring product.

Provide bounded read-only preflight and honest installation capability/health inspection, including independent
collection/destination/lifecycle status when application search is unavailable. Reuse the installed operator guide
and existing supervised status owner; do not create another dashboard, raw-content status store or alerting platform.
Explain actual source inventory, version/runtime identity, current pressure, pinned bytes, pending work, known gaps,
unsupported checks and safe recovery actions without dumping credentials or private raw logs into general guidance.
Raw source/coverage data remains protected by log authority; trusted local supervision retains its separate access.

Define explicit human-run enrollment/configuration and recovery procedures with target, prerequisites, effects,
verification and stop/recovery conditions. Privileged reader, network, mounts, storage/credentials, scheduling and
service changes require their existing installation authority; a read-only check must never perform repair/startup.
Extend one installation-owned runtime rather than adding an alternate stack. Runtime state belongs under ignored
`var/`; `.runs/` is execution evidence/scratch and must not be an application runtime dependency.

Qualify exact OS/architecture/runtime/driver/collector/backend/storage/image identities, private credentials and
namespace isolation, image provenance/security/license obligations and supported S3/SeaweedFS contracts. Loki is a
conditional planning choice, not a selected deployable image or permission to install a service. A mismatch with an
accepted contract returns to its owner; do not quietly substitute custom queues/proxies or relax source coverage.

Establish measurable physical allocations and shared-service headroom for runtime logs, collection/checkpoints/
control journal, live WAL/index/chunks/compaction, archive/catalog/versions, temporary restore and browser/application
workers/sockets/heap. Consume the finite WF-038/039/041 profiles; logical queue limits or monitored scratch are not
hard isolation. Capacity must fit representative original-byte rates, compression/overhead, retained history,
concurrency and protected growth. The existing archive admission target is not a proved 90-day guarantee. Report
finite admission/preservation conflicts rather than silently shorten retention, suppress DEBUG or promise unlimited
held bytes. Normal objectives do not pretend to apply during explicit exhaustion/shared faults.

Exercise actual operator recovery using original pending operations: source/checkpoint/replay, independent sink
outages, partial backend acceptance/WAL faults, archive intent/readback/catalog recovery, unknown preservation state,
owner-fenced deletion/reclamation and temporary scopes whose access expires before physical cleanup. These mechanisms
remain with their owning TICKETs; the operator guide composes and verifies them rather than implementing duplicate
state machines. Report exact recovered coverage and unresolved loss. Local-only archives do not survive arbitrary
host/storage loss, and manifests cannot recreate lost permissions/holds/purge authority.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Check readiness before enrollment | None; explicit read-only operator preflight | Actual runtime/source/storage/authority capability and finite headroom | No Domain event from tooling inspection | Copyable bounded safe pass/warning/unsupported/not-checkable report |
| Enroll the approved installation | Explicit authorized human-run installation operations | Exact targets, privileges, versions, expected effects and verification | Existing configuration/audit facts where required; infrastructure is not a new Workflow event | Only authorized enrollment/service/storage changes, with truthful partial outcome |
| Diagnose degraded collection or retrieval | Read-only protected application/status or trusted local inspection | Independent source/destination/catalog/restore state and freshness | None from reads | Visible gaps/unknowns even when search fails, no automatic repair |
| Recover interrupted work or capacity | Existing bounded owner operations after explicit prerequisite checks | Original dispositions, current authority, eligible bytes and actual residual usage | Owning recovery/disposal facts only | Verified recovered coverage or an explicit unsafe/unsupported stop |
| Use the complete diagnosis journey | Existing authorized live/restore/extraction interactions | Actual failed-operation/run records, context and older content | No added Workflow transition for viewing | Real operator can diagnose with declared coverage and current authority |

## Validation and permissions

Log viewing/extraction capabilities do not grant installation, Docker, backup, hold, purge or policy powers. Preserve
TICKET-00023's human-run privileged-operation model and current managed authentication/authority boundaries. Missing
specific privileged-reader, storage, policy or preservation-fence participants are blockers before the dependent
effect, not permission for a broad internal bypass. Verify source enrollment and private backend/storage isolation;
checksums do not establish authenticity against a privileged host operator.

Use only explicit safe synthetic/controlled fixtures for authorized qualification; never live secrets, transcripts
or unrelated runtime targets. Every new runtime experiment needs its own bounded target, time/resources and cleanup
authority. The completed WF-040 comparison is historical evidence, not a reusable Docker grant. Product operations
remain subject to [ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

## Acceptance and evidence

- Actual supported-platform operator guidance is exercised with declared identities, private topology/credentials,
  explicit effects, independent health/status and safe stop/recovery; unsupported platforms/checks are labelled.
- Under WF-042's controlled baseline (one hour at 10 records/second, roughly 2 KiB mean) plus a separate 60-second
  1,000-record/second burst, all expected identities/bytes are accounted for without unexplained ordinary loss.
  Include actual source families, eight native levels, non-repetitive content, ties, malformed/binary/large fragments,
  nested/non-HTTP/no-subscriber activity and short-lived sources. Owning ID factories/libraries generate identifiers.
- Exercise four tails, two shared reads/exports and one restore; measure aggregate physical/process resources and
  real retained-history queries, not just serialized budgets. Qualify supported selections up to the 1-GiB restore
  bound. Fixture clocks test eligibility, not long-duration durability or production timestamp rewriting.
- At the healthy declared baseline: live display p95 <=5 seconds/p99 <=15 seconds from trusted collection receipt;
  first bounded search/context/detail result p95 <=3 seconds with at least 100 varied requests per class; archive
  verified-publication p95 <=60 seconds. Report producer-to-capture lag, sample counts, clock uncertainty, cold/warm
  results and scanned volume. Non-exhausted burst backlogs settle within five minutes without loss. Restore's nominal
  supported valid selections succeed within its 15-minute budget; a timeout is not successful readiness.
- The complete WF-042 failure matrix covers diagnostic isolation, capture/replay/retirement, independent destination
  outages, partial acceptance, exhaustion/holds, catalog/storage corruption, local recovery, asynchronous preservation/
  purge races, current authority/revocation and actual browser slow/reconnect/expiry/partial states. No false complete,
  caught-up, verified or healthy result substitutes for evidence.
- Real native failure/run/older-log and bounded extraction journeys pass current authorization and accessible
  keyboard/focus/responsive/light-dark requirements. Ordinary users/custom roles/direct Agents cannot acquire log
  access, and revocation checks hold at actual output boundaries without promising recall of released bytes.
- Each owning TICKET supplies its behavior/integration evidence. Qualify runtime/tooling/infrastructure directly;
  never add product tests of configuration, migrations, wrappers, generated artifacts or quality mechanisms.
  Full `./bin/build` precedes implementation acceptance; independent review/QA, publication, merge and operational
  enrollment are separate receipts. A one-hour soak or small synthetic probe is not a long-term reliability claim.

## Coordination

Supply runtime, privilege, private storage and physical-resource requirements early to TICKET-00112/00113/00114/00115/
00116; no producer or lower-level supplier waits for this TICKET's final composed acceptance. The later operator
recovery/complete diagnosis evidence consumes their qualified mechanisms. No duplicate collection, catalog, authority,
restore or Workflow engine is introduced. Before dependent TASKs are called executable, name the exact capability,
owner, unresolved blocker and bounded verification path; the seven TICKETs are not a whole-TICKET dependency cycle.

The approved split now maps exact early suppliers, retaining every receiving `needs-info` gate:

| Early supplier | Receivers / required capability |
|---|---|
| [TASK-00481](../tasks/00481-TASK.md) | TASK-00455/00458 installation inventory/privilege inputs; TASK-00467/00469/00473 narrow operator/authority bindings |
| [TASK-00482](../tasks/00482-TASK.md) | TASK-00455/00461/00468/00471/00472 actual physical storage, isolation, removal accounting and copy/version/backup limits |
| [TASK-00483](../tasks/00483-TASK.md) | TASK-00456/00458/00461/00468/00471/00473 actual supervision, scoped runtime execution and residual accounting |
| [TASK-00484](../tasks/00484-TASK.md) | TASK-00474/00475/00477 native temporary reservation/launch/isolation/lifetime/removal capabilities |
| [TASK-00485](../tasks/00485-TASK.md) | TASK-00465/00478/00479 actual authenticated streaming/transfer ingress, buffering, cancellation and resource binding |

These sixteen receiving TASKs now have true metadata edges, not whole-TICKET waits. TASK-00455 consumes A/B and
supplies C's native design; C never reverse-blocks that design. D consumes the qualified backend, not completed
restore admission/import/retirement. E consumes TASK-00072's actual shared ingress; A–D do not inherit its completed
browser chain. F/G below are later consumers, never early operator/runtime suppliers.

Existing preservation/privacy and execution owners retain their business authority. TASK-00379/00380's actual early
policy/restriction participants and TASK-00140/00141's context/registration/lifetime bindings remain unresolved where
not delivered; these seven new TASKs cannot manufacture them. Keep affected records `needs-info` until actual mechanisms
and bounded qualification exist. Historical experiment authority is not a launcher grant, allocated IDs do not prove
physical isolation and failed/abandoned resources remain counted until actual disposition is established.

Downloads retain the approved two-installation/one-requester shared read/export pool, <=64-KiB batches and <=1-MiB
queued transfer output. TASK-00480 reuses the qualified export path with its own restored-scope/expiry evidence.
No second runtime, admission pool, authority store or operational grant follows from these mappings.

[TICKET-00110](00110-TICKET.md)'s human-only main-checkout Docker log pane and existing terminal-first work stay
independent: neither waiting for this release nor certifying its full pipeline. Registered-project runtime logs,
JavaScript errors and transcripts remain excluded; general metrics, tracing, external alerting, error grouping,
off-machine operational archives and non-admin delegation remain deferred without speculative TASKs.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00481](../tasks/00481-TASK.md) | Bind and preflight authorized observability operations | needs-info |
| [TASK-00482](../tasks/00482-TASK.md) | Enroll isolated operational storage with enforced physical limits | needs-info |
| [TASK-00483](../tasks/00483-TASK.md) | Supervise enrolled capture and lifecycle workers | needs-info |
| [TASK-00484](../tasks/00484-TASK.md) | Launch and reclaim bounded temporary restore runtimes | needs-info |
| [TASK-00485](../tasks/00485-TASK.md) | Support bounded authenticated log streaming and extraction | needs-info |
| [TASK-00486](../tasks/00486-TASK.md) | Inspect and recover observability through the existing operator guide | ready-for-agent |
| [TASK-00487](../tasks/00487-TASK.md) | Complete the supported live-and-archived operator journey | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this requirement group in EPIC-00019's seven-TICKET split. That requirement checkpoint allocated no
TASKs or experiment grants. Operator recovery and composed evidence remain product requirements, not authorization
to start services or a replacement for each lower-level slice's acceptance.

### Approved TASK decomposition and current handoff

John invoked `to-tasks TICKET-00117` in the selected worktree and answered **“approved”** to the seven-outcome split.
The complete agreed installation/operator/qualification scope is now written/accepted:

| Slice | TASK / outcome | Dependencies and current gate |
|---|---|---|
| A | [TASK-00481 — Bind and preflight authorized observability operations](../tasks/00481-TASK.md) | `needs-info`; TASK-00446 plus actual existing-owner authority inputs |
| B | [TASK-00482 — Enroll isolated operational storage with enforced physical limits](../tasks/00482-TASK.md) | `needs-info`; A and TASK-00374; native physical/private-storage mechanisms remain unqualified |
| C | [TASK-00483 — Supervise enrolled capture and lifecycle workers](../tasks/00483-TASK.md) | `needs-info`; A/B and TASK-00455; actual scoped native supervision/registration inputs remain gated |
| D | [TASK-00484 — Launch and reclaim bounded temporary restore runtimes](../tasks/00484-TASK.md) | `needs-info`; B/C and TASK-00461; narrow launcher/removal/isolation mechanisms remain unqualified |
| E | [TASK-00485 — Support bounded authenticated log streaming and extraction](../tasks/00485-TASK.md) | `needs-info`; C and TASK-00072; actual ingress/worker/buffering/cancellation fit remains gated |
| F | [TASK-00486 — Inspect and recover observability through the existing operator guide](../tasks/00486-TASK.md) | Decision-complete; TASK-00074/00075/00456/00460/00469/00472/00473/00477 |
| G | [TASK-00487 — Complete the supported live-and-archived operator journey](../tasks/00487-TASK.md) | Decision-complete; F and TASK-00466/00480, transitively all earlier observability slices |

A–E deliver concrete early product blockers/mechanisms; F integrates real status/recovery into the existing operator
guide; G contains mandatory composed workload/resource/operator acceptance and supported handoff reconciliation.
Each slice owns its relevant security, behavior/integration tests, direct native qualification, applicable accessibility
and full build. G is not a place to defer those tests, a general infrastructure demonstration or an unrecorded extra
parent-closeout gate. All seven are unranked; no terminal-first prerequisite or independent terminal-pane dependency
is added. Runtime effects still require the applicable explicit authority.

G retains the full WF-042 baseline/burst/concurrency/retained-history/failure matrix, exact native source/level/content
accounting, full 1-GiB restore readiness and current output-authority probes. Measured retention/capacity must fit;
50-GiB archive admission is not proof of 90 days. Missing mechanisms stay gated rather than silently weakening holds,
coverage, DEBUG, native content, private transport or fixed expiry. Local-only loss and unsupported backups remain
explicit; physical receipts, independent review/QA, publication and operational enrollment stay distinct.

Before allocation, fetched `origin/develop` remained `58b087b`; the selected branch stayed `ac41c28` without rebase
or upstream application adoption. Live/archive inventories across 17 refs/four registered worktrees ended at
TASK-00480 (committed maximum TASK-00450), with no archived TASKs or collisions. Only TASK-00481–00487 were allocated.
Recheck IDs/upstream before future allocation/publication; this snapshot reserves nothing else.

**Current map phase:** all decisions are Closed; the sole EPIC and all seven requirement TICKETs are approved/written;
**TICKET-00111–00117 TASK decompositions are complete/accepted as TASK-00451–00487**. No map planning phase remains.
This is complete decomposition, not implementation, runtime qualification or release acceptance. Parent statuses
remain open while children are unfinished. Existing local Board active/ready work and priorities remain authoritative.

### Planning verification

`./bin/planning-check --write` and `./bin/planning-check` passed with **623 records, 518 active**, generated views
current. Tracked and explicit untracked planning Markdown whitespace checks passed. A pre-edit hash/Board snapshot
under ignored `.runs/notes/observability-final-decomposition/20261015T190417Z/` confirmed only the seven approved new
TASK files and scoped planning updates changed; no prior file was removed. Exact new parent/feature/status/blank
order/pr/blocker metadata and all sixteen receiving supplier additions were checked; all receiving `needs-info`
gates remain. Five new TASKs are `needs-info`, two decision-complete and waiting. All thirty-seven observability
TASKs remain unranked with blank PRs.

The complete dependency graph is acyclic; TASK-00487 transitively reaches TASK-00451–00486. Existing local Active
Work and Ready Frontier sections are byte-for-byte unchanged from the pre-edit Board; the additions are five Needs
Info and two Waiting entries. No tracked prior TASK/TICKET source changed, and the final other-worktree check found
no new-ID collisions. The earlier fetched 17-ref inventory remains an allocation snapshot, not a reservation.

These are planning receipts only. No implementation, live content access, runtime/retention/resource qualification,
full `./bin/build`, independent review/QA, commit, publication or rebase occurred. Changes remain uncommitted; full
build and upstream/ID reconciliation remain required before commit/publication. Actual owner/native mechanisms and
separately authorized runtime experiments remain gated. Historical synthetic receipts are not fresh application
acceptance or an ongoing execution grant.

Next: `/skill:next` for read-only Board-based work selection from the published planning branch, or from `develop`
after merge, following [the EPIC's current decomposition handoff](../epics/00019-EPIC.md#approved-installation-task-decomposition-and-completed-map-planning). This
recommendation invokes no execution, changes no priority and grants no implementation, experiment or publication.
