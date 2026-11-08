# Native workflow and Jev retirement

TASK-00586 retires the local evaluator integration under the
[approved amendment](../../planning/epics/00006-EPIC.md#approved-native-pi-compaction-and-jev-retirement--2026-11-05).
Ordinary parent, SDK Build and retained Sync process children use native Pi tools. Startup never loads evaluator enrollment,
connection, cache, spend, locks or pending-liability files and never resolves evaluator credentials or sends
Decisions requests. No screening-specific command, tool, flag, diagnostic relay or preflight remains.

## Surviving consumers

| Consumer | Current supplier and boundary |
|---|---|
| Interactive selection, session-wide restore, selected checkout and role status | `src/workflow-extension.ts` and `src/workflow/access.mjs`; existing `fight-workflow-role-v1/v2/v3` entries remain readable |
| SDK stage initialized admission | `src/build/authority.mjs`, `src/build/stages.mjs` and the workflow extension; actual fresh SessionManager, role/effects, model/thinking, context, canonical report scope and registered checkout remain required. [TASK-00561's staged contract](../../planning/tasks/00561-TASK.md#approved-staged-qualification--2026-11-07) requires bootstrap Review/native QA before final cutover, fresh final Review/QA before authorized Land, and actual Land as delivery evidence |
| Supported ordinary SDK subordinates | Bundled engine child context marks real child sessions; they ignore process launcher variables and cannot promote themselves through prompts |
| Fresh Build default | `fight_build_stage` is the sole SDK route for Work/independent Review/applicable QA/authorized Land. Actual Build/TASK/checkout/default mode and supported engine/profile are required; SDK subordinates cannot coordinate nested stages. No selector flag or process fallback |
| Explicit Sync process stages | Provider `.pi/skills/build/scripts/run-stage.py` selects one native package; that package's monitor injects it into Pi and supplies `FIGHT_WORKFLOW_PHASE/TASK/CHECKOUT`. Sync owns the [retained protocol](../../.pi/skills/sync/SKILL.md#process-stage-protocol) for fresh accepted-work Engineer then automatic Land, not a Build alternative |
| Native session custody/identity and passive observation | Existing observation/custodian and Herdr modules, unchanged authority; no evaluator gate, registry or scheduler replacement |
| Grouped TUI usage/status footer | `src/status-footer.ts`, now installed by the workflow extension; historical transcript tool usage remains counted |
| Opt-in deterministic shell policy | `src/tool-policy-extension.ts` and `src/tool-policy/`; no enrollment supplier or workflow filesystem adapter; native read/write/edit stay native |
| Herdr executable startup | Exact `fight-workflow` resource-owner check replaces the retired command checks; native observer/opener ownership remains |
| Shared OpenRouter provider settings | Pi owns them. Retirement neither reads nor mutates account/provider configuration |

No live runtime caller of the evaluator event bus or file-discovery/admission services remains. Their source,
declarations, setup UI, bootstrap and owning tests are removed, not hidden behind a disabled registration.
The former `file_locate` receipt-oriented helper has no independent consumer; use native bounded reads/search.
No evaluator-only npm dependency existed: the two declared runtime dependencies belong to the bundled engine
and remain. The portable [workflow rule](WORKFLOW_RULE.md) replaces active screening instructions.

Unimplemented Jev-specific plans (typed MCP judgments, semantic assessment/ranking and memory retrieval) are
not live suppliers and are not delivered by native tools. They retain the amendment's explicit supplier-decision
hold. Historical planning approvals and usage receipts are preserved, not reinterpreted as active integration.

The [source-linked runtime view](../../docs/engineering/native-workflow/README.md) shows the surviving suppliers
and native-tool independence at the implementation revision.

## Retained state

Do **not** delete or rewrite `~/Library/Application Support/Fight Agent OS/jev/`. It may contain connection/file
policies, enrollment, legacy and monthly/session ledgers, reservations, pending records, locks, finite test grants,
caches and historical receipts. All are inert to this candidate; unresolved liabilities remain unresolved, not zero.
No reconciliation, refund, migration or budget reset follows from retirement. A future authorized accounting
operation needs the original evidence and an independently verified billing outcome, not startup cleanup.

Existing native/RPC session files, transcript branches, role checkpoints and recorded tool usage remain native
Pi data. Resuming history does not replay retired evaluator tools; an explicitly requested unavailable old tool
will be unavailable, not silently mapped to a paid replacement. The candidate does not mutate active sessions,
shared installed code, trust, account settings, credentials or unrelated OpenRouter configuration.

## Safe-boundary adoption

Repository acceptance is not installed rollout. Keep existing active clients and shared package copies untouched
until the operator explicitly selects an owned update at a safe boundary. Inspect the actual package declaration,
resource filters and copied executable prefix; follow [installation and recovery](README.md#install).

1. Record the actual intended session path privately and finish/exit that owned client normally. Do not guess
   with newest/continue, fork, clone, rewrite history or signal another session.
2. Explicitly update the reviewed owned copy/declaration. Select one complete Harness identity. Retired explicit
   `jev-extension.ts` paths and filters require an operator change; no compatibility stub or automatic settings
   rewrite is supplied. Ordinary package-root discovery follows the new manifest.
3. Fully restart the intended process and resume the **same actual file** from its original checkout. Inspect
   `/session` and `/fight-workflow`; unverifiable/forked/legacy role history defaults to Reviewer. Select the
   intended workflow interactively if needed. Never copy launcher environment flags to manufacture authority.
4. Verify normal authorized tools and the appropriate child path. Keep evaluator state/account settings intact.
   No enrollment conversion, evaluator setup, credential or paid readiness check is needed.

The new trusted process variables are `FIGHT_WORKFLOW_PHASE`, `FIGHT_WORKFLOW_TASK` and
`FIGHT_WORKFLOW_CHECKOUT`; retired screening launcher flags/variables grant nothing. These are trusted local
launcher inputs, not a hostile-process security boundary. SDK Build requires actual native session-local Build
selection, not an execution-selector flag; a prompt, fixture or label cannot create real Build authority.
Follow the [fresh-host default runbook](SUBAGENTS.md#all-role-build-default). Active hosts retain their original
loaded implementation until deliberate safe-boundary adoption; never update/reload a live journey implicitly.
Sync retains its process path. Missing capabilities and display loss cannot select a Build fallback.

## Direct qualification

Use a fresh ignored TASK evidence directory and the installed runtime's `node_modules` as `PI_SDK_MODULES`.
Prepare a private **copied** candidate package and its dependencies explicitly, never replace a shared install.
The owning probes use isolated HOME/agent directories, no evaluator credentials and no external provider calls:

```sh
node harness/pi/tests/native/module-loading.mjs "$PI_SDK_MODULES" "$task_evidence/loading"
node harness/pi/tests/native/retirement.mjs "$PI_SDK_MODULES" "$task_evidence/retirement" "$candidate_harness"
node harness/pi/tests/native/read-policy.mjs "$PI_SDK_MODULES" "$task_evidence/policy"
node harness/pi/tests/native/native-session.mjs "$PI_SDK_MODULES" "$task_evidence/session"
node harness/pi/tests/native/native-executor.mjs "$PI_SDK_MODULES" "$task_evidence/executor"
node harness/pi/tests/native/build-stages.mjs "$PI_SDK_MODULES" "$task_evidence/stages" "$candidate_harness"
```

The retirement probe covers clean and previously enrolled fixtures through the actual Pi CLI and provider Build
entry, byte-preserved retained state, role restore/input provenance and native tool effects. Scripted responses
are local qualification only, not live provider access or genuine Work/Build dispatch authority. The SDK probes
exercise fresh all-role binding, subordinate non-promotion, shared repair/pin preservation, replay/refusal,
provider failure, timeout/reload uncertainty and passive observation. The installed runtime version/capabilities, exact counts, warnings, failures and retained locators
belong in the owning TASK/handoff. Independent Review/QA and rollout remain separate.
