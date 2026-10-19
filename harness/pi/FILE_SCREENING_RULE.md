# Project access and Jev discovery

These instructions ship with the installed Fight harness and apply in every participating project. A target
project does not need a copy of this file or a new base skill. Local project instructions and task authorization
still govern the work; these tool defaults do not grant publication, merge, deployment or destructive authority.

## Read files normally; screen unfamiliar discovery

Before each file read, decide whether there is a concrete reason to read that file. Strongly prefer
`jev_read_file` before unfamiliar or speculative discovery reads. Narrow candidates using paths, symbols,
metadata or graph results; screen a small explicit batch when several candidates compete. Use a specific
information need. Do not repeatedly rephrase a negative judgment to manufacture a positive.

Read known targets directly with Pi's native `read` using `path` and optional `offset`/`limit`.
This includes logs, reports, images, project instructions and cross-project provider references. Follow Pi's
returned truncation/continuation guidance. Do not supply harness receipts, `need`, `evidence` or `exception`
arguments to native `read`; these are not prerequisites for reading.

TASK-00498 removes native-read interception from both the enrolled profile and the opt-in deterministic
policy. The harness does not veto native reads by root, extension, control bytes, link type, instruction
witness, workflow role, enrollment, budget or integrity state. Pi and operating-system permissions still apply;
a missing file or native runtime error remains an error. Read permission does not authorize disclosure,
mutation, publication or unrelated work. Keep credentials and private data out of outputs.

Jev relevance screening and `file_locate` remain optional discovery tools with their own bounds and source
policies. A negative, unavailable or rejected discovery result does not block native reading of a concretely
needed file. Briefly state that need and read directly; do not label it screened or invent a receipt.
Optional evaluator disclosure still requires its existing authorization and budget.

## Workflow roles

Standing repository enrollment includes its canonical main checkout and reciprocally registered Git worktrees,
including external worktrees created during this session. It does not include clones, matching remotes or
unregistered directories. File mutations and optional Jev use the same current membership and inherited exclusions;
no setup, per-file confirmation or budget reset is needed for a valid member. Git metadata is inspected internally,
not exposed for direct mutation. Non-Git enrolled directories retain their exact original root.

Select the task and intended checkout in the interactive invocation, for example
`/skill:work TASK-NNNNN --checkout /absolute/selected/checkout`. This names the operator's existing checkout
choice, not another enrollment or approval. A selected future Work checkout becomes writable after Git registers
it. Fresh sessions default to their containing registered checkout; trusted Build launchers pass task and checkout.
Changing directories in shell does not select another source-write target. Source writes in sibling checkouts
remain unavailable. See the complete [skill/effect inventory](FILE_SCREENING_PROFILE.md#installed-skill-effects).

The trusted launcher phase or explicit user `/skill:work`, `/skill:review`, `/skill:qa`, `/skill:land` selects the
role. Only interactive user input changes it automatically; model/extension and RPC prompt text cannot promote
the role. The operator can use `/file-screening role engineer|reviewer|qa|land`. Inspect `/file-screening` for the
active role. On every startup/reload, an explicit trusted launcher takes precedence. Otherwise, the latest
session-wide selection survives tree navigation and restart when its session identity and launcher provenance
match. Forks, legacy or unverifiable history, and removal of a prior launcher default to Reviewer until the
operator selects a workflow. A fresh unassigned local session defaults to Engineer. Failed persistence holds
mutations and optional discovery; correct the storage problem and reload before continuing. Native reads remain available during that hold; do not evade mutation controls.

Use `file_screening_status.workflow` to identify the selected skill, effect, task, checkout and mode.
The top-level `phase` is the workflow role: Build uses `phase: qa` with `skill: build` and
`effect: coordination`. That is a valid coordinator, not a QA-worker mismatch. Do not request a relaunch
or role promotion based on `phase` alone. See the [status contract](FILE_SCREENING_PROFILE.md#installed-skill-effects).

- Engineer (`work`): read and edit normal project files, including `.env.example` and project `.pi/skills`.
- Reviewer: read normal repository files; publish only `.runs/reviews/<TASK-ID>/` at the canonical base or selected
  checkout, plus `.runs/tasks/<TASK-ID>/runs/` scratch. The base report remains authoritative.
- QA: read files, execute checks/probes and use appropriate Git operations; write probes/evidence under `.runs`,
  without repairing source or tests. Its report directory is `.runs/qa/<TASK-ID>/`; Build uses
  `.runs/build/<TASK-ID>/` for coordination and cannot edit source. All have task-owned execution scratch.
- Land: read/edit project files and perform authorized Git delivery work.

Project roots and explicit exclusions constrain writes and optional discovery in every role. Credentials, Git
internals, operator state, Pi runtime configuration and the running harness remain protected from mutation. Role changes come from the operator, never
from an agent choosing a more permissive role to finish an action. Independent review/QA obligations remain.
Query/status modes do not write source or durable planning outputs. A removed or changed membership is denied
before the next effect. For `repository-metadata-unavailable`, inspect `git worktree list --porcelain` from the
enrolled repository; repair with `git worktree repair` or return to a valid registered checkout and reload.
Never hand-edit pointers, reenroll each worktree, discard liabilities or bypass mutation controls.

## Native shell remains independent

Native Agent bash and user `!`/`!!` run through Pi without Jev command screening, command disclosure or a Jev
confirmation dialog. No Jev setup is required for native shell. Existing task permissions and native runtime
permissions still apply. Keep credentials out of commands and respect authorized task and mutation boundaries.
Mutation checks do not confine native reads, shell, descendants or trusted SDK calls; do not claim OS isolation.

Use the normal provider-owned Build workflow and canonical role reports. Preserve budgets, accounting and
existing task permissions. For optional Jev batches, inspect every result and incomplete coverage. A current
cache hit reuses a judgment after local source checks; it is not permission for siblings or a fresh evaluation.
