---
id: TICKET-00137
epic: EPIC-00024
title: Coordinate accepted TASK synchronization through PR refresh
status: done
---

# Coordinate accepted TASK synchronization through PR refresh

## Problem statement

Concurrent agents advance `develop` while independently accepted TASKs await human merge. Updating a stale PR
currently requires manually coordinating base integration, conflict resolution, verification and Land. Repeating
Review and QA for integration-only changes adds ceremony without delivering new behavior.

Deliver one `/skill:sync TASK-NNNNN` journey that brings the selected accepted TASK branch current, retains both
sets of intended behavior, verifies the integrated result and refreshes its existing PR.

## Solution and boundaries

Implement the approved [EPIC-00024](../epics/00024-EPIC.md) as a local Pi coordinator similar to Build. Keep
coordination separate from source mutation: delegate integration to a fresh Engineer context and delivery to a
fresh Land context, using existing qualified dispatch, role custody, observation and resource procedures.

### Accepted input and authority

- Bind one TASK, repository, selected checkout/feature branch, original accepted candidate, current branch/base
  and existing open PR. Reuse the operator's checkout choice and ask only when missing. Preserve unrelated
  changes, other agents' active work and genuine resource protections.
- Require current independent Review ACCEPT and applicable QA PASS or justified criterion-specific N/A covering
  the work being synchronized. Inspect intervening changes and existing integration/administrative bridges;
  missing evidence or a newer failure cannot be replaced by an older pass. Original reports retain their bytes
  and actual acceptance subjects.
- Explicit Sync invocation authorizes the bounded integration, owned commit, gate execution, automatic Land,
  non-force update of the existing PR and normal eligible cleanup. Honor narrower instructions. Do not derive
  authority from prompts, pane labels or subprocess names.

### Integration-only fast path

- Fetch and merge current `origin/develop` into the selected feature branch. Do not rebase or rewrite published
  history. Retain Land's repository/base-policy checks and the PR's identity; never silently retarget it.
- Engineer reads incoming changes and the TASK's accepted intent, examines relevant interactions even without
  conflict markers, and resolves conflicts to retain both intended behaviors. Blind whole-side selection is
  not conflict resolution. Regenerate derived planning views from authoritative records and preserve
  independently authored changes.
- Inspect the integrated diff, briefly explain nontrivial resolutions and retain old/new base/head identities
  in ordinary ignored handoff evidence. No new proof machinery or independent self-acceptance is required.
- Do not routinely launch Review or QA. A code conflict alone does not trigger another acceptance cycle. Land
  checks the integration scope and evidence, not a disguised full Review/QA assessment.
- Stop for incompatible intent needing a human decision, a discovered defect, failed verification or genuinely
  new behavior beyond accepted work. Identify the concrete blocker and its owning action. New implementation
  requires the normal repair/verification/independent-acceptance route; do not silently repair unrelated defects
  or turn a green build into blanket acceptance. Missing intake authority, identity or prior evidence also holds
  advancement.

### Verification, delivery and partial outcomes

- Commit the integrated candidate and run useful focused checks plus normal `./bin/build`. Automatic verified
  receipt reuse is sufficient, including planning-only integration. Do not force application phases solely
  because HEAD changed. Record fresh checks, reused evidence provenance, warnings and unverified prerequisites.
- Proceed directly to Land in the same invocation when integration and verification succeed. No second manual
  Land command or routine approval prompt is required. Land can reuse qualifying gate evidence while retaining
  applicable required hosted/final-head checks.
- Land updates the existing PR by ordinary non-force push, verifies the actual open PR/base/head/remote OID and
  required delivery evidence, and performs applicable closeout and eligible owned cleanup through its existing
  procedure. Preserve active consumers, main data, unique work and shared resources.
- Distinguish integration, publication, cleanup and human merge. After interruption or partial publication,
  inspect actual local/remote/process state and resume only missing authorized effects; do not blindly repeat
  publication, relaunch an uncertain worker or manufacture another commit/PR. Return a finite concrete action
  when blocked. An ended PR needs its existing owner route, not automatic replacement.

Out of scope: unrelated repairs, requirement changes, new features, speculative refactoring, routine Review/QA
reruns of synchronized work, rebase/history rewriting, force-push, PR approval/merge, release/deployment,
remote-branch deletion, other-TASK sweeps, managed/database/browser workflow expansion and default-checkout
fast-forward synchronization. Do not introduce a parallel execution engine, role launcher, resource registry,
permanent delivery queue or routine post-merge planning maintenance.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Admit one accepted TASK | Explicit `/skill:sync TASK-NNNNN` | TASK/checkout/branch, current independent reports, intervening diff, base and existing PR identity | Existing local coordination evidence only | Bound integration authority or precise missing prerequisite; no source changes on refused intake |
| Integrate the updated base | Existing Git fetch/merge through Engineer; owning planning refresh when needed | Incoming and TASK intent, interactions, conflict hunks and effective diff | Existing integration handoff only | Owned integrated commit retaining both intents, or concrete blocker without scope expansion |
| Verify the candidate | Useful focused checks and normal `./bin/build` | Integrated HEAD, gate result, fresh/reused receipt identity and warnings | Existing check receipts only | Qualified verification or a finite failed/incomplete handoff; no automatic Review/QA stage |
| Refresh the existing PR | Existing Land entry and publication/cleanup tools | Exact live PR/base/head/remote OID, required checks and native resource state | Existing delivery/cleanup evidence only | Verified non-force PR update and eligible cleanup, then actual human review/merge action |
| Reconcile partial execution | Owning workflow's bounded resume/recovery | Original child/effect outcome, current Git/hosting/process state and remaining authority | Existing recovery/stop evidence only | Only missing authorized effects continue; uncertain or ended subjects remain explicitly held |

