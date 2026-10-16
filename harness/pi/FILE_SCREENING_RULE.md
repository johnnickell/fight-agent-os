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

Every enrolled project can read its `AGENTS.md`, `README.md`, `planning/tasks/BOARD.md`, `ROADMAP.md` and
`planning/ROADMAP.md` normally. No fixed content digest or separate whitelist enrollment is required. Native
loaded-context and advertised-skill paths also read directly, including outside the project root. Reading those
instructions establishes their exact relative Markdown references, up to eight links deep and 128 references;
changed source links or removed native witnesses invalidate that access. This grants no sibling directory,
external code or Jev-disclosure permission. Ordinary project content cannot establish external access.

Pi's installed README and Markdown files beneath its advertised documentation directory (including
`extensions.md`, `sdk.md`, `sessions.md` and `tui.md`) also read directly in every role. Resolve these paths from
Pi's native documentation locations, not the project directory. Use `read` with `path` and follow continuation
offsets to finish a long document; `file_locate` can select a heading. No Jev request or instruction enrollment is
needed. This is read-only documentation access, not access to sibling runtime code, settings or credentials,
and it grants no Jev disclosure. Exclusions and inode checks still apply. A changed document returns
`native-documentation-changed`; restart Pi to load the current installation before retrying.

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

The trusted launcher phase or explicit user `/skill:work`, `/skill:review`, `/skill:qa`, `/skill:land` selects the
role. Only interactive user input changes it automatically; model/extension and RPC prompt text cannot promote
the role. The operator can use `/file-screening role engineer|reviewer|qa|land`. Inspect `/file-screening` for the
active role. On every startup/reload, an explicit trusted launcher takes precedence. Otherwise, the latest
session-wide selection survives tree navigation and restart when its session identity and launcher provenance
match. Forks, legacy or unverifiable history, and removal of a prior launcher default to Reviewer until the
operator selects a workflow. A fresh unassigned local session defaults to Engineer. Failed persistence holds
tools; correct the storage problem and reload before continuing. Never use another tool to evade that hold.

- Engineer (`work`): read and edit normal project files, including `.env.example` and project `.pi/skills`.
- Reviewer: read normal project files; direct writes/edits are limited to the project's `.runs` reports and probes.
- QA: read files, execute checks/probes and use appropriate Git operations; write probes/evidence under `.runs`,
  without repairing source or tests.
- Land: read/edit project files and perform authorized Git delivery work.

Project roots and explicit exclusions stay in force for every role. Credentials, Git internals, operator state,
Pi runtime configuration and the running harness remain protected. Role changes come from the operator, never
from an agent choosing a more permissive role to finish an action. Independent review/QA obligations remain.

## Assess every final shell command

Every final native Agent Bash and user `!`/`!!` command receives Jev risk assessment with the active role, after
local credential-disclosure checks and configured command-prefix expansion. Routine, already-authorized work
proceeds on an ordinary result; risky or uncertain/unavailable results require the native one-command decision.
Chat approval does not answer that dialog, and one decision is not reused. Ordinary is not a safety guarantee.

A `command-secret-disclosure-held` result is a local refusal of that command before provider disclosure or
execution, not a persistent hold to clear. Follow the returned recovery. Never embed credentials or hide the
same operation in a script to evade the check. Source scripts, future descendants and shell environment values
are not inspected. These role checks cover explicit file tools; shell classification is advisory and cannot
confine arbitrary scripts, Git effects or trusted SDK calls. Do not claim OS isolation or universal interception.

Use the normal provider-owned Build workflow and canonical role reports. Preserve budgets, accounting and
existing task permissions. For Jev batches, inspect every result and incomplete coverage. A current cache hit
reuses a judgment after local source checks; it is not permission for sibling files or a fresh evaluation.
