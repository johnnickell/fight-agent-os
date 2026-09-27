---
name: graphify
description: Query a project's Graphify knowledge graph for relationships and source orientation, or build and refresh explicitly selected graph scopes.
---

# Graphify

Graphify supplies derived navigation evidence. Source files and accepted planning records remain authoritative;
its knowledge graph is distinct from the Planning dependency DAG and the PHP execution state machine.

## Query and trace

1. Resolve the requested project and narrowest relevant graph scope. Inspect existing `graphify-out/graph.json`,
   manifest/build metadata and report where available. Record source revision/dirty state and freshness limits;
   a recent output timestamp alone does not prove every source was extracted. Never build a missing graph merely
   because someone asked a source question; use direct source inspection and disclose the limitation.
2. Check the available CLI with `graphify --help`. When supported, use `graphify query "question" --graph PATH`,
   `graphify path "A" "B" --graph PATH` or `graphify explain "X" --graph PATH`, passing the selected graph explicitly.
   Read bounded output and inspect the cited source. Do not assume commands or schemas from another installed
   version. If unavailable, continue with source reads rather than installing software as a query side effect.
3. Distinguish extracted relationships from inferred/ambiguous ones and omissions. Verify consequential claims
   against current files, contracts and tests. Report graph scope/freshness, source locations, conclusion and
   uncertainty. Missing edges do not prove missing behavior. Do not save query memories or start watchers/hooks
   as a read-only query side effect.

## Build or refresh

An explicit build/update request authorizes the selected graph output, not global installation, hooks, model
changes or uploading unrelated private files. Read [refresh guidance](references/refresh.md) before mutation.
Keep repository scratch under ignored `.runs/` and verify the generated output's ignore policy before creation.
Do not delete existing graph caches or silently overwrite a different scope. If scope or external-processing
authority is missing, ask for that decision while continuing safe source inspection.

This is original Agent OS integration guidance for [Graphify](https://github.com/Graphify-Labs/graphify), informed
by its documented query/structural extraction features and the installed CLI inspected on 2026-09-26. It does
not bundle Graphify, copy an upstream extraction engine, or require a Claude/Codex-specific Agent API.
