# Local skill qualification — 2026-09-26

TASK-00131–00134 and TASK-00136 were walked against current source in the approved TASK-00135 checkout. This is a
bounded author walkthrough of the instructions, not an independent review or a claim that Pi executed each skill.
The shared gate and exact tested-file receipt are recorded in the TASK completion notes and ignored local evidence.
No product source, tests, external project or dependency version was changed for these exercises.

## Audit: API failure boundary

Question: does the API failure boundary expose arbitrary errors or affect non-API paths? Read
[ApiFailureMiddleware](../../src/Adapter/Http/Middleware/Api/ApiFailureMiddleware.php),
[ApiFailureMapper](../../src/Adapter/Http/Api/Failure/ApiFailureMapper.php), their
[middleware](../../tests/Unit/Http/ApiFailureMiddlewareTest.php) and
[mapper](../../tests/Unit/Http/ApiFailureMapperTest.php) tests against TASK-00020 and engineering standards.

The trace reaches the downstream handler only after exact `/api` or `/api/` matching. Non-API requests return
through the handler outside the catch/log/correlation path. Known exceptions use fixed representations; unknown
exceptions produce generic 500 output. Logger input contains fixed keys and constrained scalars, not the throwable,
request body or arbitrary message. Tests explicitly cover malformed correlation, generic lookup and secret-bearing
failure messages. These are counterevidence to the suspected leak and global interception; no confirmed defect in
this sample. A formatter/infrastructure deployment audit and other middleware paths were not performed. The
instruction correctly stops with evidence/limits, without inventing work or treating the sample as certification.

## Fix: retrospective bug handoff and duplicate rejection

Use the historical reported symptom in [TASK-00020](../../planning/tasks/00020-TASK.md): `/apiary/missing` was
intercepted by the old global JSend middleware. The record describes a failing regression followed by correction;
that is historical evidence, not a newly reproduced failure. The draft below exercises the handoff fields; it is
superseded by the existing owner and must not become a new live TASK.

| Draft field | Retrospective content |
|---|---|
| Identity/status | No new ID; standalone `kind: bug`, empty `ticket`, draft only; existing owner TASK-00020 |
| Expected/observed | Exact `/api` and `/api/` paths receive API handling; historical `/apiary/missing` instead received global JSend/correlation |
| Impact/cause | Non-API transport behavior changed; original global boundary intercepted downstream failures |
| Outcome/scope | Restrict API interception while preserving sanitized API route/method errors; exclude a new non-API error renderer |
| Use case/effects | Query/request processing only; no Domain commands/events or persisted state; API response/correlation/log effects only within scope |
| Validation/permissions | No new privileges; reject malformed correlation as a diagnostic identifier; preserve existing server-side authority checks |
| Rejection/failure acceptance | Known API errors remain sanitized; unknown API failures return generic 500; non-API exception/response passes through unchanged |
| Verification | If unresolved, reproduce through the real middleware with `/apiary/missing`, add a failing behavior regression before repair, then focused HTTP checks and full gate |
| Ownership/dependencies/order | Existing TASK owns the correction; no new dependency or priority allocation; any new record would require live/archive ID and ownership recheck |
| Handoff decision | Current middleware and `test_that_non_api_path_is_not_rendered_as_jsend` demonstrate correction; no new repair, record, ID or test is justified |

The normal accepted-draft route was desk-walked: check current ownership/allocation, write only after accepted
scope/record authority, refresh planning, then hand to `work` with reproduction evidence and existing checkout
choice. The rejected/insufficient-evidence route writes no live record and never labels a fabricated test red.
No such new bug was accepted or created by this qualification.

## Architecture: Persistence cohesion

Read [ActivationGrantRecords](../../src/Adapter/Persistence/ActivationGrantRecords.php),
[ActivationGrantTransitions](../../src/Adapter/Persistence/ActivationGrantTransitions.php),
[PostgresActivationGrantRepository](../../src/Adapter/Persistence/Repository/PostgresActivationGrantRepository.php)
and [PostgresAtomicOperation](../../src/Adapter/Persistence/PostgresAtomicOperation.php), plus current callers.
The repository's add/replace paths acquire an enclosing-transaction fence, check package-produced transitions,
and execute writes within an adapter savepoint. `PostgresAtomicOperation` alone does not guarantee an enclosing
transaction; the activation repository supplies that guard. A directory move must preserve those relationships.

