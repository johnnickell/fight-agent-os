---
name: adopt
description: Compare a named project's rules and package contracts with Agent OS guidance and propose graded alignment choices without editing the target.
---

# Adopt

Resolve one target repository and a bounded comparison topic from the request. Reuse known scope; ask only when
it is materially ambiguous. Read the target's applicable instructions, accepted decisions, project profile and
actual source/test/gate contracts. Treat its authority as local to that project; Agent OS is a comparison baseline,
not a superior policy. No project mutation or dependency change is authorized by this assessment.

Read the relevant portion of [Agent OS standards](../../../docs/engineering/STANDARDS.md) and
[ADR 0001](../../../planning/adr/0001-application-ownership-and-orchestration.md). Record inspected revisions,
dirty-state limitations and source locations. Verify Composer package/version facts from the target's actual
installation or lock; a library checkout may have no tracked lock. Do not mistake a source branch for the version
installed in a consumer. Inspect public capability signatures before proposing compatibility changes.

Compare only relevant architecture, naming, PHP/HTTP, testing, ownership and delivery behavior. Distinguish:

- **Compatible:** the same intent is already met; copying guidance adds no value.
- **Conflicting:** a real local decision or public contract differs; name the decision owner and consequences.
- **Non-applicable:** an application-specific runtime/HTTP/release assumption does not fit this target.
- **Undecided:** missing evidence or policy needs resolution; it is not a conformance defect.

For each meaningful difference, give observed evidence, applicable rule, package versus consumer ownership and
one of **adopt**, **adapt**, **defer** or **reject**, with rationale. Assess migration/backward-compatibility cost,
existing test and gate capacity, documentation impact and a bounded verification path. Do not weaken a target's
coverage, hosted-check or public API compatibility requirements because Agent OS has different ones. Similar
names do not prove compatible behavior, and absent HTTP runtime does not establish a missing library feature.

Deliver a concise comparison and recommended decisions, with uncertainty and excluded areas. Preserve private
source identities in authorized ignored local evidence rather than portable reports. No copied Factory planning,
global standards rollout, version updates, runtime enrollment, commit or PR follows from the recommendation.

Stop with the human decision or target-owned planning handoff. An approved change uses that project's own
planning/checkout process. [Architecture](../architecture/SKILL.md) explores a selected design tradeoff;
[audit](../audit/SKILL.md) assesses implementation against already accepted rules; [review](../review/SKILL.md)
independently evaluates an implementation within its authority. This skill does not perform those later stages
implicitly. The [source comparison](../../../docs/engineering/SKILL_GAPS.md) records the deliberately narrower
scope than an automated adoption-and-publication workflow.
