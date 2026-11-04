# Local tooling across projects

Each repository owns its Docker wrappers, gate and resource configuration. When shared Work, Review, QA or
Land operates on another project, resolve its local instructions and commands first. Do not import Agent OS
services, ingress, schema preparation or an application receipt into a library or starter.

For this repository use [resource lifecycle](RESOURCE_LIFECYCLE.md), [build](BUILD.md) and
[gate evidence reuse](EXECUTION.md#gate-evidence-reuse). The adopted Common, AccessControl, Slim, Symfony,
Laravel, Yii and CodeIgniter implementations expose the same explicit main/worktree command boundary through
their local `docs/engineering/LOCAL_TOOLING.md`; their actual service sets and gates remain project-owned.

Use `bin/down --worktree /absolute/registered/checkout` before qualifying source removal. Run the target's
`bin/runtime finish` from its canonical safe checkout only after delivery verification and durable outcomes.
Its guarded Worktrunk remover rechecks exact native hooks and source ownership. If the canonical target has
not yet adopted `bin/down`, keep the source hold; a provider's remover is not permission to bypass it or copy
an unreviewed command into an active main checkout. Independent Docker effects may still be retired through
their existing owning command. Never redirect removal to a provider or another project.

A normal target `bin/build` validates planning freshly and reuses only its own qualifying application receipt.
Use `--force` for deliberate fresh application execution. Shared Docker layers and local gate evidence are
separate caches: down retains shared layers, while removing/recreating the runtime invalidates application
receipt reuse. Report fresh planning, reused original application evidence and independent acceptance
separately. Review/QA requirements and publication authority remain unchanged.

For recreating a published application TASK after its original cleanup, see [human QA operations](HUMAN_QA.md).
The candidate `bin/qa up/list/down` layer uses these same supplier paths. Work's bounded macOS/Herd browser
qualification is recorded in TASK-00556; independent acceptance remains pending. Explicit end-of-session QA down does not alter Land's original cleanup timing.

This rollout uses isolated feature worktrees and existing engineering instructions without new TASK documents.
It covers Common, Agent OS, AccessControl and the five framework starters. `epic-legacy` is excluded by the
user's current scope. Implementation and direct qualification do not establish independent Review/QA acceptance.