The root Persistence namespace mixes activation mapping/transition support with session mapping and shared
PostgreSQL mechanics. Options for a future accepted refactor:

1. Keep the layout: avoids import churn, but retains the reported navigation friction.
2. Group cohesive activation support under `Persistence/ActivationGrant`, session support under
   `Persistence/RefreshSession`, and shared PostgreSQL mechanisms under `Persistence/Postgres`. Inspect broader
   callers before deciding whether repositories/hydrators should move with each capability.
3. Reorganize the entire persistence layer by aggregate: potentially stronger locality but broader churn across
   repositories, hydration, locking, tests and composition; not justified by this bounded sample alone.

Recommend exploring option 2 with the maintainer. `RefreshSessionRecords` has both session and user repository
callers, so its ownership is not safely inferred from its name. Preserve package interfaces and shared mechanics;
no generic wrapper or common grant abstraction follows from similar-looking fields. This is the approved cohesion
preference, not a current security defect or automatic acceptance blocker. No files were moved, ADR changed or
implementation TASK created.

## Adopt: library compatibility and verification

Read-only comparison used Fight Common's current project instructions/profile and public
`Application/Repository/UnitOfWork` and `TransactionalUnitOfWork` contracts, then Agent OS's lock, installed
`InvitePendingUserHandler`, persistence composition and ADR 0001. Exact external checkout identity and evidence
remain in ignored local notes rather than portable private-source references.

| Observed difference | Assessment / decision |
|---|---|
| Both use inward Domain/Application/Adapter dependencies | Compatible; retain the principle without copying application orchestration into a library |
| Common retains deprecated `UnitOfWork` compatibility extending `TransactionalUnitOfWork`; its profile protects released API compatibility | Reject removing the compatibility API merely to match Agent OS's preferred consumer interface; package release policy owns removal |
| Agent OS locks Common v1.2.0 and Access Control v0.3.0; installed invite handler and composition use `TransactionalUnitOfWork` | Adapt comparison to current signatures; ADR 0001's initial v0.2.0 observations are historical, not evidence of the installed contract today |
| Common's profile requires exact production statement coverage and project-specific public hosted checks; Agent OS currently documents a meaningful unit-coverage goal | Preserve target requirements; reject weakening Common's gate or importing its exact enforcement as an already implemented Agent OS gate |
| Agent OS's managed local TASK sandbox and application deployment planning | Non-applicable to the inspected library contract; defer any future integration until explicitly scoped |
| Target's current hosted state and full runtime conformance | Undecided/uninspected; no hosted checks, release certification or whole-library audit were run |

The result is an alignment recommendation only: no target edits, dependency update, enrollment, PR or new planning
record. A future selected change belongs to its owning project.

## Security audit: error/log trust boundary and planned isolation

Asset: confidentiality of request credentials and error internals; actor: a requester controlling correlation
headers and request fields; effect: public API error response and structured log record. The middleware and mapper
trace above, plus the existing secret-bearing failure test, supply concrete counterevidence to arbitrary message
or request logging on this path. Severity is not assigned to an unconfirmed vulnerability; confidence is limited
to the inspected source/test contract. The full gate exercises the existing tests, not an external exploit.

Separately walk [TICKET-00032](../../planning/tickets/00032-TICKET.md) and [Team roles](TEAM.md) as planned controls:
PHP authority, restricted provisioner, actual isolation verification, no agent Docker socket, explicit ownership,
reconciliation and stale-worker fencing. None is proven by development Compose or by this audit skill. Runtime
mount/network/credential/resource inspection must wait for an implemented environment and authorized diagnostics.
An instruction asking the auditor to scan an external endpoint or disclose a vulnerability exceeds this sample;
return the evidence gap/required authority while completing the safe source assessment.

