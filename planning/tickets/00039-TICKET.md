---
id: TICKET-00039
epic: EPIC-00006
title: Require relevance screening before loading file contents
status: ready-for-agent
---

# Require relevance screening before loading file contents

## Problem statement

Agents consume context by opening files before establishing whether they contain the needed information. The
Harness needs a required, economical discovery step for single and multiple files without concealing uncertainty,
missing relevant evidence or making model relevance judgments control mandatory instructions.

## Solution and boundaries

Deliver `jev_read_file` under the [Harness amendment](../epics/00006-EPIC.md#approved-incremental-harness-protection-and-retrieval--2026-10-07),
using [TICKET-00037](00037-TICKET.md). Accept a path, bounded list or glob, the information sought and a typed
question. Prefer specific yes/no questions; support declared classification choices with unknown/other exits.
Read authorized content for Jev while returning judgments and provenance instead of full contents to the main Agent.

Require Agents and sub-agents using the participating Harness profile to screen discovery reads, singly or in
batches. Deliver shared system instructions and relevant skill guidance together with the available tool; do not
activate instructions requiring a missing tool. Start with metadata/frontmatter, paths, symbols or graph candidates
when they can narrow the search without loading full files. Prompt compliance is the initial requirement;
hard interception requiring screening receipts is excluded from this delivery.

The initial full-file read threshold is a positive Yes probability `noul >= 0.70`, or a predeclared relevant
classification with reported confidence at least 0.70. A confident No/irrelevant answer does not pass. Preserve
these distinct measures rather than describing either as measured accuracy. A complete positive permits reading
relevant sections; load the whole file only where useful. Apply the rule independently to every file in a batch.

Below-threshold, unknown, failed and partial evaluations do not grant ordinary full-file reads. Narrow the need,
use bounded structural search, or apply a documented required-evidence exception. Do not repeatedly rephrase the
same question merely to get a passing result. A failed/partial evaluation cannot establish that the answer is absent.

Mandatory governing/selected-skill instructions load directly. A known-target exception also permits a direct
read before screening when concrete evidence already establishes the exact file and the current work requires
its source: an explicitly assigned file, a verified symbol/search result, or a previously established edit/quote/
verification target. Self-reported certainty (including "100% sure"), a plausible filename or familiarity alone
is not sufficient. Record the path, purpose and establishing evidence in the working trace; read the smallest
useful range, using a whole file only when that purpose requires it. Reassess when the target, source or need
changes; uncertain targets return to screening. Apply the exception independently to each file, never to an
inferred directory or glob. It bypasses only relevance screening, not permissions or sensitive-file restrictions.

Bounded exact-source reads to edit, quote or verify required evidence remain available even after a negative or
unavailable screen; record the obligation and reason if an entire file is needed. Ordinary discovery cannot
silently use these exceptions. Report outage degradation and use only these bounded search/evidence paths rather
than silently reverting to unrestricted bulk reads.

Exclude memory ingestion/promotion, replacement of graph search, compaction, workflow routing, arbitrary command
execution and hard read interception. The managed first-party MCP integration direction remains; TASK decomposition
must identify the supported local tool delivery for the interim profile without requiring full managed services.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Ask about one file | N/A: no source mutation | `jev_read_file` with path, need and typed question | Screening completed/failed observation | Authorized source read, bounded provider request and optional scoped cache entry |
| Scout several files or a glob | N/A: no source mutation | Bounded per-file relevance query | Batch coverage/results observation | Authorized discovery and bounded parallel evaluations; explicit skips/errors |
| Reuse a current judgment | N/A: cache is disposable | Match source, question, evaluator, policy and current authority | Cache-use observation where needed | No upstream call on a valid hit; no expanded access |
| Open supported evidence | Existing bounded read operation | Inspect relevant sections/full source under the screening convention or documented exception | Read/exception observation; no permission grant | Content enters Agent context only for the stated need |
| Inspect usefulness and cost | N/A: read-only reporting | Coverage, misses, saved context, total cost and latency | N/A: reporting creates no Workflow state | Bounded redacted report |

`jev_read_file` is the approved Agent-facing tool name; exact request fields and transport belong to TASK design.

## Validation and permissions

- Apply current file authorization, canonical root containment and symlink escape protection before reading or
  provider disclosure. Keep sensitive-file exclusions and provider-egress rules independent of relevance scores.
- Bind results to the exact bytes evaluated, question/choice definitions, evaluator and policy version. Detect
  changes during reading and refuse stale reuse. Recheck access on cache hits; broader callers cannot inherit
  narrower callers' private cached content or metadata.
- Bound candidate enumeration as well as evaluation: files, bytes/tokens, concurrency, time and spend. Prevent
  unbounded recursive traversal or symlink loops. Report oversized, binary, excluded, errored or unevaluated files.
- Return judgments, applicable probability/confidence, source digest/revision, coverage and per-file reasons.
  Identify chunking and incomplete evaluation. A partial screen cannot qualify a whole-file read via the normal
  positive path or establish a complete negative. Do not fabricate quotations or supporting line references.
- Keep the evaluator from executing file contents or following instructions embedded in them. The tool performs
  no shell execution, source writes, authority mutation or memory ingestion.
- Shared instructions must cover supported child sessions and show unavailable profiles honestly. Unsupported
  external harnesses are not evidence of universal compliance. Coordinate activation with TICKET-00038 when
  claiming the complete first-milestone profile, without making screening implementation wait on unrelated guard work.

## Acceptance and evidence

- Demonstrate a yes/no and a classification inquiry without returning full source to the main Agent; verify that
  relevant/irrelevant choices and the 0.70 boundary behave as specified, including 0.69, 0.70 and a confident No.
- Demonstrate single-file, batch and glob work with independent results, explicit incomplete coverage and selective
  source follow-up. Test changed-content cache invalidation and current-access checks.
- Exercise provider failure, unknown/partial results, required-instruction loading, an evidence-backed known-target
  direct read and a justified exact-evidence exception. Include a plausible-name/confidence-only case that still
  requires screening and a multi-file case where each exception needs its own evidence. A failure never becomes
  a fabricated negative or silent bulk-read permission.
- Qualify containment and bounded file handling directly, including symlinks, protected files, enumeration limits
  and denied provider disclosure. Confirm no source content/credentials leak through normal results or receipts.
- Inspect actual Pi parent/child instruction loading and representative investigation behavior; state where the
  convention is advisory instead of claiming enforced interception. Test owned tool behavior rather than prompt text.
- Compare representative work against direct reads: main-Agent context saved, total Jev/main-Agent cost and latency,
  missed relevant files, unnecessary reads and exclusions. Preserve false negatives and unavailable cost facts.
  Do not claim a 70% threshold guarantees savings or recall. Run applicable repository gates for implementation.

## Sequencing and TASK readiness

Consume TICKET-00037's access, validation and usage capability. This work can proceed independently of guard
implementation after its shared dependencies are available. It does not wait for WF-024 through WF-026 memory
contracts, database Planning, browser delivery or sandbox qualification. At TASK decomposition, pin the local tool
registration, parent/sub-agent instruction propagation, supported root/disclosure policy and bounded defaults.
Mark an affected TASK needs-info if a necessary guarantee remains unresolved; do not silently widen scope.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00162](../tasks/00162-TASK.md) | Screen individual files and require selective reads | ready-for-agent |
| [TASK-00163](../tasks/00163-TASK.md) | Screen file sets with bounded discovery and cached judgments | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this requirement split and initial 0.70 positive-read bar on 2026-10-07. This TICKET is accepted for
TASK decomposition. No active system prompt, skill instruction or tool implementation changed through this record.
Later integration with authorized memory stays with [WF-026](../wayfinder/tickets/WF-026-define-sandbox-memory-retrieval-and-mcp-access.md).

