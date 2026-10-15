# Standing project screening (TASK-00309 implementation candidate)

This profile combines [explicit file relevance admission](FILE_SCREENING.md), automatic actual-session binding,
shared monthly accounting and separate **command-risk assessment** using the existing Jev judgments supplier.
It is not a sandbox, universal file-admission boundary or new task/role permission system. Ordinary authorized
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

1. In reviewed native Pi **1.2.0 on macOS**, load the package through an existing trusted installation or explicitly
   with `pi -e /absolute/provider/harness/pi`. Never automatically install globally, trust a project or alter settings.
   Other runtimes/platforms, replaced tools and simultaneous `--fight-tool-policy` selection hold this profile.
   TASK-00160's optional deterministic policy remains separate and unchanged, including its exact-script shell policy.
2. Reuse [Pi-managed OpenRouter setup](README.md#jev-setup-and-readiness-operator-only), with the pinned Jev
   evaluator and `authorized-supplied-state` disclosure. Setup resolves no credential and makes no request.
   Shared connection/price setup is standing for the approved pins and limits, with no periodic renewal.
   TASK-00450's timer removal is integrated from approved `develop`; existing timestamps/identities need no refresh.
   Changed pins or invalid settings need explicit repair/setup, not automatic higher-price approval. Project/source/
   command authority remains separate. `/jev check` is a separately confirmed billed synthetic request, not
   source/command disclosure authority.
3. Run `/file-screening setup` in the intended canonical project root. Enter extra protected paths and exact
   governing/selected-skill instruction files (semicolon-separated). Explicitly consent to source and bounded
   nonsecret final-command/context disclosure, then inspect the full effective preview before confirmation.
   Cancel changes nothing. No JSON editing or session-ID hashing is needed. Register each intended root explicitly;
   there is no hardcoded target/provider project. A changed connection authority with other enrolled roots requires
   reviewing those projects rather than silently transferring their disclosure.
4. `/reload` binds the actual session. Enrollment is **standing until explicit revocation or authority change**:
   no expiry, routine renewal, new-session/day/month prompt or periodic lease. Resumes retain usage; fresh roles
   bind independently under the same account. Monthly reset changes available monthly funds, not enrollment.
5. `/file-screening inspect` or `file_screening_status` is read-only and makes no paid/credential request. `active`
   describes explicit-file admission and declared command-risk coverage, not provider readiness, accuracy or safety.
   `degraded` gives the precise setup/authority/price/credential/budget action; `disabled` means not enrolled.
   Status reports available/reserved/settled funds, all-month liabilities, reset, binding limit and actual session/phase.
6. `/file-screening revoke` confirms revocation for **all roots listed in this enrollment**. Their durable revoked
   record holds new/resumed native tools rather than turning deletion into an implicit opt-out. Existing runtimes
   recheck before new effects; already-running shell/descendants/remote effects are not guaranteed stopped. Preserve
   every ledger, pending charge and finite test counter. Setup may explicitly reenroll under reviewed authority;
   neither a new revision nor a new session resets accounting.

Settings live outside roots/repository instructions in the existing private operator directory:
`~/Library/Application Support/Fight Agent OS/jev/` (directory 0700, files 0600, operator-owned, no symlinks/hardlinks).
`screening-profile.json` v2 has `expiresAt: null`, explicit command disclosure and revocation state. Generated
`enforcement-<revision>.json` protects explicit file mutations, not arbitrary shell. Exact instructions, Pi settings,
operator state and sensitive names retain their explicit-tool protections. Authority/source/tool drift holds old
runtime receipts and requires correcting the actual change and reload; no daily/session/monthly renewal is added.

**Historical compatibility:** v1 profiles were deliberately timeboxed. They hold with
`historical-profile-conversion-required`, even if their old date remains in the future. `/file-screening convert`
previews all formerly bounded roots and explicitly confirms standing disclosure plus new command-context consent.
Cancel retains the original bytes; `/file-screening revoke` can withdraw them without converting disclosure.
Conversion retains roots, exclusions, exact witnesses, ceilings and all original accounting, with a new authority
revision to invalidate old receipts. It never resolves credentials, sends a request or resets finite one-off tests.
Do not convert a real operator's profile without explicit authority. Historical runtime ledgers/grants are retained,
not imported into a fresh routine execution allowance; the coordinator reconciles any in-flight effects first.

## Explicit file admission and exceptions

`jev_read_file` retains the 0.70 positive rule, validated single/list/glob/cache contract and current-source/access
checks. Each complete positive gets its own admission token; cache reuse is visible, local to the actual session,
and not a paid call. No source-body or cross-session judgment cache is added. Negative/partial/failed/unavailable
results grant no explicit read and do not establish absence. Never repeatedly rephrase to seek a positive.

Use `read` with `path`, identical `need`, `admission.receipt`, `questionDigest`, and explicit positive `offset`/`limit`
(up to 1,000 lines / 32 KiB returned). Missing or false evidence returns no source. Changed need/question/source/
revision/session/authority/connection invalidates a receipt. Discovery sources remain <=32 KiB; local exact-evidence
snapshots may reach 1 MiB without widening Jev disclosure or the 32 KiB returned-range cap.

Exceptions bypass relevance only, never permissions or sensitive-file restrictions:

- `exception: "instruction"`: exact current operator-enrolled instruction or actual authorized native context whose
  bytes match. External provider skills need their exact instruction grant, never siblings/directory permission.
  `/file-screening instruction` previews one genuinely required file, then reload.
- `file_locate({path,symbol,need})`: one unique verified definition, location/evidence only, <=40 lines and 32
  lookups/runtime. Use its evidence token with identical need. A plausible filename/certainty statement is not evidence.
- `/file-screening target`: one exact operator-established edit/quote/verification purpose and bounded range;
  use `exception: "required-evidence"` with matching need. Changed source needs reassessment.

Native mandatory context supplies current per-file witnesses. The trusted `ScreeningProfiles.intake` API remains
available for host assignments/literal required references; model claims cannot call/promote it. Each exception
needs establishing evidence; no glob/directory/sibling transfer. During evaluator outage, established local
exceptions remain available where access/boundary permits. None makes ordinary discovery relevant by assertion.

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
  distinct visible observations and hold for an explicit one-command operator decision. No UI/declined decision
  means no execution. Confirmation is an exceptional decision, not a recurring grant for normal authorized work.
  It does not fabricate a safe judgment or authorize an effect outside the operator's existing authority.
- Enrollment revocation, epoch/authority/context/settings drift and cancellation cannot be overridden by that dialog.
  Recheck after evaluation/decision and immediately before delegating the fixed command to Pi's local backend.
  A change requires a new assessment/decision; no reusable shell permit is stored.
- Never embed credentials or secret material in commands. Local tripwires hold known key/token/assignment/Bearer/
  private-key forms and obvious credential-file/environment reads **before** provider disclosure, with only hashed
  command IDs in observations. This is not a complete secret detector or shell parser. Encoding, indirect scripts,
  ambient environment, startup shell files and later substitutions can conceal effects; they are not inspected.
  Ordinary user approval/disclosure constraints remain binding. Do not interpret the classifier as secret protection.
- Shell syntax is not allowlisted. Pipelines, substitutions, scripts, Git/checks and reads are not blanket-denied;
  incomplete context can require an operator decision. Output is ordinary shell output, not relevance-admitted content.

## Coverage and normal Build

| Route | Actual treatment |
|---|---|
| Native explicit `read` / `jev_read_file` / `file_locate` | Current receipts or validated per-file exceptions; original supplier limits |
| Native explicit `write` / `edit` | Final-argument protected-path checks; omit source diffs/previews in results |
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
