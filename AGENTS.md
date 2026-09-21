# Fight Agent OS

Read [README.md](README.md), [ARCHITECTURE.md](ARCHITECTURE.md), and [planning/CONVENTIONS.md](planning/CONVENTIONS.md).
Use [planning/tasks/BOARD.md](planning/tasks/BOARD.md) for executable work and
[planning/FOUNDATION.md](planning/FOUNDATION.md) for the starting scope and unfinished integrations.

- This is a fresh, private project. Do not import existing Factory workflows, historical plans, or approvals
- Planning uses EPIC → TICKET → TASK. Grilling writes an EPIC; decomposition is a separate operation
- Follow repository terminology when using external planning skills. Do not silently map a TASK to a TICKET
- Put fresh Pi skills in `.pi/skills/<name>/SKILL.md`; design their behavior through planning
- Keep PHP business logic in Domain, orchestration in Application, and framework/provider concerns in Adapter
- Prefer capability-named interfaces without an Interface suffix, injected dependencies, and single-use-case HTTP Actions
- Use final classes and readonly properties by default, with Doctrine entity exceptions; readonly value objects
- Use multiline docblocks, capitalized active-verb summaries without periods, and inheritDoc for inherited behavior
- Keep PHP properties camelCase and HTTP JSON/database fields snake_case; follow Fight helper conventions where available
- Write testable code, then tests. For bugs, reproduce and add a failing regression test before repairing
- Use repository `./bin/*` Docker wrappers. The inherited `./bin/build` is the full gate; focused checks do not replace it
- Surface warnings and incomplete verification. Do not label inherited receipts as fresh application evidence
- Before task publication, record completed implementation and verification; keep formal review and merge status separate
- Ask for checkout/worktree choice before implementation unless already provided. Use `feature/*` from `develop`
- Preserve source projects and unrelated local work. Commit and publish only within the authorized scope
- Keep all scratch under ignored `.runs/` subfolders. Never put worktrees directly in `.runs/`
- Keep Markdown authoritative until an approved, verified database cutover; no automatic removal or archiving

## Learnings

Record concise, evidence-backed project learnings here when requested.
