---
name: review
description: Use for independent adversarial review of an implemented TASK or PR against its requirements, actual diff, tests, and evidence, returning accept or revise without fixing the work.
---

# Review

Attempt to disprove implementation acceptance. Read the project [review standards](../../../docs/engineering/REVIEW.md) before starting; consult the shared [engineering standards](../../../docs/engineering/STANDARDS.md) for application changes.

## 1. Frame the review

1. Read the TASK, parent TICKET, accepted decisions, and claimed completion evidence.
2. Name the target and exact baseline: local commits, branch range, or PR head/base. Inspect current status and include uncommitted or untracked target files explicitly.
3. Disclose any contribution to the implementation or its evidence. A contributor may provide self-review observations but cannot issue an independent acceptance verdict.
4. Confirm review authority is read-only over implementation and planning files. It may write only review-owned scratch and the canonical report beneath the base worktree's ignored `.runs/` root.
5. Resolve the base worktree from Git metadata: make `git rev-parse --path-format=absolute --git-common-dir` canonical, enumerate only `git worktree list --porcelain`, and select the sole registered worktree whose canonical absolute Git directory equals that common directory. Verify its top level and reject a bare repository, no match, multiple matches, malformed metadata, or an untrusted path. Never infer the base from the current directory, a parent path, or worktree-list ordering.
6. Set the canonical report path to `<base-worktree>/.runs/reviews/<TASK-ID>/review.md`. Verify the base identity, that `.runs/` is ignored, and that the review root, TASK directory, final file, and temporary file cannot escape through symlinks.

Start only when scope, target, baseline, claims, local state, independence, base worktree, and canonical path are explicit. Otherwise stop or state exactly how the verdict is qualified.

## 2. Build the acceptance matrix

Map every acceptance criterion to the changed behavior, tests, and claimed evidence. Inspect the actual diff and status rather than relying on summaries. Mark unsupported criteria for adversarial testing.

The matrix is complete when every requirement has supporting evidence or a named gap.

## 3. Challenge the implementation

Apply every applicable coverage area in the review standards. Trace behavior across boundaries, inspect tests and negative paths, and run fresh focused checks. Run the canonical gate when acceptance depends on it; identify output that is historical, inherited, skipped, unavailable, or inconclusive.

Preserve the target and unrelated work. Report defects without repairing implementation, changing planning status, or extending scope.

## 4. Classify findings

For each finding provide:

- severity and blocking status;
- affected requirement;
- reproducible evidence with file/line, diff, or command output;
- expected versus observed behavior;
- the correction required for acceptance.

Keep preferences non-blocking unless an approved requirement makes them mandatory. Record unproved uncertainty as residual risk, not as a confirmed defect.

## 5. Publish and return the verdict

Build the complete report before returning a verdict. Begin with YAML front matter using `review_handoff_version: 1` and the exact keys `task`, `repository_common_directory`, `base_worktree`, `canonical_report`, `target_kind`, `target_identifier`, `target_branch`, `target_worktree`, `base_commit`, `head_commit`, `target_status`, `reviewed_content_digest`, and `verdict`. Use full OIDs and canonical absolute paths; use `null` only for an inapplicable worktree or branch. `target_status` is `clean` or the complete `git status --porcelain=v1 --untracked-files=all` snapshot. For dirty targets, `reviewed_content_digest` is a SHA-256 over a documented deterministic manifest of every reviewed staged, unstaged, and untracked byte; use `null` only for clean targets. Then use this order:

1. **Independence and scope** — reviewer relationship, target, baseline, and status.
2. **Blocking findings** — ordered by severity, or `None`.
3. **Non-blocking findings** — or `None`.
4. **Acceptance matrix** — each criterion with evidence and disposition.
5. **Verification** — fresh commands, exit results, counts, warnings, and skips.
6. **Evidence limitations** — unavailable, historical, inherited, or inconclusive proof, or `None`.
7. **Residual risks** — or `None`.
8. **Verdict** — exactly `accept` only with no blocking finding and sufficient evidence; otherwise exactly `revise`.

Publish only the complete report:

1. Create the TASK report directory safely with restrictive defaults; preserve any existing `review.md` until replacement succeeds.
2. Write a report-owned temporary regular file in that same directory, close it successfully, and verify its bytes contain the identity block, all eight ordered sections, findings, evidence, verification, limitations, risks, and the same final verdict.
3. Rename the temporary file to `review.md` atomically within the directory. Read the final file back, compare it with the completed temporary content or digest, and repeat the completeness and identity checks against the final bytes.
4. On any discovery, path, creation, write, close, rename, or read-back failure, preserve the target and prior report, remove only a provably owned temporary file, and state `Review handoff incomplete` with the exact canonical path and reason. Do not return `accept` or `revise` as an actionable completed verdict only in chat.
5. On success, make the final response prominently state the verdict and absolute canonical report path so another session can open it directly.

Stop after the persisted report handoff. Review does not authorize fixes, planning finalization, approval, landing, push, merge, archive, release, or deployment.