On 2026-10-07 John raised a known-target exception while approving the access TASK split. The exception above
refines the existing exact-source allowance: concrete target evidence and a stated need, not a subjective
certainty score. It remains a planned instruction convention, not an implemented interception rule.

### Approved TASK decomposition — 2026-10-07

John approved [TASK-00162](../tasks/00162-TASK.md) for the single-file tool and required selective-read guidance,
blocked by TASK-00159, followed by [TASK-00163](../tasks/00163-TASK.md) for bounded lists/globs and cached judgments.
Both remain unranked and preserve current Board priorities; neither waits for command-guard implementation.
Local delivery uses the existing Pi extension, with tool and shared instructions loaded together in each qualified
participating parent/child session. Operator roots, exclusions and disclosure permissions are mandatory.

Initial limits are 32 KiB source per file, additionally bounded by the shared encoded request ceiling; batch limits
are 50 files, 1 MiB total source, 10,000 visited directory entries and 60 seconds. Shared concurrency, request
timeouts and spending ceilings apply. The session-local cache retains at most 128 judgment-metadata entries,
with current-access checks, digest validation and no source bodies/cross-session sharing. The TASKs own detailed
acceptance, including the 0.70 rule, known-target/required-evidence exceptions and honest incomplete coverage.

The selected first-milestone TICKETs 00037–00039 now have approved TASK decompositions. This does not complete the
rest of EPIC-00006 or its follow-on maps, activate runtime tools/instructions, or authorize implementation/publication.
