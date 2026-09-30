# Test, code and documentation quality

Use these checks when writing or assessing changed tests, code, instructions or human-facing documentation.
[Engineering standards](STANDARDS.md) retain ownership of architecture, test boundaries and the canonical gate.
The target repository's accepted rules prevail when a skill is used elsewhere. Findings need evidence; a pattern
match or a green suite alone does not establish a defect or correctness.

## Scope and workflow

| Workflow | When and how to apply these checks |
|---|---|
| Work | Check tests while writing them; inspect the TASK diff and directly affected contracts/docs before handoff. Repair only within authorized scope. |
| Review | Independently challenge the changed scope and evidence; use existing review IDs and report corrections without applying them. |
| Audit | Apply relevant checks to the named boundary, including its tests and instructions/docs; report sampled files, paths and unchecked areas. No automatic whole-repository sweep. |
| Architecture | Use contract, duplication and instruction questions to compare design options and their verification/documentation impact. Stop at the proposal. |
| Writing for Agents | Reconcile instructions within the authorized document scope; preserve policy authority and route unresolved decisions. |

Inspect directly related consumers when necessary to validate a finding; that inspection does not expand edit
scope. Record unrelated debt separately. No check adds automatic delegation, tools, branches, approval gates or
publication. A useful result may be to retain the current code or guidance with a concrete reason.

## Tests that protect contracts

For each new or materially changed test, identify the observable contract, a plausible defect it would catch,
and the source of the expected result. Requirements, published specifications, independently worked examples
and deliberately reviewed fixtures can supply expectations. Copying the current implementation's output or
repeating its algorithm cannot establish correctness by itself. Make this reasoning apparent in the test or
supporting evidence; do not add a mandatory comment or report row to every assertion.

Check whether a behavior-preserving internal refactor would break the test. Investigate these signals:

- Assertions about source text, private structure or internal call order instead of an owned behavior.
- Expected values copied from production constants/configuration without an independently established contract.
- Comparing a result with itself, calling the same implementation to obtain the expectation, or reproducing its
  algorithm in the test so both can share the same error.
- Snapshots accepted solely because they match current output, with no reviewed behavioral requirement.
- Mock arrangements that prescribe the implementation's entire call graph or only echo supplied inputs.

These are prompts to inspect the contract, not automatic deletion rules. Exact public error codes, wire values,
compatibility shapes and reviewed snapshots can be valid expectations. An outbound payload, required absence of
an effect, transaction ordering, or a retry/idempotency sequence may itself be the observable contract. Prove
that boundary behavior without freezing incidental collaborator order. Mocking alone is not a defect, and a
test failure after a claimed refactor may reveal a real behavioral change.

Retain a test when its contract and defect sensitivity are justified. Rewrite a weak test against returned
values, state, rejection/failure behavior or boundary effects where a contract exists. Delete only when no
useful contract remains or equivalent meaningful coverage is demonstrated. If removal would lose critical or
uniquely required coverage, add the replacement in the same change; record gaps honestly. Do not invent a route,
public API, wrapper or abstraction to satisfy coverage. Preserve the existing exclusions for product tests of
tooling/configuration and the regression-first rule for confirmed bugs. These reasoning checks do not require
mutation tooling, deliberately seeded failures or tests of tests.

## Code quality with evidence

Use the following questions within the selected scope. A cleanup preserves accepted behavior; a discovered bug
or compatibility change needs its own explicit contract and authorized scope, not a hidden refactor.

| Concern | Evidence and countercheck before proposing a change |
|---|---|
| Repeated logic or types | Identify the knowledge owner and reasons to change. Similar syntax or field shape does not justify merging separate domain/module contracts. Prefer an existing owned concept over a generic shared bucket. |
| Weak types or unchecked casts | Trace the value's producer, consumers and schema before tightening it. Preserve validated unknown input at trust boundaries; do not guess a type or replace runtime validation with an assertion. Apply the target language's conventions, including PHP and TypeScript where used. |
| Apparently unused code | Check callers, framework discovery, routes, container/configuration references, reflection and external public consumers. A text search with zero hits is insufficient evidence for deletion. |
| Legacy paths and fallbacks | Establish supported versions, deployed readers/writers, migration/recovery needs and flag state. Data no longer written can still need reading. Unknown reachability or compatibility stays an open question. |
| Defensive/error handling | Trace possible failures and intended recovery. Preserve trust-boundary validation, authorization, transaction cleanup and documented fallback behavior. Remove only demonstrated redundancy; make swallowed required failures explicit. |
| Comments and control flow | Prefer explanations of constraints and reasons; simplify needless nesting when behavior remains clear. Preserve required Fight docblocks, public documentation, specification references and useful historical rationale. |

