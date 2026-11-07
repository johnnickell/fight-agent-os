# Accepted-work integration assignment

Use only for a fresh Engineer assigned by a genuinely selected Sync coordinator. Engineer remains Work/source;
this reference narrows the assignment and does not promote Engineer into a coordinator. Read the resolved
project context, target instructions, complete TASK/parents, original canonical Review/QA and
[advancing-base policy](../../../../docs/engineering/LANDING.md#reconcile-an-advancing-base).
Apply [readable handoffs](../../../../docs/engineering/HANDOFFS.md#readable-handoff-text).

## Verify before integration

Independently verify selected repository/common-directory/canonical base, checkout/feature branch, clean status,
original accepted base/head and intervening history, current reports/limitations and exact existing OPEN PR.
Use the target's Land binder and exact remote OID checks. Confirm the selected branch contains published history.
Reject missing/contradictory evidence, a newer failure or unreviewed behavior; do not infer acceptance from status.
Read actual incoming intent and accepted TASK intent, including interacting changes without conflict markers.
Preserve unrelated/active source and original reports byte-for-byte; record their identities in the existing handoff.
No new independent report, proof framework, requirement changes, unrelated repairs or remote mutation.

## Merge and inspect

1. Record old accepted/current base/head and published OID, then `git fetch origin`. Bind to the target's verified
   policy base (Agent OS: `develop`), inspect the fetched changes and merge current `origin/develop`, never rebase.
   Use ordinary Git merge, retaining published ancestry. Do not stage unrelated work or rewrite existing commits.
2. Resolve meaningful code/configuration overlap by retaining both intended behaviors. Inspect relevant
   non-conflicting interactions too. Blind whole-side selection is prohibited. For authored planning conflicts,
   preserve independently authored intent/records; reconcile authored sections before using the target's owning
   planning refresh/check to regenerate derived blocks. Do not hand-author generated views or discard either record.
3. Inspect the effective result against both original accepted change and incoming base, including dependencies
   and nontrivial resolutions. Record concise decisions and old/new OIDs in the handoff, not a second acceptance
   report. A conflict alone is not a reason to request Review/QA. Stop for concrete incompatible intent (human
   decision), discovered defect (repair owner), new behavior (normal implementation/independent acceptance), or
   failed/unavailable required checks (check/evidence owner). Do not silently repair any of these within Sync.
4. Commit only the owned integration, then run useful focused checks and normal target `./bin/build` on that
   committed candidate. No forced application rerun merely for a HEAD change. If a check requires repair, stop;
   never publish a failing candidate. Preserve actual fresh planning versus executed/reused application evidence,
   original receipt identity/subject, warnings and incomplete prerequisites. Run planning validation and
   `git diff --check`; verify clean status and accepted/published ancestry after commit. A no-op integration needs
   no artificial commit but still requires candidate/gate validation.

## Return to Sync

Write `<canonical-base>/.runs/tasks/<TASK-ID>/runs/sync-integration.md`: repository/checkout/branch,
accepted and old/new base/head, exact PR/published OID, original report identities and byte-preservation result,
intervening-change applicability, incoming interactions, concise nontrivial resolutions, commands/results/counts,
normal gate receipt and fresh/reused provenance/warnings, clean/merge state and native runtime/cleanup locators.
Use the target's existing runtime; main data and shared/foreign resources remain protected. Keep evidence needed
by Land. Do not push, edit the PR, close your own unaccepted new work or dispatch another role.

Stop with integration VERIFIED or the concrete BLOCKED/INCOMPLETE owner/action. On success return to the owning
Sync coordinator for automatic fresh Land, not a routine Review/QA recommendation. This is acceptance reuse of
input work, never self-acceptance of Sync's implementation.
