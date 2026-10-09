# Behavioral QA and visual evidence

## Sequence and authority

The local [qa skill](../../.pi/skills/qa/SKILL.md) runs in an explicitly assigned independent session after
technical acceptance and before ordinary landing. Default flow: `work → review → qa → land → human merge`.
The planned managed QA Engineer consumes the same boundaries; a local report is not an implemented runtime gate.
An authorized [draft PR for hosted evidence](LANDING.md#draft-publication-to-obtain-hosted-evidence) may precede
QA while disclosing that QA remains pending. The skill also accepts an already-open PR for pre-merge QA or reruns.

QA does not change implementation, tests, requirements, Planning status or technical review reports. It may run
owned isolated services, exercise application writes against disposable data and write ignored QA artifacts within
the invocation's scope. External side effects, shared/production data, new credentials, tool installation and
artifact publication require their own authority. Never put secrets or personal data into commands or evidence.
A contributor to the implementation or its submitted acceptance evidence cannot independently pass that work;
builder self-checks are diagnostic only. Independent QA may produce its own verification evidence.

## Applicability

After technical acceptance, route behavior-affecting changes to QA before ordinary landing. A UI is not required:
APIs, commands, libraries/domain logic, workers, integrations, operational tooling and executable instructions
can all expose observable contracts. Review owns structural/code/test quality; QA tries to find where the reviewed
change fails in use. A green build or thorough technical review does not substitute for that exercise.

Choose the smallest useful set of scenarios for the changed contract and likely regressions. During review,
record affected behaviors and suggested challenges; QA refines and executes them. Pure spelling, formatting or
historical-record changes with no behavioral consequence can have criterion-specific N/A reasons recorded in the
technical handoff or canonical QA report. Consider runnable commands and workflow/skill decisions before calling
documentation N/A. Missing applicability assessment routes to `qa` to assess it; absence of UI, small diff size,
passing unit tests or missing tools are not N/A reasons. Landing verifies the stated reasons against the scope.

Once a required or requested scenario is selected, do not silently drop it to obtain PASS. A narrowed diagnostic
run cannot establish complete TASK QA unless remaining scenarios have applicable prior evidence or authorized
exclusions. Distinguish visual-evidence N/A from overall behavioral-QA N/A: an API can pass QA without screenshots.

## Subject and runtime

Read the TASK, parent and current canonical technical review under [review standards](REVIEW.md). Identify repository,
TASK/requirements revision, branch, full baseline and implementation commits, working-tree state, review identity
and any accepted reconciliation bridge. For PR intake, verify base/head from the hosting service and resolve the
TASK from its record/body. Missing or ambiguous identity is INCOMPLETE, not permission to guess a TASK.

For executable scenarios, prove that the running process uses the identified source: inspect its owned launch command, checkout/mount or
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

## Adversarial scenarios

Start from accepted requirements, public contracts and the reviewer’s risk notes. For each selected scenario,
name the plausible failure, input/action, expected observable result and its independent source before execution.
Do not derive expected values by copying the implementation's current output or repeating its algorithm.
Exercise the intended path and the most consequential ways it might fail; prioritize changed behavior and nearby
regression risks rather than running every category below for every TASK.

Useful challenges include empty/malformed/oversized or boundary inputs; the wrong actor or resource owner;
duplicate submission, retry or reordered operations; cancellation, dependency failure and partial writes;
state transitions, persistence and restart; and relevant concurrency, accessibility or responsive behavior.
Assert rejection and absence of forbidden effects as well as successful output. Test authorization with isolated
test identities. Any fault simulation belongs at an existing, controlled test-environment boundary; record it
and its limits. Do not edit the implementation or seed a defect merely to demonstrate QA FAIL.

| Surface | Useful exercise and evidence |
|---|---|
| Browser UI | Perform actual navigation, input and submission; inspect relevant loading, empty, success, error and permission states. Try keyboard/focus and narrow viewports where affected; inspect associated network/state effects. |
| API | Send real requests to the identified isolated service with valid and adversarial inputs/identities. Check status, headers and response contract plus state, emitted effects and forbidden writes where relevant. Keep sanitized requests/responses and state evidence. |
| CLI / TUI | Invoke real commands; assert output, exit status and effects. Use an actual PTY for interactive input, resizing, cancellation and exit. A redirected transcript does not prove terminal layout. |
| Library / Domain / worker | Use an existing entrypoint or a disposable driver through the real public contract and owned dependency setup. Check returned values, errors, state, retries and effects; no invented HTTP endpoint or production wrapper solely for QA. |
| Tooling / executable documentation | Run the affected owning tool or documented command safely in isolation. For skill/workflow decisions, trace concrete normal and adversarial requests through actual entrypoints and references, recording route, stop boundary and evidence. Label instruction walkthroughs explicitly; they do not prove live agent/tool execution. |

For planning-path changes, independently exercise the full planning-reference audit across live and archived records, Wayfinder notes, templates and generated views. Resolve Markdown links/anchors, code-span/plain-text local artifact paths and absolute checkout paths to their *intended* targets, checking the [scratch relocation manifest](SCRATCH.md) and actual destinations. Classify historical transcripts, templates and external links; disclose missing evidence and report incorrect/stale paths as QA findings. Do not repair Planning or alter Review/QA receipts; a passing planning validator or the builder's own audit is not behavioral QA proof.

For dependency behavior that cannot safely be exercised, use a supported isolated substitute only when it proves
the scoped contract, disclose the substitution, and leave required real-integration behavior INCOMPLETE. Existing
tests may help set up or reproduce a case; merely rerunning the build is not the entire QA investigation.

### Disposable probes

When existing tools do not expose a useful check, write a throwaway script in the ignored run directory that
executes against the reviewed implementation. Retain the script, sanitized invocation, inputs/fixture provenance,
seed when randomized, expected assertions, observed output/exit and relevant state changes so another agent can
reproduce it. Capture its digest with the other evidence. Use the repository's runtime/wrappers and real contracts;
do not rewrite business logic in the probe, patch source/tests, install a new framework or promote it into the
product suite during QA. A confirmed defect goes to `work` for its durable regression and repair.

Bound time, request volume/concurrency and data effects before executing probes. Prefer a few well-chosen cases;
no unbounded fuzzing, load or destructive testing is implied. Use owned disposable data/resources, record cleanup
or retention, and stop on unexpected external effects. Missing authority or prerequisites leave the relevant
scenario INCOMPLETE with a specific recovery action; they do not authorize modifying shared or production systems.

## Visual evidence

For affected UI/TUI behavior, capture Before from the recorded baseline and After from the reviewed implementation.
Use comparable scenario, data, viewport/terminal dimensions, theme and zoom; note intentional differences. Wait
for the intended render state (including assets/fonts where relevant) and capture loading/error states separately
when they are part of the contract. Do not hide a genuine transient defect to make a cleaner screenshot.

Choose readable, well-framed captures: enough page/screen context to orient the reviewer, with a detail capture
when a full-page image makes the change illegible. Keep comparable framing and include the relevant success,
failure or responsive state rather than only a polished initial screen. Inspect every final sanitized image
before citing it for correctness, clipping, legibility and accidental secrets/personal data. Retain captions that
explain the action and visible result; do not alter application content to manufacture visual proof.

For a new screen, capture the real prior state or state "Not present before"; never invent a Before image.
Unavailable baseline or capture tooling is a limitation, not evidence of absence. Missing required visual proof
is INCOMPLETE. If TUI image capture is unavailable, retain a sanitized real transcript/recording and explain which
criteria it can and cannot prove. It cannot substitute for required visual layout evidence.

Screenshots support behavioral assertions; they do not replace interaction evidence. Record capture time,
scenario, commit identities, dimensions, artifact references and SHA-256 digests of the final sanitized artifacts.
Keep real failure captures and reproducible steps. Preserve relevant console/network/process diagnostics without
dumping credentials, personal data or unrestricted logs. Non-visual scenarios use request/output/state artifacts;
do not manufacture screenshots of code or logs simply to populate a Before/After table.

## Dispositions and repair routing

| Disposition | Required meaning | Next action |
|---|---|---|
| QA PASS | Independent session, applicable technical acceptance, known current subject (and runtime for executable scenarios) and every required scenario passed with sufficient evidence | Authorized `land`, or human pre-merge handoff for an existing PR |
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
Remove it or explain visual N/A for non-visual work, while retaining the behavioral scenarios/results and relevant
request, output or state evidence in the PR verification summary. Publish only sanitized artifacts within the approved audience; an
existing PR or QA invocation alone does not authorize posting comments or uploading private captures.

Mechanical documentation/Board reconciliation can reuse evidence when effective behavior/runtime and dependencies
are unchanged and the provenance bridge proves this. Material changes rerun affected scenarios; explain why
unaffected evidence remains applicable. Broad reruns need interaction risk or uncertainty, not just a changed OID.
Retain reports/captures outside disposable worktrees. Stop only owned temporary processes and remove only proven
disposable data when authorized; preserve useful failed-run environments for repair. Landing cleanup must not
remove the only copy of QA evidence or leave required publication pending while claiming success.
