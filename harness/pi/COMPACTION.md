# Native compaction and verified resume

Pi owns context reduction and session persistence. Fight uses native automatic compaction, `/compact [instructions]`
and exact-session resume, not a replacement summarizer, Jev dependency or second checkpoint store. Keep automatic
compaction enabled. The [workflow rule](WORKFLOW_RULE.md#compaction-and-verified-continuation) supplies the
required-fact and current-authority continuation procedure in participating native and SDK child sessions.
This is model guidance, not a deterministic operation gate or an OS sandbox.

## Operation

- Keep objective, constraints/decisions, done/pending work, exact open questions, revision-bound evidence,
  uncertain effects and next safe action explicit in session messages. Save durable project outcomes in their
  owning records. Thinking is omitted and tool results are truncated to 2,000 characters for summarization;
  never leave the only required fact buried in a long tool result.
- Use `/compact` in native Pi. Optional instructions focus the summary; they cannot renew approval. Pi appends
  a compaction entry, retains recent history and leaves raw entries available in the same private session.
- Resume the exact intended session with native `pi --session /absolute/session.jsonl`, or select it explicitly
  in `/resume`. `--continue` means the most recent session for the directory, not necessarily the intended TASK.
  `fight-workspace --session` additionally requires the current cwd to match the session header. An ephemeral
  `--no-session` conversation cannot survive process exit.
- On continuation inspect `fight_workflow_status` and current instructions/record before dependent work. Trusted
  stage bindings are live host state, not something a saved summary recreates. Forked/unverifiable sessions and
  ordinary SDK subordinates retain their existing Reviewer fallback; independent Build stages start fresh.
  Do not resume a completed independent worker as another role or import its private transcript.
- On cancellation or failure, inspect the last saved compaction and original operation status. A failed attempt
  is not a new checkpoint. Correct the concrete provider/storage issue before an explicit retry; do not disable
  auto-compaction or blindly replay tools to escape failure. Unknown outcomes, unavailable evidence or current
  authority hold dependent actions. TASK-00166 owns bounded selective repair, not automatic bulk re-ingestion.

Compaction settings resolve global/project configuration and exact provider/model overrides. The native rule is
`contextTokens > contextWindow - reserveTokens`; `keepRecentTokens` controls the retained-history cut. Native
usage-backed context and character estimates can differ. Do not implement timing from the displayed estimate.
TASK-00168 owns Fight's configurable timing tiers. No settings file, model limit or active installation is changed
by this implementation.

## Qualification — TASK-00164, 2026-11-10

Tested installed Pi **1.5.2**, Node **22.23.1**, configured `openai-codex/gpt-6.1-sol`, thinking **medium**.
These are evidence, not version gates or provider execution attestation. Native capability checks, not equality
with this version, govern future adoption. No Jev tools/credentials were used. Existing provider authentication
remained Pi-owned; no credential values were inspected or logged.

Effective normal model limits: **500,000** context / **128,000** maximum output; compaction enabled,
reserve **16,384**, keep-recent **20,000**. Normal threshold therefore **483,616**. The native settings API and
`session_before_compact.preparation.settings` agreed. No matching override was effective. Summary requests and
first subsequent responses expose separate usage; a null post-compaction context is **unavailable**, not zero.

Disposable synthetic histories represented an inventory report with read-only/no-publication constraints,
SKU-counting decision, completed schema mapping, pending totals, an exact damaged-stock question, revision R7,
and a timed-out REPORT-17 with UNKNOWN outcome. Live summaries/responses used the authorized configured provider.
The harness did not manufacture summary text or provider usage. Generated background histories are not records
of actual warehouse operations.

| Observed path | Result |
|---|---|
| Native SDK core with normal discovered Fight extensions | Two manual compactions; same-file separate-process restart preserved objective, constraints, decision, done/pending work, exact question, evidence and next safe action |
| Default automatic trigger | Native `threshold` at **486,716** tokens; next response context **17,250** tokens |
| Abort during third manual summary | `session_compact_failed`, aborted; saved compaction count remained **2 → 2**; separate-process resume succeeded |
| Failed redundant manual request | Native `Already compacted`; no new compaction entry; more history permits another compaction |
| Native terminal PTY | Exact-file resume, native `/compact` entry persisted; continuation restated the exact question and refused uncertain-effect retry; no Herdr or RPC substitute |
| Bundled SDK engine used by Build (`runAgent`, `fight-engineer`, no inherited conversation) | Manual compaction and native child-file reopen retained required facts; current revoked approval/missing R7/UNKNOWN outcome kept effects paused |
| Same SDK child automatic path | In-memory-only qualification reserve **450,000**, keep-recent **20,000**, auto enabled; native `threshold` at **69,595**, subsequent context **36,798**; no persistent timing setting changed |

Fresh source-package qualification separately loaded this checkout's seven extension entries (without the
installed copy or personal extensions), confirmed the new continuation rule in the effective system prompt,
and repeated two manual compactions, cancellation and separate-process resume. Required facts and safe pause
survived; load errors were empty. Initial attempts to select source through settings overrides still loaded the
installed copy and are not source qualification. The corrected probe used explicit additional extension paths.

