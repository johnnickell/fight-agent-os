# QA and visual evidence

## Sequence and authority

The local [qa skill](../../.pi/skills/qa/SKILL.md) runs in an explicitly assigned independent session after
technical acceptance and before ordinary landing. Default flow: `work → review → qa → land → human merge`.
The planned managed QA Engineer consumes the same boundaries; a local report is not an implemented runtime gate.
An authorized [draft PR for hosted evidence](LANDING.md#draft-publication-to-obtain-hosted-evidence) may precede
QA while disclosing that QA remains pending. The skill also accepts an already-open PR for pre-merge QA or reruns.

UI/TUI changes require applicable QA. Other TASKs may receive requested behavioral QA or justified N/A. Once a
required or requested scenario is selected, do not silently drop it to obtain PASS. A narrowed diagnostic run
cannot establish complete TASK QA unless remaining scenarios have applicable prior evidence or authorized exclusions.

QA does not change implementation, tests, requirements, Planning status or technical review reports. It may run
owned isolated services, exercise application writes against disposable data and write ignored QA artifacts within
the invocation's scope. External side effects, shared/production data, new credentials, tool installation and
artifact publication require their own authority. Never put secrets or personal data into commands or evidence.
A contributor to the implementation or its submitted acceptance evidence cannot independently pass that work;
builder self-checks are diagnostic only. Independent QA may produce its own verification evidence.

## Subject and runtime

Read the TASK, parent and current canonical technical review under [review standards](REVIEW.md). Identify repository,
TASK/requirements revision, branch, full baseline and implementation commits, working-tree state, review identity
and any accepted reconciliation bridge. For PR intake, verify base/head from the hosting service and resolve the
TASK from its record/body. Missing or ambiguous identity is INCOMPLETE, not permission to guess a TASK.

Prove that the running process serves the identified source: inspect its owned launch command, checkout/mount or
image provenance and relevant build output. A URL, page appearance, browser cache or successful health check alone
does not prove which revision is running. Record service/port identity, dependency/build context, test data,
browser/terminal dimensions and tool versions where available. Pre-existing dirty changes affecting behavior or
an unproven deployed build prevent a reviewed-subject PASS. Use a separately owned checkout for baseline captures;
do not rewind the user's current checkout. Keep secrets out of environment inspection output.

Use target-owned wrappers and existing qualified tooling. Check resource ownership and port collisions before
startup; do not repoint shared services at another checkout. When an earlier landing removed the worktree or
runtime, recover the exact revision in an isolated environment within existing authority, or report what is missing.
Recheck the source/runtime and PR head at completion. If it moved, retain historical results but require new QA
or a documented behavior-preserving bridge before describing the current target as passed.

## Scenarios and captures

Map affected acceptance criteria to actions and assertions. Exercise actual browser behavior in headless or visible
mode: relevant success, loading, empty, error, permission and responsive states. Include appropriate CLI behavior
for non-visual tasks. Use an actual PTY for interactive terminal behavior, including input, resizing and exit where
relevant. Neither a command piped to a file nor a rendered mockup proves an interactive terminal screen.

Capture Before from the recorded baseline and After from the reviewed implementation using comparable data,
viewport/terminal size and scenario. For a new screen, capture the real prior state or state "Not present before";
never invent a Before image. Inspect captures to confirm they show the stated result. For failures, record the
actual failure state and reproducible steps; do not seed a defect just to demonstrate QA FAIL. If TUI image
capture is unavailable, retain a sanitized real transcript/recording and explain the limitation. Missing required
visual proof remains INCOMPLETE when a transcript cannot demonstrate the relevant criterion.

Screenshots support behavioral assertions; they do not replace interaction evidence. Record capture time,
scenario, commit identities, artifact references and SHA-256 digests of the final sanitized artifacts. Preserve
relevant console/process diagnostics without dumping credentials, personal data or unrestricted logs.

## Dispositions and repair routing

| Disposition | Required meaning | Next action |
|---|---|---|
| QA PASS | Independent session, applicable technical acceptance, known current subject/runtime and every required scenario passed with sufficient evidence | Authorized `land`, or human pre-merge handoff for an existing PR |
| QA FAIL | At least one confirmed behavioral defect; report unexecuted scenarios and missing prerequisites separately | Authorized `work` consumes the canonical findings; material repair gets independent review, then affected QA |
| QA INCOMPLETE | No confirmed defect but required scenario, independence, technical acceptance, runtime identity, tool or evidence is missing | Resolve the named prerequisite, then resume QA; missing evidence alone does not authorize source repair |
| QA N/A | No applicable behavioral QA for the scoped change, with criterion-specific reasons | Continue the applicable review/landing path; N/A is not technical acceptance |

FAIL takes precedence when a real defect coexists with missing evidence; INCOMPLETE takes precedence over PASS.
N/A cannot hide unsupported tools or failed startup for behavior that needs QA. Diagnostic checks before technical
acceptance may report FAIL or INCOMPLETE, but never independent PASS. Failed startup is not automatically a product
defect: distinguish an unavailable environment from a demonstrated violation in the approved product scope.

Every finding has a stable `QA-01`-style ID, severity/impact, TASK criterion, steps, expected and actual results,
subject and evidence. Retain IDs across reruns of the same finding. `work` revalidates current applicability,
adds the required failing regression for a confirmed bug and repairs within the TASK's authority. New/out-of-scope
work returns to a human. QA provides a handoff, not automatic dispatch or a separate unlimited repair loop.
Technical and QA blocking repairs share any configured Workflow revision-cycle allowance.

A QA blocker prevents accepted landing. A demonstrated technical defect invalidates applicable technical
acceptance; recording new supporting QA artifacts alone does not. QA records that boundary and routes it without
rewriting the technical review. Preserve previous reports; a failed rerun cannot fall back to an older PASS.

## Canonical handoff and evidence storage

Resolve the base worktree from Git's common directory and registered-worktree metadata, as for technical review.
The canonical report is `<base-worktree>/.runs/qa/<TASK-ID>/qa.md`. If the base cannot be established or written,
report an incomplete handoff rather than silently choosing a second canonical location. Reject symlinked output
paths that escape the owned ignored directory. Concurrent runs for one TASK must coordinate a single canonical
writer; do not overwrite another active run's report.

Allocate a unique `runs/<run-id>/` below that TASK directory, containing `qa.md` and captures/diagnostics. Before
runtime work, preserve any existing canonical report in its run history (copy legacy reports into an unused
history path) and atomically publish an INCOMPLETE report for the new run. This makes an interrupted rerun visible.
Use a temporary file in the canonical directory and rename it atomically; verify read-back. On completion, retain
the completed run report and atomically replace the canonical report with it. Report write/read-back failure as
an incomplete handoff even if local scenarios passed. Never select an older history file as the latest verdict.

Use the [QA report template](QA_REPORT_TEMPLATE.md). Include scenario totals and individual results, with explicit
N/A and omissions; implementation and environment identity; independence; findings; captures/digests; prior-evidence
reuse; publication readiness; and the next authorized action. Chat is a pointer to this durable report.

## Publication, freshness and cleanup

`qa` prepares local evidence. `land`, or an explicit evidence-publication request for an existing PR, owns uploads
and PR updates. Local behavioral PASS can precede publication; record publication as pending separately. Before
claiming delivery complete, verify that required evidence is accessible to the intended PR audience. Local file
paths and private or expired links are not usable public GitHub evidence. No supported upload path means pending
publication with an exact human action, not fabricated URLs or committing screenshots to product source.

Use the PR template's simple **Before | After** table with scenario captions and links to appropriate evidence.
Remove it or explain N/A for non-visual work. Publish only sanitized artifacts within the approved audience; an
existing PR or QA invocation alone does not authorize posting comments or uploading private captures.

Mechanical documentation/Board reconciliation can reuse evidence when effective behavior/runtime and dependencies
are unchanged and the provenance bridge proves this. Material changes rerun affected scenarios; explain why
unaffected evidence remains applicable. Broad reruns need interaction risk or uncertainty, not just a changed OID.
Retain reports/captures outside disposable worktrees. Stop only owned temporary processes and remove only proven
disposable data when authorized; preserve useful failed-run environments for repair. Landing cleanup must not
remove the only copy of QA evidence or leave required publication pending while claiming success.
