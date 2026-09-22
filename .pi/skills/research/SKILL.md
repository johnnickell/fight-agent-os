---
name: research
description: Use to answer a factual question from primary sources and produce a cited Markdown research note for a planning decision, TICKET, TASK, or Wayfinder map.
---

# Research

Research gathers facts so humans can make decisions and agents can plan without guessing.

## Process

1. State the research question and why it matters.
2. Prefer primary sources: official docs, standards, source code, release notes, APIs, and repository files.
3. Follow claims to their owning source. Use secondary sources only as pointers.
4. Capture tradeoffs, version constraints, licensing, security, operational implications, and unknowns.
5. Save the note where the caller expects it:
   - Wayfinder research: `planning/wayfinder/research/`
   - Task-local scratch: `.runs/notes/<task-or-topic>/`
   - Durable project research without a map: propose a planning location before writing.
6. Link the research note from the planning artifact that needed it.

## Output shape

```markdown
# Research title

## Question

## Short answer

## Findings

- Finding with citation.

## Sources

- [Source title](url-or-path)

## Open questions
```

Do not turn research into implementation without an approved planning handoff.
