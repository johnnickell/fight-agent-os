# Project access and Jev discovery

These instructions ship with the installed Fight harness and apply in every participating project. A target
project does not need a copy of this file or a new base skill. Local project instructions and task authorization
still govern the work; these tool defaults do not grant publication, merge, deployment or destructive authority.

## Read files normally; screen unfamiliar discovery

Before each file read, decide whether there is a concrete reason to read that file. Strongly prefer
`jev_read_file` before unfamiliar or speculative discovery reads. Narrow candidates using paths, symbols,
metadata or graph results; screen a small explicit batch when several candidates compete. Use a specific
information need. Do not repeatedly rephrase a negative judgment to manufacture a positive.

Read known targets directly with `read` and `path`: assigned files, changed files under review, failure locations,
verified references, core project instructions and the selected skill. `need`, `offset` and `limit` are optional.
Direct project reads do not require a receipt, a structural lookup or operator confirmation. Use the smallest
useful range and follow the returned continuation offset. Default output is 200 lines, at most 1,000 requested
lines and 32 KiB; local text files are capped at 1 MiB. A source-size limit is not a provider outage.
This includes canonical review/QA reports and their evidence in registered checkouts. Read those by path;
calling a file an instruction or required evidence does not establish an exception witness. Use
`exception: "instruction"` only for host-established instructions or their exact linked Markdown references.
That mode follows the same reference chain and bounded continuation as an ordinary instruction read.

Every enrolled project can read its `AGENTS.md`, `README.md`, `planning/tasks/BOARD.md`, `ROADMAP.md` and
`planning/ROADMAP.md` normally. No fixed content digest or separate whitelist enrollment is required. Native
loaded-context and advertised-skill paths also read directly, including outside the project root. Explicitly
selected installed hidden skills (including `build` and `next`) have the same exact read access. Pi still hides
`disable-model-invocation` skills from automatic invocation; a read grant does not authorize invoking them.
Native `~/` text paths resolve before applying the ordinary authority checks. Reading those
instructions establishes their exact relative Markdown references, up to eight links deep and 128 references;
changed source links or removed native witnesses invalidate that access. This grants no sibling directory,
external code or Jev-disclosure permission. Ordinary project content cannot establish external access.

Pi's installed README, Markdown documentation (including `extensions.md`, `sdk.md`, `sessions.md` and `tui.md`)
and published example source/documentation also read directly in every role. Resolve paths from Pi's native
README/docs/examples locations, not the project directory or an inferred global installation. Beneath the
examples directory, `.md`, `.ts`, `.tsx`, `.mts`, `.cts`, `.js`, `.jsx`, `.mjs`, `.cjs` and `.json` text files read
normally; nested `node_modules` is excluded. This includes `examples/extensions/{timed-confirm,modal-editor,question,commands}.ts`.
Use `read` with `path` and follow continuation offsets; `file_locate` can select a heading or symbol. No Jev request
or instruction enrollment is needed. This read-only access grants no writes, runtime code outside examples,
settings/credentials, linked external files or Jev disclosure. Exclusions and inode checks still apply. A changed
document/example returns `native-documentation-changed`; restart Pi to load the current installation before retrying.

If Jev is negative, uncertain or unavailable but a file is concretely needed, briefly state the reason and read
it directly. A negative relevance judgment is not an access denial or proof that information is absent. Do not
claim a direct read was screened. Explicitly supplied receipts or evidence still must be current; stale or invented
receipts are errors, not automatic fallbacks. `file_locate` remains optional: use `symbol` for code identifiers or
`heading` for exact Markdown titles, then use its returned bounded range. It is not a prerequisite for Board reads.

Access denials remain different: roots, explicit operator exclusions, secret files, symlinks/hardlinks and runtime
integrity checks still apply. Never use `cat`, encoding or another shell route to evade an actual access denial.
`.env` and `.env.local` remain protected; `.env.example` is an ordinary template file, not a promise its contents
are nonsecret. Images read directly with `path`, retain format/size checks, and never go to the text evaluator.

## Workflow roles

Standing repository enrollment includes its canonical main checkout and reciprocally registered Git worktrees,
including external worktrees created during this session. It does not include clones, matching remotes or
unregistered directories. Direct reads and optional Jev use the same current membership and inherited exclusions;
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
tools; correct the storage problem and reload before continuing. Never use another tool to evade that hold.

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

Project roots and explicit exclusions stay in force for every role. Credentials, Git internals, operator state,
Pi runtime configuration and the running harness remain protected. Role changes come from the operator, never
from an agent choosing a more permissive role to finish an action. Independent review/QA obligations remain.
Query/status modes do not write source or durable planning outputs. A removed or changed membership is denied
before the next effect. For `repository-metadata-unavailable`, inspect `git worktree list --porcelain` from the
enrolled repository; repair with `git worktree repair` or return to a valid registered checkout and reload.
Never hand-edit pointers, reenroll each worktree, discard liabilities or use shell to bypass an actual denial.

## Native shell remains independent

Native Agent bash and user `!`/`!!` run through Pi without Jev command screening, command disclosure or a Jev
confirmation dialog. No Jev setup is required for native shell. Existing task permissions and native runtime
permissions still apply. Keep credentials out of commands and never use shell to bypass an actual file denial.
Explicit-file checks do not confine shell, descendants or trusted SDK calls; do not claim OS isolation.

Use the normal provider-owned Build workflow and canonical role reports. Preserve budgets, accounting and
existing task permissions. For optional Jev batches, inspect every result and incomplete coverage. A current
cache hit reuses a judgment after local source checks; it is not permission for siblings or a fresh evaluation.
