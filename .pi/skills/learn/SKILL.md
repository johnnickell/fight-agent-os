---
name: learn
description: Teach a requested concept through a small, grounded explanation, a concrete example and optional practice or continuation.
---

# Learn

Teach the user, rather than automatically changing agent instructions or saving personal memory.
Start from their question and evident familiarity. Ask about their goal only when it would change the lesson.
Explain one idea at a time with the shared [plain-language rules](../../../docs/engineering/QUALITY.md#plain-language-output).

For project lessons, inspect current source and applicable rules; cite the example's real location. Separate
implemented behavior from planned architecture. For external subjects, use reliable primary sources where needed.
Use a small worked example or diagram when it helps, then offer one optional practice question or next concept.
Do not claim the reader understands something merely because it was explained.

Keep a short lesson in chat. Create a standalone lesson, glossary or learning record only when requested,
using the target's artifact location and retention rules. Do not require HTML, a course workspace, a quiz,
or a growing set of notes for every question. Exercises are read-only unless the user authorizes changes.
Use [ask](../ask/SKILL.md) for a quick factual answer, [what](../what/SKILL.md) to repair a confusing answer,
and [handoff rules](../../../docs/engineering/HANDOFFS.md) for a useful continuation.
