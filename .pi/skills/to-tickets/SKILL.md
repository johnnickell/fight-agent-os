---
name: to-tickets
description: Use after an EPIC is approved to decompose it into requirement TICKET records. Do not create implementation TASKs.
---

# To Tickets

This skill turns an approved EPIC into one or more `planning/tickets/NNNNN-TICKET.md` records. A TICKET groups related use cases and requirements. It is not a TASK and is not normally one PR.

## Process

1. Read the EPIC, relevant ADRs, `planning/CONVENTIONS.md`, and existing live plus archived TICKETs before allocating IDs.
   If it belongs to a Wayfinder handoff, inspect that map's phase under [map-wide planning order](../../../planning/CONVENTIONS.md#map-wide-planning-order).
   Favor remaining decisions/grills and unwritten approved EPICs first. A direct user request to decompose this
   EPIC is an explicit narrower choice; record the remaining map work without asking for that choice again.
2. Identify cohesive requirement areas: user journeys, commands, queries, events, permissions, validation, and observable acceptance evidence.
3. Draft a proposed decomposition for the user:
   - TICKET title
   - Problem and outcome
   - Included use cases
   - Explicit exclusions
   - Dependencies or sequencing concerns
4. Ask whether the split is right before writing files.
5. After approval, create TICKET records from `_TICKET_TEMPLATE.md` with `epic: EPIC-NNNNN`.
6. Run `./bin/planning-check --write` and `./bin/planning-check`.

## Guardrails

- Do not silently map a TASK to a TICKET.
- Do not create TASK records here; use `to-tasks` after TICKET requirements are accepted.
- Prefer domain language over file paths.
- Explain non-applicable security, validation, event, command, and query concerns rather than omitting them.

Finish with an explicit `Next:` under the [handoff rules](../../../docs/engineering/HANDOFFS.md). Default to
`to-tickets` for the next undecomposed EPIC in the selected map before any `to-tasks`; if an explicit slice was
chosen, account for the map's earlier unfinished phase in the recommendation. Use actual accepted records, not
proposed IDs or merely nonempty child tables. Pending split approval remains a human action, not phase completion.
