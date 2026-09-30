# Engineering guidance comparison and skill gaps

This is a bounded comparison for [TASK-00130](../../planning/tasks/00130-TASK.md), not an adoption of Fight Software Factory authority. Source snapshot: local read-only `fight-software-factory` checkout `5ba0fc0` (paths below relative to that checkout). Current authority is [ADR 0001](../../planning/adr/0001-application-ownership-and-orchestration.md), [engineering standards](STANDARDS.md), [review standards](REVIEW.md), and the project-local `work`/`review` skills. The maintainer approved four separately bounded skill [TASKs](../../planning/tasks/BOARD.md); none is installed by TASK-00130.

| Source at Factory `5ba0fc0` | Comparison with Agent OS; disposition and reason |
|---|---|
| `docs/engineering/standards/Architecture.md` — inward dependencies, entity-owned decisions, handler coordination, repository-aware specifications, transaction/effect distinctions | **Adapt:** retain inward direction and keep state policy with its owner; require package-owned messages/handlers directly under ADR 0001, owned orchestration only for an observed cross-boundary need. Do not transplant Factory's generic `CompositeSpecification` default or `LookupException`→adapter error rule: neither grants an HTTP 404. |
| `docs/engineering/standards/HTTP.md` — single-use-case Actions, Responders, safe Views, protocol-specific transports | **Adapt:** use responsibility-based `Adapter/Http/Api` homes and distinguish actual middleware from mapper/representation; JSend only within the versioned API boundary. **Reject:** development stack-trace exposure and its example's general `LookupException`→not-found response; TASK-00020 explicitly requires sanitized output and explicit not-found types. |
| `docs/engineering/standards/Naming.md` and `PHP.md` — intent/fact message names, camelCase/snake_case mapping, Fight docblocks | **Adopt compatible subset:** existing local conventions now explicitly cover transport-specific placement. Do not impose Factory's sample namespace path or require new wrapper types solely for naming consistency. |
| `docs/engineering/standards/Testing.md` — behavior paths, bug-first regression, complete gate, line-coverage goal | **Adapt:** meaningful success/rejection/failure and owned contract evidence at narrow seams, actual coverage reporting and canonical `./bin/build`. **Reject:** raw 100% line goal, mandatory screenshots for documentation, Factory's private-hosted CI policy and documentation-only gate reuse as Agent OS rules. Do not add tooling-text, seeded-failure or test-only-route product tests. |
| `docs/engineering/standards/Review.md`, `skills/Fight-Review-Skill.md`, `skills/Fight-Work-Skill.md` — stable Spec/Standards criteria, evidence and independence | **Adapt:** stable project-local IDs with two named passes in one independent invocation; builder uses them only for internal completeness. **Reject:** required GPT reviewer pair, numerical scores/overrides, Factory orchestration/spokes and automatic push/PR as part of work. Agent OS review writes one canonical report, and `land` separately owns publication. |
| `skills/Fight-Audit-Skill.md` — read-only broader-scope audit, adversarial findings, proposed groupings | **Handoff:** [TASK-00131](../../planning/tasks/00131-TASK.md) designs the project-local audit skill. Its PDF/Desktop output and Factory map/grill commands are not Agent OS requirements. |

## Skill handoffs from TASK-00130

1. **[Audit — TASK-00131](../../planning/tasks/00131-TASK.md):** one scoped read-only cross-module assessment beyond a TASK diff, with sampling limits, counterchecked findings and work-group recommendations. No code repair, certification or automatic planning.
2. **[Fix — TASK-00132](../../planning/tasks/00132-TASK.md):** diagnose a reported problem and draft a human-approved standalone `kind: bug` TASK; `work` owns the failing regression and repair. Reject Factory's combined fix-and-publish workflow.
3. **[Architecture — TASK-00133](../../planning/tasks/00133-TASK.md):** examine one bounded architectural question for package ownership, CQRS flow, authority, transaction/effect guarantees and dependency semantics. Verify Matt Pocock's `improve-architecture` source and relevant Mathias Verraes/Robert C. Martin work before adapting anything; Agent OS naming and accepted ADRs prevail. No autonomous refactoring or independent TASK approval.
4. **[Adopt — TASK-00134](../../planning/tasks/00134-TASK.md):** compare a chosen project's existing authority, conventions and Fight package contracts with current Agent OS guidance; offer graded alignment choices, not automatic adoption or a PR.

