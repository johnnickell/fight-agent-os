# Fight Agent OS

## Work routing

For “What's next?”, `next`, or an unqualified `/ask-matt`, go straight to the [Board](planning/tasks/BOARD.md)
before general project orientation. Read its current human decision and active work; otherwise select the first
executable ready TASK in Board order. Use the [Wayfinder index](planning/wayfinder/README.md) for map status and current frontier pointers.
Follow [lightweight work routing](planning/CONVENTIONS.md#lightweight-work-routing) for validation,
bounded evidence reads and planning handoffs. Recommend and stop; load execution guidance when work is invoked.
This routing path does not require reading README, architecture, full project context, task history or receiving skills.

## Project guidance

Keep verification proportional to the changed behavior. Each added check must cover a concrete risk that
existing checks do not already cover. Do not duplicate dependency installation, validation, test suites or
release lanes for unchanged inputs. Reuse qualifying build evidence; run dependency preparation only on a
cache miss. Validate tooling with its owning commands, without adding tests of wrappers, checks or release
machinery. Investigate flaky failures; do not hide them with retries or accept a warm rerun as proof that a
clean build works. Expand release certification only for an explicitly approved compatibility requirement.

For execution or substantive planning changes, load the applicable guidance below. The read-only routing
path above takes precedence over broad orientation reads; it does not waive engineering or delivery rules.

For Docker preparation/teardown, isolated worktrees, guarded source removal and build-cache evidence, follow
[local tooling](docs/engineering/LOCAL_TOOLING.md). Use normal `./bin/build` for fresh planning and qualified
application reuse; use `--force` for deliberate fresh application execution.

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
- Use bounded native discovery and file access under the installed [workflow rule](harness/pi/WORKFLOW_RULE.md).
  Load mandatory governing/selected-skill instructions directly; no evaluator enrollment or screening is required
- For PHP implementation, planning or assessment, read the shared [architecture contract](docs/engineering/STANDARDS.md#architecture):
  aggregate-first ownership, Project vocabulary, registered handlers, adapter admission, typed creation/results,
  transaction/audit and application breaking-change/data rules. ADRs retain rationale and explicit amendments;
  current code is not an alternative authority. Qualify suppliers and keep target versus implementation distinct
- For console entry points, bus pipelines and shared container services, follow [shared services and dispatch](docs/engineering/STANDARDS.md#shared-services-and-bus-dispatch)
- Use final classes and readonly properties by default, with Doctrine entity exceptions; readonly value objects
- Use multiline docblocks, capitalized active-verb summaries without periods, and inheritDoc for inherited behavior
- Keep PHP properties camelCase and HTTP JSON/database fields snake_case; follow Fight helper conventions where available
- Write testable code, then tests. For bugs, reproduce and add a failing regression test before repairing
- Test owned application behavior and important integration contracts; never add product-suite tests for architecture, dependency rules, container/configuration wiring, migrations, generated files, wrappers, build/planning/static-analysis mechanisms, or test infrastructure
- Validate tooling and infrastructure directly with their owning tools; never test that tests or quality tools detect deliberately seeded failures
- Use repository `./bin/*` Docker wrappers. Focus iteration, then run `./bin/build` normally; it freshly validates planning and automatically reuses verified application evidence when build inputs match. Planning records do not invalidate application reuse; governing instructions, code, dependencies and artifacts remain covered. Use `--force` for deliberate fresh execution; follow [gate evidence reuse](docs/engineering/EXECUTION.md#gate-evidence-reuse) for boundaries and evidence reporting
- Surface warnings and incomplete verification. Do not label inherited receipts as fresh application evidence
- Land closes accepted TASKs and applicable current Board/Roadmap/README/docs during delivery, validates the exact live PR and completes eligible owned local Git/Docker/storage cleanup. No persistent delivery markers/queue or routine post-merge planning maintenance; historical acceptance/publication remain evidence. Planning checks/refresh stay offline
- Explicit `/skill:sync TASK-NNNNN` coordinates accepted-work integration through fresh Engineer then Land,
  using existing dispatch and the [advancing-base boundary](docs/engineering/LANDING.md#reconcile-an-advancing-base).
  Preserve original Review/QA reports; code overlap alone needs no new acceptance. New behavior/defect repair
  retains normal independent acceptance. Sync updates only the same existing open PR, never rebases or force-pushes
- Before task publication, record completed implementation and verification; keep formal review and merge status separate. Follow the [authored routing boundary](planning/CONVENTIONS.md#authored-routing-boundary): keep acceptance/delivery journals in owning records, not the Board
- Ask for checkout/worktree choice before implementation unless already provided. Use `feature/*` from `develop`
- Prefer the simplest native-tool workflow; delete unnecessary machinery instead of adding compatibility/proof/recovery layers
- Maintainability is the primary project goal. For integrations, identify surviving callers and delete superseded
  implementations, redundant adapters and obsolete tests/docs after replacement verification. Keep required live
  paths; do not accumulate compatibility layers or equate an arbitrary deletion count with maintainability.
- Reuse one native Compose project per registered worktree across roles; follow [explicit main/worktree commands](docs/engineering/RESOURCE_LIFECYCLE.md), not resource/PID/worker receipts. Main data stays persistent; task DB/storage is disposable. Docker teardown precedes Worktrunk source removal. Routine owned cleanup is standing authority:
  completed, failed or cancelled tasks must not accumulate temporary worktrees, runtimes, fixtures, logs, sessions
  or cleanup archives. Keep evidence only while needed and record final outcomes in the existing TASK/PR. Preserve
  unique work, active consumers, unknown/shared/durable data and genuine protections; a narrow hold cannot block
  independent effects. The shared guarded Worktrunk remover handles the exact reviewed repository down hook
  unattended; do not request routine user approval or persist trust. Unknown/user/changed hooks hold source only
- Preserve source projects and unrelated local work. Commit and publish only within the authorized scope
- Keep application-generated files and local runtime state under ignored `var/`; application runtime must never depend on `.runs/`
- Keep all execution scratch under ignored `.runs/` subfolders. Never put worktrees directly in `.runs/`
- Keep Markdown authoritative until an approved, verified database cutover; no automatic removal or archiving

## Learnings

- Generate Doctrine migration versions with `doctrine-migrations generate` or `diff` so new names carry their real generation timestamp; never invent future dates. When correcting an older version, use a verifiable historical timestamp and account for databases that already recorded the old version. TASK-00012/00013 migrations were named October 1/2 before those dates; their introduction commits on September 25 provide verifiable correction times.
- Generate UUIDs using the owning package's ID factory or a UUID library, including in fixtures and examples; never improvise UUID literals. This is a convention, not a reason to add product tests of tooling or naming.
