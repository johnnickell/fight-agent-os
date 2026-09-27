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

[Release](release/SKILL.md) coordinates versioned package releases from their observed stage, using each target's
own policy and certifier. It covers docs, branches, human signing scripts, publication, Packagist and merge-back.
Use `/skill:release 0.4.0 status` for a read-only inventory or `/skill:release 0.5.0` to prepare and continue within
the authorized scope. The existing global Pi skills link on the maintainer's machine also exposes this skill in
other repositories; run `/reload` in an active Pi session after updating these files. No new installation is needed
where that link is configured. Release guidance does not enable managed Runner execution or application deployment.
