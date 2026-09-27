# Pi Skills

This is the fresh project-local home for Fight Agent OS skills:

```text
.pi/skills/<skill-name>/SKILL.md
```

Pi discovers skills here. Each skill has `name` and `description` frontmatter and focused instructions.
Design new capabilities through planning instead of copying the existing Factory suite. Follow this repository's
[planning conventions](../../planning/CONVENTIONS.md) when writing their outputs. Disposable product-design
exploration also follows the shared [design standards](../../docs/design/STANDARDS.md), and independent critique follows the
[design-review standards](../../docs/design/REVIEW.md).

Installing Pi, providers, third-party skills, and terminal extensions is a separate setup step. No account
configuration or global skill installation is changed by this scaffold.

[Writing for Agents](writing-for-agents/SKILL.md) supports skill/instruction authoring now.
[Graphify](graphify/SKILL.md) queries derived source relationships and scopes explicit maintenance; installing this
skill does not install its CLI or refresh a graph. The [skill assessment](../../docs/engineering/SKILL_GAPS.md)
tracks remaining capabilities. Future managed identities and phase order follow [Team roles](../../docs/engineering/TEAM.md).

Read-only engineering skills are available locally: [audit](audit/SKILL.md) for scoped conformance,
[architecture](architecture/SKILL.md) for design options, [security-audit](security-audit/SKILL.md) for trust
boundaries, and [adopt](adopt/SKILL.md) for project comparisons. [Fix](fix/SKILL.md) diagnoses bugs and prepares
a tracking handoff; `work` owns repair. None substitutes for independent `review`.
