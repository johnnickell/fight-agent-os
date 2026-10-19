# Fight Agent OS

## Work routing

For “What's next?”, `next`, or an unqualified `/ask-matt`, go straight to the [Board](planning/tasks/BOARD.md)
before general project orientation. Read its current human decision and active work; otherwise select the first
executable ready TASK in Board order. Use the [Wayfinder index](planning/wayfinder/README.md) for map status and current frontier pointers.
Follow [lightweight work routing](planning/CONVENTIONS.md#lightweight-work-routing) for validation,
bounded evidence reads and planning handoffs. Recommend and stop; load execution guidance when work is invoked.
This routing path does not require reading README, architecture, full project context, task history or receiving skills.

## Project guidance

For execution or substantive planning changes, load the applicable guidance below. The read-only routing
path above takes precedence over broad orientation reads; it does not waive engineering or delivery rules.

Read [README.md](README.md), [ARCHITECTURE.md](ARCHITECTURE.md), and [planning/CONVENTIONS.md](planning/CONVENTIONS.md).
Use [planning/tasks/BOARD.md](planning/tasks/BOARD.md) for executable work and
[planning/FOUNDATION.md](planning/FOUNDATION.md) for the starting scope and unfinished integrations.

- This is a fresh, independent project. Do not import existing Factory workflows, historical plans, or approvals
- Planning uses EPIC → TICKET → TASK. Grilling writes an EPIC; decomposition is a separate operation
- Propose TASKs only for named product outcomes or concrete delivery blockers; defer speculative features and infrastructure demonstrations under planning/CONVENTIONS.md
- Follow repository terminology when using external planning skills. Do not silently map a TASK to a TICKET
- Use `.pi/skills/writing-for-agents/SKILL.md` when authoring skills or agent-facing instructions
- Put fresh Pi skills in `.pi/skills/<name>/SKILL.md`; design their behavior through planning
- Never pin Pi to a version number. For Pi integrations, qualify the currently installed supported APIs and behavior;
  record the tested version as evidence, not an equality gate or future compatibility guarantee. Missing capabilities
  block the affected behavior; preserve historical version receipts and unrelated dependency/model pins.
- In a participating session with `jev_read_file` and its shared rule available, follow
  [selective file reads](harness/pi/FILE_SCREENING_RULE.md). Load mandatory governing/selected-skill instructions
  directly. Native reads need no screening receipt or exception; discovery refusals do not prohibit them
- Keep PHP business logic in Domain, orchestration in Application, and framework/provider concerns in Adapter
- Prefer capability-named interfaces without an Interface suffix, injected dependencies, and single-use-case HTTP Actions
- For console entry points, bus pipelines and shared container services, follow [shared services and dispatch](docs/engineering/STANDARDS.md#shared-services-and-bus-dispatch)
- Use final classes and readonly properties by default, with Doctrine entity exceptions; readonly value objects
- Use multiline docblocks, capitalized active-verb summaries without periods, and inheritDoc for inherited behavior
- Keep PHP properties camelCase and HTTP JSON/database fields snake_case; follow Fight helper conventions where available
- Write testable code, then tests. For bugs, reproduce and add a failing regression test before repairing
- Test owned application behavior and important integration contracts; never add product-suite tests for architecture, dependency rules, container/configuration wiring, migrations, generated files, wrappers, build/planning/static-analysis mechanisms, or test infrastructure
- Validate tooling and infrastructure directly with their owning tools; never test that tests or quality tools detect deliberately seeded failures
- Use repository `./bin/*` Docker wrappers. `./bin/build` is the full gate; focused checks do not replace it
- Surface warnings and incomplete verification. Do not label inherited receipts as fresh application evidence
- Before task publication, record completed implementation and verification; keep formal review and merge status separate
- Ask for checkout/worktree choice before implementation unless already provided. Use `feature/*` from `develop`
- Preserve source projects and unrelated local work. Commit and publish only within the authorized scope
- Keep application-generated files and local runtime state under ignored `var/`; application runtime must never depend on `.runs/`
- Keep all execution scratch under ignored `.runs/` subfolders. Never put worktrees directly in `.runs/`
- Keep Markdown authoritative until an approved, verified database cutover; no automatic removal or archiving

## Learnings

- Generate Doctrine migration versions with `doctrine-migrations generate` or `diff` so new names carry their real generation timestamp; never invent future dates. When correcting an older version, use a verifiable historical timestamp and account for databases that already recorded the old version. TASK-00012/00013 migrations were named October 1/2 before those dates; their introduction commits on September 25 provide verifiable correction times.
- Generate UUIDs using the owning package's ID factory or a UUID library, including in fixtures and examples; never improvise UUID literals. This is a convention, not a reason to add product tests of tooling or naming.
