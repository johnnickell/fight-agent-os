# Define browser planning and conversation ownership

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-010](WF-010-define-authoritative-planning-domain-and-lifecycle.md)

## Question

How should persistent browser conversations perform Wayfinder, grill, to-tickets, and to-tasks work through the same authoritative application operations as direct artifact editing?

## Must decide

- Ownership and lifecycle of a planning conversation, its selected repository, participants, resumable Pi session, active planning artifact, and immutable context/policy revision.
- How proposed artifact changes are previewed, edited, validated, accepted, rejected, retried, and committed as planning revisions.
- Recovery from browser disconnects, agent interruption, stale revisions, and simultaneous direct edits without losing conversation history or silently overwriting artifacts.
- Browser journeys for exploration, decision questions, requirement and acceptance-criteria editing, hierarchy browsing, dependencies/blockers, progress, and archive/restore.
- Permission boundaries for viewing, proposing, accepting, archiving, and restoring records.
- Use of the canonical next-executable-TASK query from WF-010, including human-readable eligibility explanations; browser and the `next` skill must not invent separate rules.
- Separation of conversation persistence, planning revisions, execution workflow sessions, review acceptance, publication, and merge state.

## Resolution boundary

This decision owns product and application-operation behavior for planning conversations. It does not choose exact React components, Pi process topology, database mappings, or session-dashboard presentation. It must preserve grill → EPIC, to-tickets, and to-tasks as separate operations.

## Preferences required

John must choose where explicit confirmation is required before an agent-authored planning revision becomes authoritative and how much conversation history is visible by default. The recommendation should save proposals separately from accepted revisions and make acceptance a revision-checked application command.

## Resolution

Write this only when the browser planning contract and confirmation boundaries are approved.
