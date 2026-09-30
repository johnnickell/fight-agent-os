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

Useful completions end with an explicit, copyable `Next:` command under the [handoff rules](../../docs/engineering/HANDOFFS.md).
Engineering routes through work/review repair loops, QA and land according to actual evidence. For a selected
Wayfinder map, favor finishing its decisions/grills and all approved EPICs, then all TICKET decompositions, then
all TASK decompositions before implementation selection. Recommendations preserve human decisions and do not
automatically invoke the next skill.

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

[Shared quality checks](../../docs/engineering/QUALITY.md) help `work` catch weak tests while writing them and
keep directly affected docs accurate with detail consulted on demand. `review` owns the detailed architecture,
code, test, instruction and README/CHANGELOG assessment; `audit` applies it to its declared sample; `architecture` identifies contract and documentation
impacts. `writing-for-agents` handles authorized instruction reconciliation. These checks preserve each skill's
scope and authority and do not install or automatically run an external cleanup workflow.

[QA](qa/SKILL.md) challenges reviewed TASKs or open PRs through actual UI, API, CLI, library/worker probes or
executable-instruction scenarios and writes canonical
PASS/FAIL/INCOMPLETE/N/A evidence. Use `/skill:qa TASK-NNNNN` or `/skill:qa <PR URL>` in an independent session.
Default flow is `work → review → qa → land → human merge`; `work` consumes QA failures and `land` publishes
suitable evidence. Changed behavior requires applicable QA regardless of UI; pure non-behavioral edits can have
reasoned N/A. Visual changes use inspected, comparable before/after captures; non-visual changes use appropriate
request/output/state evidence. This local skill does not provision managed QA Agents or enforce a hosted merge gate.

[Release](release/SKILL.md) coordinates versioned package releases from their observed stage, using each target's
own policy and certifier. It covers docs, branches, human signing scripts, publication, Packagist and merge-back.
Use `/skill:release 0.4.0 status` for a read-only inventory or `/skill:release 0.5.0` to prepare and continue within
the authorized scope. The existing global Pi skills link on the maintainer's machine also exposes this skill in
other repositories; run `/reload` in an active Pi session after updating these files. No new installation is needed
where that link is configured. Release guidance does not enable managed Runner execution or application deployment.
