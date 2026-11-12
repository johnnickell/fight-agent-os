---
name: ask
description: Answer a question about a project from current evidence, with a concise explanation and relevant source links.
---

# Ask

Resolve the named repository or current checkout. Read its applicable instructions and inspect the smallest
source boundary that can answer the question. Use an existing graph for navigation when useful, verifying
important claims in current source. Do not build a graph, install tools or scan the whole project for a narrow question.

Give the answer first, then the evidence and any uncertainty. Link the few files or external sources that
support the conclusion. Distinguish current behavior from intended policy and proposed work. Use the shared
[plain-language rules](../../../docs/engineering/QUALITY.md#plain-language-output); choose depth from the question
without a mandatory preliminary interview. Ask only when a missing fact materially changes the answer.

Keep the answer in chat unless the user requests an artifact. A question does not authorize source changes,
tracking records or external messages. For next-work selection use [next](../next/SKILL.md); confirmed bug
diagnosis belongs to [fix](../fix/SKILL.md), design alternatives to [architecture](../architecture/SKILL.md),
and a lesson to [learn](../learn/SKILL.md). Answer what is already clear before recommending another skill.
Use a concrete [Next](../../../docs/engineering/HANDOFFS.md) only when useful; do not dispatch it automatically.