## Validation and permissions

This is trusted local workflow coordination, not a server-managed grant or an OS isolation boundary. New
application API endpoints, database permissions, migrations and Domain events are N/A; existing local role
selection and explicit invocation govern effects. Queries inspect identity/evidence and do not themselves grant
integration or publication authority. Keep credentials, private source and raw worker dialogue out of public
handoffs; use ignored `.runs/` evidence and existing sanitized publication conventions.

Reconcile participating Work/Build/Land/Review/QA instructions with the approved integration-only boundary where
they actually govern Sync. Preserve independent acceptance for genuinely new implementation and retain existing
Build's ordinary delivery sequence. This is not a general exemption from Review/QA or permission to rewrite
canonical reports. The new Sync implementation itself still requires normal independent implementation Review
and applicable QA; the fast path applies to its already-accepted input TASKs.

## Acceptance and evidence

- **AC-01 — Correct intake:** Real invocation/discovery binds one intended TASK and selected checkout with
  current independent acceptance and an existing open PR. Missing/contradictory identity, newer failure,
  unreviewed intervening behavior or missing authority stops the affected effects without guessing.
- **AC-02 — Real role progression:** Fresh Engineer then Land contexts use the existing qualified role machinery;
  the coordinator does not repair source. Integration-only runs launch no routine Review/QA children and require
  no second Land invocation. Missing dispatch capability is disclosed, not bypassed by fabricated authority.
- **AC-03 — Both intents retained:** Direct qualification covers a clean merge, meaningful overlapping code and
  authored/generated planning conflicts. Inspect incoming/TASK intent and the effective result, preserve both,
  regenerate derived views and retain original acceptance reports. Conflict markers alone do not decide reuse.
- **AC-04 — Concrete stop boundaries:** Incompatible intent, discovered defects, new behavior and failed checks
  cannot proceed as successful Sync. Evidence names the real decision/repair/prerequisite and does not claim new
  independent acceptance or silently broaden scope.
- **AC-05 — Normal gate reuse:** Execute ordinary `./bin/build` on the integrated committed candidate. Qualify
  both changed-input execution and valid planning-only application reuse, preserving fresh/reused distinctions,
  warnings and applicable required hosted/final-head delivery gates.
- **AC-06 — Exact PR delivery:** Exercise automatic Land against the existing PR with ordinary push and exact
  live candidate verification. No force-push, duplicate/replacement PR, automatic approval or merge occurs.
- **AC-07 — Honest partial outcomes:** Exercise blocked/interrupted/partial cases and eligible cleanup through
  their owning tools. Preserve completed effects and protected/shared resources; distinguish publication from
  cleanup and return finite remaining actions rather than blindly retrying or claiming complete delivery.
- **AC-08 — Proportionate evidence:** Qualify actual workflow/tool behavior directly and distinguish instruction
  walkthroughs from real role/Git/gate/PR effects. Do not add product-suite tests of skill prose, planning or build
  machinery. Acceptance of Sync's implementation includes its normal independent Review and applicable QA.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00587](../tasks/00587-TASK.md) | Deliver local Sync from accepted TASK to refreshed PR | done |
<!-- /planning:children -->

## Decisions and progress

The human approved one cohesive TICKET for EPIC-00024: intake, integration, normal gate execution/reuse, automatic
existing-PR refresh and blocked/partial outcomes belong to the same delivery journey. There is no Wayfinder
map-wide planning dependency. No TASK decomposition or implementation is authorized by this record alone.

Reuse [TICKET-00084](00084-TICKET.md)'s local Build coordination and the currently qualified dispatch/observation
paths owned by [TICKET-00130](00130-TICKET.md), plus existing Land/resource procedures. Do not reopen completed
owners or duplicate their mechanisms. Qualification must establish the actual supported path; it does not claim
the unfinished all-role SDK cutover is delivered or require an unrelated cutover before defining Sync.

Managed review/publication remains [TICKET-00083](00083-TICKET.md)'s separate scope; designated default-checkout
synchronization remains [TICKET-00109](00109-TICKET.md)'s separate scope. No new PHP application architecture,
persistence or browser contract is introduced, so no new application ADR is needed for this local journey.

TICKET decomposition is approved and written in full for this standalone EPIC.

### Approved TASK decomposition and current handoff

The human approved one complete [TASK-00587 — Deliver local Sync from accepted TASK to refreshed PR](../tasks/00587-TASK.md),
covering AC-01–AC-08 in one implementation PR. No blockers were identified; the TASK remains unranked and does
not reorder the existing Board. Workflow admission/dispatch integration, instruction alignment and actual direct
qualification belong to that same TASK, not separate scaffolding or qualification-only records.

This TICKET's TASK decomposition is approved and written in full. Its sole child TASK-00587 has independent
Review ACCEPT and QA PASS on the candidate named in its accepted implementation checkpoint. The complete
AC-01–AC-08 outcome is accepted; automatic parent completion closes this TICKET with the child. Publication,
eligible cleanup and human merge remain separate in the TASK's owning delivery receipt and actual PR. This
does not adopt a shared installation or complete the all-role SDK cutover. Consult the TASK Board for work
selection rather than starting work as a planning side effect.
