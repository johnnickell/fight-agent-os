# Foundation and capability inventory

Snapshot: 2026-09-26, refreshed during TASK-00135 and the approved skill closeout from current source and planning records. A completed component
is not proof of an end-to-end journey. The [Roadmap](ROADMAP.md) owns sequence; the [Board](tasks/BOARD.md) owns
executable priority. Historical gate receipts remain historical.

| Capability | Current evidence | Remaining boundary |
|---|---|---|
| Slim/PHP, Composer dependencies, Domain/Application/Adapter ownership | `src/`, `composer.lock`, ownership ADR and completed TASK-00010 | Later framework decisions require an observed need |
| PostgreSQL and managed migrations | Guarded development/test services, repositories, grant/session persistence; TASK-00012–00016 complete | Database Planning and runtime Workflow persistence are not implemented |
| API/security foundation | Versioned JSend, input validation, correlated sanitized failures and CSRF code; TASK-00017–00020 complete | Full authenticated browser journeys and UI remain in their TASKs; do not infer completion from storage primitives |
| Local Pi skills | `.pi/skills/` inventory includes planning/work/review/land, Graphify, Writing for Agents and audit/fix/architecture/adopt/security-audit | Skill instructions and bounded source walkthroughs are present; full Pi-session invocation is unverified and CLI installation remains separate |
| Browser experience | Non-product React/TypeScript shell at `/app`, strict public boot config, Docker-pinned frontend checks; TASK-00026 accepted locally; typed API/CSRF service in TASK-00027 independently accepted with scoped non-visual QA; TASK-00029's neutral production components and Storybook evidence have independent technical and scoped browser QA acceptance | The shell does not invoke the service or mount authority; TASK-00028's injected-loader cache, guard/action and safe navigation foundation has independent review and scoped behavioral/browser QA acceptance. Real `/me`, authentication integration, product journeys and complete browser Planning/observability remain planned |
| Registered Planning | EPIC-00005 and TICKETs | Registered repository/checkout authority, verified import and database cutover remain planned; Markdown is authoritative now |
| Managed Harness and SDLC team | EPIC-00006, team-template requirements; installable `harness/pi` terminal identity | Presentation does not imply managed Agent execution |
| Isolated TASK execution | EPIC-00007, TICKET-00032 and TASK-00138 [local VM qualification](../docs/engineering/DOCKER_SANDBOX_QUALIFICATION.md) | Two VMs passed synthetic storage/restart checks; pinned Pi CLI passed synthetic SSE, secret substitution and revocation. Full isolation/profile, real-provider broker and host accounting remain unqualified; production PHP start/recovery is unimplemented |
| Independent review, QA and delivery | Local review/land guidance; EPIC-00008 and TICKET-00033 | Managed review/QA/publication phases remain planned; release/deployment need separate runbooks and implementation |
| Verification | Current `scripts/build.php`: read-only Composer/planning/metadata freshness, syntax/style/types/dependencies/Rector, one guarded all-suite backend coverage phase, frontend coverage/production/catalog/browser checks and source stability | TASK-00033 has independent technical acceptance and scoped tooling/CLI QA PASS at `bd4f300`; publication/merge remain separate. Combined coverage and automated browser observations are not universal accessibility certification |

## Accepted direction

This is an independent application. Fight Common and Fight Access Control retain package ownership. Design
architecture and interfaces for human maintainers; prefer cohesive deeper namespaces when flat directories mix
unrelated responsibilities. Aim for meaningful 100% unit coverage without imposing TDD on ordinary feature work
or adding tests of generated/configuration/tooling text.

The first managed execution proof remains one approved TASK through Team Lead and Software Engineer to durable
Awaiting review. The next slice adds Senior Engineer, QA Engineer and Release Manager. Explorer stays available
for bounded research; Project Manager owns planning; Hotfix Engineer has a narrowly authorized emergency path.
See [Team roles](../docs/engineering/TEAM.md).

The Runner is deterministic PHP infrastructure. It prepares worktrees and isolated local container environments
through a constrained provisioner and verifies isolation before any Agent starts. A prompt, Compose project name
or separate directory alone does not provide a security boundary. Prefer proven isolation mechanisms and qualify
the configured policy on supported hosts; report residual risks without promising zero blast radius.

## Working material and authority

Keep scratch and local verification under ignored `.runs/` subfolders. Use approved dedicated worktree roots
(such as `.runs/worktrees/`, never directly in `.runs/`); never repurpose human checkouts. Planning uses EPIC → TICKET → TASK, with small standalone
chores/bugs allowed. Preserve Markdown until an explicitly approved and verified database cutover. No automatic
archive, project import, release or deployment follows from writing these plans.

## Historical bootstrap evidence

The initial inherited scaffold gate passed with 33 tests / 205 assertions and dependency lanes of 26 tests /
152 assertions; one lane reported five deprecations. These are historical bootstrap observations, not current
application or coverage evidence. Current results belong to the TASK that actually ran the current gate.
