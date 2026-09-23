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

Every completed review publishes its full report at `<base-worktree>/.runs/reviews/<TASK-ID>/review.md` and retains the same bytes in immutable sequence history at `review-<NNNNN>.md`; chat output is only a pointer and summary. `review.md` is always the sole canonical latest handoff, so consumers never choose among history files. Resolve the base from Git's canonical common directory and registered-worktree metadata by selecting the sole worktree whose Git directory is the common directory. Verify the selected top level. Do not assume the current checkout is primary, treat the first listed worktree as authority without validation, derive the base from a parent directory, or search outside registered worktrees.

The report starts with YAML front matter identified by `review_handoff_version: 2`. Its exact identity keys are `review_sequence`, `task`, `repository_common_directory`, `base_worktree`, `canonical_report`, `history_report`, `target_kind`, `target_identifier`, `target_branch`, `target_worktree`, `base_commit`, `head_commit`, `target_status`, `reviewed_artifact_count`, `reviewed_content_digest`, and `verdict`. Use canonical absolute paths and full commit OIDs. The positive sequence must equal the five-digit `history_report` suffix. Status is `clean` or the complete porcelain-v1 snapshot including untracked files.

Snapshot identity covers every reviewed staged, unstaged, and untracked version plus every explicitly reviewed ignored artifact. When any such state exists, section 1 contains a fenced `Reviewed snapshot manifest` in canonical JSON Lines. Every record uses keys `domain`, `path`, `root`, `type`, `mode`, `size`, and `sha256` in that order. Domains sort as `index`, `worktree`, then `external`; records then sort by normalized target-relative UTF-8 path bytes. External selected roots use `root: true`, descendants use false, directories are recursively inventoried, and `reviewed_artifact_count` counts the roots. Types are `regular`, `directory`, `symlink`, and `absent`; modes are lower-case octal; sizes and SHA-256 values cover regular-file bytes or non-followed symlink-target bytes, while directories and absence records use null size/digest. Reject escaping, duplicate, non-UTF-8, or special-file entries. The digest is SHA-256 of the exact newline-terminated manifest bytes and is null only for a Git-clean target with no reviewed ignored artifacts. Consumers re-enumerate each root, so a path, type, mode, content, addition, or removal changes identity.

The report then contains, in order, independence and scope, blocking findings, non-blocking findings, acceptance matrix, verification, evidence limitations, residual risks, and verdict. Identity, status, manifest, findings, evidence, limitations, and verdict describe one snapshot. Exclude secrets, credentials, private production data, and unrelated worktree content.

The report directory and files remain within the ignored base `.runs/reviews/` root and never traverse symlinks. Numbered files must be contiguous, regular, non-symlink files and are never rewritten or removed. The canonical file must be byte-identical to the highest numbered report. If a sole pre-sequence canonical report exists, preserve it unchanged as `review-00001.md` before publishing sequence 2; all other gaps, malformed names, divergence, or interrupted states fail closed.

Write a mode-`0600` report-owned temporary regular file beside the final files and verify completeness. Atomically publish it without replacement as the next history report and atomically replace `review.md` with the same bytes. Read the final canonical and history files back and verify byte equality, digest, ordered sections, identity, sequence, manifest, and verdict. Publication completes only after both names validate. Any base, path, sequence, write, publication, rename, or read-back failure means no completed handoff exists: name both exact paths and the failure, preserve immutable history and the implementation, and do not leave an actionable `accept` or `revise` verdict only in ephemeral output.

A successful final response prominently names the verdict, sequence, absolute canonical path, and immutable history path. The canonical persisted report is the unambiguous handoff consumed by later execution and landing sessions; history is retained evidence, not an alternative input. Both remain ignored scratch, not authoritative planning records or committed artifacts.

## Verdict

Return `revise` when any blocking finding remains or evidence is too incomplete to establish acceptance. Return `accept` only when every acceptance criterion is supported, applicable coverage has been challenged, warnings and limitations are disclosed, and no blocking finding remains.

Neither verdict marks planning complete or grants PR approval, landing, push, merge, release, or deployment authority.
