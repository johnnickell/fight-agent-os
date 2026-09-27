---
name: release
description: Prepare or resume a versioned package release by inspecting its current stage, updating release docs, certifying the commit, and coordinating human signing, publication and Packagist verification.
---

# Release

Use for a package release in the **current target repository**, including a release already partly completed.
Examples: `/skill:release 0.5.0`, `/skill:release 0.4.0 status`, or
`/skill:release 0.4.0 resume after signing`. These arguments describe intent, not a shell command to evaluate.
`status` is read-only apart from permitted ignored evidence. A bare version requests preparation and continuation
within the user's existing authorization; it does not approve every external effect. Ask for a missing version
before release mutations. Normalize an optional `v` prefix; validate against the target's supported version syntax.

This is Release Manager guidance. TASK implementation/review/landing and application deployment retain their own
workflows. Do not implement missing release features or turn this skill into a second release runner/certifier.

## Establish authority and state

1. Read the target's `AGENTS.md`, delivery/testing/review rules, release tooling, accepted release ADRs and
   canonical planning. Resolve superseded rules from accepted decisions; ask about an unresolved conflict.
   Use [package profiles](references/package-profiles.md) for Fight Common or Access Control as discovery hints,
   then verify them against current files. Paths in profiles are relative to the target, never this skill's home.
2. Confirm repository, package name, remote/fork identity, release line, checkout ownership and requested version.
   Inspect status, worktrees, branches, tags and existing PRs. Follow local checkout/branch policy; preserve
   unrelated changes. Fetch the needed refs without overwriting local tags or changing the human checkout.
   Read hosted release and Packagist state when relevant. Failed authentication, transport or parsing means
   **unknown**, not “does not exist.” Do not infer the target from a hardcoded machine path.
3. Build a short stage ledger: phase, observed identity, timestamp/evidence, completed/needed/blocked/unknown,
   and next authorized action. Include docs/readiness, branch/PR, integration, final certification, local signed
   tag, remote tag, GitHub release, Packagist, merge-back and cleanup. Determine these facts independently;
   Packagist may observe a pushed tag before GitHub publication. Reconcile any old handoff against live state.
4. State the next step and continue authorized work. Preserve approvals already supplied for the same effect
   and identity. Ask only for missing decisions or authority, with the concrete candidate and effect prepared.
   If local policy requires fresh per-effect approval, identify that rule. Never treat a skill as a grant.

## Execute the next incomplete stage

Read [release stages](references/stages.md) for preparation, certification, publication, Packagist and closeout.
Reuse the existing release branch/PR/tag/release when their identities and state match. Do not restart from
`develop` simply because the skill was invoked again. If the release is already merged to `main`, discover that
merge and proceed to its remaining checks; if already published, verify it and continue downstream.

Use target-owned commands and canonical gates. Record direct exits, timestamps, exact candidate identities,
required output/artifact hashes and warnings. A prior green build or equivalent tree is not fresh final-commit
certification. Required independent acceptance cannot be supplied by the release coordinator itself.

Before signing, read [human signing handoff](references/signing.md). Generate the concrete terminal script only
after resolving its exact candidate, verified receipt, remote, tag and approved signer. Give the user its path
and exact commands, with signing and tag push separately executable. Keep keys and passphrases out of agent
tools and transcripts. After the human returns, inspect postconditions; “I ran it” alone is not publication proof.

Before each external mutation, refresh the facts relevant to that effect. On timeout or lost response, inspect
the provider before retrying. Never force, replace or delete a published tag, overwrite a conflicting release,
bypass a required check, or treat an API failure as permission to create a duplicate. Stop at the specific
unresolved boundary with a recovery step; complete independent authorized preparation meanwhile.

## Handoff and completion

Keep the resumable ledger, notes, scripts and raw receipts in the target's approved ignored evidence location
(normally a version-scoped `.runs/` subfolder). Record approvals with their scope and identity, not secrets.
Update canonical release/planning records only for verified facts using the target's normal delivery process.
Scan public notes, scripts and attachments for private local paths, research identifiers and credentials.

Report version, commit, stage, evidence, effects actually performed and the next human action. Separate local
certification, signed tag, remote publication, registry projection and consumer installation. “Release complete”
requires all stages required by this request and target policy, including merge-back/cleanup when included.
If any remain blocked or unverified, name them rather than claiming completion. No automatic archive or deployment.
