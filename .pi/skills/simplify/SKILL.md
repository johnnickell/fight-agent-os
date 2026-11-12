---
name: simplify
description: Assess a selected code, documentation or test boundary for unnecessary complexity and propose evidence-backed simplifications or hand authorized cleanup to work.
---

# Simplify

Resolve the target repository and requested scope: code, writing, tests, or a named combination. Without a
boundary, start with the current change or ask which area matters; do not silently audit the whole repository.
Read the target's rules and the applicable [quality guidance](../../../docs/engineering/QUALITY.md).

- **Code:** trace real callers and ownership before proposing removal of unused code, forwarding layers,
  duplicate logic, obsolete fallbacks or needless branching. Similar syntax is not necessarily shared knowledge.
  Preserve validation, authorization, transaction behavior, public contracts and required data handling.
- **Writing:** remove repetition, stale explanations and comments that merely narrate obvious code. Retain useful
  reasons, constraints, public documentation and required docblocks. Apply the shared plain-language rules without
  changing requirements, technical names or quoted evidence. Do not shorten instructions by hiding conditions.
- **Tests:** identify each relevant behavior, independently established expectation, plausible failure caught,
  overlapping protection and runtime/maintenance cost. Look for tautological tests, obsolete scenarios and tests
  coupled to incidental implementation. Prefer retain, rewrite or consolidate when deletion would lose useful
  protection. Simple immutable Commands, Queries and Events still carry contracts worth testing.

Follow the target's mandatory unit-only coverage requirement and its test-count guidance. Ratios, line coverage,
similar assertions or a passing suite alone never prove a test redundant. Before removal, show where equivalent
meaningful protection remains or add its replacement in the same authorized change. Preserve distinct failures
and regressions. Do not add weak tests to inflate the unit count, combine unrelated assertions, or relabel slow
tests as units. Measure expensive suites and prefer the cheapest boundary that proves the behavior.

Focused mutation analysis can help check whether application assertions catch mistakes. Use existing supported
tools within the authorized scope, measure the cost, and inspect surviving changes; equivalent mutations can
leave behavior unchanged. Do not install a new tool, create tests of quality tools, or require a whole-project
mutation run merely to assess a cleanup.

Report a small prioritized set: exact location, practical benefit, evidence, proposed change and protection to
retain. Separate confirmed redundancy from uncertain reachability or optional taste. No arbitrary deletion quota.
Assessment selects an inspect workflow and does not edit source. When cleanup is already authorized, preserve
that decision and hand the bounded scope to [work](../work/SKILL.md) under the target's planning and checkout rules;
do not silently promote the inspect role. New design decisions belong to [architecture](../architecture/SKILL.md),
discovered bugs to [fix](../fix/SKILL.md), and independent acceptance to review/QA. End with a concrete
[Next](../../../docs/engineering/HANDOFFS.md), not an automatic cleanup or publication.