## Complete Factory skill inventory at `5ba0fc0`

The table enumerates all 16 `skills/Fight-*-Skill.md` files in that snapshot. Source paths are relative to the read-only Factory checkout; local links point to current Agent OS skills. A counterpart means a **bounded local responsibility**, not imported Factory authority or equivalent runtime automation. The four selected handoffs above retain independent acceptance records; their local implementation is recorded below.

| Factory source path | Agent OS counterpart or disposition | Boundary / reason |
|---|---|---|
| `skills/Fight-Adopt-Skill.md` | **Selected, adapt:** [TASK-00134](../../planning/tasks/00134-TASK.md) | Compare project authority and offer choices; no automatic project rewrite, enrollment, push or PR. |
| `skills/Fight-Ask-Skill.md` | **Defer:** read-only questions need no dedicated skill yet; [next](../../.pi/skills/next/SKILL.md) routes only the planning frontier | Do not confuse the Factory read-only repository explorer with the local next-work router or grant a question permission to create records. |
| `skills/Fight-Audit-Skill.md` | **Selected, adapt:** [TASK-00131](../../planning/tasks/00131-TASK.md) | Read-only broader-scope evidence; no required PDF/Desktop artifact or automatic TASK creation. |
| `skills/Fight-Build-Skill.md` | **Do not transfer:** [work](../../.pi/skills/work/SKILL.md), [review](../../.pi/skills/review/SKILL.md), [land](../../.pi/skills/land/SKILL.md) remain separate | No Factory launcher, spokes, auto-review/repair loop or automatic publication. |
| `skills/Fight-Deploy-Skill.md` | **Defer:** no project-local deploy workflow approved | Production mutation needs its own target, runbook and explicit authority; a green build is not deployment proof. |
| `skills/Fight-Fix-Skill.md` | **Selected, narrow:** [TASK-00132](../../planning/tasks/00132-TASK.md) | Diagnose and draft an approved bug TASK; regression-first implementation belongs to `work`, not fix-and-publish. |
| `skills/Fight-Grill-Skill.md` | **Existing bounded counterpart:** [grill](../../.pi/skills/grill/SKILL.md) | Interview into one EPIC only; decomposition remains separate. |
| `skills/Fight-Land-Skill.md` | **Existing bounded counterpart:** [land](../../.pi/skills/land/SKILL.md) | Publish accepted work as a PR; do not import Factory's automatic merge/release cleanup authority. |
| `skills/Fight-Map-Skill.md` | **Existing bounded counterpart:** [wayfinder](../../.pi/skills/wayfinder/SKILL.md) | WF decision map and handoff before EPIC; no automatic grilling or archival. [research](../../.pi/skills/research/SKILL.md), [prototype](../../.pi/skills/prototype/SKILL.md), [design](../../.pi/skills/design/SKILL.md) supply separate bounded evidence, not Factory map stages. |
| `skills/Fight-Plan-Skill.md` | **Do not transfer as coordinator:** wayfinder → grill → [to-tickets](../../.pi/skills/to-tickets/SKILL.md) → [to-tasks](../../.pi/skills/to-tasks/SKILL.md) only by their own authorizations | No implicit end-to-end decomposition from an idea or automatic implementation. |
| `skills/Fight-Release-Skill.md` | **Defer:** no project-local release skill approved | Factory signed-library/version and maintenance-line procedure is not the application's release policy. |
| `skills/Fight-Review-Skill.md` | **Existing, adapted:** review | Two named Spec/Standards passes by one independent reviewer; no scored reviewer pair or Factory handoff format. |
| `skills/Fight-Self-Learn-Skill.md` | **Defer:** no trustworthy Agent OS run/usage observability yet | Do not extrapolate cost/quality trends from absent telemetry or install Factory's registry/launcher. |
| `skills/Fight-Tasks-Skill.md` | **Existing bounded counterpart:** to-tasks | Decompose accepted **TICKET** requirements into TASKs; never silently treat a TASK as a TICKET. |
| `skills/Fight-Tickets-Skill.md` | **Existing bounded counterpart:** to-tickets | Decompose an approved EPIC into requirement TICKETs only. |
| `skills/Fight-Work-Skill.md` | **Existing, adapted:** work | Implement and verify one TASK; separate independent review and publication instead of Factory's push/PR in work. |

