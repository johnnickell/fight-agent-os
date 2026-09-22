---
name: land
description: Use after independent acceptance to finalize one TASK, publish its pull request, clean ownership-proven resources, and hand the PR link and merge control to a human.
---

# Land

Close reviewed implementation without re-reviewing or merging it. Read the project [landing standards](../../../docs/engineering/LANDING.md) before starting; use the linked execution and review boundaries when qualifying evidence.

## 1. Establish the landing target

1. Read the TASK, parent TICKET, accepted decisions, Board, completion claims, and independent review report.
2. Inspect repository identity, feature branch and `develop` ancestry, reviewed base/head, status, authenticated remote/hosting access, remote branch, and existing PR.
3. Inventory unrelated changes and preview the exact worktree, ignored build output, container, volume, process, database, and scratch cleanup candidates without modifying them.
4. Confirm the landing session runs outside any isolated target worktree it must remove. If the target is the active checkout, stop before mutation and require `land` to be rerun from another checkout.

Explicit `/skill:land TASK-NNNNN` invocation grants bounded authority to finalize that TASK's planning, create landing commits, push its named feature branch without force, create or update its PR against the accepted base, and remove its clean isolated worktree plus exact ownership-proven TASK resources. It never grants approval, merge, remote-branch deletion, archive, release, deployment, certification, broad pruning, shared teardown, or ambiguous deletion.

Stop before mutation for ambiguous scope, target, local state or ownership, dirty cleanup candidates, missing credentials, or unavailable remote/hosting operations. A successful landing may not degrade to a local-only handoff.

## 2. Qualify review and evidence

Verify that the report discloses independence, TASK, exact baseline and target, status, checks, limitations, findings, and an `accept` verdict. Reconcile every blocking and non-blocking finding with an explicit disposition. Confirm the accepted target is current and acceptance evidence is complete.

Do not inspect implementation to issue another verdict or repair a finding. Missing review, unresolved blockers, stale target evidence, failed checks, or contradictory claims require refusal and a handoff to `review` or `work`.

## 3. Finalize the TASK

1. Run the required focused checks, `./bin/planning-check`, `git diff --check`, and `./bin/build` to obtain fresh results and counts before asserting completion.
2. Record those actual commands, results, and counts with the acceptance evidence, review and finding dispositions, warnings, risks, pending publication, planned cleanup, and retained human actions in the TASK.
3. Set the TASK to `done` only when implementation acceptance and required verification are supported, then run `./bin/planning-check --write` and inspect the generated changes.
4. Rerun the focused checks, `./bin/planning-check`, `git diff --check`, and final `./bin/build` after the authoritative record update.
5. Inspect the complete diff/status, stage only owned paths, inspect the staged diff, and create the landing commit.

A failed or skipped required check blocks publication. If a rerun changes a recorded result, update the TASK and repeat the final checks before commit. Keep planning truthful as directed by the landing standards; never present an earlier receipt as final-tree evidence.

## 4. Publish the complete human-review handoff

1. Push only the intended feature branch without force, then create or update only its PR against the accepted base.
2. Capture the canonical PR URL. Record it in the TASK and every generated planning view, record actual publication state, run `./bin/planning-check --write`, and inspect the changes.
3. Rerun focused checks, `./bin/planning-check`, `git diff --check`, and `./bin/build`; commit only the PR metadata and resulting truthful evidence, then push again without force.
4. Query the hosting service and verify the PR is open, has the accepted base and feature head, and its remote head equals final local `HEAD`.

Any authentication, push, PR, metadata, verification, or final-push failure blocks cleanup and successful landing. Preserve the worktree and report the exact failure. Never approve or merge the PR.

## 5. Clean proved ownership after publication

Use the preview from intake and recheck state immediately before cleanup. Remove only exact TASK-owned containers and volumes proven by Compose project, labels, or IDs; never prune globally or stop shared services. Preview ignored files inside the target worktree and remove only inspected disposable build output. If containerized checks created root-owned ignored output, an ephemeral helper may mount only the exact target worktree and remove only those approved paths; record its image, mount, and paths.

Require the isolated worktree to remain registered, clean, non-active, and at the verified published commit. Remove it without force, then verify registration and path removal. Retain the local feature branch for the human until merge. Preserve dirty/current/unregistered worktrees, source, required evidence, shared or durable resources, and anything ambiguous; report refusal as a landing failure rather than weakening cleanup.

## 6. Return human control

Report TASK, branch and commits, review target/verdict and finding dispositions, final checks and counts, warnings and risks, final remote-head verification, every cleanup result/refusal, unrelated work preserved, and retained human actions. Present the canonical PR URL as a clickable link.

Distinguish TASK completion, commits, push, PR handoff, human approval/merge, local/remote branch deletion, archive, release, deployment, and certification. Stop before approval or merge; those remain human actions.
