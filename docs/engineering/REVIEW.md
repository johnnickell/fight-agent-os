# Review Standards

Review is an independent attempt to disprove a TASK's acceptance claims. It reports findings and evidence; implementation, publication, and merge are separate.

## Target and independence

Read the TASK, its accepted decisions, the diff against the identified base, current status, and claimed tests. Disclose whether the reviewer authored, directed, repaired, or supplied evidence for the implementation. A material contributor cannot independently accept it. Include staged, unstaged, and untracked target changes in the reviewed scope; a clean committed target is preferable for a landing handoff.

A review of a rebased branch challenges the new base and effective diff, not just the old patch. A prior report may remain applicable through a **proven mechanical reconciliation** under [landing standards](LANDING.md); changed commit IDs alone neither prove a defect nor require another review. Substantive behavior changes or uncertain integration require a new independent review.

## Two named passes

Independently challenge the exact TASK, accepted decisions, exclusions, effective diff and claimed evidence in **Spec** and **Standards** passes. One independent reviewer may perform both passes; they are not two independent agents. A builder's two internal completeness checks are never independent approval. Preserve the criterion IDs below across revision rounds; map each TASK acceptance criterion to at least one applicable ID and cite the specific requirement and observed evidence. Do not substitute a green build for semantic review.

| Spec ID | Question to disprove |
|---|---|
| SP-01 | Does the delivered use case satisfy each accepted outcome and stay inside its scope? |
| SP-02 | Are validation, rejection and authorization accounted for at all exposed entry paths, including target/ownership checks where applicable? |
| SP-03 | Do commands, queries, events, state changes and external side effects match the use-case contract? |
| SP-04 | Are failure, compatibility, recovery and security/secret-safety requirements met where applicable? |
| SP-05 | Do tests, direct checks and fresh verification actually establish every claimed acceptance outcome and limitation? |

| Standards ID | Question to disprove |
|---|---|
| ST-01 | Are Domain knowledge, package ownership, Application coordination, dependency direction and injection placed correctly? |
| ST-02 | Do HTTP/other adapters preserve transport scope, safe Views, mapping and server authority rather than recreate policy? |
| ST-03 | Are naming, PHP style, documentation and applicable local conventions followed? |
| ST-04 | Do tests prove meaningful owned behavior and important integration contracts at proportionate seams without testing tooling? |
| ST-05 | Are planning, delivery, verification, warnings, resource hygiene and review/merge boundaries accurate? |

For **each** ID record `Pass`, `Fail`, `Unverified`, or `N/A`, with an exact location, command/result or traceable evidence and any limitation. `N/A` needs a reason grounded in scope; silence or missing proof is not N/A. `Unverified` means necessary evidence is missing, not that a defect has been demonstrated. `Fail` needs a demonstrated violation. Account for all TASK criteria even when a checklist category contains several. A documentation-only change need not invent runtime paths or product tests; direct inspection, links, planning validation and the required gate can establish its claims.

Check server authority, secret handling, success/rejection/failure, persistence/transaction effects and protocol compatibility only where relevant, without inventing new policy or endpoints. Follow [engineering standards](STANDARDS.md) and the [ownership ADR](../../planning/adr/0001-application-ownership-and-orchestration.md); a passing dependency checker cannot prove package or business-policy ownership. Inspect actual diff and directly affected consumers, not unrelated debt. Challenge unsupported claims at the narrowest useful boundary; run the canonical gate when acceptance depends on it. Surface actual counts, warnings, skips and unverified areas. Do not fix implementation or change planning status while reviewing.

## Findings and verdict

A blocking finding is a traceable acceptance, correctness, security, data-integrity, scope, or evidence defect. Give severity (`critical`, `high`, `medium`, `low`), affected criterion, reproducible evidence, expected versus observed behavior, and correction. Separate non-blocking improvements from unproved residual risks. `accept` requires **Pass on every applicable Spec and Standards criterion**, every TASK acceptance criterion accounted for, and no blockers; any Fail or Unverified requires `revise`. Do not average away a missing requirement or score a N/A as a pass. Findings must identify which pass/ID and TASK criterion they affect. An independent review does not approve a PR or authorize merge.

## Durable handoff

Publish the complete report at `<base-worktree>/.runs/reviews/<TASK-ID>/review.md`; chat is only a pointer. Resolve the base via Git's common directory and registered worktrees, and keep the ignored report directory within that base without following symlinks. Existing numbered `review-<NNNNN>.md` files are historical evidence: preserve them, but do not create new sequence entries. Replace only the canonical report, using a temporary file in the same directory and atomic rename. If writing or read-back fails, report an incomplete handoff and do not return an actionable chat-only verdict.

New reports use this small header:

```yaml
---
review_handoff_version: 3
task: TASK-NNNNN
target_branch: feature/example
base_commit: <full Git OID>
head_commit: <full Git OID>
target_status: clean
verdict: accept
---
```

`target_status` is `clean` or the full `git status --porcelain=v1 --untracked-files=all` snapshot (use a YAML block scalar for multiple lines). For dirty reviews, identify each reviewed file version by path, state and digest or retained patch so a later session can detect drift; identify any selected ignored evidence even when Git status is clean. Include reviewer relationship, exact target/base, separate Spec and Standards tables with all stable IDs, TASK-criterion-to-evidence mapping, findings (or `None`), fresh commands/results, limitations, risks, and final verdict in the body. Read the canonical report back before handoff.

Older version-2 reports remain valid handoffs; read their canonical file and verify its declared branch, base/head, status, verdict, and any recorded snapshot against the target. Keep existing immutable history untouched. Only the canonical `review.md` supplies the latest verdict; never pick an older numbered file to override it.
