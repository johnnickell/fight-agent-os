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
4. Confirm review authority is read-only over implementation and planning files. It may write only review-owned scratch plus canonical and immutable history reports beneath the base worktree's ignored `.runs/` root.
5. Resolve the base worktree from Git metadata: make `git rev-parse --path-format=absolute --git-common-dir` canonical, enumerate only `git worktree list --porcelain`, and select the sole registered worktree whose canonical absolute Git directory equals that common directory. Verify its top level and reject a bare repository, no match, multiple matches, malformed metadata, or an untrusted path. Never infer the base from the current directory, a parent path, or worktree-list ordering.
6. Set the canonical latest path to `<base-worktree>/.runs/reviews/<TASK-ID>/review.md` and immutable sibling history paths to `review-<NNNNN>.md`, using five-digit positive sequences. Verify the base identity, that `.runs/` is ignored, and that the review root, TASK directory, final files, and temporary files cannot escape through symlinks.
7. Enumerate that one directory without following links. Accept only a contiguous numbered history and a canonical file byte-identical to its highest entry. If only a pre-sequence `review.md` exists, preserve it unchanged as `review-00001.md` before a later completed publication; otherwise reject gaps, malformed review names, mismatches, and interrupted publications. The next sequence is therefore exact, never inferred from chat or worktree search.

Start only when scope, target, baseline, claims, local state, independence, base worktree, canonical path, and sequence state are explicit. Otherwise stop or state exactly how the verdict is qualified.

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

Build the complete report before returning a verdict. Begin with YAML front matter using `review_handoff_version: 2` and the exact keys `review_sequence`, `task`, `repository_common_directory`, `base_worktree`, `canonical_report`, `history_report`, `target_kind`, `target_identifier`, `target_branch`, `target_worktree`, `base_commit`, `head_commit`, `target_status`, `reviewed_artifact_count`, `reviewed_content_digest`, and `verdict`. `review_sequence` is the positive integer encoded by `history_report`; both report paths are canonical absolute paths. Use full OIDs and canonical absolute paths; use `null` only for an inapplicable worktree or branch. `target_status` is `clean` or the complete `git status --porcelain=v1 --untracked-files=all` snapshot.

Identity covers all reviewed state outside `HEAD`, including explicitly reviewed ignored artifacts. Within section 1, include a `Reviewed snapshot manifest` fenced block of canonical JSON Lines whenever the target is dirty or `reviewed_artifact_count` is nonzero. Record every staged/index version, unstaged or untracked worktree version, and every selected ignored root and descendant. Each line has keys in this order: `domain`, `path`, `root`, `type`, `mode`, `size`, `sha256`; use domains `index`, `worktree`, or `external`, normalized target-relative UTF-8 paths with `/`, booleans only for selected external roots, types `regular`, `directory`, `symlink`, or `absent`, lower-case octal modes, byte sizes, and SHA-256 of regular-file bytes or symlink-target bytes. Do not follow symlinks. Include directories and absence records with null size/digest, sort by domain in the stated order and then path UTF-8 bytes, reject escaping, duplicate, non-UTF-8, or special-file entries, and count selected external roots in `reviewed_artifact_count`. `reviewed_content_digest` is SHA-256 of the exact newline-terminated canonical JSON Lines bytes; it is `null` only when status is clean and no ignored artifact is reviewed. Re-enumerate roots so additions, removals, type/mode changes, and content changes alter identity.

Then use this order:

1. **Independence and scope** — reviewer relationship, target, baseline, status, and reviewed snapshot manifest.
2. **Blocking findings** — ordered by severity, or `None`.
3. **Non-blocking findings** — or `None`.
4. **Acceptance matrix** — each criterion with evidence and disposition.
5. **Verification** — fresh commands, exit results, counts, warnings, and skips.
6. **Evidence limitations** — unavailable, historical, inherited, or inconclusive proof, or `None`.
7. **Residual risks** — or `None`.
8. **Verdict** — exactly `accept` only with no blocking finding and sufficient evidence; otherwise exactly `revise`.

Publish only the complete report:

1. Create the TASK report directory safely with restrictive defaults and revalidate its sequence state. For the one-time legacy case, atomically create `review-00001.md` without replacement from the existing complete `review.md`, verify identical bytes, and assign the new report sequence 2. Never rewrite or remove a numbered report.
2. Write a report-owned temporary regular file in that same directory with mode `0600`, close it successfully, and verify its bytes contain the version-2 identity, manifest when required, all eight ordered sections, findings, evidence, verification, limitations, risks, and the same final verdict.
3. Publish those exact bytes atomically and without replacement to `history_report`, then atomically replace `review.md`. Read the final canonical and history regular non-symlink files back; require byte equality and repeat the completeness, identity, sequence, manifest, and verdict checks. A publication is complete only after both names validate.
4. On any discovery, path, sequence, creation, write, close, publication, rename, or read-back failure, preserve the target, every immutable history entry, and any prior canonical report when still present; remove only a provably owned temporary file. State `Review handoff incomplete` with the exact paths and reason. A canonical/history mismatch is an interrupted publication and must fail closed rather than selecting either file. Do not return `accept` or `revise` as an actionable completed verdict only in chat.
5. On success, make the final response prominently state the verdict, sequence, absolute canonical report path, and immutable history path so another session can open them directly.

Stop after the persisted report handoff. Review does not authorize fixes, planning finalization, approval, landing, push, merge, archive, release, or deployment.
