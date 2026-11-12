---
name: what
description: Re-explain the previous answer or a named passage in plain language when the reader did not understand it.
---

# What

Re-read the answer and the user's current objective. With no argument, explain the last assistant answer;
with a named passage, focus on that passage. If neither is available, ask for the text.

State the point first, add only the context needed to understand it, and explain any necessary technical term.
Use the project's own names. Follow the shared [plain-language rules](../../../docs/engineering/QUALITY.md#plain-language-output).
Preserve facts, uncertainty, decisions and the strength of requirements. If the original answer was wrong,
identify the correction rather than quietly rewriting its meaning.

Keep the result short. Finish with the actual next action when one exists, or say that no action is needed.
Do not invent a decision, new recommendation or investigation merely to explain the previous answer.
No source edits, tools, reports or workflow dispatch are needed for an ordinary rewrite.

Inspired by Matt Pocock's [wait-what](https://github.com/mattpocock/skills/blob/4ae2317ddb22b046e26455e4f9c9b3aaaf62212d/skills/productivity/wait-what/SKILL.md).
This is original Fight guidance; it does not claim formal Simplified Technical English compliance.