No application-wide security claim, browser test, sandbox escape test, vulnerability disclosure, remediation or
production certification results from these walkthroughs. Relevant source provenance and adaptation decisions are
in the [skill comparison](SKILL_GAPS.md#architecture-and-security-source-decisions).

## Release: scoped author qualification — 2026-09-27

[TASK-00143](../../planning/tasks/00143-TASK.md) adds the portable [release skill](../../.pi/skills/release/SKILL.md).
Read the actual Fight Common release README/accepted ADR 0027 and Access Control release wrapper/Delivery standard
to derive the two conditional profiles. Their certification schemas and asset policies differ; neither profile
replaces current target authority. Official Git, GitHub CLI/immutable-release and Packagist references are linked
at the relevant steps. Instructions are original; no external workflow or source implementation was copied.

The following are **author instruction walkthroughs**, not autonomous agent trials or live release effects:

| Scenario | Routed outcome and boundary checked |
|---|---|
| Version requested before a release branch exists | Inventory readiness and docs, use the current target's branch/review/gate policy; no implied blanket publication authority |
| Release already merged to main, no tag yet | Identify the actual merge; certify the final commit and prepare its signing handoff instead of cutting another branch |
| Main merge has the candidate's tree but a different commit | Candidate receipt is insufficient for final signing; certify the tag's exact commit |
| Existing local or remote tag has a conflicting object, signer or peeled commit | Stop mutation and reconcile; no deletion, force or automatic re-signing |
| Tag push or release publication loses its response | Inspect provider state before retry; a matching effect is reused, transport failure remains unknown |
| Common release has no uploaded assets | Read the applicable accepted decision; zero assets may be valid, with observed inventory recorded |
| Access Control certification directory already exists | Inspect partial evidence and preserve it; use supported recovery or an approved fresh worktree, not evidence deletion |
| Human handoff runs after checkout, remote or receipt changes | Required script preconditions fail before sign/push; handoff binds full commit, files, signer and destinations |
| GitHub release published, Packagist not yet showing the version | Keep publication recorded, bound registry observations and report projection incomplete; do not recreate the release |
| Packagist supplies abbreviated Composer v2 metadata | Expand using owning tooling before comparing version/references; a raw abbreviated entry is not complete evidence |
| Published version has stale planning prose | Separate ordinary docs correction and merge-back; preserve the published tag and historical checkpoints |
| Read-only status request | Inspect the ledger only; no release docs, branch, tag or provider mutation |

Pi 0.87.1's actual skill loader found exactly one `release` entry through the existing global skill path when
called with Agent OS, Fight Common and Access Control as working directories; no discovery diagnostics were
reported. No global configuration changed. This checks resource discovery, not a live model-driven Pi release.
YAML/frontmatter and linked local resources were checked directly. The Skill Creator Python validator could not
start because PyYAML is absent in both available Python runtimes; Ruby YAML checks and Pi's loader passed instead.
No dependency was installed to run that optional validator.

No package was modified, certified, signed or published. No real signer script was generated without a concrete
certified candidate. Its generation/guard contract was inspected; execution of such a script and end-to-end
release behavior remain unverified until an authorized release. No tests of tooling/instructions were added to
the product suite. Fresh application gate results and ignored evidence locations are recorded in TASK-00143;
independent formal review remains separate from this builder qualification.


## Hosted-evidence handoff: author qualification — 2026-09-28

TASK-00144 corrects a reproduced instruction cycle: acceptance requires PR-triggered hosted proof while ordinary
landing requires acceptance before publishing the PR. This is direct author inspection and scenario walkthrough,
not independent acceptance or a live model-driven invocation. No consumer review or CI policy was modified.

| Scenario inspected | Route established by the revised instructions |
|---|---|
| Independent review passes everything except PR-triggered hosted CI; publication authorized | `land` publishes/reuses a draft, preserves incomplete status and report, and returns evidence |
| Same report but only `work` authorized | No edit, empty commit or push; identify the missing publication authority |
| Missing hosted proof plus an implementation defect or missing local proof | Draft exception is unavailable; address the actual outstanding work |
| Hosted run succeeds for unchanged reviewed content | Independent reviewer checks provenance and new evidence; inherited local checks stay labeled |
| CI tests a merge candidate or an old source head | Establish source/base/tested-commit identities; an old run cannot prove current acceptance |
| Run queued, absent, cancelled or infrastructure-failed | Evidence action remains pending; inspect triggers/cause, do not manufacture a repair or poll indefinitely |
| Source changed substantively after review | Review affected implementation and interactions; no evidence-only shortcut |
| Existing ready PR or pending visual QA | Reuse PR as draft; required QA remains a prerequisite for accepted landing |
| No hosted requirement exists, including private repositories with no routine hosted runs | Local gate and acceptance evidence support review; report hosted checks as not run/unavailable without inventing a blocker |
| Workflow file exists but no accepted hosted requirement | The file does not opt the TASK into a hosted acceptance gate |
| Optional hosted run reports a reproducible application failure | Investigate the actual defect; optional CI does not excuse broken implementation |
| Hosted checks required only by branch protection | Report the merge restriction separately; technical acceptance does not authorize bypass |
| Old report incorrectly assumes optional CI is mandatory | Independent reassessment uses the explicit policy; builder does not overwrite the verdict |
| Next-work request while CI is the sole blocker | Keep Board selection, recommend the evidence action rather than another implementation pass |

Ruby YAML checks passed for all four changed skill entrypoints. Local Markdown file/heading links, planning and
whitespace checks passed. The optional skill-creator Python validator could not run because PyYAML is absent;
no dependency was installed. These checks establish instruction structure, not runtime behavior.

The canonical build passed in the isolated `fight-agent-os-task00144` Compose project: 7 Pi tests, 147 PHP tests /
1,133 assertions; Deptrac 651 allowed with zero violations, uncovered dependencies, warnings or errors. The build
reported no skips. Test database setup emitted two normal migration notices and completed six migrations.
Receipts, logs and tested-file hashes originally lived under ignored `.runs/notes/task-00144/` in the retired
worktree; their current main-checkout locator is
`.runs/notes/task-00144-land/retained-worktree-runs/notes/task-00144/`. Only completion/qualification records and
generated planning views changed after that build and were revalidated directly.

Global Pi settings were inspected read-only and still reference the main checkout. These worktree instructions
are not globally active until integrated there. No hosted publication or independent formal review was performed.


The local-first refinement was freshly revalidated on 2026-09-28: the final canonical build exited 0 with the
same 7 Pi tests and 147 PHP tests / 1,133 assertions, no reported build warnings or skips, and zero Deptrac
violations/warnings/errors. Ruby metadata, 724 local file/heading links, planning and whitespace checks passed.
Optional hosted absence, workflow-file-only discovery, actual failures in optional CI, separate merge checks
and mistaken older review requirements were covered by direct author walkthroughs. Final receipts and source
hashes are in `local-first-final-build.*` and `local-first-final-tested-files.json` under the TASK scratch directory.
The revised instructions were not executed in a live Pi publication workflow. Consumer repositories and their
reviews remain untouched. Temporary validation containers, network and owned volume were retired.

### R1 revision: final candidate and tracked completion

Historical qualification: the independent delivery-continuation gate in this subsection was superseded by
[land-owned administrative closeout](#land-owned-administrative-closeout) at the user's request. Its traces
remain the record of that revision, not current routing instructions.

The independent TASK-00144 review identified a second cycle. Before editing, the author traced its retained
snapshot: hosted success and acceptance of A allowed completion metadata B, but B needed hosted success while
TASK status had to remain incomplete. Recording `done` afterward created C and repeated the condition. This is
an instruction-level regression reproduction, not a failing product test. The review's 13 file hashes matched
revision intake. The canonical report remains untouched and still says `revise`; the following is author
qualification only.

The revised [closeout contract](LANDING.md#acceptance-candidate-and-administrative-closeout) was walked through
[completion](../../planning/CONVENTIONS.md#metadata-and-lifecycle), review continuation and land/work/next routing:

| Scenario | Trace and stopping boundary |
|---|---|
| Accepted policy permits candidate acceptance plus final-head delivery checks | Publish draft A with incomplete TASK → required CI for A succeeds → independent acceptance of A → required QA → prepare B containing `done`, A's acceptance evidence, actual PR URL and regenerated views, explicitly noting delivery pending → local gate, commit and non-force push → literal-head CI for B succeeds → independent continuation proves administrative-only A → B and run provenance → record success in canonical report/ignored receipt/PR body → ready PR and ownership-proven cleanup. B stays the final head; no C is created. |
| Literal-final-head success is an acceptance prerequisite for tracked `done` | Stop completion mutation and request the requirement owner's amendment to the split; publication authority is insufficient. No consumer criterion is weakened by these Agent OS instructions. |
| Owner approves or declines amendment | Approval must be recorded in the owning policy/TASK and independently assessed; a changed requirement is substantive, so establish a new acceptance candidate before closeout. Decline or ambiguous scope leaves the TASK incomplete and worktree retained, not an endless status/CI loop. |
| B's required run is queued, unavailable, cancelled or infrastructure-failed | Retain B, draft and worktree; report delivery incomplete and retry the same head within authority. A's accepted checkpoint and tracked `done` do not claim B passed. Focused review may say `revise`/`Unverified` for delivery while preserving A's acceptance. Resume the final-delivery path, not initial draft intake; no status-only C. |
| B's run demonstrates an actual implementation defect | Acceptance is invalidated; stop landing/cleanup and hand off to `work` to reopen/repair, verify and obtain independent review of a new candidate. No claim that an earlier green A excuses the defect. |
| B changes requirements, instructions, dependencies or behavior | It is not administrative closeout. Stop for a new candidate and fresh review rather than borrowing A's completion claim. |
| B's only evidence is A's green run, a skipped job or the wrong merge/source identity | Required B proof remains missing. Literal source-head requirements cannot be satisfied by a merge-candidate run; retain draft/resources. |
| Accepted policy requires hosted proof for A but no final-head delivery check | Do not invent a B CI requirement; prove the administrative bridge and local gate, then use ordinary publication/cleanup rules. |
| No hosted acceptance requirement exists | Local-first path is unchanged; optional absence cannot create a blocker, while a demonstrated defect and actual merge restrictions remain actionable. |

These are direct instruction traces, not simulated hosting results, live Pi invocations, or independent approval.
No runtime tests of instructions were added. Fresh revision checks and gate results are recorded in
[TASK-00144](../../planning/tasks/00144-TASK.md); earlier receipts above remain historical.


## QA skill: bounded qualification — 2026-09-28

This is historical TASK-00146 qualification. TASK-00150's [follow-up below](#qa-and-review-responsibilities-follow-up--2026-09-30)
supersedes the non-interactive skip rule; current [QA applicability](QA.md#applicability) governs new work.

[TASK-00146](../../planning/tasks/00146-TASK.md) adds the [qa skill](../../.pi/skills/qa/SKILL.md), canonical report
and work/review/next/land handoffs. This qualification covers instructions and available tools; it is not an
independent TASK acceptance or an end-to-end model-driven QA run.

An independently delegated session read the actual entrypoint, references and callers and walked five cases:

| Case | Result of instruction walkthrough |
|---|---|
| Reviewed interactive TASK before land | Independent PASS requires known source/runtime, all required scenarios and durable evidence; publication remains separate |
| Open PR moves during QA | Historical evidence cannot pass the current PR without a proven behavior-preserving bridge or affected rerun |
| Confirmed defect plus unavailable scenario | Overall FAIL; stable repair finding and missing-prerequisite evidence both survive |
| Documentation-only TASK | Criterion-specific N/A without invented runtime/screenshots or technical acceptance |
| Rerun interrupted after earlier PASS | Initial canonical INCOMPLETE prevents fallback to historical PASS |

No blocking instruction contradiction was found in those cases. The evaluator did not author the instructions,
change implementation, run product scenarios, publish evidence or issue a formal review. Its report and evaluated
file hashes originally lived under ignored `.runs/notes/task-00146/forward-test.md` in the retired worktree;
the current main-checkout locator is
`.runs/notes/task-00146-cleanup/retained-worktree/.runs/notes/task-00146/forward-test.md`.

Ruby YAML validation passed for all five affected skill entrypoints. Pi 0.87.1's actual local skill loader found
exactly one `qa` entry and no diagnostics. This proves discovery, not a live skill invocation. The optional Skill
Creator Python validator could not start because PyYAML was absent from both inspected Python runtimes; no
package was installed. Local Markdown references and planning were checked directly.

The owned isolated application responded with its readiness text, and container mount inspection established its
source checkout. That is startup evidence only. The in-app browser rejected the local URL with
`net::ERR_BLOCKED_BY_CLIENT`; Chrome was unavailable. No browser interaction or screenshot was captured, and live
browser qualification remains unverified. No access restriction was bypassed.

A real PTY launched the existing Pi extension with an isolated empty agent profile, offline mode, tools and
sessions disabled. Its captured output contained the Fight Agent OS header; the process exited successfully.
No model request was sent. The attempted header command produced no attested state transition, so the retained
sanitized transcript proves startup/output capture only, not interactive scenario completion or visual layout.
Pi warned that `fd` was unavailable and its download was skipped in offline mode. No terminal screenshot was made.

Canonical report atomicity/concurrent writers, actual bug repair/review/rerun, live model-driven QA and PR artifact
upload/access were not exercised. The instructions cover these boundaries but do not attest their runtime success.
No tooling/prose tests or seeded defects were added to the product suite. Fresh full-gate results and exact tested
file hashes are recorded in the TASK's ignored receipt. Global skills still resolve the main checkout; these
instructions become available there after integration. Managed QA Agents and Workflow enforcement remain future work.

A follow-up independent routing check found ambiguous wording that could send an unimplemented TASK to review.
The final caller now explicitly routes unimplemented work/implementation findings to `work`, completed unreviewed
implementation to `review`, and accepted work with missing required/requested evidence to `qa`. Accepted
non-interactive work without required/requested QA can proceed to `land` without an invented report requirement.

## QA and review responsibilities follow-up — 2026-09-30

[TASK-00150](../../planning/tasks/00150-TASK.md) moves the detailed quality assessment to review and makes
post-review QA depend on changed behavior, including non-UI contracts. Direct builder walkthroughs traced the
actual work/review/qa/next/land/audit entrypoints, shared rules and report template:

| Scenario | Instruction result and boundary |
|---|---|
| Small implementation with relevant architecture rule | Work loads the relevant section, writes contract-based tests and outcome evidence; review owns the full matrix. Applicable standards remain binding. |
| Reviewed API/library/worker change with green unit tests | Route to QA; choose actual requests or a disposable public-contract driver and independently expected output/effects. No UI is not N/A and no new product route is needed. |
| Invalid input, wrong owner, duplicate submission or partial failure | Choose relevant adversarial cases, assert rejected/absent effects, preserve reproducible findings for work; no seeded source defect or QA repair. |
| Probe would reach shared data or require unavailable tooling | Stop the affected scenario with INCOMPLETE and a recovery action; no implicit install, destructive load, external effect or fabricated PASS. |
| Changed screen with a new error state | Exercise actual interactions and capture readable comparable baseline/result states; inspect every sanitized image. Missing required captures stay INCOMPLETE. |
| Library change has no images | Omit the visual table while retaining behavioral QA results, inputs, outputs and state evidence. |
| Skill routing change versus spelling-only edit | Trace concrete requests through changed decisions for the skill; pure spelling can have criterion-specific N/A in the technical handoff or QA report. Do not claim a walkthrough is live agent execution. |
| Missing applicability assessment on accepted TASK | Review hands off risks; next/land route missing assessment to QA. Unimplemented work still routes to work and completed unreviewed work to review. |
| Current FAIL, interruption or source/PR-head drift | Preserve current FAIL/INCOMPLETE and provenance checks; do not use a historical PASS or rewrite technical review. |
| Scoped audit encounters a non-UI QA skip | Report the evidence gap and propose suitable scenarios within the sample, without issuing QA PASS or launching unrequested probes. |

These are instruction traces and builder qualification, not independent review/QA, empirical agent trials,
executed API/fault-injection tests or screenshot evidence. This change does not claim new browser availability,
managed QA enforcement or successful artifact publication. Earlier TASK-00146 runtime limitations remain historical;
current validation counts and exact build receipts belong to TASK-00150's completion evidence.


## Next-command handoffs and map-wide planning — 2026-09-30

TASK-00150's final follow-up adds [explicit next-command handoffs](HANDOFFS.md) and the default
[map-wide planning order](../../planning/CONVENTIONS.md#map-wide-planning-order). Sixteen additional builder
walkthroughs trace work/review repair loops, non-UI QA, missing prerequisites, hosted-evidence continuation,
publication/human merge, pending WF decisions, multi-EPIC and multi-TICKET phase order, closed-map handoffs,
explicit narrower requests, uncertain approvals, unordered maps and Board-owned execution selection.

The direct traces confirm that a recommendation uses the actual target and never invokes the next skill,
waives an approval or claims an accepted record from a proposal. Grill remains one approved EPIC per invocation;
Wayfinder owns interviews whose resolution boundary excludes EPIC creation. Existing live/archived planning and
authoritative Board semantics remain intact. These are builder instruction checks, not independent acceptance,
real planning mutations, automatic skill execution or empirical agent trials. The TASK and ignored final receipt
record current metadata/link/planning checks, full-gate results and committed content identity.


## Land-owned administrative closeout

The user reported an already accepted TASK left draft after QA and final-head CI passed, solely for another
independent delivery continuation. The previous land, landing and review instructions required that continuation
and a canonical review update. The user explicitly removed this extra gate while retaining initial independent
acceptance, required delivery checks and renewed review for substantive changes, defects or unproven integration.
The correction assigns closeout verification to [land](LANDING.md#verify-and-publish) and aligns review, next,
execution and handoff routing. The earlier R1 traces above remain historical.

Direct author walkthroughs of the revised instructions:

| Scenario | Resulting route and stopping boundary |
|---|---|
| A independently accepted; B administrative; QA and required final-head checks pass | Land verifies A → B and exact run provenance, records its ignored receipt and PR body, marks ready and performs ownership-proven cleanup. No further independent review, canonical review update or tracked commit solely for delivery. |
| A accepted; B's required check pending, cancelled or infrastructure-failed | Retain B, draft and resources; disclose the missing proof and resume land when available. Acceptance remains intact; no status toggle or delivery-only review verdict. |
| B has only A's green run, wrong source/merge identity or skipped required job | Land keeps delivery incomplete until the accepted final-head requirement is proven. |
| An older canonical delivery-only revise report explicitly preserves accepted A | Land verifies the preserved independent acceptance and absence of unresolved acceptance findings, then owns the bridge and delivery proof without replacing the canonical report. Ambiguous or absent acceptance routes to independent review. |
| Initial acceptance still lacks required hosted proof | Authorized draft publication obtains evidence; independent review assesses that evidence before acceptance. The correction does not grant land initial acceptance authority. |
| A accepted; no hosted final-head check is required | Land verifies the administrative bridge and existing publication requirements. It does not invent a hosted check or another independent review. |
| Commit IDs change through proven mechanical integration | Existing reconciliation rules preserve acceptance with a verified behavior-preserving bridge and required checks. A changed ID alone requires no independent review. |
| B changes requirements, instructions, code, dependencies or behavior, or integration is unproven | Stop for fresh independent review of the affected target; required implementation repair first routes to work. Calling a change metadata does not make it administrative. |
| A delivery run demonstrates an implementation defect, or current QA fails | Stop landing and cleanup, route to work for repair, then renewed independent review and affected QA. Earlier acceptance does not excuse a demonstrated defect. |
| The accepted target requires literal-final-head success before tracked completion | The existing requirement-owner amendment boundary still applies; publication permission cannot silently redefine that criterion. |

These are author instruction traces, not live hosted runs, independent acceptance or QA PASS. This correction
changes executable instructions and therefore still needs its own initial independent review and applicable QA.
No consumer PR, review report or TASK status was changed by this qualification.