Local [design-review](../../.pi/skills/design-review/SKILL.md) critiques disposable design evidence, not a Factory implementation review counterpart. Factory protocol/security assessments beyond these skill files remain unapproved; do not create them from this map.

## Checklist exercise: TASK-00020 boundary correction

Apply the current [Spec and Standards IDs](REVIEW.md#two-named-passes) to the **original proposed global JSend/correlation middleware**, using the accepted [TASK-00020](../../planning/tasks/00020-TASK.md) API-only requirement and its recorded revision as the comparison. This is a retrospective desk exercise, not a claim of a prior two-agent review or fresh execution of the old tree. The revised implementation gates exact `/api` and `/api/` descendants before routing, leaves non-API paths alone, and locates only the actual middleware in `Middleware`; the TASK records its focused and full-gate results. These are historical receipts, not TASK-00130 build evidence.

| Pass / ID | Direct question against original global proposal | Desk outcome and evidence to request |
|---|---|---|
| Spec SP-01 | Does `/` and `/apiary/missing` retain non-API behavior while `/api/missing` gets JSend? | **Fail for original:** global rendering/correlation breached the accepted exact-segment scope. Request the real root smoke and focused `/apiary` versus API route/method checks; the revision records those boundaries. |
| Spec SP-02 | Does the scope apply to API route misses before group middleware, without altering non-API validation/authority? | **Unverified on the proposal alone:** demand route/method checks at the kernel. No new permissions belong to this middleware; authentication/ownership of future Actions is N/A here, not a new exception mapper privilege. |
| Spec SP-03 | Are correlation headers and unknown-error diagnostics limited to API requests, with no domain mutation or event? | **Fail for original:** global correlation/logging changed non-API side effects; request direct boundary logging/header checks for `/`, `/apiary` and API errors. Domain commands/events are N/A within this criterion. |
| Spec SP-04 | Are non-API errors left to their transport, explicit not-found used, and unknown API failures sanitized? | **Fail for original:** a global JSend response changes non-API error presentation; verify no generic lookup becomes 404 and no stack/secret leaks in API mapper tests. Do not demand a non-API renderer in this TASK. |
| Spec SP-05 | Can a green mapper-unit suite alone prove the path boundary? | **Unverified without scoped middleware/kernel evidence:** a passing mapper suite or full build would not establish exact path dispatch. Revised TASK-00020 records a first failing `/apiary` regression and a passing rerun, but this exercise does not re-run it. |
| Standards ST-01 | Does the transport policy belong to Domain or a package? | **N/A to placement defect:** the work is an Adapter HTTP concern; review still checks no package/application policy is duplicated. |
| Standards ST-02 | Is only pipeline code in `Middleware`, and are response mapping and values under `Adapter/Http/Api`? | **Fail for original:** global boundary crossed transport scope and mapper/representation initially lived under `Middleware`; the revision moved them to API homes. |
| Standards ST-03 | Are naming and documentation compliant? | **Unverified without source/style inspection:** scope evidence cannot substitute for checking style; don't invent a naming failure. |
| Standards ST-04 | Are failure mapping/logging tested at their seams without test-only routes or needless whole-app proof? | **Unverified on the original proposal:** request mapper/log unit evidence plus existing real-kernel paths; no synthetic product route. |
| Standards ST-05 | Are revision, gate and acceptance claims honest? | **Unverified until fresh target evidence:** an earlier accept for the global tree does not accept the revised scope; later TASK-00020 notes distinguish the fresh revision review and gate from that receipt. |

The original would receive **revise**, not a numerical average: SP-01/SP-03/SP-04/ST-02 are confirmed scope/placement failures, and missing route-boundary evidence cannot be waved away by the other passes. For a new review, replace each desk outcome with evidence from the actual reviewed tree; N/A and Unverified must be justified per target. This demonstrates why Spec checks the API/non-API requirement even if style passes, while Standards catches misplaced mapper/value types even if API responses otherwise work.

## Broader skill assessment — approved 2026-09-26

The original Factory comparison above is historical source analysis, not a complete inventory of useful workflows.
TASK-00135 expands it without importing another project's authority.

| Capability | Current decision |
|---|---|
| Graphify | Added locally: scoped query/trace and explicitly requested maintenance, source/freshness/provenance checks; no automatic install, hooks or broad extraction |
| Writing for Agents | Added locally now with exact upstream attribution/license; EPIC-00006 later distributes trusted revisioned resources |
| Audit / fix / architecture / adopt | Implemented locally under TASK-00131–00134 in the approved skill batch; architecture includes human-first interfaces and namespace cohesion |
| Security audit | Implemented locally under [TASK-00136](../../planning/tasks/00136-TASK.md); bounded threat/evidence assessment, no automatic repair |
| QA | Local [qa](../../.pi/skills/qa/SKILL.md) and canonical behavioral handoff under [TASK-00146](../../planning/tasks/00146-TASK.md); [TASK-00150](../../planning/tasks/00150-TASK.md) broadens adversarial non-UI exercises and visual evidence guidance; managed QA remains with [TICKET-00033](../../planning/tickets/00033-TICKET.md) |
| Skill maintenance | Use Writing for Agents plus explicit inventory/provenance checks; add automation only after demonstrated need |
| Domain modeling / testing / conflict resolution | Improve existing planning/work/review guidance; ordinary feature TDD is not required; meaningful 100% unit coverage is the goal |
| Release / hotfix | Package [release](../../.pi/skills/release/SKILL.md) guidance added under [TASK-00143](../../planning/tasks/00143-TASK.md); target-owned certification, resumable stages and human signing. Hotfix, deployment and managed automation remain separate work under [Team roles](TEAM.md) |
| Portable handoff | Reuse existing work/review evidence contracts first; a cross-session convenience skill remains a later candidate |
| Self-learning | Deferred until reliable usage, review, recovery and outcome metrics exist; no automatic instruction mutation |
| Daily planning / closeout | Excluded from this engineering suite |

Raw class counts, framework preferences and confidence numbers do not establish defects. Keep security severity
separate from confidence, and distinguish local taste recommendations from violations of accepted requirements.

## Local skill implementation — 2026-09-26

The maintainer subsequently authorized skill implementation and related TASK closeout in the same checkout as
TASK-00135. TASK-00131–00134 and TASK-00136 retain their own scope and completion records; the separate-PR packaging
of the original handoffs is superseded for this batch. The source comparisons above remain historical evidence.

| Skill | Source comparison and local decision |
|---|---|
| [audit](../../.pi/skills/audit/SKILL.md) | Re-read `Fight-Audit-Skill.md`: **adopt** bounded use-case tracing and adversarial counterchecks; **adapt** to local standards/severity, explicit sampling and optional work groups; **reject** mandatory PDF/Desktop output and Factory map/grill invocation. Original local instructions, no copied source artifact. |
| [fix](../../.pi/skills/fix/SKILL.md) | Re-read `Fight-Fix-Skill.md`: **adopt** expected/observed diagnosis and duplicate-owner checks; **adapt** to standalone `kind: bug` draft, live/archive allocation and explicit acceptance; **reject** automatic continuation through repair/publication. `work` owns the authorized regression-first repair. |
| [adopt](../../.pi/skills/adopt/SKILL.md) | Re-read `Fight-Adopt-Skill.md`: **adopt** inspecting target authority and compatibility before decisions; **adapt** to graded read-only options; **reject** default standards/planning replacement, run reorganization, enrollment and commit/push/PR. The target's local rules and package contracts prevail. |
| [architecture](../../.pi/skills/architecture/SKILL.md) | Bounded design alternatives, package/transaction authority, human-first interfaces and namespace cohesion; source decisions below. No automatic source/ADR mutation or independent acceptance. |
| [security-audit](../../.pi/skills/security-audit/SKILL.md) | Original local instructions for threat/evidence tracing, confidence separate from severity, runtime versus planned controls and safe handoff; source decisions below. No scanner installation, exploitation, remediation or certification. |

### Architecture and security source decisions

Primary sources inspected on 2026-09-26; they supply ideas, not local authority:

- Matt Pocock's [improve-codebase-architecture](https://github.com/mattpocock/skills/blob/c55ee46073ed923f86ce59a5eb3b6d895095d1b7/skills/engineering/improve-codebase-architecture/SKILL.md),
  commit `c55ee46073ed923f86ce59a5eb3b6d895095d1b7`, is the current source behind the earlier `improve-architecture`
  shorthand. **Adapt** exploration of navigation friction and module deepening into small domain-facing interfaces
  that hide useful complexity and reduce what callers must know. TASK-00150 makes the before/after caller comparison
  explicit while preserving DDD ownership, aggregate invariants, bounded contexts, CQRS and accepted package
  contracts; module depth is distinct from namespace depth. **Reject** mandatory vocabulary, automatic subagents, HTML/CDN report, grilling and inline
  domain-document mutation. Its repository MIT notice was verified and is retained in the local architecture skill.
- Mathias Verraes, [DRY is about Knowledge](https://verraes.net/2014/08/dry-is-about-knowledge/), 2014-08-02:
  **adopt** reasons for change as a cohesion test; **reject** deduplication based only on similar syntax. This supports
  evaluating distinct persistence responsibilities rather than merging them into a generic helper. Link and original
  summary only; no article/code reproduction or assumption of a software license.
- Robert C. Martin, [The Clean Architecture](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html),
  2012-08-13: **adopt** inward source dependencies and separation of policy from mechanisms; **adapt** through accepted
  Domain/Application/Adapter and package ownership. **Reject** adding layers/wrappers merely to imitate a diagram.
  Link and original summary only; no article/diagram reproduction or unverified reuse license.
- OWASP, [Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html):
  **adopt** tracing server-side permission/ownership checks and failed-check behavior. It supports checking every
  relevant entry path, without replacing Fight's accepted permission model. The page identifies CC BY-SA 4.0;
  no source prose, tables or code are copied into the original local skill.
- OWASP, [LLM Prompt Injection Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/LLM_Prompt_Injection_Prevention_Cheat_Sheet.html):
  **adapt** tool-call permission validation and least privilege to deterministic PHP and the trusted provisioner.
  A model guardrail is supplementary; **reject** treating it as sandbox enforcement or logging all raw conversations
  despite local secret-handling rules. The series identifies CC BY-SA 4.0; this is a cited idea comparison, not a
  copied or translated cheat sheet.

The [bounded qualification](SKILL_QUALIFICATION.md) records real source walkthroughs, a retrospective bug handoff,
non-mutating project comparison and limits. These are builder qualification, not independent forward-testing or
formal TASK review. Local skill discovery metadata is verified separately from any future managed Harness execution.

## Test, code and documentation quality source decisions

[TASK-00150](../../planning/tasks/00150-TASK.md) incorporates four ideas from
[brandhaug/skills](https://github.com/brandhaug/skills/tree/9cb193614af39847d80ab687ef3a8de01841632e),
inspected at commit `9cb193614af39847d80ab687ef3a8de01841632e` on 2026-09-30. The pinned tree contains no license
file; no upstream skill text or agent prompts are vendored into tracked files. The local
[quality guidance](QUALITY.md) is original writing with source attribution. The sources are comparison material,
not installed skills or authority to execute their workflows.

| Source | Local decision and integration |
|---|---|
| [remove-tautological-tests](https://github.com/brandhaug/skills/blob/9cb193614af39847d80ab687ef3a8de01841632e/remove-tautological-tests/SKILL.md) | **Adopt** examining contract, independent expectations and refactor fragility while writing tests. **Adapt** into work self-checks and independent review/audit questions. **Reject** automatic deletion based on pattern matches, blanket rejection of snapshots/internal sequencing, or loss of required coverage. Retain justified boundary ordering and independently reviewed fixtures; replace critical coverage in the same change. |
| [deslop](https://github.com/brandhaug/skills/blob/9cb193614af39847d80ab687ef3a8de01841632e/deslop/SKILL.md) and [category prompts](https://github.com/brandhaug/skills/blob/9cb193614af39847d80ab687ef3a8de01841632e/deslop/AGENTS.md) | **Adapt** seven quality concerns into evidence-based questions, including PHP/TypeScript boundaries. Work uses the TASK scope, review independently challenges it, audit declares sampling, and architecture compares ownership options. **Reject** mandatory seven-agent/per-language fan-out, full-repository default, numeric confidence as proof, deletion bias, root scratch reports, cleanup branch/merge machinery and automatic remediation. |
| [write-agents-md](https://github.com/brandhaug/skills/blob/9cb193614af39847d80ab687ef3a8de01841632e/write-agents-md/SKILL.md) | **Adopt** meaningful instruction boundaries, linked shared authority and considering additions/edits/removals. **Adapt** through writing-for-agents with affected-document checks in work/review/audit and impact proposals in architecture. **Reject** unverified auto-loading assumptions, code as policy authority, fixed word budgets, mandatory cuts/delegation, deleting discoverable facts regardless of instructional value, empty-diff fallback to unrelated commits and automatic merge/post-commit synchronization. Preserve accepted invariants, necessary rationale and historical decisions. |
| [write-like-a-human](https://github.com/brandhaug/skills/blob/9cb193614af39847d80ab687ef3a8de01841632e/write-like-a-human/SKILL.md) | **Adopt** direct prose, reduced repetition and preservation of meaning/voice. **Adapt** to current README capability evidence, CHANGELOG conventions and architecture explanations. **Reject** mechanical word/punctuation bans, rewriting quoted/code/API content, weakening qualifications or restyling historical releases. Factual corrections need evidence separately from prose polish. |

These checks extend existing ST-03/ST-04 and applicable Spec/Standards criteria; they add no score or acceptance
authority. Direct skill walkthroughs and the canonical local gate qualify the implementation; another agent
must independently review TASK-00150. No source code, product tests, consumer repository or global installation
is changed by this integration.

The maintainer's TASK-00150 follow-up assigns the detailed quality assessment to review. Work keeps immediate
contract/test and affected-document guidance, consulting detail on demand instead of duplicating review's full
matrix. The same follow-up broadens local QA to adversarial API, CLI, library/worker and executable-instruction
scenarios, bounded disposable probes and inspected visual comparisons. These are local requirements, not claims
that the four upstream skills supply a QA workflow. [QA standards](QA.md) own applicability and evidence.
