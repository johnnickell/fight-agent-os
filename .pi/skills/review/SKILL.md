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
4. Confirm review authority is read-only except for review-owned scratch beneath `.runs/`.

Start only when scope, target, baseline, claims, local state, and independence are explicit. Otherwise stop or state exactly how the verdict is qualified.

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

## 5. Return the verdict

Use this order:

1. **Independence and scope** — reviewer relationship, target, baseline, and status.
2. **Blocking findings** — ordered by severity, or `None`.
3. **Non-blocking findings** — or `None`.
4. **Acceptance matrix** — each criterion with evidence and disposition.
5. **Verification** — fresh commands, exit results, counts, warnings, and skips.
6. **Evidence limitations** — unavailable, historical, inherited, or inconclusive proof, or `None`.
7. **Residual risks** — or `None`.
8. **Verdict** — `accept` only with no blocking finding and sufficient evidence; otherwise `revise`.

Stop at the report. Review does not authorize fixes, planning finalization, approval, landing, push, merge, archive, release, or deployment.
