---
id: TICKET-00101
epic: EPIC-00009
title: Confirm and recover one durable creation attempt
status: ready-for-agent
---

# Confirm and recover one durable creation attempt

## Problem statement

Creating a project crosses filesystem, Git, executable preparation, GitHub and database boundaries that cannot
roll back together. A browser wizard or process exit cannot establish consent, completion or safe retry; humans
need one exact preview and durable, inspectable partial outcomes.

## Solution and boundaries

Own the authenticated browser creation form, read-only preflight, complete preview, explicit confirmation and
single durable creation-attempt lifecycle. Require editable display name, Workspace slug, starter, Runner-accessible
parent directory, independent local directory name, initial branch, preparation choice and development profile.
Suggest sensible defaults without conflating identities; optional publication has independently editable owner,
repository name, private-by-default visibility and description. Show a heightened warning and require explicit
acknowledgement for public visibility.

Before preview validate pinned snapshot/manifest integrity, all transformation expectations, names/collisions,
canonical path/root containment and ownership, Runner availability/capabilities, profile revision and applicable
GitHub identity/owner/name/visibility availability. Preview every intended filesystem, Git, executable/network,
source-host, registration and context effect, including exact source/tag/commit/digest, transformations, selected
branch/initial commit intent, preparation command, publication target and selected profile revision. Preflight
reads create no project, remote, registration or preparation process and never authenticate accounts automatically.

One confirmation authorizes only that immutable plan. Recheck material input, snapshot, authority, capability,
collision and external-state changes before effect admission. An invalidated preview requires explicit revised
consent, not silent continuation. Do not prompt repeatedly between unchanged ordinary stages. Outside-root setup
remains the separate human-run installation operation; preserve the volatile form/preview where safe without
claiming it survived reload or granting a browser permission to change mounts.

Persist one attempt identity, immutable confirmed plan, initiating actor/grant, stage identities and verified
intent/result evidence. Sequence: generate; initialize Git/initial commit; optional preparation; optional GitHub
creation/push; register Repository/Checkout/Worktree; resolve first database-authoritative context. Stage adapters
from TICKET-00100/00102/00103 supply exact outcomes; this owner alone decides attempt transitions. It is not a
coding Workflow, TASK claim, Team Lead/Software Engineer assignment or Review/publication pipeline.

Commit state, required audit and delivery intent together before external effects. Use bounded delivery/claim and
currentness contracts compatible with the existing eligible Runner, not another general job platform. Duplicate
confirmation/delivery returns the original operation where applicable. Progress and receipt queries read durable
facts; queue delivery, process exit, live UI or Agent inference never establishes success. Report partial/stale/
unavailable observations distinctly and reconcile after browser disconnect, application/Runner restart or process
interruption without relaunching uncertain effects.

Explicit retry revalidates every completed effect and resumes only at the first incomplete safe stage. Prevent
duplicate directory, initial commit, remote, push, Repository/Checkout link or context revision. Changed authority,
missing capability, unexpected filesystem/Git/provider state, ambiguous ownership or unsupported manifest behavior
pauses or enters Needs Human. Preserve original uncertainty until reconciled; do not substitute a new attempt to
hide an unresolved one. Cancellation stops before the next effect and reports any already-running effect truthfully;
it never claims completed resources were rolled back.

Retain local Git after preparation/GitHub failure, both local/remote state after push failure, and prior effects
after registration/context failure. Offer explicit policy-permitted continuation, including completion without
publication, recording revised scope/consent without rewriting the original plan. Cleanup is a separate previewed,
confirmed, currently authorized and ownership-checked operation with complete impact. Recheck retained references,
process quiescence and exact resources; ambiguous or unrelated content is never deleted. Preserve history even
when a cleanup result is partial/uncertain. Never automatically delete directories, commits, remote repositories,
snapshots, registrations, profiles or context revisions as compensation.

Use the existing browser shell/authentication and component contracts. Expose real progress, retained resources,
failures and explicit retry/continue/cancel/cleanup choices with independent denied, unavailable and interrupted
states. TICKET-00103 supplies the completion receipt and Dashboard handoff; TICKET-00104 supplies optional help.
No mock success, second client state authority, automatic retry on reconnect, automatic Planning/Agent startup,
source release automation or general-purpose workflow engine is included.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Prepare a creation preview | N/A — read-only preflight | Current catalog/profile, inputs, paths, Runner and optional GitHub availability | N/A — a preview is not authorization | Complete bounded effect summary or actionable refusal |
| Confirm the exact operation | Confirm Create Project with current preview identity and public acknowledgement where needed | Current material facts and original command outcome | Committed attempt authorization/queue facts | One durable immutable plan and recoverable dispatch intent |
| Advance and observe creation | Admitted stage dispatch and expected-state outcome recording | Exact stage intent/result, current grant and safe progress | Committed confirmed stage/attempt facts | Only the next authorized stage; browser reads launch nothing |
| Recover or continue | Explicit original-attempt reconcile/retry or confirmed revised continuation | Completed effects, uncertainty, retained resources and current policy | Attributed recovery/hold/continuation facts | No duplicate effect; original plan/history preserved |
| Cancel or clean up | Cancel future effects; separate confirmed cleanup | Current execution, ownership, references and complete cleanup impact | Confirmed cancellation/cleanup facts, never inferred rollback | Retain completed effects unless exact cleanup is separately authorized |

## Validation and permissions

Require current server intent-specific creation/view/recovery/cleanup authority and real Workspace/Host/Runner
scope. Independently check stage-specific filesystem/preparation/source-host/registration authority; profile
selection and earlier confirmation cannot override revocation. Use expected revisions and shared resource fences,
strict bounded input and secret-safe errors. External effects stay outside business transactions under ADR 0002.

Apply ADR 0003/0004: protected reads/actions, volatile drafts, no-store safe responses, late-response/context fences
and no automatic replay after authentication refresh. Viewing a receipt or support suggestion grants no effect.
No credentials, source-host tokens, raw environment or hidden reasoning enter previews, events, logs or exports.

## Acceptance and evidence

- Complete real previews show all effects and public acknowledgement; material drift rejects stale confirmation.
  Duplicate confirmation creates no second attempt and unchanged ordinary stages need no repeated prompts.
- Controlled competing requests, restart/redelivery and uncertain process/provider outcomes preserve original
  identity and truthful partial states; no repeated directory/commit/remote/link/context effects.
- Cancellation/continuation and separately authorized cleanup preserve unrelated work and completed history.
  Browser disconnect, permission loss and stale responses cannot claim success, repeat work or leak private data.
- Production form/progress/recovery behavior has real browser keyboard/focus/responsive evidence and supports
  TICKET-00103's real integrated journey. Owned application/PostgreSQL/client tests and `./bin/build` pass;
  no tests of queue libraries, wrappers, configuration or a proof-only wizard.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 3 of EPIC-00009's six-TICKET split. Applies
[WF-021](../wayfinder/tickets/WF-021-define-new-project-creation-and-registration.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

[TICKET-00099](00099-TICKET.md) supplies immutable catalog inputs; [TICKET-00100](00100-TICKET.md),
[TICKET-00102](00102-TICKET.md) and [TICKET-00103](00103-TICKET.md) supply owned stage contracts. Define early
admission/effect/result primitives before final browser integration, not mutually blocked whole-TICKET journeys.
Reuse installation/Runner/authentication owners; creation is not a new prerequisite of terminal TASK execution.
Optional [TICKET-00104](00104-TICKET.md) assistance never gates this path. Exact TASKs remain separate; this approval
creates no attempt, consent grant, queue message, filesystem effect, cleanup, runtime or changed Board priority.
