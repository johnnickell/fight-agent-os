---
name: architecture
description: Explore a bounded architecture question through real use cases and offer human-first interface, ownership and namespace options without refactoring.
---

# Architecture

Start with the question, affected use cases, maintainer's constraints and inspected revision. Read applicable
accepted ADRs, especially [ADR 0001](../../../planning/adr/0001-application-ownership-and-orchestration.md), and
[engineering standards](../../../docs/engineering/STANDARDS.md). Scope missing information before expanding a scan.
This is design exploration; cross-module compliance belongs to [audit](../audit/SKILL.md), and independent TASK
acceptance belongs to [review](../review/SKILL.md).

Trace the real caller, input contract, policy owner and outcome before proposing a new layer. As relevant, inspect:

- Domain decisions versus Application coordination; package-owned capabilities used directly versus justified
  owned orchestration. Preserve local command intent/event fact naming and accepted public package contracts.
- Adapter/framework dependencies, transport representations and server-side authorization across entry paths.
- Transaction owner and shared connection, durable intent, post-commit effects, retry/idempotency and failure
  recovery. Separate demonstrated guarantees from planned ones; do not claim exactly-once external effects.
- Human understanding: does an interface express the domain use case, hide useful complexity and leave changes
  near their owning concept? Design architecture/interfaces first; tests verify those contracts afterward.
- Namespace cohesion: unrelated classes in one directory can justify deeper capability subnamespaces. Trace
  callers and reasons to change; distinguish a reusable persistence mechanism from an activation/session concern.
  Class count is a signal, never a threshold. Avoid one-class folder inflation and extracting abstractions solely
  for test convenience. Include `Adapter/Persistence` when that is the requested scope, not on every invocation.

Compare proportionate options, including retaining the current design when warranted. Show affected files and
caller relationships, interface/ownership changes, benefits, tradeoffs, migration/test impact and uncertainty.
A small diagram helps when relationships are the issue; no HTML artifact or visual scaffold is mandatory.
Do not combine similar-looking code unless it represents the same knowledge and changes for the same reason.

Use the shared [quality checks](../../../docs/engineering/QUALITY.md) when test fragility, duplication, types or
guidance drift bears on the question. Identify the observable contracts and independently justified expectations
that would verify each option, preserving required side-effect ordering and compatibility. Explain affected
instruction boundaries and README/CHANGELOG implications; distinguish descriptive drift from a violated policy.
These are design and verification proposals, not permission to delete tests, refactor, or synchronize documents.
Apply the [human-facing prose guidance](../../../docs/engineering/QUALITY.md#human-facing-documentation) to the
explanation without weakening technical qualifications or inventing implemented guarantees.

Label observed violations separately from taste or speculative improvements. Recommend an option with a concrete
reason and identify the decision owner. An ADR conflict requires a visible proposed decision and consequences;
do not silently override the ADR. External authors' vocabulary and examples do not replace Fight names or policy.

Stop after options and the appropriate decision/planning handoff. No source move, interface implementation, ADR
mutation, automatic grilling, agent delegation or approval follows from exploration. Accepted implementation
belongs to a bounded TASK and [work](../work/SKILL.md).

Inspiration and adopted/adapted/rejected decisions are in the
[source comparison](../../../docs/engineering/SKILL_GAPS.md#architecture-and-security-source-decisions).
Matt Pocock's MIT-licensed exploration ideas informed this local adaptation; the [license](LICENSE) is retained.
Verraes and Martin are cited for ideas, without reproducing their articles.
