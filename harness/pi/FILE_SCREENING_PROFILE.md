# Enrolled file screening (TASK-00309 implementation candidate)

This profile adds automatic actual-session binding, durable shared accounting and receipt-enforced **Agent tool**
reads to the [existing single-file/list/glob consumer](FILE_SCREENING.md). It uses the same Jev client and
session-local judgment cache. It is not installed or enrolled by this documentation, the $10 planning approval,
a repository instruction or a model response. Bounded synthetic live native/build evidence is recorded; this is
not representative-work qualification, complete answer synthesis or independent acceptance. Controlled local
fixtures remain distinct from live provider evidence. See the [TASK checkpoint](../../planning/tasks/00309-TASK.md).

## Operator setup and inspection

1. Load the reviewed package in native Pi 1.2.0 on macOS APFS, either through an existing trusted installation or
   explicitly with `pi -e /absolute/provider/harness/pi`. Do not install/update global resources automatically.
   Other platforms/filesystems, conflicting `--fight-tool-policy` selection and replaced tool ownership hold
   activation. The ordinary presentation-only, unenrolled package remains usable.
2. Reuse the [pinned connection and Pi-managed OpenRouter setup](README.md#jev-setup-and-readiness-operator-only).
   `/jev setup` never resolves a key or dispatches a request. Choose `authorized-supplied-state`; confirm the
   current pinned prices. The existing 24-hour price-confirmation expiry still binds. `/jev check` is a separately
   confirmed, billed synthetic readiness request, not permission to disclose project source.
3. In the intended project, run `/file-screening setup`. Enter additional protected paths and exact governing/
   selected-skill instruction paths as semicolon-separated paths, and finite expiry (1–90 days). Confirm Jev
   source disclosure, then inspect/confirm the complete effective preview: canonical roots, exclusions, exact
   instruction digests, connection, tools/coverage, allocations and shared budget. Cancel either confirmation to
   change nothing. No JSON editing, credential entry or session-ID hashing is required.
4. `/reload` binds this actual session. Subsequent native sessions under the enrolled root bind automatically;
   trusted build children do so independently, without per-session permission prompts. Resumes retain spending.
   Inspection never dispatches a provider request or resolves credentials. A configured credential is not proof
   of successful resolution, billing capacity or live endpoint readiness.
5. Use `/file-screening inspect` in native TUI/RPC/JSON, or the stable read-only `file_screening_status` tool.
   `active` describes the local receipt boundary, not provider accuracy/availability. `degraded` names a setup,
   coverage, price, credential, provider or budget failure. `disabled/not-enrolled` promises no enforcement.
   Status includes request/session limits, the binding aggregate limit, available/reserved/settled monthly funds,
   all-month outstanding liabilities, Chicago reset time and the next operator action. Never infer zero from
   unavailable cost. Missing/unloaded extensions cannot announce their own absence: only the declared reviewed
   native/package or preflighted build launch is supported.

Settings live under `~/Library/Application Support/Fight Agent OS/jev/`, outside repository instructions and
allowed roots: directory `0700`, settings `0600`, operator-owned, no symlinks/hardlinks. `screening-profile.json`
selects roots and authority; `enforcement-<revision>.json` is its generated deterministic boundary. Setup also
protects project Pi settings/models/MCP files. Instructions are source-digest-bound and protected from Agent
writes. Do not place the operator directory inside a repository or allowed root. A profile revision/expiry or
observed source/tool/policy drift holds the old runtime; restore/reload explicitly, never silently reuse tokens.
Renew/revise through the same preview. Existing session ledgers and pending charges are never deleted/reset.

## Reading and bounded exceptions

`jev_read_file` keeps its supplier contract and 0.70 positive rule. An enrolled result includes per-file
`admission.receipt` and `admission.questionDigest` only for complete trusted positives. Cached judgments are
rechecked against current source/access and issue fresh local admission tokens without another paid call.
Batch authority is bound on the trusted envelope; every file retains its own decision and admission token.
No source body is cached, and no cross-session judgment cache is added.

Then use `read` with the exact `path`, identical `need`, returned `receipt` and `questionDigest`, plus explicit
positive `offset` and `limit` (at most 1,000 lines / 32 KiB returned). Ordinary Jev discovery sources remain at
most 32 KiB. Exact instruction/assignment/reference evidence may snapshot at most 1 MiB locally, with the same
32 KiB returned-range cap; read smaller ranges to follow a larger mandatory source completely. This never
widens provider disclosure or the evaluator cap. Only the useful authorized range returns.
An ordinary `read` lacking evidence returns a bounded instruction, **no source**. A copied/model-authored token,
wrong need/question/file/session, changed bytes/revision/connection/authority, negative, failed or partial screen
cannot authorize content. There is no automatic rephrasing/retry to seek a passing answer.

Exceptions bypass relevance only, never access, sensitive-file policy or main-Agent source authority:

- `exception: "instruction"` admits only an exact trusted instruction whose digest still matches: an operator-
  enrolled file, actual authorized native context, or trusted launcher intake. External provider instructions still
  require their exact operator grant; native intake cannot enlarge source roots. Use `/file-screening instruction` to preview/add a genuinely required instruction, then reload. An exact
  provider skill/instruction may be outside the target root: this grants only that pinned instruction's main-Agent
  read, never its directory/siblings or Jev disclosure. Provider ownership and target source authority stay separate.
- `file_locate({path, symbol, need})` verifies one exact class/function/interface/trait/enum/type definition.
  It returns location/evidence metadata, not source. One unique definition establishes a known target for the
  identical need, current digest/revision and at most 40 lines from that definition. Use its `evidence` in `read`.
  There are at most 32 structural lookups per runtime; a plausible name or certainty claim establishes nothing.
- `/file-screening target` records an operator-established exact edit/quote/verification obligation, source
  digest, purpose and smallest useful range. After reload, use `exception: "required-evidence"` with that exact
  purpose as `need`, inside its range. Changed source requires reassessment. It works after a negative/unavailable
  evaluator without pretending that discovery became relevant.

Every exception is per-file and visible. Neither a directory/glob nor a sibling positive transfers permission.
Expired/revoked local authority or a broken interception boundary may still hold all reads; the diagnostic names
that prerequisite rather than claiming that an exception overrides it. During an evaluator-only outage, established
bounded evidence paths remain available. Native startup/selected-skill prompt loading is trusted host intake,
not an Agent discovery bypass. The actual native host's `contextFiles` establish direct instruction witnesses only
when their bytes match a current authorized source snapshot. A supplied path or model assertion establishes none.
Trusted launchers can also use `ScreeningProfiles.intake(cwd, {instructions, assignments, references})`: assignments
name one file, purpose, line range and nonsecret assignment reference; a required reference must be a literal
Markdown link from an established digest-bound instruction. The trusted host selects applicability and range;
the Agent cannot call this intake API or promote a plausible filename. Changed establishing instructions invalidate
their reference witnesses. Intake never grants a new root, sibling access or Jev disclosure.

## Coverage and launchers

The enrolled profile embeds TASK-00160's qualified final-argument write/edit operations and source/policy freshness
checks. Read content is returned from a freshly checked bounded snapshot, not a separately reopened native tool.
Write/edit results and errors omit native diffs/source previews; edits cannot smuggle existing source through an
error. Late argument-changing hooks therefore meet the final boundary, not an earlier allow decision.

| Route | Declared treatment |
|---|---|
| `read` | Current local positive receipt or independently established bounded exception |
| `jev_read_file` | Existing evaluator, access/disclosure, cache/list and budget checks; visible internal start/completion |
| `file_locate` | Bounded structural locations only; no content lines |
| `write`, `edit` | Existing deterministic protected-path effects; no source-bearing result/diagnostic |
| `file_screening_status` | Always registered, read-only local diagnostic, no provider request |
| Shell/readers/interpreters, `grep`, `find`, `ls`, powershell, codemode/tool search, MCP/alternate tools | Denied by name even if later activated |
| Nested tool calls and generic Agent-callable child launchers | Denied before execution |
| `file_screening_dispatch` | One immutable, single-use trusted ticket; fixed target/request/provider/model/runtime, safe observations only |
| Native `!`/`!!`, RPC user bash | Handled with denial, no shell fallthrough |
| Trusted local build monitor | Read-only preflight from its **provider** package, explicit package/authority flag; actual child ID bound by native session start |
| Trusted plugins/SDK host APIs, external editors, startup resources, user prompt attachments, separately launched unprotected Pi | Outside Agent-tool interception; not a sandbox or universal enforcement claim |

The [real build monitor](../../.pi/skills/build/scripts/run-stage.py) resolves its bootstrap beside its owning
provider skill, never by assuming the target has the provider's Harness. It adds the approved provider package and
an exact profile-identity requirement only when the target root is enrolled. Missing/stale preflight fails launch;
a missing/changed required enrollment holds the native boundary. This requires local Node 22.23.1+ as well as
Python 3.9+/Pi 1.2.0. A repository-provided script cannot grant authority or launch a child through an Agent tool.
Enrolled coordinators use `file_screening_dispatch({ticket})`, never shell or a generic child launcher. A trusted
operator/host prepares a finite set of tickets through the owning provider's `ScreeningDispatch.prepare()` under
already granted build/main-provider authority, or an operator previews one with `/file-screening dispatch`. The
host route does not add another human approval gate to an already authorized child. The Agent tool accepts only
the ticket ID listed by `file_screening_status`: it cannot supply a command, prompt, target, model, timeout or
permission override. Preparation pins the canonical enrolled target, profile identity, exact request digest and
provider monitor digest; timeout is 1–1,800 seconds and ticket expiry at most one hour/profile expiry. Requests,
optional per-file trusted intake and all evidence stay in the protected operator directory. Preparation makes no
model request. It is not a general scheduler or authority to author independent verdicts.

Claiming is atomic across processes and consumes a ticket before launch. Cancellation, failure or a lost parent
never makes it replayable; `consumed-or-uncertain` requires trusted liveness/effect inspection, not another launch.
The child binds its own actual native identity under the same enrollment/monthly ledger and cannot recursively
dispatch. Revocation/drift stops a running child; the monitor applies its own finite deadline and process-group
termination. Same-user/escaped-process limitations still apply. Normal completion requires native settlement and
a normal final assistant stop, not merely exit zero. Tool results and progress relay only validated screening,
cache, exception/block and status fields, never raw child prose, source, credential diagnostics or reasoning.
Handoff/source access remains a separately authorized bounded read. Provider scripts remain owned/protected beside
the provider skill, not resolved from a target's lookalike path. Normal coding checks use trusted operator
infrastructure outside Agent shell access; unrestricted shell coordinators are not supported by this profile.

Extensions run as the OS user. Review all plugins. Prompt attachments, native skill expansion and trusted scripts
are not OS-confined. Same-user races, hostile trusted plugins, extension unload and arbitrary SDK/OS access retain
TASK-00160's limitations. Installing a package is not independent runtime qualification.

## Shared monthly accounting and recovery

Explicit enrollment applies one operator-wide **10,000,000 micro-USD ($10) per America/Chicago calendar month**,
across all enrolled projects, parents and children. There is no rollover or $10-per-project/session allocation.
External clients and unrelated model spending are not covered. Each attempt reserves the entire pinned worst case
**2,688 micro-USD** before credential resolution, with durable dispatch marking before possible provider effects.
SQLite transactions/OS locks (`synchronous=FULL`, finite lock wait) serialize processes, including concurrent resumes;
there are no stale application lock files to delete. Node 22's SQLite API currently emits an experimental warning.

Automatic session ceilings are at most **100,000 micro-USD ($0.10)**, further bounded by the existing connection;
request ceiling is 2,688, also bounded by that connection. They are limits, not duplicated earmarked funds. The
supplier's eight questions are **per request**, not eight per session. Retain eight questions/request, sixteen
choices, two in-process outstanding operations, 15 seconds, encoded bounds and **zero retries**. New roles/forks
receive new session limits but cannot multiply the shared monthly cap. Shared per-session consumption persists
across processes, resumes, profile revisions and months. Legacy session settled/pending exposure is conservatively
inherited without rewriting another runtime's ledger; new accounting does not enroll already-running legacy clients.
All legacy `budget-<session-hash>.json` ledgers are inspected profile-wide, not just the matching session. Any
unimported nonzero liability holds paid screening for every new/resumed parent and child. Writer locks, malformed
records or unsafe files fail closed. `/file-screening legacy` previews the complete digest-bound inventory and
requires the trusted operator to stop all old clients and confirm nonsecret inventory/quiescence evidence. Import
conservatively charges all undated settled exposure to the current Chicago month; unknown pending exposure binds
**every** month until independently reconciled. It is shown in ordinary `reservedMicroUsd`; new-request reservations
are separately identified, and conservative legacy charges remain distinct from measured new-provider settlement.
Imports serialize with new reservations, are idempotent and preserve old ledgers byte-for-byte. Changed old records
hold screening again and add conservative exposure on a new import; missing imported records retain their charge
and hold the profile. Never reset/delete accounting or assume old clients are quiescent from a momentary empty lock.
This profile cannot stop unrelated already-running legacy clients: trusted quiescence is an enrollment prerequisite,
not a claim that their external spending is capped.

Every attempted provider effect is a distinct ledger row, including unsuccessful responses/retries. Valid reported
cost settles once, rounded upward; duplicate recovery cannot change an already settled charge. Predispatch failure
releases only that request. Cancellation/crash/missing or invalid cost retains its reservation; unknown is never
zero. SQLite recovers interrupted transactions. Unknown dispatched effects are not replayed. Old-month liabilities
retain their original month and remain visible after reset, while new-month requests use only the new cap. An
upstream price-bound breach records the actual amount and persists a profile-wide halt: reconcile and requalify
prices before further admission; server behavior outside its pinned billing contract cannot be locally guaranteed.

`/file-screening reconcile` is native operator-only. Select the exact outstanding ID, independently verify its
billed/no-charge outcome, enter upward-rounded integer micro-USD and a nonsecret evidence identifier, then confirm.
The same command offers imported legacy pending liabilities first, requiring the exact digest, independently
verified actual amount, billing month and evidence before one immutable settlement. Historical attribution is
retained; a price breach persists the global halt. Unknown stays reserved. Do not use zero merely because a
process died, a response failed or the calendar changed.
A price halt deliberately has no automatic reset. Preserve the SQLite database, its journal and legacy ledgers;
never delete accounting to regain budget. Diagnostic initialization does not create a fresh ledger or paid request.

## Observations and qualification limits

Native status/tool observations identify actual session, operation, phase, hashed path/need, range, decision,
reason and available cost/latency. They omit source bodies, caller prose, credentials and private reasoning.
The build monitor continuously drains stderr and relays only validated screening fields into its ordinary feed;
raw diagnostics remain private. Cache reuse is distinct and reports zero provider calls, not a newly billed screen.

Owned behavior tests cover actual admission and accounting. Qualify native loading/setup/TUI, hook order, launchers,
source protection and unsupported routes directly; do not add product-suite tests of wrappers/Markdown/configuration.
Local synthetic transport outcomes, forced process death and concurrent accounting are not live Jev/model-accuracy
or savings evidence. Native PTY transcripts are not terminal images. Live endpoint/source authority, representative
screening investigations and independent Review/QA remain explicit TASK gates, not waived by green local checks.
A bounded real native parent → Agent dispatch tool → provider build monitor → fresh native child investigation has
completed on Sol/high with actual main-provider inference and source-bound instruction/assignment/reference/known-
target reads. Native operator enrollment/import/reconciliation and controlled cancellation/revocation are also
qualified. Those earlier probes are not live Jev evidence or a representative screening savings/recall comparison.

A subsequent bounded live qualification and one explicitly approved corrected recovery pair used the same two
unchanged synthetic candidates, production client/Pi-managed credentials and real shared monthly ledger. The
original parent positive/negative/cache/read evidence was retained, not replayed; recovery established successful
parent/child instruction exceptions and child screening, cache reuse, receipt read and post-negative ordinary
blocking. All four authorized paid attempts are consumed: 66 micro-USD settled, zero reserved, plus 13 imported
legacy micro-USD. Temporary enrollment is withdrawn; unused dollar allowance grants no further run. The child
final omitted the requested fee formula, so process/screening completion is not useful answer synthesis. Genuine
zero-inference operator PTY interactions and rendered-line captures are retained, **not screenshots** or independent
visual certification. The artificial corpus establishes neither representative recall nor net context/cost savings.
See the [recovery checkpoint](../../planning/tasks/00309-TASK.md#engineer-approved-corrected-recovery-pair--2026-10-15)
and source-bound handoff for combined provenance, costs, counts and remaining independent acceptance boundaries.
