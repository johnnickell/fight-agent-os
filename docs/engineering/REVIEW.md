# Review Standards

Review is an independent attempt to falsify an implementation's acceptance claims. It reports evidence and a verdict; implementation and landing remain separate work.

## Independence and target

Disclose whether the reviewer authored, directed, repaired, or supplied acceptance evidence for the target. Any material contribution prevents an independent verdict. Identify the approved TASK, exact base and head, working-tree state, and claimed evidence before judging the change. Ambiguous scope or provenance limits the verdict explicitly.

Use repository files and fresh command output as primary evidence. A summary, prior green receipt, generated status, or inherited test proves only what can be traced to the reviewed target.

## Coverage

Attempt to disprove every applicable area:

- **Scope:** acceptance is complete; exclusions and unrelated files remain untouched; planning claims match observable state.
- **Architecture:** dependency direction, Domain/Application/Adapter ownership, injected capability boundaries, and stated transaction or delivery guarantees hold.
- **CQRS and HTTP:** commands, queries, events, Actions, Responders, safe Views, validation, mapping, and error behavior keep their accepted responsibilities.
- **Security:** authorization is server-enforced; credentials, secrets, sensitive data, and diagnostics are handled safely; negative and abuse paths fail closed.
- **Behavior and tests:** success, rejection, failure, regression, and side effects are proved at the narrowest useful boundary without tests that merely mirror implementation.
- **Evidence:** commands are fresh and reproducible; counts and warnings are accurate; failures, skips, deprecations, unavailable checks, and uncertainty are disclosed.

Mark a coverage area not applicable only with a reason tied to the TASK and diff.

## Findings

A blocking finding is an acceptance, correctness, security, authorization, data-integrity, scope, or evidence defect that prevents trust in the claimed result. Rank it `critical`, `high`, `medium`, or `low` by plausible impact and reach.

A non-blocking finding is traceable improvement work that does not invalidate acceptance. A subjective preference is not a finding unless an approved standard or requirement establishes the expected behavior. A residual risk records an evidence limit or untested uncertainty without asserting a defect.

Every actionable finding names the affected requirement, reproducing evidence, expected and observed behavior, and required correction. Findings without traceable evidence do not support a `revise` verdict.

## Durable review handoff

Every completed review publishes its full report at `<base-worktree>/.runs/reviews/<TASK-ID>/review.md`; chat output is only a pointer and summary. Resolve the base from Git's canonical common directory and registered-worktree metadata by selecting the sole worktree whose Git directory is the common directory. Verify the selected top level. Do not assume the current checkout is primary, treat the first listed worktree as authority without validation, derive the base from a parent directory, or search outside registered worktrees.

The report starts with YAML front matter identified by `review_handoff_version: 1`. Its exact identity keys are `task`, `repository_common_directory`, `base_worktree`, `canonical_report`, `target_kind`, `target_identifier`, `target_branch`, `target_worktree`, `base_commit`, `head_commit`, `target_status`, `reviewed_content_digest`, and `verdict`. Use canonical absolute paths and full commit OIDs. Status is `clean` or the complete porcelain-v1 snapshot including untracked files. A dirty target includes a SHA-256 and documented deterministic manifest method covering every reviewed staged, unstaged, and untracked byte; only a clean target uses a null content digest. It then contains, in order, independence and scope, blocking findings, non-blocking findings, acceptance matrix, verification, evidence limitations, residual risks, and verdict. Identity, status, findings, evidence, limitations, and verdict must describe the same reviewed snapshot. Exclude secrets, credentials, private production data, and unrelated worktree content.

The report directory and files must remain within the ignored base `.runs/reviews/` root and must not traverse symlinks. Write a restrictive report-owned temporary regular file beside the final file, verify completeness, then atomically rename it over the stable `review.md`. Read the final bytes back and verify content, digest, ordered sections, identity, and verdict before responding. Keep an existing report intact until replacement succeeds. Any base/path/write/rename/read-back failure means no completed handoff exists: name the exact path and failure, preserve the implementation, and do not leave an actionable `accept` or `revise` verdict only in ephemeral output.

A successful final response prominently names the absolute canonical path and verdict. The persisted report is the review handoff consumed by later execution and landing sessions; it remains ignored scratch, not an authoritative planning record or committed artifact.

## Verdict

Return `revise` when any blocking finding remains or evidence is too incomplete to establish acceptance. Return `accept` only when every acceptance criterion is supported, applicable coverage has been challenged, warnings and limitations are disclosed, and no blocking finding remains.

Neither verdict marks planning complete or grants PR approval, landing, push, merge, release, or deployment authority.
