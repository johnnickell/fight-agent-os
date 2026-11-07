# Bundled subagents and Fight observation

## Component and current qualification

`vendor/pi-subagents/` is the one Fight-maintained MIT-licensed upstream engine, not a submodule, separate
installation, second scheduler or alternate session engine. Its [README](vendor/pi-subagents/README.md) records
upstream 0.23.1/base `50460b5df6934c4237983a504808a04ee0f8a0a0`, attribution and maintained hooks. Fight owns
workflow policy and observation; the engine owns SDK execution. Automatic worktree management, scheduling,
workflows and nesting stay disabled in the supported profile.

TASK-00560's real Engineer/Herdr journey has independent acceptance recorded in its TASK. TASK-00561 extends that
source boundary to all four roles and corrects demonstrated follower gaps. **This is an incomplete bootstrap:**
controlled copied-install checks do not establish the required real all-role delivery/repair journey or default
cutover. Existing active Build and Sync process callers stay live. No global package/settings/trust or active
session is changed by this source update. Full TASK-00561 acceptance and process-launch retirement remain held.

## Select the supported profile

Before starting a deliberately selected new installation:

1. Inspect `profiles/subagents.json` and explicitly merge it into the project's `.pi/subagents.json` or the private
   agent-directory `subagents.json`. Project settings override private defaults. Never overwrite another operator's
   configuration. Installation does not apply the profile, select workflow authority or grant delivery effects.
2. Select **one complete Fight package** in Pi's configured `packages[]`. Inspect `pi list` and direct extension
   paths/filters. Remove only an explicitly replaced standalone `npm:@tintinweb/pi-subagents...` declaration in the
   intended settings scope after affected clients exit. Never load standalone and bundled engines together.
   `pi -e` alone does not make package agents discoverable to the engine. Source-local installs need explicit
   `npm --prefix harness/pi ci --ignore-scripts --legacy-peer-deps --no-audit --no-fund`; copied npm installations
   install declared dependencies. Pi is capability-qualified, never version-equality gated.
3. In upstream `/agents`, confirm worktree isolation/workflows/scheduling/nesting disabled, strict agent files,
   no fallback/default agents and only Fight package agents. Inspect local agent-definition collisions rather
   than assuming a package name proves provenance. Read `/fight-subagents` for passive observation diagnostics.
