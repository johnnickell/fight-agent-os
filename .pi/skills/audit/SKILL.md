---
name: audit
description: Assess a named code boundary against accepted project standards with read-only traces, counterchecked findings and explicit sampling limits.
---

# Audit

Use for a scoped code assessment beyond one implementation diff. Establish the repository, revision or dirty-tree
identity, selected modules/use cases, question and evidence limits from the request. Read its applicable instructions
and accepted decisions. For Agent OS use [engineering standards](../../../docs/engineering/STANDARDS.md) and
[ADR 0001](../../../planning/adr/0001-application-ownership-and-orchestration.md). Another project's rules remain
its authority. Ask only for missing scope that materially changes the assessment.

Trace representative success, rejection and failure paths through their real callers, policy owner, persistence,
external effects and presentation. Read complete relevant methods and their contracts; name what was sampled.
Use [Graphify](../graphify/SKILL.md) for orientation when useful, then verify claims in current source. A graph,
search hit or passing gate does not establish semantic correctness or coverage of unread code.

For each candidate finding, check the strongest counterexample: alternate entry paths, package ownership,
accepted exceptions, transaction guarantees and existing tests. Use the severity meanings in
[review standards](../../../docs/engineering/REVIEW.md), without turning this assessment into independent TASK
acceptance. A finding states:

- The violated local requirement/rule and exact file/symbol/line at the inspected revision.
- Trigger, affected behavior and practical consequence, with observed or traced evidence.
- Counterevidence considered, remaining uncertainty, severity and a feasible bounded correction.

Keep confirmed defects, unanswered questions and optional improvements distinct. Missing evidence is not proof
of a defect; style taste is not a failure unless an applicable accepted rule requires it. No findings is valid for
a small sample, and does not certify the rest of the system.

Deliver scope, findings in impact order, evidence, sampling limits and any useful work group proposals. A proposed
group names its outcome, affected area, dependency, risk and verification; it is not a created TASK. Keep ordinary
reports in the response; requested local evidence belongs under ignored `.runs/` subfolders. Sanitize private data.
No PDF, Desktop output, scanner or repository-wide sweep is required.

Stop at the assessment. Do not repair code, create records, publish or approve implementation. Route a selected
architecture choice to [architecture](../architecture/SKILL.md), a reported bug to [fix](../fix/SKILL.md), or broader
accepted requirements through [planning conventions](../../../planning/CONVENTIONS.md). An independently requested
TASK review belongs to [review](../review/SKILL.md), preserving reviewer independence. Source comparison and local
qualification are recorded in the [skill assessment](../../../docs/engineering/SKILL_GAPS.md).
