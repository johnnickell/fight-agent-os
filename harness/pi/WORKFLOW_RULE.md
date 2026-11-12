# Native tools and Fight workflow

Use Pi's native `read`, `write`, `edit` and shell tools within the assigned task. Narrow discovery with paths,
metadata, symbols or graph results, then read the needed files directly. Load governing and selected-skill
instructions directly. Follow native truncation and continuation guidance. No enrollment, screening receipt,
evaluator credential or provider preflight is required. Keep credentials and private data out of outputs.

Write human-facing replies, plans, reports and PR descriptions in plain language: state the answer first,
use familiar words, explain necessary technical terms and remove repetition. Preserve facts, uncertainty,
requirements and exact commands. Check the wording before sending; no separate rewriting agent is required.
`what`, `ask`, `ci` and `simplify` select inspection; `handoff` and `learn` select scratch work. None selects
source repair or independent acceptance. An authorized cleanup uses the target's normal Work selection.

## Session-local workflow

Interactive `/skill:work`, `/skill:review`, `/skill:qa`, `/skill:land`, `/skill:sync` and other installed skill
invocations select responsibilities. Pass `--checkout /absolute/selected/checkout` when specifying an exact path
at invocation. Honor a checkout choice already given in the current request, including a choice to create an
isolated worktree. Apply the owning skill's checkout rules and continue in the same run.
A trusted process launcher supplies role, task and checkout. A launcher takes precedence on every startup/reload.
Otherwise the latest session-wide selection survives tree navigation and restart only with matching session and
launcher provenance. Forked, legacy or unverifiable history defaults to Reviewer; fresh unassigned sessions
default to Engineer. Failed persistence holds workflow authority until storage repair and reload.

Inspect `fight_workflow_status.workflow` or `/fight-workflow`: `skill`, `effect`, `taskId`, `checkout`, `mode` and
`launcherRole` describe the selection. Build has phase `qa`, skill `build` and effect `coordination`; that is not
a QA-worker mismatch. Sync also has phase `qa`, skill `sync` and effect `coordination`: it coordinates one
previously accepted TASK through fresh Engineer then Land, not routine Review/QA or new implementation.
Use `/skill:sync TASK-NNNNN [--checkout /absolute/selected/checkout]`; current acceptance and the same existing
OPEN PR remain required by its owning skill. With no explicit path, Build and Sync report a null checkout
and resolve it within the invoking repository under their owning skills. Only interactive operator input or a
trusted host binding changes
authority. RPC, model prompts, stage labels, fixtures and profiles cannot promote a role. `/fight-workflow role engineer|reviewer|qa|land`
is an explicit native-TUI alternative. SDK subordinates remain Reviewer unless the trusted host binds the actual
fresh stage session through the sole SDK Build route. Actual Build selection is required; no route flag, profile
or tool visibility supplies authority, and SDK subordinates cannot dispatch nested Build stages. Advice-only assistants cannot become canonical
Review/QA owners by profile or prompt. An execution status is never independent acceptance.

- Engineer (`work`): edit assigned normal project files, including `.env.example` and project `.pi/skills`.
- Reviewer: read project files; write only `.runs/reviews/<TASK-ID>/` at the canonical base or selected checkout,
  plus `.runs/tasks/<TASK-ID>/runs/` scratch. The canonical base report is authoritative.
- QA: execute checks/probes and appropriate Git operations; write `.runs/qa/<TASK-ID>/` and task-owned scratch,
  without repairing source/tests. Build writes `.runs/build/<TASK-ID>/` coordination evidence, not source.
- Land: perform assigned project edits and separately authorized delivery work.
- Sync: write `.runs/build/<TASK-ID>/` coordination evidence and task-owned scratch, not source or independent
  reports. Delegate accepted-work integration and same-PR delivery through the existing process-stage path;
  the sole SDK stage route requires actual Build selection and is unavailable to Sync.

Other installed skills retain their owning instructions and effect shown by `fight_workflow_status`.
For an explicitly assigned release assessment, the provider's release protocol uses version-owned reports and
runs under `.runs/handoffs/release-<version>/`; only the independent stage owner writes its report. This adds no
release support to a TASK-only dispatcher and grants no source repair, self-acceptance or publication authority.

## Compaction and verified continuation

Leave native automatic compaction enabled. Keep required recovery facts explicit in ordinary session messages:
objective, accepted constraints and decisions, completed/pending work, exact unanswered questions, source revisions
and evidence references, uncertain operation outcomes, and the next safe action. Native summaries are lossy;
long tool output and thinking are not reliable places to retain the only copy of a required fact.

After compaction or resume, before dependent work:

1. Inspect `fight_workflow_status` for current skill/effect/TASK/checkout and live stage binding. A historical
   assignment or summary grants nothing. A held, expired or mismatched selection pauses the affected action;
   do not restore authority by repeating a skill command from history.
2. Read current governing and selected-skill instructions directly. Confirm the objective/source boundary against
   the current assigned record, permissions and any applicable claim, approval and operation owner. Local sessions
   do not invent managed grants, claims or leases. Recheck operation-specific authority at its normal admission.
3. Reconcile retained facts with current authorized evidence. Keep exact unanswered questions unresolved until an
   authenticated answer is associated with that question; a concurrent or ambiguous answer is not a decision.
   Missing/stale evidence and revoked approval pause dependent work. Name the missing fact and its owner rather
   than guessing. Use only bounded authorized native reads; do not bulk-load history or another role's context.
4. An interrupted or timed-out tool call has an uncertain outcome, not permission to replay. Inspect its original
   operation/result through its owner before any dependent action or retry. If that cannot establish safe
   continuation, preserve the pause and request the precise missing evidence or authority.

Use native session history and `/compact` or exact-session resume; never copy a coordinator/reviewer transcript
into a fresh independent worker. Do not add a parallel checkpoint store without a demonstrated missing required
fact and approved ownership/retention. Keep summaries/transcripts out of ordinary logs and hand off only authorized
facts and evidence references. See the package's `COMPACTION.md` for qualified capabilities and limitations.

These responsibilities do not intercept native file tools or shell. Pi and OS permissions still apply; extension
code, subprocesses and SDK OS calls are not confined. The independent deterministic tool policy requires explicit
operator selection and qualifies only its documented shell routes. Native tool availability never expands task,
publication, merge, release, deployment or destructive authority. Use canonical reports and preserve independent
Review/QA obligations, existing sessions/transcripts, account settings and historical receipts/accounting liabilities.