The SDK engine probe deliberately had **no trusted Build stage binding** and therefore did not grant Work or
independent acceptance authority. It qualified the shared child execution/compaction path, not nested Build
coordination or restart of a live stage. Existing trusted Work status was inspected independently; the host owns
binding lifecycle. The PTY probe resumed only disposable history, never the active coordinator or this worker.
A first PTY continuation was cancelled by the probe's too-early exit; the corrected probe awaited a finalized
assistant response. A subsequent redundant `/compact` correctly had no new material to summarize.

### Separate measurements

Durations below are measured wall time around native summary hooks/calls. First-response latency is measured
from the subsequent prompt to the first **text delta**, not first network byte or hidden reasoning. Values are
single samples, not latency guarantees. Input/output/cache counts are Pi-reported provider usage, not independently
metered billing. Provider pricing-derived costs are estimates; no cost acceptance claim is made here.

| Sample | Summary duration | Before context | First text after compaction | Subsequent context | Summary cache read/write | Subsequent cache read/write |
|---|---:|---:|---:|---:|---:|---:|
| Manual 1 | 8,566 ms | 30,460 | 1,562 ms | 39,125* | 0 / 0 | 0 / 0 |
| Manual 2 | 8,517 ms | 39,125 | 2,549 ms | 51,574* | 0 / 0 | 2,944 / 0 |
| Default automatic | 15,434 ms | 486,716 | 1,947 ms | 17,250 | 0 / 0 | 0 / 0 |
| Separate-process resume after abort | N/A | 51,574 | 2,445 ms | 51,932 | N/A | 51,456 / 0 |
| SDK child manual | 6,652 ms | 64,594 | unavailable | 36,834 | 0 / 0 | unavailable |
| SDK child automatic | unavailable | 69,595 | unavailable | 36,798 | 0 / 0 | unavailable |
| Cancelled manual | 57 ms to abort | 51,574 | N/A | previous state retained | unavailable | N/A |

`*` The subsequent prompt deliberately added background history for repeated-compaction qualification; these
are not pure compression ratios. Immediately after successful compaction, `getContextUsage().tokens` was null.
The initial seeded-history estimates (for example 525,172) were not provider measurements. A history with no
assistant usage did not trigger the pre-prompt check; subsequent real responses supplied usage and the observed
normal automatic threshold. Do not confuse estimated context above the window with a measured overflow.

### Privacy, retention and limits

No demonstrated required-fact loss justified companion storage. Native session files retain raw history and
compaction entries, including native workflow provenance; no extra state schema, writer, retention policy,
conversation copier or restoration authority is introduced. The native summary remains lossy: representative
success does not guarantee arbitrary recall. Missing required facts must pause dependent actions. A summary's
historical approval never defeats current denial, and a concurrent unauthenticated “yes” did not resolve the
exact question in the resume probe. REPORT-17 remained UNKNOWN and was not replayed.

Private probe scripts, synthetic session files, captures and raw measurements live in canonical ignored
`.runs/tasks/TASK-00164/runs/` only while consumed by independent Review/QA. Ordinary logs contain no transcript
or checkpoint contents; the detailed probe outputs are private TASK evidence, not runtime logging. Preserve
existing real sessions/settings and historical receipts. Land retires eligible disposable probe material after
outcomes reach the owning TASK/PR. Do not enable transcript export/share as a compaction recovery fallback.

Not qualified: SIGKILL during a filesystem append, disk corruption/full-disk durability, provider overflow/error
recovery beyond the tested cancellation, managed server grants/leases, other models/providers, another platform,
Herdr visual layout, a genuine Build-bound stage restart, and exact timing of TUI automatic compaction. Native
core default automatic behavior plus TUI manual/resume and SDK child behavior were exercised separately. The
SDK child's lowered disposable threshold is not evidence of production Fight timing tiers. No global installation,
trust, active coordinator, current stage state or shared settings were modified. Independent Review/QA remain
required; runtime qualification is not acceptance.

References: installed supported Pi [compaction](https://pi.dev/docs/latest/compaction), session format, sessions,
SDK and extension references; current source `workflow-extension.ts`, `workflow/access.mjs`, and the bundled
engine's `agent-runner.ts`. Future supplier changes require affected-path requalification, not a hard version pin.
