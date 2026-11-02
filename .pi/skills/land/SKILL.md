---
name: land
description: Publish an accepted TASK, obtain required hosted evidence, resume an ended handoff, or finish ownership-proven local cleanup without republishing.
---

# Land

Resolve [project context](../../../docs/engineering/PROJECT_CONTEXT.md), target rules and
[landing standards](../../../docs/engineering/LANDING.md). Follow the
[portable access rule](../../../harness/pi/FILE_SCREENING_RULE.md). This is one subject, not a fleet sweep.

1. **Choose the route.** Explicit `cleanup-only` enters
   [cleanup intake](../../../docs/engineering/LANDING.md#cleanup-only-intake), never publication. An ended PR
   enters [ended-handoff recovery](../../../docs/engineering/LANDING.md#resume-an-ended-delivery-handoff).
   Missing explicitly required hosted acceptance proof uses the authorized draft-evidence path, not invented
   acceptance or implementation edits. Otherwise require current independent Review ACCEPT and applicable QA
   PASS/criterion-specific N/A. A current FAIL/INCOMPLETE cannot be replaced by an older pass.
2. **Bind the subject.** Verify target origin/live default/policy/base, exact checkout/ref/OIDs, canonical reports,
   independence and actual live PR. An advancing base needs a proven mechanical bridge or fresh Work/Review/QA;
   never rewrite acceptance or a published branch. Preserve unrelated work and historical evidence.
3. **Close out and publish only when authorized.** Reconcile applicable current TASK/parent/docs/handoffs;
   generated Board changes only here unless separate authored planning is approved. Refresh/check planning,
   inspect exact administrative diff and whitespace. [Reuse qualified gate evidence](../../../docs/engineering/EXECUTION.md#gate-evidence-reuse)
   rather than repeating it for metadata. Run `./bin/build` normally for automatic reuse; use `--force` for
   deliberate fresh execution and distinguish reused receipts from fresh evidence. Substantive changes need
   renewed verification/independence. Commit only
   owned files, non-force push, create/update the exact PR using its template and `TASK-NNNNN — <title>`. Verify
   current base/head/remote OID, required checks and audience-accessible applicable captures. Follow the standards'
   upload/partial-publication recovery; missing required proof keeps delivery incomplete. No approval or merge.
4. **Retire and inspect.** Follow the one shared [resource procedure](../../../docs/engineering/RESOURCE_LIFECYCLE.md).
   Routine owned cleanup is already authorized. Invoke `bin/down --worktree SELECTOR` FIRST: task DB/storage
   is disposable; native Compose/Docker metadata governs teardown without worker/PID/receipt gates. Preserve
   main data and foreign/shared projects. Then separately qualify source/ref/private data, consumers and genuine
   filesystem protections before Worktrunk removal. Use the shared guarded native remover: it rechecks effective
   canonical/target hooks and uses per-invocation noninteractive consent only for absent hooks or the exact
   reviewed repository down hook. Do not ask the user for that routine cleanup; unknown/user/changed hooks
   refuse source removal without blocking Docker teardown. Never persist approvals, bypass hooks, force, prune,
   strip protections or stop a shared VM.
   No mandatory source copies or permanent local archive. Keep active-role evidence until outcomes reach TASK/PR.
   If this worker consumes source, Build continues source removal from the safe checkout after actual exits;
   that source hold cannot block explicit Docker teardown. No second cleanup helper or resource ledger.
5. **Report and stop.** Return actual publication state separately from removed/absent/held/failed resource counts,
   exact remaining identities/reasons, fresh/reused evidence and finite next action. Verify postconditions yourself;
   a helper exit, successful PR or technical acceptance is not complete cleanup. No tracked cleanup-result loop,
   persistent delivery queue, routine post-merge maintenance, remote-ref deletion, archive, release or deployment.

Use [readable handoffs](../../../docs/engineering/HANDOFFS.md). Finish with the actual human PR review/merge URL,
cleanup prerequisite, or owning Work/Review/QA route. Never recommend merging an already-ended original PR.