Use existing owning tools when relevant, followed by source/contract inspection. Do not install a scanner,
reinterpret confidence scores as proof, or flag local style solely because an external checklist prefers another
form. Tie findings to a practical consequence or accepted rule, and separate optional readability improvements.

## Agent instructions and policy drift

When a change affects ownership, entry points, commands, invariants or workflow guidance, inspect the relevant
AGENTS.md files and linked standards/skills. Consider missing guidance, inaccurate guidance and obsolete content.
Place shared rules in one authority and link to it. Keep the root focused on universal requirements and routing;
add narrower instructions only at demonstrated responsibility boundaries. Do not create a file per directory or
force every instruction document into a fixed section template.
Keep content proportionate without a hard word quota or mandatory deletion count. An additions-only change is
valid when existing guidance remains accurate; an empty relevant diff does not justify inspecting unrelated
recent commits or inventing updates. Preserve necessary rationale and commands even when discoverable in code.

Verify how the active harness discovers nested instructions and follows references; do not assume an ancestor
chain automatically loads in every harness. Preserve accessibility of required rules through explicit routing
when automatic loading is unverified. Use [Writing for Agents](../../.pi/skills/writing-for-agents/SKILL.md) for
authorized edits, including triggers, completion, uncertainty and the next permitted action.

Distinguish descriptive facts from normative requirements. A renamed file can justify repairing a reference.
Code that violates an accepted invariant does not authorize deleting or weakening that invariant. Trace the
owning decision and raise the mismatch for correction or an explicit policy decision. Do not silently promote
observed behavior into approved policy. For uncertain removals, preserve the requirement and record the specific
question and decision owner. Remove stale guidance only with evidence that its referent or obligation ended;
leave historical decision records and approvals intact. Explain substantive removals in the change evidence.

## Human-facing documentation

Check meaning and accuracy before polishing prose. Follow the target's established README and CHANGELOG format;
update documents affected by the authorized change, not every document in the repository.

- README: verify commands, prerequisites, entry points and capability claims against current evidence. Keep
  implemented, planned, optional and unverified behavior distinguishable. Do not infer a complete user journey
  from scaffolding or a passing component test.
- CHANGELOG: describe observable changes and relevant fixes, compatibility effects, migrations and deprecations.
  Use the existing unreleased/release convention. Do not invent a version/date, imply publication, or rewrite
  historical release entries as a style exercise. No new changelog or entry is required absent target policy or
  an applicable change; a past factual correction needs explicit scope and traceable evidence.
- Prose: state the action or result directly, use specific nouns and verbs, and remove repeated framing, inflated
  claims, filler and duplicated explanations. Preserve the author's voice and useful structure. Patterns such
  as a dash, fragment or list are not defects by themselves; avoid mechanical word or punctuation bans.
- Preserve code, commands, API names, quoted text, links, technical qualifications and normative force during a
  style pass. Any factual or policy correction needs separate evidence and scope; elegance cannot weaken a rule.

Agent instructions prioritize precise decisions and boundaries; human documentation explains use and meaning.
Do not apply prose shortening in a way that hides either audience's needed constraints.

## Findings and completion

Under [review standards](REVIEW.md), use ST-04 for test quality and ST-03 for documentation/style; use SP-05 when
weak evidence leaves acceptance unproved and other existing IDs when ownership or behavior is implicated. A
missing proof remains Unverified; a demonstrated violation is Fail. Audit uses the same evidence discipline
without issuing a TASK verdict. Do not introduce a separate score or duplicate findings under every category.

Name the contract/rule, exact location, practical consequence, counterevidence and a bounded correction for each
finding. Distinguish confirmed defects, uncertainty and optional improvements. Report the inspected scope,
retained exceptions, meaningful coverage removed/replaced and remaining gaps. Work records self-check results;
review independently evaluates them. The canonical gate remains required and does not replace these judgments.

The [source comparison](SKILL_GAPS.md#test-code-and-documentation-quality-source-decisions) records the four
upstream inspirations and the local adaptations; this reference is original Agent OS guidance.
