# Native workflow supplier flow

![Native workflow after Jev retirement](workflow-flow.png)

**Existing implementation at `e4959587b6721c174ca78b11a72e58492b8243c2`** (TASK-00586).
[Editable Python source](workflow-flow.py). This bounded runtime view explains the surviving supplier ownership
and the absence of a file-screening dependency, not the whole Harness. Dotted arrows denote runtime interactions;
selection metadata is not native filesystem permission, process isolation, real Build dispatch authority or acceptance.

| Arrow | Source at that revision | Meaning |
|---|---|---|
| 1. Monitor → Pi | [package monitor `run`](../../../harness/pi/src/observation/run-stage.py), [provider `main`](../../../.pi/skills/build/scripts/run-stage.py) | Provider resolves one trusted package; its fixed monitor launches that same package and supplies role/task/checkout, without enrollment preflight |
| 2. Pi → workflow | [`installWorkflow`](../../../harness/pi/src/workflow-extension.ts), [`WorkflowAccess.restore/input`](../../../harness/pi/src/workflow/access.mjs) | Session start restores provenance or uses the explicit launcher; only interactive operator input can reselect ordinary workflow |
| 3. Workflow → authority | [`publishWorkflow`](../../../harness/pi/src/build/authority.mjs) | Ephemeral exact-SessionManager selection consumed by SDK Build checks; persistence failures publish restrictive authority |
| SDK → authority | [`runEngineer`](../../../harness/pi/src/build/engineer.mjs), [`bindEngineerSession/engineerIdentity`](../../../harness/pi/src/build/authority.mjs) | Actual fresh child identity, model/thinking, governing context and selected registered checkout are checked before initialized admission |
| Pi → tools | [workflow extension](../../../harness/pi/src/workflow-extension.ts), [independent policy](../../../harness/pi/src/tool-policy-extension.ts) | Workflow does not wrap native files/shell; explicit deterministic policy alone wraps its documented shell routes |
| Pi → observation | [`installNativeObservation`](../../../harness/pi/src/native-observation-extension.ts), [`NativeObservation`](../../../harness/pi/src/observation/native-observation.mjs) | Lifecycle/tool notifications update passive custody/readers without authorizing execution |

Retrieve cited source with `git show e4959587b6721c174ca78b11a72e58492b8243c2:<path>`. A later documentation-only
commit may carry these artifacts; any changed represented source requires a new trace. The legend and arrows
are source-verified, not an assertion that every path succeeds. Direct native/SDK qualification is separately
recorded in TASK-00586. The SDK fixture is not genuine Build authority. Provider transport, full engine scheduling,
Herdr layout, resource teardown, detailed persistence internals and future managed execution are omitted.

Render with `./bin/diagrams render docs/engineering/native-workflow/workflow-flow.py` and inspect the PNG under
the [diagram contract](../DIAGRAMS.md). Publication/independent acceptance and installed rollout are separate.
