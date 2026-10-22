# Standing project screening (TASK-00309 implementation candidate)

This profile combines normal project-file access, optional [Jev relevance discovery](FILE_SCREENING.md),
automatic session binding and shared monthly accounting for optional Jev discovery. It is not a sandbox or universal file-access boundary. Ordinary authorized
shell remains useful, including `rg`, `cat`, `git diff`, checks, commits, non-force push and owned-worktree cleanup.
Shell source reads can bypass relevance screening. Same-user shell can access operator state; discovery exclusions do not provide OS containment. Restrictive shell containment/MCP replacements are future work.

The [TASK amendment](../../planning/tasks/00309-TASK.md#approved-interim-scope-correction--2026-10-15) supersedes
the earlier candidate's blanket shell denial and finite enrollment. Historical checks are not current acceptance.
John subsequently approved the narrow [AC8/E1 evaluation deferral](../../planning/tasks/00309-TASK.md#approved-comparative-evaluation-deferral--2026-10-15):
comparative usefulness remains unestablished, with no effectiveness, recall or net context/latency/billed-cost
savings claims. Independent Review and remaining behavioral/visual QA still apply; green controlled checks do not
replace them. No global installation/trust/settings or real enrollment occurs by reading these instructions.

TASK-00500 removes native write/edit interception, extending TASK-00499's native-read change. Native file tools
retain Pi behavior even while optional discovery is held. Workflow metadata records role and task ownership;
it is not a file-tool permission boundary.

TASK-00492 supersedes the earlier shell-assessment requirement. Jev setup and enrollment are optional; an
unconfigured installation uses native tools without a setup prompt or provider request. The following setup
applies only when the operator chooses Jev file screening. Historical records remain evidence of earlier behavior.

## Operator setup and inspection

1. In reviewed native **stable Pi 1.x from 1.2.0 onward on macOS APFS**, load the package through an existing
   trusted installation or explicitly with `pi -e /absolute/provider/harness/pi`. Never automatically install
   globally, trust a project or alter settings. The shared [runtime compatibility contract](TOOL_POLICY.md#runtime-compatibility)
   requires actual hook registrations and APIs; older/prerelease/new-major runtimes, unsupported platforms,
   replaced tools and simultaneous `--fight-tool-policy` selection hold this profile. Compatible minor/patch
   upgrades need no allowlist edit, reenrollment or accounting reset. Fresh probes cover Pi 1.2.0 and 1.3.0;
   future releases are not individually verified. Restart after guard/runtime upgrades and inspect the profile.
   TASK-00160's optional deterministic policy remains separate, including its exact-script shell policy.
2. Reuse [Pi-managed OpenRouter setup](README.md#jev-setup-and-readiness-operator-only), with the pinned Jev
   evaluator and `authorized-supplied-state` disclosure. Setup resolves no credential and makes no request.
   Shared connection/price setup is standing for the approved pins and limits, with no periodic renewal.
   TASK-00450's timer removal is integrated from approved `develop`; existing timestamps/identities need no refresh.
   Changed pins or invalid settings need explicit repair/setup, not automatic higher-price approval. Project/source authority remains separate. `/jev check` is a separately confirmed billed synthetic request, not
   source disclosure authority.
3. Run `/file-screening setup` in the intended canonical project root. Enter extra protected paths and exact
   governing/selected-skill instruction files (semicolon-separated). Explicitly consent to source disclosure, then inspect the full effective preview. Use Up/Down or Page Up/Down
   to scroll and Home/End to jump; the footer shows active bindings and position. Enter continues to a separate
   short save confirmation; it does not save from the preview. Escape cancels either step without changes.
   Enlarge a terminal too small to display the preview controls before continuing. No JSON editing or session-ID
   hashing is needed. Register each independent repository explicitly; its canonical main checkout and verified
   linked worktrees inherit that enrollment automatically, including newly created external worktrees;
   there is no hardcoded target/provider project. A changed connection authority with other enrolled roots requires
   reviewing those projects rather than silently transferring their disclosure.
   Repeating `/jev setup` with identical effective choices preserves the saved connection identity and its
   original confirmation metadata. Changed disclosure or ceilings still establish a different authority.
   If `stale-file-policy` reports an old enrollment binding, inspect `/jev inspect`, then use
   `/file-screening rebind`: review the current connection and **all enrolled projects** in the scrollable
   preview, then confirm saving. Recovery retains roots, exclusions, instruction witnesses, disclosure flags
   and protected runtime paths. It only reduces spending ceilings if the connection is more restrictive;
   it never resets accounting or pending liabilities. Cancel makes no change; settings/enrollment changes
   while the dialog is open invalidate the preview. Revoked enrollment and historical conversion remain
   separate operations. Restart Pi after installing a harness repair; otherwise `/reload` after saving.
4. `/reload` binds the actual session. Enrollment is **standing until explicit revocation or authority change**:
   no expiry, routine renewal, new-session/day/month prompt or periodic lease. Resumes retain usage; fresh roles
   bind independently under the same account. Monthly reset changes available monthly funds, not enrollment.
5. `/file-screening inspect` or `file_screening_status` is read-only and makes no paid/credential request. `active`
   describes optional file-screening authority and coverage, not provider readiness, accuracy or safety.
   `degraded` gives the precise setup/authority/provider/price/credential/budget action; `disabled` means not enrolled.
   A per-file refusal such as `source-limit` does not degrade an otherwise active session or clear a prior provider
   failure. The rejected file still has no positive receipt; a concretely needed file can be read directly within local limits.
   Status reports available/reserved/settled funds, all-month liabilities, reset, binding limit and actual session/phase.
6. `/file-screening revoke` confirms revocation for **all roots listed in this enrollment**. Their durable revoked
   record holds new/resumed optional discovery tools rather than turning deletion into an implicit opt-out. Existing runtimes
   recheck before new discovery effects; already-running shell/descendants/remote effects are not guaranteed stopped. Preserve
   every ledger, pending charge and finite test counter. Setup may explicitly reenroll under reviewed authority;
   neither a new revision nor a new session resets accounting.

Settings live outside roots/repository instructions in the existing private operator directory:
`~/Library/Application Support/Fight Agent OS/jev/` (directory 0700, files 0600, operator-owned, no symlinks/hardlinks).
`screening-profile.json` v2 has `expiresAt: null` and revocation state. Its legacy `commandDisclosure` field
remains readable for compatibility but conveys no active command-screening behavior; new profiles default false. Generated
`enforcement-<revision>.json` retains discovery authority and integrity checks. Explicit operator exclusions,
operator state and sensitive names retain their optional-discovery protections, not native mutation restrictions. Project instructions and `.pi/skills`
remain ordinary native-file targets under task authorization; `.env.example` is an ordinary template. Authority/source/tool drift holds old
runtime receipts and requires correcting the actual change and reload; no daily/session/monthly renewal is added.

**Historical compatibility:** v1 profiles were deliberately timeboxed. They hold with
`historical-profile-conversion-required`, even if their old date remains in the future. `/file-screening convert`
previews all formerly bounded roots and explicitly confirms standing source disclosure.
Cancel retains the original bytes; `/file-screening revoke` can withdraw them without converting disclosure.
Conversion retains roots, exclusions, exact witnesses, ceilings and all original accounting, with a new authority
revision to invalidate old receipts. It never resolves credentials, sends a request or resets finite one-off tests.
Do not convert a real operator's profile without explicit authority. Historical runtime ledgers/grants are retained,
not imported into a fresh routine execution allowance; the coordinator reconciles any in-flight effects first.

## Project reads, discovery and workflow roles

Native Pi `read` is independent of this profile, including when enrolled, revoked, misconfigured or held.
TASK-00499 removes the read replacement and read-blocking hooks from both harness profiles. Text, images,
terminal-formatted logs, links and cross-project instructions use native path/range/format behavior and OS
permissions. No enrollment, instruction witness, receipt or special exception is needed. Native errors remain
honest errors. Use the [shared rule](FILE_SCREENING_RULE.md) for direct reads and optional discovery.

Optional `jev_read_file` and `file_locate` retain their bounded source and disclosure checks; failures do not
prohibit native reads, writes or edits. Workflow roles and selected checkout below describe task responsibilities.

The operator's interactive `/skill:work`, `/skill:review`, `/skill:qa` or `/skill:land` selects its role, as does a
trusted launcher phase. `/file-screening role engineer|reviewer|qa|land` explicitly selects a role in the TUI;
model/extension and RPC prompt text cannot promote roles. On every startup/reload an explicit trusted launcher
takes precedence over stored selections, including earlier operator overrides. Local sessions restore the latest
session-wide selection, independent of the navigated branch, only with matching session identity and local
launcher provenance. Forks, legacy/unverifiable history and removal of a prior launcher default to Reviewer;
the operator can then select the intended workflow explicitly. Fresh local sessions default to Engineer.
Selections must persist before changing authority. Failed persistence holds optional discovery; fix storage and reload.
An uncertain failed append is superseded by a Reviewer checkpoint so reload cannot restore privilege from it.

- Engineer and Land can read/edit normal project files, including instructions, `.env.example` and `.pi/skills`.
- Reviewer writes its selected task's `.runs/reviews/<TASK-ID>/` and task execution scratch.
- QA writes its selected task's `.runs/qa/<TASK-ID>/` and task execution scratch, and can execute checks/probes.
- Every role retains its existing task authorization; discovery exclusions do not restrict native file tools.

These are workflow responsibilities, not native file-tool checks or shell isolation. Native shell does not receive Jev assessment. Selecting Land does not grant publication, destructive or unrelated-work authority.

### Inherited repository membership

The harness resolves `.git`, `commondir` and reciprocal registered `gitdir` pointers as bounded canonical filesystem
metadata, without calling Git or trusting remote URLs, directory names, prompt JSON or relevance judgments.
It checks current membership for optional discovery and pins observed root/Git-directory inode identities during
the process. It discovers at most 128 linked worktrees; unsupported metadata or limits hold discovery, not native file tools. Repository-relative
exclusions propagate to each checkout; external exclusions remain exact. A removed/forged registration withdraws
only that member, and an unrelated unavailable enrollment does not poison valid projects. Replaced observed
identities cannot reuse an old process's authority. Fresh processes validate current registration independently;
this is not protection against a same-user process forging all trusted Git metadata.

Enrollment identities, disclosure, session budgets, monthly charges and outstanding liabilities do not change
when membership changes. Build's read-only preflight resolves the same membership and requires native child
binding; it never silently switches an enrolled worktree to an unenrolled execution profile. For metadata failures,
inspect `git worktree list --porcelain` from the enrolled repository, use `git worktree repair` for legitimate moved
worktrees, or return to a valid registered checkout and reload. Do not manually edit pointers, enroll every
worktree, reset accounting to recover discovery.

### Installed skill effects

This is the workflow responsibility inventory introduced by TASK-00489. TASK-00500 removes its native
mutation-tool enforcement; accepted target instructions and human authorization still govern work. An allowed path alone does not authorize a business action.
Trusted interactive `/skill:<name>` selects the skill, subject and mode; launcher roles take precedence on reload.
`--checkout /absolute/path` carries an already selected checkout, including a future Work checkout before creation.
Fresh sessions use their containing registered root. Unknown skills never default to source-writing authority.
Current checkpoints retain skill/subject/checkout/mode only for matching local session provenance, with the same
fork/tree/restart and failed-persistence safeguards as role selection.

All skills use native reads, writes and edits without harness file denials. All selected TASKs may write their `.runs/tasks/<TASK-ID>/runs/` evidence beneath the canonical base
or selected checkout. Canonical Review/QA directories remain independently owned. Existing ownership rules still
prohibit overwriting another run. Query/trace and status/preview/dry-run modes permit only owned ignored evidence.

| Installed skill | Authorized workflow outputs |
|---|---|
| `work` | Ordinary files in the selected implementation checkout, including planning, skills and `.env.example` |
| `review` | `.runs/reviews/<TASK-ID>/` reports/history at base or selected checkout; explicit release subjects use the release report contract below |
| `qa` | `.runs/qa/<TASK-ID>/` evidence/captures at base or selected checkout; explicit release subjects use the release report contract below |
| `build` | `.runs/build/<TASK-ID>/` requests/ledger at base or selected checkout; no source promotion |
| `land` | Selected checkout reconciliation, base `planning/` delivery metadata and owned task evidence |
| `next`, `audit`, `architecture`, `security-audit`, `adopt` | Inspection and owned task evidence only |
| `fix` | Selected `planning/tasks/` and `planning/ROADMAP.md` after record creation is authorized |
| `grill` | Selected `planning/epics/` after the interview is approved |
| `to-tickets` | Selected `planning/tickets/` and parent `planning/epics/` |
| `to-tasks` | Selected `planning/tasks/` and `planning/ROADMAP.md` |
| `wayfinder` | Selected `planning/wayfinder/` |
| `research` | Selected `planning/wayfinder/research/`, `.runs/notes/<subject>/` |
| `design`, `prototype`, `design-review` | `.runs/prototypes/<subject>/`; disposable evidence only |
| `writing-for-agents` | Selected `AGENTS.md`, `.pi/skills/`, `docs/engineering/` |
| `graphify` | Explicit build/refresh output beneath selected `graphify-out/`; query/trace remains read-only |
| `release` | Selected `CHANGELOG.md`, `VERSION`, `docs/releases/`, `planning/`; version-owned `.runs/handoffs/release-<version>/` and `.runs/notes/release-<version>/` at base or selected checkout; no signing/publication grant |

Release uses its documented version subject (`/skill:release 0.5.0`, `0.4.0 status`, or
`v0.4.0 resume after signing`), normalizing the optional `v` prefix. The transport accepts a bounded numeric
three-part version with optional prerelease/build identifiers; target release policy still validates its supported
versions before preparation. A missing/invalid version grants no release mutations. Status/query modes retain
only the exact selected version's ignored evidence, with no other-version, TASK, canonical Review/QA or source
write grant. Resume retains the same version boundary; signing and publication require their existing authority.

For explicitly assigned `/skill:review release <version>` and `/skill:qa release <version>`, follow the provider's
[release acceptance protocol](../../.pi/skills/release/references/acceptance.md). Canonical packet/reports live at
the base in `.runs/handoffs/release-<version>/`; review and QA each own only their respective report and run
history/evidence, with packet assembly limited by that protocol. The coordinator owns its ledger/packet, never
independent verdicts. Status cannot revise a packet or replace independent reports. These are documented workflow
responsibilities, not new native-tool checks or a release-aware parser/launcher implementation. A missing parsed
TASK does not authorize a fake TASK identity; role selection and supported dispatch requirements remain unchanged.

Role aliases remain Engineer/Software Engineer → `work`, Reviewer/Senior Engineer → `review`, QA Engineer → `qa`,
Team Lead → `build`, Release Manager → `land`. Auxiliary skills use Reviewer's workflow phase with their own
narrower workflow responsibilities. Work/Land cannot edit another checkout's source; reports remain accessible at
the canonical base even when only a linked worktree was enrolled. Native tools use Pi/OS permissions, including for secret variants, Git internals, operator state,
symlinks/hardlinks and runtime resources; this does not authorize unrelated changes or disclosure.

`file_screening_status` reports the selected `workflow.skill`, `effect`, `taskId`, `checkout` and `mode`.
Its `phase` is the workflow role: Build deliberately uses `qa` with the `coordination` effect. This does
not make Build a QA worker or prevent child dispatch. `workflow.launcherRole` records the startup role
(`null` for an unassigned interactive launcher), not the current skill. Diagnose a workflow mismatch from
the selected skill and scope, never from `phase: qa` alone; do not promote Build to Engineer or Land.

The reusable owning native probe is `tests/native/worktree-access.mjs`: pass an existing installed Pi
`node_modules` directory and an ignored evidence directory. It uses real disposable Git worktrees, native
`session.prompt`, installed skill metadata, final tool hooks, fresh role sessions and separate-process restart.
Model output and evaluator responses are controlled; it neither reads real credentials nor qualifies live
provider accuracy. The normal Harness wrapper runs the deterministic owning tests; native qualification is a
separate macOS/APFS check against each installed supported runtime.

`jev_read_file` retains its 0.70 positive rule, bounded list/glob/cache contract and current-source checks.
Positive results can still issue receipts; cache reuse is session-local and visible. Negative, partial or failed
results do not issue positive receipts or prove absence. Do not repeatedly rephrase for a positive result.
Receipts describe optional screening only; do not pass them to native `read`. No source-body or cross-session
judgment cache is added.

`file_locate` accepts exactly one `symbol` (code identifier) or `heading` (ATX title without `#`), plus path and
need; its 32 lookups/session, 1 MiB scan and bounded 40-line location remain. Use the returned path and range
with native `read`, without its evidence token or legacy instruction/required-evidence exceptions. Lookup is
not a prerequisite for reading a large Board. Jev source disclosure remains capped at 32 KiB; native reads do
not call the evaluator.

## Images and screenshots

Use Pi's native `read` with an image path. Pi owns supported formats, conversion, resizing and output limits;
there is no harness image admission, approval or file-path exclusion. Images are sent to the main model through
native behavior, never automatically to Jev. Keep task and disclosure authorization distinct from read access.

## Native shell and task authority

TASK-00492 removes Jev command screening, local command tripwires and the parent/child approval relay.
Native Agent `bash`, user `!` / `!!` and RPC user bash use Pi's ordinary implementation, configured prefix,
output limits and cancellation. They do not resolve Jev credentials, disclose commands to Jev, spend a Jev
budget or request a Jev confirmation. Missing setup, an unavailable evaluator or file enrollment status does
not gate shell execution. No switch or automatic approval substitute retains the removed gate.

Existing task authorization and native host/runtime permissions remain binding. Do not put credentials in
commands. Discovery restrictions do not contain native file tools or same-user
shell, descendants, plugins or SDK calls. The separate explicitly selected deterministic tool-policy profile
retains its own shell restrictions. Managed execution without Agent shell access remains future work under
[tool-mediated execution](../../docs/engineering/TOOL_MEDIATED_EXECUTION.md).

Fully restart Pi after updating the harness; a running process can retain old modules. Resume the original
Build ledger and model pins without resetting repair counts, enrollment, charges or unresolved liabilities.

## Coverage and normal Build

| Route | Actual treatment |
|---|---|
| Native explicit `read` | Unmodified native Pi reader; no harness path, receipt, file-type or authority gate |
| `jev_read_file` / `file_locate` | Optional discovery with original supplier/lookup limits |
| Native explicit `write` / `edit` | Unmodified native Pi tools, including diffs/results and filesystem errors; no harness mutation gate |
| Native Agent `bash` | Ordinary native execution; no Jev request, setup dependency or command confirmation |
| Native user `!` / `!!`, RPC user bash | Ordinary native execution and prefix handling; no Jev command disclosure |
| Provider-owned Build monitor | Read-only provider-relative preflight; enrolled children explicitly load package plus required authority identity, actual child sessions independently bind |
| Code/script descendants, monitor's process launch, Git hooks, native shell startup, other tools/SDK/OS calls, plugins, prompt attachments, external editors | Not recursively intercepted or OS-confined; no universal enforcement claim |
| Alternate/nested explicit tools in this profile | Native reads, writes, edits and bash remain independent; other unsupported routes are held; ordinary useful work uses native bash, not bespoke capability replacements |

Start ordinary Pi with the reviewed package and invoke **`/skill:build TASK-NNNNN`** from the owning provider skill.
Use the [normal provider-owned Build monitor](../../.pi/skills/build/SKILL.md#dispatch-fresh-roles), ordinary shell,
Git and target `./bin/*` checks, with its existing coordinator ledger, model pins, independent roles, repair budget,
canonical reports and liveness rules. Provider instructions/monitor need not live in the target project. No
`/screened-build`, replacement executor, stage/report tool, recurring lease or per-child approval is required.
Instruction reads need no harness grant. Mutation and evaluator-disclosure authority remain separate prerequisites.

The removed candidate-only scheduler/tickets/external-work/report transport no longer has a concrete purpose once
shell works. Its machine report envelope is not an acceptance substitute. Coordinators must still validate report
TASK/branch/base/head/status, independence, findings and evidence under canonical Work/Review/QA/land instructions,
preserve historical reports and hold malformed/stale/wrong-subject evidence. Transport removal does not self-close
old R3 findings or change independent reports. Original repair/test counters and uncertain effects survive migration;
never delete old records or use a new command/directory to reset them. Never rerun exhausted paid qualification.

## Shared monthly accounting and recovery

Explicit enrollment applies one operator-wide **10,000,000 micro-USD ($10) per America/Chicago calendar month**,
no rollover, across projects/parents/children for file-relevance requests. Historical command charges and liabilities remain accounted for. External clients
and unrelated main-model spending are not capped. Every attempt reserves **2,688 micro-USD** worst-case before
credential resolution, marks dispatch durably and settles actual reported cost once, rounded upward. SQLite full
synchronous transactions/finite locks serialize processes. Its experimental Node warning remains visible.

Automatic session ceilings are <=100,000 micro-USD, bounded further by connection limits. Session consumption
survives processes/resumes/revisions/months. Fresh roles cannot multiply the shared month cap. Retain eight
questions/request, sixteen choices, two in-process outstanding, 15-second deadline, encoded limits and zero retries.
Unknown dispatched usage remains reserved, not zero; month rollover keeps original billing attribution and all
outstanding liabilities. Predispatch failure releases only that request; price-bound breaches persist a global halt.
No server behavior outside the pinned billing contract or unrelated spend is guaranteed locally.

Legacy `budget-<session-hash>.json` settled/pending exposure remains byte-for-byte. Unimported liabilities, unsafe
records or writer locks hold paid assessments globally. `/file-screening legacy` previews a digest-bound inventory
and requires old-client quiescence evidence. Import conservatively charges undated settled exposure to the current
Chicago month and pending exposure to every month until independently settled; changed/missing records retain
liabilities and hold. Imports are idempotent and serialize with reservations. Never infer old clients stopped merely
from an empty lock. No automatic import, reset or reconciliation accompanies standing-enrollment conversion.

`/file-screening reconcile` is operator-only: exact outstanding ID (or legacy digest), independently verified actual
integer micro-USD, billing month where needed and nonsecret evidence, then confirmation. Unknown stays reserved;
a dead process is not evidence of zero cost. Price halt has no automatic reset. Preserve database/journal/legacy
ledgers. Status initializes no fresh paid request; missing cost remains unknown.

## Observations and evidence limits

Interactive sessions are quiet by default: no automatic JSON dumps at startup/before turns, no raw diagnostic
stderr competing with the TUI renderer, and no routine success messages.
The compact status remains; changed degraded states and blocked operations produce concise, deduplicated warnings.
`/file-screening inspect` explicitly requests the detailed status. Quiet presentation does not suppress file-tool errors, authority checks or accounting.

The normal monitor drains private stderr and projects only declared safe screening/status fields into its ordinary
feed: actual hashed session, phase, sequence, path/need/operation correlation, decision, reason, range, available
usage/latency, available/reserved/settled funds and Chicago reset/action codes. Command strings, source, keys and
private reasoning are not relayed. Cache reuse is distinct from provider attempts. The obsolete JS replacement-
dispatch relay is removed; the normal Python monitor retains the safe monthly/correlation projection (R2).

Earlier four paid synthetic Jev attempts are **exhausted**, 66 micro-USD settled / zero reserved plus legacy13;
both authorized main qualification pairs are consumed. Prior process/receipt evidence is historical, not current
useful-work, savings, recall or command-risk live qualification. The original child omitted its requested formula.
The separately authorized E1 experiment stopped after its original screened parent used two main responses and
zero Jev requests: its private guard blocked the mandatory shared instruction. The final private correction admits
the exact hash-bound main-only instruction, but native admission correctly rejects the original call's competing
exception/evidence arguments. Full corrected composition remains unqualified; no additional live pair was run.
The approved AC8/E1 deferral applies, not another automatic recovery or counter reset. No product defect or
comparative benefit is inferred. Real baseline/current wide/narrow terminal PNGs now cover bounded degraded status;
full behavioral/visual QA remains independent. PTY text is not a screenshot. If future captures are unavailable,
use the operator-approved fallback with explicit limits; never fabricate images or claim visual certification.
