# Foundation and capability inventory

Snapshot: 2026-09-26 foundation inventory, with the execution strategy amended during TASK-00138 on 2026-10-06.
A completed component is not proof of an end-to-end journey. The [Roadmap](ROADMAP.md) owns sequence; the [Board](tasks/BOARD.md) owns
executable priority. Historical gate receipts remain historical.

| Capability | Current evidence | Remaining boundary |
|---|---|---|
| Slim/PHP, Composer dependencies, Domain/Application/Adapter ownership | `src/`, `composer.lock`, ownership ADR and completed TASK-00010 | Later framework decisions require an observed need |
| PostgreSQL and managed migrations | Guarded development/test services, repositories, grant/session persistence; TASK-00012–00016 complete | Database Planning and runtime Workflow persistence are not implemented |
| API/security foundation | Versioned JSend, input validation, correlated sanitized failures and CSRF code; TASK-00017–00020 complete | TASK-00117 adds the matched-Action JWT/current-permission gate and real safe `/api/v1/me` server query; independent acceptance is pending. Full authenticated browser journeys and UI remain in their TASKs; do not infer completion from this early gate |
| Local Pi skills | `.pi/skills/` inventory includes planning/work/review/land, Graphify, Writing for Agents and audit/fix/architecture/adopt/security-audit | Skill instructions and bounded source walkthroughs are present; full Pi-session invocation is unverified and CLI installation remains separate |
| Browser experience | Non-product React/TypeScript shell at `/app`, strict public boot config, Docker-pinned frontend checks; TASK-00026 accepted locally; typed API/CSRF service in TASK-00027 independently accepted with scoped non-visual QA; TASK-00029's original neutral production components and Storybook evidence have independent technical and scoped browser QA acceptance; TASK-00050's accepted semantic theme and local appearance selector are implemented pending independent review | The shell mounts real public invitation activation at `/app/activate` pending independent acceptance; its other foundation routes do not mount authority; TASK-00028's injected-loader cache, guard/action and safe navigation foundation has independent review and scoped behavioral/browser QA acceptance. Typed client `/me`, other authentication journeys, the protected product frame and complete browser Planning/observability remain planned; TASK-00117 supplies the server read pending independent acceptance |
| Registered Planning | EPIC-00005 and TICKETs; TASK-00078 implements explicit Workspace bootstrap and protected registration/inspection pending independent Review/QA | Checkout linking, explicit Repository access writers, verified import and database cutover remain planned; registration grants none of them and Markdown remains authoritative |
| Managed Harness and SDLC team | EPIC-00006, team-template requirements; installable `harness/pi` terminal identity | Presentation does not imply managed Agent execution |
| Isolated TASK execution | October 6 [tool-mediated strategy](../docs/engineering/TOOL_MEDIATED_EXECUTION.md) replaces the first root-VM profile; bounded two-session file/notes/correction prototype demonstrated, no candidate execution | Production authorization, file/message capabilities, restricted checks/protected results, accounting and recovery remain unimplemented/unqualified; historical VM receipts do not certify the new profile |
| Independent review, QA and delivery | Local review/QA/land guidance; EPIC-00008, completed local TICKET-00033, managed TICKET-00083 and local coordination TICKET-00084 | Managed review/QA/publication phases remain planned; local skill completion does not close that scope; release/deployment need separate runbooks and implementation |
| Verification | Current `scripts/build.php`: read-only Composer/planning/metadata freshness, syntax/style/types/dependencies/Rector, one guarded all-suite backend coverage phase, frontend coverage/production/catalog/browser checks and source stability | TASK-00033 has current independent technical acceptance and scoped tooling/CLI QA PASS at `36d9a9c`, resolving LAND-01/R1/R2/R3 with actual clean native Linux wrapper install/write, cold-cache analysis, repeated normal-use reaping, receipt privacy/access and guarded cleanup/development-preservation evidence. Historical prepared-runtime acceptance is not substituted. TASK and TICKET-00013 close together; PR #69 publication/final verification remain pending at this checkpoint, with human merge separate. Combined coverage and automated browser observations are not universal accessibility certification |

## Accepted direction

This open-source application is a software factory: software to build software. Its planning, agent execution,
review and delivery ambitions concern engineering work; they do not include a general-purpose personal AI
operating system or an Internet homepage. The latter belong to a separate future project, not this roadmap.
Current capabilities and outstanding integrations are distinguished above.

Fight Common and Fight Access Control retain package ownership. Design
architecture and interfaces for human maintainers; prefer cohesive deeper namespaces when flat directories mix
unrelated responsibilities. Aim for meaningful 100% unit coverage without imposing TDD on ordinary feature work
or adding tests of generated/configuration/tooling text.

The first managed execution proof remains one approved TASK through Team Lead and Software Engineer to durable
Awaiting review. The next slice adds Senior Engineer, QA Engineer and Release Manager. Explorer stays available
for bounded research; Project Manager owns planning; Hotfix Engineer has a narrowly authorized emergency path.
See [Team roles](../docs/engineering/TEAM.md).

The Runner remains deterministic PHP infrastructure. The selected first profile gives Engineer scoped source/
scratch writes and TASK-only Review/QA reads, with no shell/private engine. Trusted infrastructure owns Git,
permissions, provider access and fixed restricted-container check operations; the Team Lead may request routine
checks automatically within per-run grants derived from administrator-owned standing policy, with no routine
workflow-start confirmation. The [autonomy amendment](../docs/engineering/TERMINAL_AUTHORITY.md#standing-execution-policy--approved-amendment-2026-10-18)
is required future behavior; managed execution remains unimplemented. File/handoff readiness is separate from candidate-check readiness.
The disposable two-session prototype is partial tool/context evidence, not production authorization or OS isolation.
A prompt, worktree or Compose name alone is not a security boundary. Qualify actual capabilities and preserve
independent results, limits and recovery rather than promising zero blast radius.

## Working material and authority

Keep scratch and local verification under ignored `.runs/` subfolders. Use approved dedicated worktree roots
(such as `.runs/worktrees/`, never directly in `.runs/`); never repurpose human checkouts. Planning uses EPIC → TICKET → TASK, with small standalone
chores/bugs allowed. Preserve Markdown until an explicitly approved and verified database cutover. No automatic
archive, project import, release or deployment follows from writing these plans.

## Historical bootstrap evidence

The initial inherited scaffold gate passed with 33 tests / 205 assertions and dependency lanes of 26 tests /
152 assertions; one lane reported five deprecations. These are historical bootstrap observations, not current
application or coverage evidence. Current results belong to the TASK that actually ran the current gate.
