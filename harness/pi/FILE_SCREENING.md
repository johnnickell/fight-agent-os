# Single-file relevance screening

`jev_read_file` is an opt-in consumer of the [shared typed-judgment capability](JUDGMENTS.md), not another provider
client. TASK-00162 supplies one regular UTF-8 file per call, controlled behavior and Pi SDK integration qualification.
Live file screening and unscripted model compliance remain **unverified** pending fresh bounded operator authority.
Previous TASK-00159/00184 live allocations are exhausted. Do not reuse their grants or reset ledgers.

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

Inputs: `path`, `need` (1–4,096 UTF-8 bytes) and one `question`:

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
plus one detection byte. Metadata/path identity are checked again after the
read. The source is re-resolved and compared before disclosure and after provider completion. Changed/replaced or
unstable source discards the judgment. Same-user arbitrary filesystem mutation is not an OS security boundary.

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
allocations for their actual session IDs. Direct qualification creates real Pi parent/child runtimes and executes
controlled tool calls; the child is a bounded synthetic investigation, **not** an independent workflow role.
No managed launcher is added. CLI sessions using default prompts are expected to use the same extension, but a
native CLI/TUI child and unscripted model investigation are not qualified by this SDK evidence.

Pi's example subagent launcher, external harnesses, missing/filtered/disabled extensions, hidden declarations,
custom/forced prompts omitting tool guidelines, concurrent processes resuming one session and automatic grant
inheritance are **unsupported/unqualified**. A tool alone does not establish instruction propagation. Parent and
child share no cross-process concurrency or aggregate ledger. The operator must separately bound both allocations
and their combined account exposure; no aggregate parent guarantee is advertised.

## Evidence and next qualification

Owned behavior tests exercise probability/classification boundaries, input/access/disclosure, encoded bounds,
changed-source/policy, redaction and failures. Filesystem handling, actual tool/rule loading, lifecycle, parent/child
and instruction walkthroughs are qualified directly under ignored `.runs/tasks/TASK-00162/initial/`.
Controlled transport is not remote-provider evidence or a model-accuracy/recall/savings experiment. The initial
direct-read comparison reports exact source/result bytes and latency under fixtures; main-Agent cost and actual
live misses/unnecessary reads remain unavailable, never zero. TASK completion, independent Review/QA and delivery
remain separate; see [the TASK checkpoint](../../planning/tasks/00162-TASK.md#implementation-checkpoint).

Proposed fresh live approval: **at most four** synthetic-only single-file requests, no retries/readiness call,
`POST https://openrouter.ai/api/alpha/decisions`, requested `typesafe/jev-1.13`, required TypeSafe build
`typesafe/jev-1.13-20260917`. Parent receives two yes/no inquiries (relevant and irrelevant synthetic files);
child receives two classifications (relevant and irrelevant), separate actual Pi IDs and source roots. Each file
is <=1 KiB and contains only artificial kettle/color facts and unrelated arithmetic, no repository/private source.
Request ceiling **2,688 micro-USD ($0.002688)**; each parent/child session ceiling **5,376 ($0.005376)**; combined
maximum reserved exposure **10,752 ($0.010752)**. Credential access is **only** production Pi-managed
`modelRegistry.getApiKeyForProvider('openrouter')` through `PiCredentials`, without key printing, auth-file reads,
new fallback sources or auth/global settings edits. Require renewed price confirmation and private run-scoped
settings/ledgers; preserve all prior grants/accounting. This is a proposal, not authorization. Unscripted parent/
child model-compliance evidence additionally requires authorized main-Agent requests, not fabricated controlled
responses; keep configured openai-codex/gpt-6.1-sol/high and record provider-reported identity only if supplied.
