# Bounded file relevance screening

**TASK-00309 implementation candidate:** the [enrolled profile](FILE_SCREENING_PROFILE.md) adds operator preview,
automatic actual-session allocation, shared monthly accounting, trusted evidence intake and local receipt admission.
The amended standing profile keeps ordinary authorized shell with separate command-risk assessment; shell can
bypass relevance admission and is not OS-confined. Use normal Pi/provider-owned Build, not a replacement executor.
Historical bounded native/build dispatch and synthetic live screening/admission have recorded evidence, including
one explicitly approved corrected recovery pair; parent screens were not replayed. The child final omitted the
requested formula. Representative investigations, terminal screenshots and independent acceptance remain pending;
see the [historical recovery checkpoint](../../planning/tasks/00309-TASK.md#engineer-approved-corrected-recovery-pair--2026-10-15).
The manual activation/SDK limitations below remain the supplier/legacy path and historical evidence; they are not
a claim about complete profile delivery.

`jev_read_file` is an opt-in consumer of the [shared typed-judgment capability](JUDGMENTS.md), not another provider
client. TASK-00162 supplies one regular UTF-8 file per call, controlled behavior and bounded live Pi SDK
qualification. Synthetic parent/child investigations, fresh evidence synthesis and an unscripted direct-read
comparison are retained; these are not independent acceptance, general accuracy evidence or global activation.
Previous TASK-00159/00184 allocations and TASK-00162 qualification session grants are closed. Do not reuse grants
or reset ledgers; a later session requires its own explicit operator allocation.

## Operator activation (not automatic)

Load the trusted package normally; do not alter Pi's main provider/model. The extension does not create a file
policy, resolve credentials or make a paid call on load. No machine-global settings were activated by this TASK.
The shipped default is unavailable, with `jev_read_file` hidden and no screening rule requiring it.

1. Configure the pinned Jev connection through the [operator setup](README.md#jev-setup-and-readiness-operator-only).
   `authorized-supplied-state`, current price confirmation and request/session ceilings are prerequisites, not
   permission to disclose repository/private source. The OpenRouter/TypeSafe connection/build remain pinned.
2. The operator must create **nonsecret** `file-screening.json` beside `connection.json` in
   `~/Library/Application Support/Fight Agent OS/jev/`, directory `0700`, file `0600`, operator-owned and not
   symlinked. There is no Agent-callable policy setter. Use the schema below; obtain actual values from the
   intended session and redacted `/jev inspect`. Generate the revision with a UUID library, not an invented UUID.
3. Allocate each participating Pi session explicitly by SHA-256 of its actual session ID. Parent and child
   allocations are separate; a new/forked session is not automatically authorized. Set finite expiry and positive
   integer micro-USD ceilings (at least 2,688 per request, request <= session <= connection session ceiling).
   `providerDisclosure: true` is explicit source disclosure permission for the configured roots/exclusions and
   allocation, independent of permission to read locally. Deny it to disable this profile.
4. Restart/reload the trusted extension, or start an explicitly loaded SDK session, and inspect **both** the tool
   declaration and its effective shared rule before advertising participation. A complete default Pi prompt must
   include tool guidelines. Missing/expired allocation, invalid settings, mismatched connection or denied
   disclosure hides the tool and rule at startup/before each Agent run. Runtime drift fails closed at each call.
   Operator changes to roots/disclosure/connection require a new revision and fresh qualification.

Policy schema (descriptive, not a copy-ready grant):

| Field | Value |
|---|---|
| `version` | `file-screen-v1` |
| `revision` | Operator-generated UUID or bounded nonsecret authority revision |
| `configuration` | Exact 64-character connection identity from `/jev inspect` |
| `allowedRoots` | 1–16 absolute **canonical** directory paths; symlink authority roots are refused |
| `excludedPaths` | 1–128 absolute protected file/directory paths; containment exclusions |
| `excludedNames` | 1–128 exact protected names; matched in any path component |
| `sessions` | Up to 64 entries keyed by SHA-256 of actual Pi session ID |
| Session entry | `requestMicroUsd`, `sessionMicroUsd`, integer `expiresAt` (Unix milliseconds), boolean `providerDisclosure` |

Policy keys are exact; empty roots/exclusions and malformed allocations fail closed. Built-in protections also
exclude `.git`, `.ssh`, `.aws`, `.gnupg`, `.env`/`.env.*`, `auth.json`, private-key names and `.pem/.key/.p12/.pfx`.
The operator must additionally exclude project-specific secrets, private data and policy/extension resources.
Permissions protect against other users, **not** malicious same-user tools/extensions. This is no sandbox or
hard interception of ordinary reads; positive relevance cannot override any access/disclosure rule.

## Tool contract

Inputs: exactly one of `path`, `paths` (1–50 explicit files), or `glob`, plus `need` (1–4,096 UTF-8 bytes) and one `question`:

Globs support `*` and `?` within segments and whole-segment `**` recursion. Braces, character classes and backslash
patterns are refused; no shell expansion occurs. Narrow the literal directory prefix before requesting broad discovery.
Enumeration reads directory entries through held no-follow descriptors, never following symlinks. Linux uses
Node's descriptor-relative `/proc/self/fd` path with one-entry buffers. On macOS, Node cannot enumerate `/dev/fd`
directories: the fixed `file-discovery.py` helper uses system `/usr/bin/python3` (qualified Python 3.9.6), isolated
stdlib-only mode, `os.scandir(fd)`/`fdopendir` and descriptor-relative `O_NOFOLLOW` child opens. It receives only
nonsecret policy/pattern data and the held directory descriptor, with a minimal environment, bounded I/O and a
killable deadline. No shell, source execution, package installation or arbitrary command is involved. Missing helper
runtime is an explicit incomplete enumeration, not fallback to a pathname/shell scan. This is a new macOS glob
runtime prerequisite; single files/lists do not require Python. It stops at 50 candidates, 10,000 visited entries, depth 128 or 60 seconds; skipped protected directories are not explored.
Counts describe only visited/enumerated scope. Count/entry/time stops report additional candidates may exist, not an
invented total. Explicit lists deduplicate canonical paths, and unauthorized paths are redacted. Authorized batch results
include bounded relative paths for follow-up (maximum 512 bytes); longer names are skipped without source disclosure.
The single-file result keeps its existing hashed path identity.

Batches consume at most 1 MiB total initial-source bytes and use at most two workers, still subject to the shared
two-outstanding-request, fifteen-second request, encoded-payload and persisted spending ceilings. Initial reads consume
the allowance even when decoding, stability checks or payload admission later fail; unread oversized/denied files
consume no bytes. `coverage.totalSourceBytes` reports this consumption; `admittedSourceBytes` separately counts bytes
in successful initial UTF-8 snapshots, not necessarily completed evaluations. Neither counter includes validation
rereads. Each file gets an independent result. Partial failures preserve valid completions; cancellation stops new
dispatch and rejects obsolete late answers. Results are
bounded to 64 KiB: if judgment metadata does not fit, explicit `result-presentation-limit` markers replace it and
coverage is incomplete. Full shared receipts remain in their owning bounded inspection buffer; batch receipts are compact.
`providerAttempts` counts calls to the shared capability, including admission failures, not necessarily dispatched/billed
calls. Available reported usage sums once; `unknownCosts` and retained reservations remain separate from known cost.

A 128-entry LRU stores judgment metadata only, at most 8 KiB per entry (1 MiB retained metadata ceiling). Cache identity
binds actual session, complete authority policy/allocation, exact content digest, need, declared question/choices,
configuration/evaluator, rubric and policy. Complete positive and negative judgments may be reused; unknown/failure
results are not cached. Every hit rechecks current policy, source access and exact digest, with fresh source revision.
Hits have `receipt: null`, `cache.hit: true`, and no new provider usage. Local `sourceChecks` counts snapshot attempts;
`sourceCheckBytes` counts all bytes actually read, including rejected snapshots and independent validation rereads.
Authorization checks may reread the same bytes several times, so this local total can exceed the initial-source ceiling.
No source body enters the cache; shutdown/reload and observed authority drift clear it. It is not shared with children.
Current access is checked again before delivery, including early batch completions. This remains same-user trusted
runtime protection, not enforced read interception or an OS sandbox.

- `{ type: 'yes-no', instructions }` is preferred.
- Classification uses `{ type: 'choice', instructions, choices, unknownChoice, otherChoice, relevantChoices }`.
  Choices map identifiers to descriptions; unknown and other must be distinct declared choices, neither relevant.
  Declare the nonempty relevant set **before** the answer. Shared limits allow at most 16 total choices.

Question instructions are at most 4,096 UTF-8 bytes. The complete consumer request **and** actual encoded provider
payload each remain <=65,536 bytes; escaping may make even a 32 KiB source too large. Sources are not truncated.
One question consumes the existing eight-question ceiling; two shared outstanding operations, fifteen seconds,
no retries, price confirmation and persisted shared monetary ceilings remain binding.

Access checks precede source opening. Canonical and lexical paths must remain within the operator roots and outside
exclusions. Symlinks below roots (even in-root aliases), hardlinked files, non-regular inputs, invalid UTF-8/control
bytes and sources >32 KiB are refused. The open itself prevents ancestor-symlink races: qualified macOS uses the OS `O_NOFOLLOW_ANY` flag; the Linux
filesystem adapter used by focused checks uses descriptor-relative `/proc/self/fd` traversal with `O_NOFOLLOW`
on every component. Other filesystem hosts fail closed, and the participating Jev runtime remains macOS-only.
Before reading, the opened descriptor must match the authorized inode and metadata; reads are bounded to 32 KiB
plus one detection byte, further capped by the remaining initial-source allowance for a batch candidate. When no
detection byte fits, post-read size/metadata checks still reject growth rather than accepting a truncated snapshot.
Metadata/path identity are checked again after the read. The source is re-resolved and compared before disclosure
and after provider completion. Changed/replaced or unstable source discards the judgment. Same-user arbitrary filesystem mutation is not an OS security boundary.

Results contain `status`, `threshold`, typed `judgment`, question digest and predeclared relevance set, raw-byte
SHA-256/source revision/byte count, coverage and the shared redacted receipt. Source/path identifiers are hashes,
not content or excerpts. Revision hashes bind inode/size/nanosecond timestamps/mode/link count; raw digest binds
exact evaluated bytes. The shared receipt's source digest instead covers encoded state (need plus untrusted file).
Request and question digests bind evaluator input. Provider explanations, quotes, supporting lines and source
bodies never enter normal results/receipts. The Agent already retains its question in the tool-call arguments.

A complete Yes probability >=0.70 or predeclared relevant class confidence >=0.70 is `positive`. Confident No,
below-bar and irrelevant/other classes are `not-positive`; unknown is `incomplete`/`unresolved`. Failure is
unresolved with no invented negative. `coverage.complete` means wholly answered and source-current, not model
accuracy. The [shared rule](FILE_SCREENING_RULE.md) requires selective follow-up and records direct-read exceptions.

Explicit outcomes include denied root/sensitive file/symlink, unsupported file/hardlink, binary/source/payload
limits, missing source, unstable source, unallocated session, stale policy, denied disclosure and every shared
provider/credential/budget/malformed-answer failure. Raw filesystem/provider errors are not echoed. Rejected
source/policy inputs have no credential retrieval or provider effect. Unknown provider cost stays null; measured
usage/cost enters Pi tool accounting only when total cost is actually reported. Pi requires numeric cache/cost
buckets, so zero cache buckets are accounting placeholders, not evidence of reported cache absence.

## Supported session path and limits

The supported integration path is Pi 1.2.0/macOS SDK: the host explicitly loads this package's Jev extension in
both parent and child, binds session lifecycle, uses the standard system prompt and supplies distinct operator
allocations for their actual session IDs. Qualification includes controlled tool calls and unscripted live
parent/child investigations; the child is a bounded synthetic investigation, **not** an independent workflow role.
A fresh evidence-only SDK parent/child also receives actual allocation/root/disclosure facts in its prompts;
its inherited observations are explicitly external evidence, not resumed history or fresh screens.
No managed launcher is added. CLI sessions using default prompts are expected to use the same extension, but a
native CLI/TUI child is not qualified by this SDK evidence.

Pi's example subagent launcher, external harnesses, missing/filtered/disabled extensions, hidden declarations,
custom/forced prompts omitting tool guidelines, concurrent processes resuming one session and automatic grant
inheritance are **unsupported/unqualified**. A tool alone does not establish instruction propagation. Parent and
child share no cross-process concurrency or aggregate ledger. The operator must separately bound both allocations
and their combined account exposure; no aggregate parent guarantee is advertised.

## Evidence and next qualification

Owned behavior tests exercise probability/classification boundaries, input/access/disclosure, encoded bounds,
changed-source/policy, redaction and failures. Filesystem handling, actual tool/rule loading, lifecycle, parent/child
and instruction walkthroughs are qualified directly under ignored `.runs/tasks/TASK-00162/initial/`.
Controlled transport is not remote-provider evidence or a model-accuracy/recall/savings experiment. Live
qualification is separately retained under `.runs/build/TASK-00162/run-r3ZyZd/live-VCA2d5/` and
`evidence-recovery-517dvl/`, with exact drivers, actual session snapshots, request/prompt inspections, disclosure
manifests and durable accounting. See [the qualification checkpoint](../../planning/tasks/00162-TASK.md#live-qualification-checkpoint)
for source identity, gate binding and the distinction between initial, inherited live and fresh evidence.

The operator's later `$0.10` TASK-wide Jev grant superseded the initial four-call proposal. Eight paid effects
settled at **159 micro-USD**, no pending reservations; closed stages release **99,841 micro-USD** to the coordinator,
not automatically to later sessions. Seven original inquiries were preserved without replay. The original parent
final response failed; a **fresh**, explicitly labeled synthesis completed the answer and one-line exact-evidence
quote after the inherited negative screen. One new supplemental child fixture received a fresh screen/read.
The original prompts omitted explicit operator allocation metadata; the fresh parent/child prompts expose it.
This is combined provenance, not an uninterrupted historical investigation or universal compliance claim.

Only <=1 KiB artificial files were disclosed through production `PiCredentials`/modelRegistry and
`DecisionsTransport` at `POST https://openrouter.ai/api/alpha/decisions`, requested `typesafe/jev-1.13`, returned
TypeSafe / `typesafe/jev-1.13-20260917`. No repository/private source, raw auth-file access, installation,
auto-trust, global activation or auth/settings edits were performed. The policy permits the union of the
explicit parent/child synthetic roots, not separate per-session source confinement. Main sessions remain
`openai-codex/gpt-6.1-sol/high`; fresh provider stream observations report `gpt-6.1-sol`.

The unscripted zero-Jev baseline chose 12 source reads. Original selective follow-up plus the required quote
returned **2,168** serialized source-result bytes versus **2,506** baseline bytes (**338** avoided). All tool
results including recovery were **25,371** versus **3,275** baseline bytes: an increase, not net savings.
Recovery also includes inherited evidence replay, mandatory-instruction reloads and a supplemental objective.
Full original parent sent-context bytes were lost on its error path; complete net-context savings are unknown.
Successful selective/recovery main tokens priced by Pi plus reported Jev cost total **$0.164883066**, versus
baseline main catalog cost **$0.0358668**; these are not billed USD. Original failed-request billing is unknown,
not zero. Baseline parent/child elapsed **65.42/25.88 s**; fresh recovery **87.23/43.78 s** (children included in
parents), in addition to the failed original investigation. No speed/cost advantage is established.

No demonstrated false-negative occurred among eight completed artificial candidate/need pairs; this is unblinded,
not an accuracy estimate. The baseline child read one irrelevant worksheet; selective ordinary reads did not.
The original concurrency rejection and undispatched unchanged retry remain visible. Live unknown-choice behavior
was not observed; boundary/denial/failure cases rely on controlled behavior evidence, not invented live results.
Independent Review/QA, TASK completion and delivery remain separate.

## TASK-00163 implementation checkpoint

Bounded lists/globs and the metadata LRU are implemented but not independently accepted. Fresh evidence is under
ignored `.runs/tasks/TASK-00163/runs/engineer-scWR1F/`; see the
[TASK checkpoint](../../planning/tasks/00163-TASK.md#engineer-implementation-checkpoint--2026-10-14) for exact
runtime/source manifests, tests, comparisons, accounting and unresolved qualification.

Real Pi 1.2.0/macOS SDK parent/child inquiries used lists, yes/no and predeclared classification with selective reads.
A separate fresh pump inquiry used a glob and retained protected-subtree/symlink skips as incomplete coverage.
The first macOS filesystem attempt failed because Node cannot enumerate `/dev/fd`; the fixed descriptor-relative
helper passed direct boundary checks and the new glob inquiry. Later early-result source checks have fresh failing-
then-passing regressions; earlier live manifests are not mislabeled as literal-final-source evidence.

The initial two scripted nested-cache probes failed in their evidence drivers; their cache/source-withdrawal assertions
did not complete. The first recovery, separately authorized as a ninth identity, also failed: it inspected top-level
`details`, but `ctx.executeTool()` returns an envelope whose screening metadata is **`outcome.result.details`**. Persisted
`ToolResultMessage.details` is a different contract. All failures and consumed grants remain retained, not reconstructed
as success. Owned behavior checks cover cache withdrawal, source changes, bounds and cancellation.

John then authorized one tenth identity. After complete offline direct qualification of the scripted sequence, actual
Pi 1.2.0/macOS SDK/json recovered the missing assertions on committed production source: **seven raw nested outcomes,
eight controlled evaluations, 12 passing assertions**. The initial glob returned independent results with redacted
protected-subtree/symlink skips. A repeat produced two cache hits, zero upstream attempts and six local checks/141 bytes;
current disclosure withdrawal redacted prior metadata. Mutation after an early completion invalidated only that result.
Two fresh cache seeds and two confirmed hits immediately preceded actual reload; afterward, two fresh evaluations and
zero hits demonstrated clearing. Tool/shared-rule declarations, unchanged source/model, orderly shutdown and grant
withdrawal were captured without duplicate reload subscriptions. See the
[recovered TASK checkpoint](../../planning/tasks/00163-TASK.md#engineer-qualification-recovery--2026-10-14) for exact
identity, source manifests, raw outcome paths and accounting.

The tenth identity made **zero external Jev calls**, using controlled synthetic credentials/transport and real pinned
Codex main requests. Controlled ledger amounts are simulations, not billed USD. Aggregate actual TASK Jev remains
319 micro-USD settled, zero pending; all **ten identities** are consumed/disposed and grants closed. Remaining budget is
not authorization to repeat an inquiry, resume a disposed identity or create an eleventh subject. This evidence recovers
the SDK behavior checkpoint, not live accuracy, native CLI/TUI qualification, independent Review/QA or acceptance.

All fifteen native Jev requests disclosed exact synthetic manifest inputs only and reported TypeSafe /
`typesafe/jev-1.13-20260917`. Reported cost totaled $0.000312312; ledgers settled 319 micro-USD, pending/uncertain zero,
with 59,681 micro-USD of the Engineer allocation releasable. Main sessions stayed `openai-codex/gpt-6.1-sol/high`;
auxiliary raw provider events reported `gpt-6.1-sol`. Main invoiced billing is unknown, not zero. No global activation.

The initial selective parent/child pair avoided 319 serialized source-result bytes but increased total tool-result bytes,
serialized sent-context bytes, known Pi-catalog-plus-Jev cost and latency compared with direct reads. The glob comparison
also increased total overhead. No demonstrated false-negative in this small unblinded sample is not an accuracy or
savings guarantee. Preserve negative screens, baseline irrelevant reads and per-file mandatory/known-target/exact-evidence
exceptions. Controlled cases are not live accuracy evidence; native CLI/TUI children and external launchers remain unqualified.
