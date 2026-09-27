# Human terminal signing handoff

The deliverable at this stage is a **concrete script for this certified release**, saved in the target's approved
ignored handoff folder, plus exact copyable terminal commands. Do not deliver a template full of placeholders or
ask the user to assemble a command sequence. Do not generate a runnable script before its required identities
and evidence are known. Use the actual installed Git/signing tools and target policy; don't install a signer,
modify global Git/GPG settings, or add another tracked release executable.

## Bind the script to inspected evidence

Capture literal, safely quoted values for repository/worktree path, expected fetch and push destination, full
release ref, version, tag, exact certified commit, receipt path and hash, required artifact hashes, approved signer
identity/fingerprint and annotation-file hash. Confirm the configured signing format and distinguish an approved
primary key from an allowed signing subkey. Do not substitute a display name/email for cryptographic identity.
Reject ambiguous multiple push destinations, unresolved placeholders and unsupported tooling before handing off.

Derive receipt validation from the current owning certifier: check version, exact commit, required successful
outcomes and artifact/log integrity before generating the script. Pin those reviewed files in its manifest and
recheck them at execution. A hash check preserves inspected evidence; it does not independently certify a build.
Keep private paths/receipts local. Never copy credentials from a remote URL into the script or transcript.

Give it explicit `sign` and `push-tag` modes; running it with no valid mode prints usage and exits without effects.
No chained sign-and-publish default. Each mode must be independently resumable and enforce its preconditions.
Use strict error handling, quoted arguments, no `eval`, no shell tracing and no ignored verification errors.
Do not repurpose HOME or other system variables. Validate syntax with the target shell before delivery.

## Preconditions for both modes

1. Resolve the canonical repository and worktree identity; check the checkout is clean, including untracked files
   except properly ignored evidence. Require `HEAD` to equal the pinned certified commit. Do not checkout/reset
   automatically when it differs. Verify the pinned receipt, annotation and artifact files still match.
2. Resolve the actual remote URLs again, including push rewrites; reject a changed or ambiguous destination.
   Read current release-line and exact tag refs from the expected server. Check command success separately from
   empty output; network/authentication failure must stop, not appear as an absent tag. Do not overwrite local tags
   with a fetch or suppress errors using `|| true`.
3. For an unsigned current-line candidate, require the verified remote release-line tip to remain the approved
   certified commit. If it advanced, stop for reconciliation. On a resume with an existing signed/published tag,
   verify the pinned historical release identity and target policy; never silently repoint it at a newer tip.
4. Check local and remote tag identities independently. Require an annotated tag object, the exact peeled commit
   and approved signature where a tag exists. If the remote tag exists but is absent locally, reconcile it safely
   and verify its object/signature before regenerating the handoff; don't blindly sign a second object.

## `sign` mode

Require execution by the human in their own interactive terminal. For GPG use the actual terminal for pinentry
(for example, `GPG_TTY` from `tty`); do not pipe a password, use loopback passphrase arguments or capture terminal
input. Other approved signing formats must follow their verified operator procedure; do not assume GPG flags work.

If the exact local signed annotated tag already exists, verify signer, object and peeled commit and report that
signing is complete without recreating it. Otherwise, only after every precondition passes, create the signed
annotated tag at the explicit pinned commit using the approved key and annotation file. Git's `-s` creates a signed
annotated tag; `-u` selects the signing key. Never use force or infer the target from ambient HEAD.

Verify the actual signature using supported machine-readable signer evidence, including allowed subkey mapping,
plus tag type and peeled commit. A “Good signature” string alone does not identify the approved signer.
Record the verified tag object ID, peeled commit and signer fingerprint for the next phase. If signing succeeded
but verification failed, preserve the tag and stop; do not delete/recreate it as automatic recovery.

## `push-tag` mode

Require tag-push authorization separately from signing under target policy. Recheck evidence, destination, local
tag object, signature and peeled commit immediately before this effect. Use the recorded approved tag object ID
so a changed local annotation/signature cannot pass solely because it peels to the same commit.

If the remote already has the same object **and** peeled commit, report an already-completed push. Any conflicting
object or commit stops, even if only the annotation differs. Otherwise push only the explicit single tag ref to
the approved destination with no force and no `--tags`/`--follow-tags`. Avoid implicit branch pushes; inspect push
configuration and disable automatic follow-tags for this operation. Re-read both remote tag object and peeled
commit after the push. A failed/lost response requires this observation before retrying.

Keep GitHub draft/publication and Packagist operations outside the signing script. Give the user separate exact
invocations and expected results for sign and push; do not ask them to paste a passphrase or raw terminal secrets.
After they return, independently inspect the local/remote facts before continuing the release ledger.

## Validate the generated handoff

Inspect its shell syntax and literal quoting. Walk or directly exercise read-only preconditions with the actual
approved evidence. Confirm dirty/stale checkout, changed evidence/destination, conflicting tag and failed remote
query all stop before mutation; don't run signing/pushing merely to test the script. Report which cases were only
inspected. If deterministic checks cannot enforce the required policy, report the missing contract instead of
shipping a script that prints “verified” without proof. Never put tooling tests in the package product suite.

Reference: [Git tag documentation](https://git-scm.com/docs/git-tag), including signed annotations, explicit target
objects and signature verification. Inspect installed tool help for supported options when generating the script.
