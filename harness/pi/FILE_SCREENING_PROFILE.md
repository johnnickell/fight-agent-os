# Standing project screening (TASK-00309 implementation candidate)

This profile combines normal project-file access, optional [Jev relevance discovery](FILE_SCREENING.md),
automatic session binding, role-scoped explicit writes, shared monthly accounting and separate **command-risk
assessment** using the existing Jev judgments supplier. It is not a sandbox or universal file-access boundary. Ordinary authorized
shell remains useful, including `rg`, `cat`, `git diff`, checks, commits, non-force push and owned-worktree cleanup.
Shell source reads can bypass relevance screening. Same-user shell can access operator state; explicit-tool
protected paths do not provide OS containment. Restrictive shell containment/MCP replacements are future work.

The [TASK amendment](../../planning/tasks/00309-TASK.md#approved-interim-scope-correction--2026-10-15) supersedes
the earlier candidate's blanket shell denial and finite enrollment. Historical checks are not current acceptance.
John subsequently approved the narrow [AC8/E1 evaluation deferral](../../planning/tasks/00309-TASK.md#approved-comparative-evaluation-deferral--2026-10-15):
comparative usefulness remains unestablished, with no effectiveness, recall or net context/latency/billed-cost
savings claims. Independent Review and remaining behavioral/visual QA still apply; green controlled checks do not
replace them. No global installation/trust/settings or real enrollment occurs by reading these instructions.

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
   Changed pins or invalid settings need explicit repair/setup, not automatic higher-price approval. Project/source/
   command authority remains separate. `/jev check` is a separately confirmed billed synthetic request, not
   source/command disclosure authority.
3. Run `/file-screening setup` in the intended canonical project root. Enter extra protected paths and exact
   governing/selected-skill instruction files (semicolon-separated). Explicitly consent to source and bounded
   nonsecret final-command/context disclosure, then inspect the full effective preview. Use Up/Down or Page Up/Down
   to scroll and Home/End to jump; the footer shows active bindings and position. Enter continues to a separate
   short save confirmation; it does not save from the preview. Escape cancels either step without changes.
   Enlarge a terminal too small to display the preview controls before continuing. No JSON editing or session-ID
   hashing is needed. Register each intended root explicitly;
   there is no hardcoded target/provider project. A changed connection authority with other enrolled roots requires
   reviewing those projects rather than silently transferring their disclosure.
4. `/reload` binds the actual session. Enrollment is **standing until explicit revocation or authority change**:
   no expiry, routine renewal, new-session/day/month prompt or periodic lease. Resumes retain usage; fresh roles
   bind independently under the same account. Monthly reset changes available monthly funds, not enrollment.
5. `/file-screening inspect` or `file_screening_status` is read-only and makes no paid/credential request. `active`
   describes project access and declared command-risk coverage, not provider readiness, accuracy or safety.
   `degraded` gives the precise setup/authority/provider/price/credential/budget action; `disabled` means not enrolled.
   A per-file refusal such as `source-limit` does not degrade an otherwise active session or clear a prior provider
   failure. The rejected file still has no positive receipt; a concretely needed file can be read directly within local limits.
   Status reports available/reserved/settled funds, all-month liabilities, reset, binding limit and actual session/phase.
6. `/file-screening revoke` confirms revocation for **all roots listed in this enrollment**. Their durable revoked
   record holds new/resumed native tools rather than turning deletion into an implicit opt-out. Existing runtimes
   recheck before new effects; already-running shell/descendants/remote effects are not guaranteed stopped. Preserve
   every ledger, pending charge and finite test counter. Setup may explicitly reenroll under reviewed authority;
   neither a new revision nor a new session resets accounting.

Settings live outside roots/repository instructions in the existing private operator directory:
`~/Library/Application Support/Fight Agent OS/jev/` (directory 0700, files 0600, operator-owned, no symlinks/hardlinks).
`screening-profile.json` v2 has `expiresAt: null`, explicit command disclosure and revocation state. Generated
`enforcement-<revision>.json` protects explicit file mutations, not arbitrary shell. Explicit operator exclusions, Pi runtime settings,
operator state and sensitive names retain their explicit-tool protections. Project instructions and `.pi/skills`
are editable for Engineer/Land unless explicitly excluded; `.env.example` is an ordinary template. Authority/source/tool drift holds old
runtime receipts and requires correcting the actual change and reload; no daily/session/monthly renewal is added.

**Historical compatibility:** v1 profiles were deliberately timeboxed. They hold with
`historical-profile-conversion-required`, even if their old date remains in the future. `/file-screening convert`
previews all formerly bounded roots and explicitly confirms standing disclosure plus new command-context consent.
Cancel retains the original bytes; `/file-screening revoke` can withdraw them without converting disclosure.
Conversion retains roots, exclusions, exact witnesses, ceilings and all original accounting, with a new authority
revision to invalidate old receipts. It never resolves credentials, sends a request or resets finite one-off tests.
Do not convert a real operator's profile without explicit authority. Historical runtime ledgers/grants are retained,
not imported into a fresh routine execution allowance; the coordinator reconciles any in-flight effects first.

## Project reads, discovery and workflow roles

The installed [shared rule](FILE_SCREENING_RULE.md) is injected into every participating session. It is the
portable source of agent guidance; targets need no copied policy or new base skill. Strongly prefer Jev for
unfamiliar/speculative discovery. Known targets, changed files, failure locations and core instructions read
directly with `read` and `path`. This includes `AGENTS.md`, `README.md`, `planning/tasks/BOARD.md`, root or
planning `ROADMAP.md`, without individual whitelist enrollment or fixed content digests. `need` and ranges are
optional; defaults are 200 lines, with at most 1,000 requested lines / 32 KiB output and a 1 MiB local snapshot.
Follow the returned continuation offset for more. A negative or unavailable relevance judgment does not deny
access: briefly state the concrete need before a direct read, and never label it screened.

Native loaded-context and advertised-skill paths read directly outside the project too. Reading trusted
instructions establishes their exact relative Markdown references, bounded to eight links deep and 128 paths.
Source changes and removed registrations invalidate those witnesses. Unlinked siblings, whole provider roots
and Jev disclosure are not granted. Explicit exclusions, protected resources and inode checks remain binding.
Do not use a shell workaround for an actual access denial.

The runtime also supplies its installed README and Markdown documentation directory directly through Pi's
`getReadmePath()` and `getDocsPath()` APIs. All workflow roles can read these documents and locate headings
without Jev, per-file enrollment or provider effects. This does not import a path from prompt text or grant
runtime writes, sibling source-code access, external links or evaluator disclosure. Documents retain the local
1 MiB snapshot / 32 KiB output limits, explicit exclusions and inode checks; their first-read digest is pinned
for that session. Changed documentation requires a Pi restart, and lifecycle invalidation withdraws admission.

The operator's interactive `/skill:work`, `/skill:review`, `/skill:qa` or `/skill:land` selects its role, as does a
trusted launcher phase. `/file-screening role engineer|reviewer|qa|land` explicitly selects a role in the TUI;
model/extension and RPC prompt text cannot promote roles. On every startup/reload an explicit trusted launcher
takes precedence over stored selections, including earlier operator overrides. Local sessions restore the latest
session-wide selection, independent of the navigated branch, only with matching session identity and local
launcher provenance. Forks, legacy/unverifiable history and removal of a prior launcher default to Reviewer;
the operator can then select the intended workflow explicitly. Fresh local sessions default to Engineer.
Selections must persist before changing authority. Failed persistence holds tools; fix storage and reload.
An uncertain failed append is superseded by a Reviewer checkpoint so reload cannot restore privilege from it.

- Engineer and Land can read/edit normal project files, including instructions, `.env.example` and `.pi/skills`.
- Reviewer can read project files and write reports/probes only beneath the project's `.runs`.
- QA can read and execute checks/probes and use appropriate Git operations; direct writes are limited to `.runs`.
- Every role retains explicit exclusions, credential/runtime protections and existing task authorization.

These are explicit-file-tool checks, not shell isolation. Every final shell command still receives assessment,
now with the active role. Selecting Land does not grant publication, destructive or unrelated-work authority.

`jev_read_file` retains its 0.70 positive rule, bounded list/glob/cache contract and current-source checks.
Positive results can still issue receipts; cache reuse is session-local and visible. Negative, partial or failed
results do not issue positive receipts or prove absence. Do not repeatedly rephrase for a positive result.
Explicitly supplied receipts must match path, need, question, source, session and authority; invalid receipts
fail rather than silently becoming direct reads. No source-body or cross-session judgment cache is added.

Existing instruction/required-evidence exceptions and exact structural evidence remain compatible, but are
optional. `file_locate` accepts exactly one `symbol` (code identifier) or `heading` (ATX title without `#`),
plus path and need; its 32 lookups/session, 1 MiB scan and 40-line grant limits remain. Use its returned token
and bounded range, never prose in the evidence field. It is not a prerequisite for reading a large Board.
Jev source disclosure remains capped at 32 KiB; local direct reads do not call the evaluator.

## Images and screenshots

Ask the Agent to inspect an image normally. `read` accepts a PNG, JPEG, GIF or WebP `path` directly, including
Desktop files outside project roots. No path-only user message, input-hook selection, special command or approval
dialog is required, in interactive or noninteractive sessions. Relative, absolute, `~/`, spaced and Unicode paths
are supported, as are matching quotes/backticks and space-escaped paths. `need` and `exception: "image"` remain
optional for compatibility; omit text ranges, receipts and evidence tokens.

Images bypass text relevance screening and go directly through Pi's native image processing to the main model,
never Jev. Supported file signatures, the 10 MiB byte cap, enrolled exclusions, protected resources, sensitive names
and symlink/hardlink checks still apply. Invalid images and processing failures return an error, never text fallback.
A non-vision main model cannot inspect the attachment; Pi retains its normal limitation notice.

There are no retained image selections or image-specific authorization state. Each read checks the current file;
a file changed during native processing must be retried. Standing text roots, credentials, budgets and finite
counters remain unchanged. This does not waive ordinary task permissions, text admission, command-risk checks
or runtime source-integrity/lifecycle checks, and does not claim same-user OS containment.

## Command risk and authorization

Native Agent `bash` uses Pi's ordinary shell tool/rendering/output limits and local execution backend. The wrapper
assesses the **final command at `BashOperations.exec`**, after tool-call hooks and configured command prefix.
It captures cwd, root, phase and shellPath/prefix settings only; no environment values, prompt/history, file bodies
or script contents are fetched for risk assessment. Final command is <=16 KiB; shell settings are <=4 KiB each.
The same shared Jev supplier, credentials, pins, concurrency, zero-retry contract and durable account are used.
Risk is a separate choice rubric, not the file-relevance score or an additional provider client/cache.

- Complete `ordinary` with confidence >=0.90 may proceed **only within existing task/role/operator authorization**.
  It means no apparent dangerous effect in supplied context, not measured safety, containment or new permission.
- Risky, low-confidence/unknown, denied, failed, unavailable, budget-limited or price-unqualified assessments emit
  distinct held outcomes and require an explicit one-command operator decision. Native TUI uses concise warnings,
  not routine event/JSON popups; noninteractive monitor observations remain structured. No UI/declined decision
  means no execution. Confirmation is an exceptional decision, not a recurring grant for normal authorized work.
  It does not fabricate a safe judgment or authorize an effect outside the operator's existing authority.
- Enrollment revocation, epoch/authority/context/settings drift and cancellation cannot be overridden by that dialog.
  Concurrent Agent/user command approvals are queued through one native dialog slot. Waiting commands revalidate
  before displaying; each decision remains bound to its exact command. Aborting a waiting command removes it
  without dismissing another command's dialog. Aborting the visible command dismisses it; lifecycle invalidation
  withdraws all pending approvals. Only the displayed approval reports Herdr blocked, with a balanced clear on
  answer, cancellation or error. Execution may remain parallel after separate decisions. The shell timeout starts
  after approval and does not impose a deadline on the operator.
  Recheck after evaluation/decision and immediately before delegating the fixed command to Pi's local backend.
  A change requires a new assessment/decision; no reusable shell permit is stored.
- Never embed credentials or secret material in commands. Local tripwires hold known key/token/assignment/Bearer/
  private-key forms and obvious credential-file/environment reads **before** provider disclosure, with only hashed
  command IDs in observations. This is not a complete secret detector or shell parser. Encoding, indirect scripts,
  ambient environment, startup shell files and later substitutions can conceal effects; they are not inspected.
  Ordinary user approval/disclosure constraints remain binding. Do not interpret the classifier as secret protection.
- Shell syntax is not allowlisted. Pipelines, substitutions, scripts, Git/checks and reads are not blanket-denied;
  incomplete context can require an operator decision. Output is ordinary shell output, not relevance-admitted content.

`command-secret-disclosure-held` is a local refusal of the supplied command, before provider disclosure or shell
execution. It does not set a session-wide hold, and there is no hold-clear command. Chat approval cannot override
it. Remove actual credential values or secret-file/environment reads; report a non-sensitive false-positive
command for repair. Never encode, split or move the same operation into a script to evade the check. Unrelated
non-sensitive commands remain assessable. Complete quoted display labels such as `print('tokens:', count)` and
the exact template filename `.env.example` alone do not count as credentials. The whole command still receives
risk assessment; real assignments, quoted credential object keys, `.env`/`.env.local`, secret expansions and mixed
commands retain their checks. Template contents are not inspected or guaranteed safe by the filename.

`command-context-disclosure-held` concerns the configured shell path/prefix or phase; the operator should inspect
settings locally without pasting sensitive values. `command-operator-decision-required` instead means the risk
assessment needs the native one-command confirmation. Chat approval does not answer that dialog, and no
confirmation is reused for later commands. A blocked result includes this recovery guidance directly, so obtaining
it does not depend on screening another policy document. Authority drift and revocation remain separate failures.

## Coverage and normal Build

| Route | Actual treatment |
|---|---|
| Native explicit `read` | Normal project reads and exact portable instructions; optional explicit receipts/evidence remain validated |
| `jev_read_file` / `file_locate` | Optional discovery with original supplier/lookup limits |
| Native explicit `write` / `edit` | Role-scoped final-argument protected-path checks; omit source diffs/previews in results |
| Native Agent `bash` | Final-command shared-Jev risk assessment and visible exceptional decisions; shell relevance bypass |
| Native user `!` / `!!`, RPC user bash | Custom operations assess final prefixed command before local execution. `!!` excludes main-model context, not approved Jev command disclosure |
| Provider-owned Build monitor | Read-only provider-relative preflight; enrolled children explicitly load package plus required authority identity, actual child sessions independently bind |
| Code/script descendants, monitor's process launch, Git hooks, native shell startup, other tools/SDK/OS calls, plugins, prompt attachments, external editors | Not recursively intercepted or OS-confined; no universal enforcement claim |
| Alternate/nested explicit tools in this profile | Unsupported and held; ordinary useful work uses assessed bash, not bespoke capability replacements |

Start ordinary Pi with the reviewed package and invoke **`/skill:build TASK-NNNNN`** from the owning provider skill.
Use the [normal provider-owned Build monitor](../../.pi/skills/build/SKILL.md#dispatch-fresh-roles), ordinary shell,
Git and target `./bin/*` checks, with its existing coordinator ledger, model pins, independent roles, repair budget,
canonical reports and liveness rules. Provider instructions/monitor need not live in the target project. No
`/screened-build`, replacement executor, stage/report tool, recurring lease or per-child approval is required.
Missing exact instruction/root authority remains a real prerequisite, not automatic broader disclosure.

The removed candidate-only scheduler/tickets/external-work/report transport no longer has a concrete purpose once
shell works. Its machine report envelope is not an acceptance substitute. Coordinators must still validate report
TASK/branch/base/head/status, independence, findings and evidence under canonical Work/Review/QA/land instructions,
preserve historical reports and hold malformed/stale/wrong-subject evidence. Transport removal does not self-close
old R3 findings or change independent reports. Original repair/test counters and uncertain effects survive migration;
never delete old records or use a new command/directory to reset them. Never rerun exhausted paid qualification.

## Shared monthly accounting and recovery

Explicit enrollment applies one operator-wide **10,000,000 micro-USD ($10) per America/Chicago calendar month**,
no rollover, across projects/parents/children and both file-relevance and command-risk requests. External clients
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
stderr competing with the TUI renderer, and no success messages such as `command-risk-complete complete`.
The compact status remains; changed degraded states and blocked operations produce concise, deduplicated warnings.
`/file-screening inspect` explicitly requests the detailed status. Command holds still require their existing
one-command decision; quiet presentation does not suppress tool errors, authority checks or accounting.

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
