---
name: graphify
description: Use existing Graphify graphs first for codebase orientation, architecture, callers, dependencies and change impact; report stale coverage with a refresh command, or refresh explicitly selected graph scopes.
---

# Graphify

Use bounded native discovery and direct file reads under the shared
[native workflow rule](../../../harness/pi/WORKFLOW_RULE.md); load mandatory instructions directly.

Graphify supplies derived navigation evidence. Source files and accepted planning records remain authoritative;
its knowledge graph is distinct from the Planning dependency DAG and the PHP execution state machine.

## Query and trace

1. For source orientation and relationship questions, query an existing graph before broad searches; exact-file
   edits and Board-first work routing need no graph detour. Resolve the project and narrowest relevant scope.
   Inspect existing `graphify-out/graph.json`,
   manifest/build metadata and report where available. Record source revision/dirty state and freshness limits;
   a recent output timestamp alone does not prove every source was extracted. Never build a missing graph merely
   because someone asked a source question; use direct source inspection and disclose the limitation.
2. Check the available CLI with `graphify --help`. When supported, use `graphify query "question" --graph PATH`,
   `graphify path "A" "B" --graph PATH` or `graphify explain "X" --graph PATH`, passing the selected graph explicitly.
   Read bounded output and inspect the cited source. Do not assume commands or schemas from another installed
   version. Select terms from actual graph labels; prefer specific symbols over generic words such as "user".
   Use supported `graphify affected "Symbol" --graph PATH` for reverse change-impact traversal. If unavailable,
   continue with source reads rather than installing software as a query side effect.
3. Distinguish extracted relationships from inferred/ambiguous ones and omissions. Verify consequential claims
   against current files, contracts and tests. Report graph scope/freshness, source locations, conclusion and
   uncertainty. Missing edges do not prove missing behavior. Do not save query memories or start watchers/hooks
   as a read-only query side effect.
4. Compare relevant manifest content hashes/coverage with current source and inspect any update marker.
   A silent `graphify check-update ROOT` only means no pending marker was reported; it does not prove complete
   coverage. When changed, removed or missing relevant source makes the graph stale, notify the user once with
   the reason and exact refresh command: `graphify update '/actual/project'` for supported structural updates,
   or `/skill:graphify /actual/project --update` for scoped semantic maintenance. Recheck affected coverage after
   edits and report newly stale graphs. If freshness cannot be established, say so and verify directly.

## Build or refresh

An explicit build/update request authorizes the selected graph output, not global installation, hooks, model
changes or uploading unrelated private files. Read [refresh guidance](references/refresh.md) before mutation.
Keep repository scratch under ignored `.runs/` and verify the generated output's ignore policy before creation.
Do not delete existing graph caches or silently overwrite a different scope. If scope or external-processing
authority is missing, ask for that decision while continuing safe source inspection.

This is original Agent OS integration guidance for [Graphify](https://github.com/Graphify-Labs/graphify), informed
by its documented query/structural extraction features and the installed CLI inspected on 2026-09-26. It does
not bundle Graphify, copy an upstream extraction engine, or require a Claude/Codex-specific Agent API.