4. Restart only the intended client at a safe boundary. Preserve operator credentials, account settings, existing
   sessions and historical receipts. Applying this candidate to a shared everyday installation is a separate
   explicit operator action, not a Work/Build side effect. See [installation/recovery](README.md#install).

Ordinary subordinate profiles remain `fight-engineer`, `fight-explorer`, `fight-review-assistant` and
`fight-qa-assistant`. They inherit selected model/thinking but no workflow promotion. Review/QA assistants supply
advice only, never canonical acceptance. `fight-engineer` becomes an actual Work owner only through trusted
binding. New `fight-reviewer`, `fight-qa` and `fight-land` profiles stop when unbound; tools/profile names alone
cannot grant independence or publication authority. Native tool availability is not OS confinement.

All profiles use the engine's unscoped extension selector `agent-os-pi`, append governing instructions, use fresh
contexts and disable raw transcript output. The deterministic tool policy, if separately selected, refuses
Agent delegation at its tool boundary; SDK/extension OS calls are not sandboxed by that policy.

## All-role Build bootstrap

The candidate provides **`--fight-build-stages sdk`** and **`fight_build_stage`** for fresh Engineer, Reviewer,
QA and Land owners through the same engine. It replaces the Engineer-only source entry/tool/flag, not an active
installed host. There is no compatibility alias or silent fallback. Existing copied installations retain their
original Engineer route until explicitly updated. The default process Build route remains until replacement evidence supports cutover under
[TASK-00561's approved staged qualification](../../planning/tasks/00561-TASK.md#approved-staged-qualification--2026-11-07).
That same TASK receives bounded bootstrap Review then native/installed QA, final cutover then fresh final
Review/QA, and only then authorized Land as the actual delivery observation. Bounded acceptance is not
complete-TASK acceptance or Land authority; never claim that retaining the process Build default completes it.

### Prepare a fresh native host

Keep the current coordinator/shared installation untouched. Under the canonical TASK scratch, explicitly create
a private **copied** candidate installation (not a symlink back to disposable source). For example, from the
selected source checkout, with absolute task-owned values:

```sh
npm install --prefix "$private_prefix" --install-links --ignore-scripts --legacy-peer-deps --no-audit --no-fund "$PWD/harness/pi"
```

Inspect that `$private_prefix/node_modules/@fight/agent-os-pi` is a real copy. Prepare the private Pi directory
with `settings.json` containing `packages: ["/absolute/private-prefix/node_modules/@fight/agent-os-pi"]`, the actual
provider skill directory in `skills`, and an absolute task-owned `sessionDir` at the canonical base or selected
checkout. Apply the shipped profile to that private directory before launching. Resolve provider authentication
through the existing supported operator/SDK path; never copy credential files into the private directory or logs.
Unavailable auth holds real qualification, not permission to use a faux provider as real acceptance.

Start native Pi in an owned Herdr terminal, in the operator-selected registered checkout:

```sh
PI_CODING_AGENT_DIR="$private_agent_dir" pi --fight-build-stages sdk \
  --provider "$coordinator_provider" --model "$coordinator_model" --thinking "$coordinator_thinking"
```

The **operator** must genuinely select `/skill:build TASK-NNNNN --checkout /absolute/selected/checkout` with the
actual TASK. Inspect `fight_workflow_status`: Build/coordination, same TASK/checkout, default mode. The flag only
selects route readiness; it does not select Build authority. An Engineer, profile, RPC prompt or programmatic
interactive-input fixture cannot promote itself. No automatic active-host `/reload`, user-input synthesis or
shared installation update is allowed. If this session cannot express that genuine selection, commit the
bootstrap incomplete and return this precise preparation/continuation to its owning Build.

Before continuing an earlier journey, reconcile its original worker liveness and remote/local effects; a fresh
host is not proof that old effects stopped. Carry the original authorization, reports, pinned identities, single
Compose locator and repair usage forward. The human's fresh Build selection must preserve the existing delivery
scope; it does not authorize another TASK, merge, release or deployment.

### Dispatch one stage

Build prepares and reads back a fresh canonical `.runs/build/<TASK-ID>/<run>/<attempt>/request.md` under its
[owning request rules](../../.pi/skills/build/SKILL.md). Every request names absolute target/skill/context/evidence
paths, exact candidate, permitted effects, pinned model and existing runtime. Review/QA never inherit Engineer
conversation. Land receives current independent acceptance and the original invocation's exact publication or
cleanup-only scope. Advice-only helpers are not substitutions for these fresh owners.

Call `fight_build_stage` with:

- `task`; canonical `role: engineer|reviewer|qa|land`; exact canonical registered `checkout`; current full `head`;
  fresh absolute `attempt` containing `request.md`;
- target canonical evidence `base`, current exact `branch`, absolute target `record` and task-owned Build `context`;
- exact original per-role `provider`, `model`, `thinking`; finite `timeout` seconds, at most 21600;
- `repairLimit` and `repairsUsed` from the **existing** shared ledger; `repair: true` only for an Engineer repair,
  with that cycle already consumed. Initial Work is not a repair. Assessors/Land carry unchanged usage and false.

[PROJECT_CONTEXT](../../docs/engineering/PROJECT_CONTEXT.md) resolves target facts; the host does not impose Agent
OS planning paths/status words/branch prefixes on another target. It requires matching registered repository,
base/checkout, exact branch/head/context/record, actual Build authority and the engine's real
`beforeSessionStart`/`boundRootSession` capabilities. The actual SessionManager owns route readiness; SDK siblings
cannot borrow it or a native parent's launcher environment. Concurrent sessions never mutate shared `process.env`
to impersonate role authority.

The tool claims `dispatch.lock` before calling the one upstream manager. Its awaited setup runs after factories
but before extension binding, `session_start` and prompting. It verifies actual fresh session/root/checkout,
model/thinking, task-private storage, loaded Fight extensions and inherited native governing instructions, then
binds the actual role/skill/TASK/checkout/attempt/effects. Workflow initializes that role and verifies it at input
before a provider request. An unbound SDK child stays Reviewer regardless of transcript, profile or prompt.

### Outcomes, reports and recovery

The tool returns `sdk-outcome.json` identity/lifecycle metadata, not worker dialogue. It consumes its own engine
completion notification. `acceptance` is always false. Role pins and the shared repair usage persist in Build's
existing session checkpoints; changed pins/target, allowance reset or unresolved prior execution refuse dispatch.
A new host still consumes the original ledger; its absent checkpoints do not create a fresh repair allowance.

Review/QA report locations derive from the resolved **canonical base**, not model-selected output paths. Only
actual independent owners write them under their owning skills. `reportObservation` records path/digest, changed
bytes, bound session and assigned candidate. This is **not** a parsed verdict, proof of writer identity against
hostile local code, or proof that the report covers that candidate. Build must read the report and verify full
TASK/branch/base/head/status, independence, Spec/Standards, scenarios, findings and limits. Missing, unchanged,
wrong-candidate, REVISE, FAIL or INCOMPLETE reports do not advance; no older PASS overrides newer failure. SDK
completed/steered/error labels, process exits and pane state never supply acceptance. Native tools are trusted-local
responsibilities, not filesystem/OS isolation.

Timeout, caller cancellation, settings change and parent shutdown/reload request cancellation and allow ten
seconds for the original promise **and** actual streaming session to settle. Monotonic grace measurement avoids
wall-clock jumps. Missing/replaced records, unsettled execution or possibly-started exceptions remain uncertain;
old terminal status alone is not quiescence. Reload restores the unresolved-attempt hold. Inspect the original
execution, reports, Git/remote PR and cleanup effects before any replacement; do not replay publication or switch
to Python to recover output. There is no automatic reconciliation/acceptance command or alternate scheduler.
Reader closure remains passive; upstream runtime shutdown/reload can abort/dispose workers.

### Opt-in Build Engineer

This anchor preserves existing links to TASK-00560's historical Engineer-only qualification. Its accepted real
journey and original receipts remain in [TASK-00560](../../planning/tasks/00560-TASK.md). The current copied source
uses the all-role bootstrap above; do not pass its removed Engineer-only flag/tool to the new package. Earlier
0.22.1/source `fc089c4b7ab0f1185453b2e53027997c1f637e38`, Pi 1.4.0 and Herdr 0.9.5 are historical observation receipts.
The original 0.23.0/Pi 1.5.0 missing-hook probe remains evidence in TASK-00560, not a current API limitation or
version gate. John approved the bundled component on 2026-11-04; its supported local setup/root hooks are in
[the engine RPC contract](vendor/pi-subagents/docs/rpc.md). No external fork or upstream publication is required.

### Build Engineer prerequisite — TASK-00560 held

Historical locator for TASK-00560's 2026-11-03 missing-capability evidence: read the
[original hold at revision 03241436](https://github.com/johnnickell/fight-agent-os/blob/0324143618496c3cf26bdd3e315243a47934dbc9/harness/pi/SUBAGENTS.md#build-engineer-prerequisite--task-00560-held).
The later bundled hooks and accepted Engineer journey superseded that hold. Its old setup instructions are
not current commands; use the all-role bootstrap above for this candidate. This reference does not clear
TASK-00561's remaining qualification or default-cutover requirements.

## Supported observation boundary

The passive bridge resolves actual manager records with exact native-root custody, stable record/session objects,
registered worktrees and no nested/workflow ownership. Foreign/rootless/replaced records remain refused. Event/tool
input is only a lookup candidate. Foreground resume, which has no upstream started event, waits for an actual
record run transition; its display attempt is fresh without executing/recreating the SDK session.

At most eight records are sampled once a second. Assistant prose and tool **names** are bounded; reasoning,
arguments, result bodies and transcript files are never ingested. Live-session finalized text recovers early/final
output; transport coalescing preserves terminal frames. Slow legitimate session initialization now produces a
finite delayed diagnostic while retaining only that same owned record; five seconds is not an execution deadline.
Record replacement, actual terminal absence or shutdown still detaches it. Execution owns cancellation/deadlines.

AGENTS automatically follows a newer already-terminal SDK stage even when it completed between polls. A genuine
explicit history choice or pin stays selected. Preferences preserve automatic-versus-explicit intent through
reader recreation; recreating a companion must not silently pin an automatically followed completed stage.
Selection changes reset frozen output and carry only verified existing worktree/log context. Lost custody removes
log authority. Native daemon exit/disconnect becomes unavailable; it never replaces or replays a worker.

Existing eight-child history retirement preserves selected/pinned terminal entries, active SDK work and connected
process monitors. Capacity refusal detaches display only. SDK outcomes remain distinct from real process exits.
Closing/reopening readers is passive; reopening after native runtime reload requires new context/custody.
No pane output enters the model, and missing display cannot establish execution success.

## Owning qualification and retirement inventory

Run with new canonical task-private evidence and an explicit private copied package:

```sh
node --test harness/pi/tests/build-stages.test.mjs harness/pi/tests/subagents.test.mjs harness/pi/tests/registered-children.test.mjs
node harness/pi/tests/native/build-stages.mjs "$PI_SDK_MODULES" "$stage_evidence" "$installed_harness"
FIGHT_QUALIFICATION_HARNESS="$installed_harness" node harness/pi/tests/native/module-loading.mjs "$PI_SDK_MODULES" "$loading_evidence"
python3 -B harness/pi/tests/native/portable-stages.py "$process_evidence" "$installed_harness" "$read_only_consumer"
```

The all-role probe exercises actual copied engine/SDK/Fight initialization and seven fresh controlled role/repair
sessions, a normal model/tool pipeline, canonical negative/incomplete fixture reports, per-role pins, unbound
siblings, provider errors, timeout and reload holds. It never writes real TASK acceptance or performs publication.
Controlled launcher fixtures are **not** genuine operator selection, real provider Work, independent Review/QA,
Herdr capture or the required all-role delivery journey. Record source/install/model identity and limitations.
Use real native/installed interaction and inspected comparable captures for the remaining qualification, then
normal `./bin/build` and independent Review/applicable QA. Missing evidence leaves TASK-00561 incomplete.

| Path | Actual current caller / retirement decision |
|---|---|
| `src/build-stages-extension.ts`, `src/build/{stages,authority,history}.mjs` | Manifest and Build's explicit all-role route; one engine, no scheduling/acceptance replacement |
| Former Engineer-only extension/module and two native probes | Replaced by qualified copied all-role source/probe; removed, without compatibility wrappers |
| Provider `.pi/skills/build/scripts/run-stage.py` | This active pre-bootstrap Build and explicit Sync still use it; retained until real replacement qualification |
| `src/observation/run-stage.py`, `automatic_observation.py`, `observation.py`, birth helpers | Same live process callers plus their native qualification; retain, not hypothetical fallback for an SDK attempt |
| Native custody/observer and `workspace/registered-children.mjs`, Herdr activity/logs | Shared passive path for both routes, not replaced launchers |

No release-version dispatch is added: the release protocol explicitly uses independent standalone sessions when
its version-owned dispatcher is unavailable. The TASK-only tool cannot supply release authority. Prior explicit-socket
JavaScript readers were retired by TASK-00554; its historical 657/657 observation run and later recovery/repair
receipts remain with that TASK, not fresh all-role qualification. Full default cutover, the required real
journey/repair, inspected native captures and evidence-backed process retirement remain continuation work.
