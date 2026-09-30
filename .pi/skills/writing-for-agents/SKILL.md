---
name: writing-for-agents
description: Write or revise skills, agent instructions and planning guidance with precise triggers, clear completion criteria and focused references.
---

# Writing for Agents

Use this for instructions an agent must act on. Read the document's callers, local conventions and approved
scope before editing. This skill improves an authorized document; it does not authorize changing policy,
creating implementation work, installing skills globally or publishing anything.

1. **Identify the reader and decision.** State what the reader needs to do, when the instruction applies and
   what observable result completes it. Separate an instruction from background or a suggested option.
2. **Make routing precise.** Put the actual capability and triggering situation in a skill's description.
   Link required references at the point where they matter, explaining when to read them. Avoid a broad trigger
   that captures unrelated work or a private machine path that another installation cannot resolve.
3. **Keep one authority.** Reference shared planning, engineering and delivery rules instead of copying them.
   Put universal steps in the entrypoint; move substantial conditional detail into linked references. Keep each
   rule's rationale and exceptions beside it. Do not add companion files merely to fill a template.
4. **Write actionable boundaries.** Name inputs, expected output, source ownership, failure/uncertainty handling
   and the next permitted action. Preserve existing user authorization; ask only for genuinely missing decisions
   or effects outside it. A Skill is guidance, never a source of Permissions or independent acceptance.
5. **Validate the instruction.** Walk a realistic normal request and a relevant failure/ambiguity case. Check
   that the reader reaches the needed reference, knows when to stop, and cannot mistake a proposal for execution.
   Validate metadata and relative links directly; remove placeholders, duplication and unsupported tool commands.

For AGENTS.md and related instruction maintenance, follow the [instruction reconciliation rules](../../../docs/engineering/QUALITY.md#agent-instructions-and-policy-drift).
Inspect affected responsibilities and linked authorities; consider additions, corrections and evidenced removals.
Keep shared facts in one owner and narrower guidance at meaningful boundaries, verifying the active harness's
discovery behavior before relying on nested files. Preserve accepted requirements when implementation disagrees;
record the mismatch and decision owner instead of silently weakening policy. Explain substantive removals and
leave historical decisions intact. Use the [prose guidance](../../../docs/engineering/QUALITY.md#human-facing-documentation)
for human explanations while retaining exact commands, normative force and actionable agent boundaries.

For Pi skills, use `.pi/skills/<name>/SKILL.md` with matching lowercase-hyphenated `name` and a concise
`description` in YAML frontmatter. Add the entry to [the index](../_index.md). Design capabilities through
[planning](../../../planning/CONVENTIONS.md); preserve EPIC → TICKET → TASK terminology. Use ordinary Markdown
links. Do not import another harness's command/delegation API or UI metadata without a demonstrated Pi need.

For architecture and engineering prose, write for human maintainers first: name the domain concept, ownership,
interface and tradeoff clearly. Do not make instructions dictate speculative abstractions or test-shaped APIs.

Adapted from Matt Pocock's MIT-licensed
[writing-for-agents](https://github.com/mattpocock/skills/blob/c55ee46073ed923f86ce59a5eb3b6d895095d1b7/skills/productivity/writing-for-agents/SKILL.md)
and its skill-mechanics companion, inspected at commit `c55ee46073ed923f86ce59a5eb3b6d895095d1b7`.
The [license notice](LICENSE) is retained. Agent OS planning, Pi packaging and authority rules are local adaptations.
