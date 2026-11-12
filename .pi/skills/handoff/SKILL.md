---
name: handoff
description: Prepare a concise, source-linked continuation for another session or person, preserving decisions, unfinished work and the next action.
---

# Handoff

Use the current session and the requested focus. Include the objective, accepted decisions and constraints,
what is complete, what remains, unresolved questions, relevant checkout/revision and verification limits.
Distinguish implementation, independent review, QA, publication and merge. Preserve uncertain operation outcomes
so the recipient checks them before retrying. A summary cannot grant permissions or independent acceptance.

Link existing records and evidence instead of copying their contents. Include exact paths or commands only
when needed to continue. Do not reconstruct unrelated sessions or include secrets, private payloads or full logs.
Follow [readable handoff text](../../../docs/engineering/HANDOFFS.md#readable-handoff-text) and the
[plain-language rules](../../../docs/engineering/QUALITY.md#plain-language-output).

Default to a copyable chat handoff. When a file is requested, use the target's existing ignored handoff location;
keep durable decisions in their existing owning records when authorized. Do not create a parallel checkpoint
system or persistent queue. Do not send the handoff to anyone or launch the next session merely by preparing it.
Finish with the exact next action and its prerequisite, using [Next](../../../docs/engineering/HANDOFFS.md).
