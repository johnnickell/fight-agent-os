---
name: work
description: Implement or revise an approved TASK on a feature branch, verify it, and hand the committed work to independent review.
---

# Work

Read the TASK and parent, [engineering standards](../../../docs/engineering/STANDARDS.md) and [execution standards](../../../docs/engineering/EXECUTION.md). Work owns implementation, not independent acceptance or publication.

1. **Bound and place.** Check accepted scope, blockers, Board, branch, ancestry and status. Preserve unrelated changes. Ask whether to use the main checkout or an isolated worktree unless chosen. For a new TASK use `feature/task-NNNNN-<slug>` from `develop`; preserve a pre-existing authorized TASK branch and keep scratch under `.runs/`.
2. **Read the handoff.** Resolve the canonical base worktree through Git metadata and inspect `.runs/reviews/<TASK-ID>/review.md`. A first implementation may have none; a revision requires the canonical `revise` report and traces every blocker. Version-2 and version-3 reports are usable when their target identity and recorded snapshot match. If the branch has moved, trace changes to know whether findings remain applicable. An accepted implementation routes to `land`, which may reconcile an advancing base; do not use `work` solely to rebase an unchanged accepted implementation.
3. **Implement and reconcile before review.** Follow the TASK's dependencies and [engineering standards](../../../docs/engineering/STANDARDS.md): identify package versus owned use cases, place Domain policy with its owner and transport mapping with its adapter, and keep server authority across entry paths. Add behavior and meaningful success/rejection/failure tests at the narrowest useful boundary; do not manufacture routes or tests of tools to satisfy a checklist. Internally check each TASK criterion against both the Spec and Standards IDs in [review standards](../../../docs/engineering/REVIEW.md), recording evidence and N/A reasons without issuing an independent verdict or formal scores. Integrate current `develop` when needed, regenerate planning views from authoritative records, inspect conflicts and effective changes, and preserve other TASKs' work. If a conflict changes behavior or scope, record it explicitly for review. Never force-update a published branch.
4. **Verify and record.** Run focused checks, `./bin/planning-check` when planning changed, `git diff --check`, and `./bin/build`. Inspect the final diff/status. Record actual results, counts, warnings, omissions, files changed and remaining risks in the TASK; keep review and PR status truthful.
5. **Commit and hand off.** Stage only owned files, inspect staged changes, and commit. Return branch/head, scope, evidence, risks and unrelated work preserved; request independent review. Stop before push, PR, approval or merge without separate authority.

A failing required check or unsupported criterion is incomplete work, not a green handoff.
