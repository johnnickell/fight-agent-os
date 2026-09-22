---
name: land
description: Use after independent acceptance to finalize one TASK, verify the final tree, publish only explicitly authorized work, clean only ownership-proven resources, and hand merge control to a human.
---

# Land

Close reviewed implementation without re-reviewing or merging it. Read the project [landing standards](../../../docs/engineering/LANDING.md) before starting; use the linked execution and review boundaries when qualifying evidence.

## 1. Establish the landing target

1. Read the TASK, parent TICKET, accepted decisions, Board, completion claims, and independent review report.
2. Inspect repository identity, feature branch and `develop` ancestry, reviewed base/head, status, remote branch, and existing PR.
3. Inventory unrelated changes and candidate resources without modifying them.
4. Name the separately granted authority for planning edits, a landing commit, push, PR creation/update, and each cleanup operation.

Stop for ambiguous scope, target, local state, ownership, or authority. An implementation request does not imply publication or cleanup permission.

## 2. Qualify review and evidence

Verify that the report discloses independence, TASK, exact baseline and target, status, checks, limitations, findings, and an `accept` verdict. Reconcile every blocking and non-blocking finding with an explicit disposition. Confirm the accepted target is current and acceptance evidence is complete.

Do not inspect implementation to issue another verdict or repair a finding. Missing review, unresolved blockers, stale target evidence, failed checks, or contradictory claims require refusal and a handoff to `review` or `work`.

## 3. Finalize the TASK

1. Run the required focused checks, `./bin/planning-check`, `git diff --check`, and `./bin/build` to obtain fresh results and counts before asserting completion.
2. Record those actual commands, results, and counts with the acceptance evidence, review and finding dispositions, warnings, risks, publication state, and cleanup decisions in the TASK.
3. Set the TASK to `done` only when implementation acceptance and required verification are supported, then run `./bin/planning-check --write` and inspect the generated changes.
4. Rerun the focused checks, `./bin/planning-check`, `git diff --check`, and final `./bin/build` after the authoritative record update.
5. Inspect the complete diff/status, stage only owned paths, inspect the staged diff, and create an authorized landing commit.

A failed or skipped required check blocks publication. If a rerun changes a recorded result, update the TASK and repeat the final checks before commit. Keep planning truthful as directed by the landing standards; never present an earlier receipt as final-tree evidence.

## 4. Publish only what was authorized

Restate network authority before using it. Push only the intended feature branch without force, then create or update only its PR against the accepted base. If push or PR authority is absent, stop with the local commit preserved. Never approve or merge the PR.

## 5. Clean only proved ownership

Build and preview an exact cleanup set. For each path, worktree, process, database, container, or volume, verify the resource-specific ownership evidence required by the landing standards and confirm its contents or state are disposable. Apply only explicitly authorized cleanup with exact identifiers.

Preserve active checkouts, uncommitted work, source, required evidence, shared services, durable records, and ambiguous resources. Broad cleanup and forced deletion are prohibited.

## 6. Return human control

Report TASK, branch and commits, review target/verdict and finding dispositions, final checks and counts, warnings and risks, push/PR effects or missing authority, every cleanup decision, unrelated work preserved, and retained human actions.

Distinguish TASK completion, commits, push, PR handoff, human approval/merge, archive, release, deployment, and certification. Stop before approval, merge, archive, release, deployment, or certification unless a separate workflow and explicit authority grant that action.
