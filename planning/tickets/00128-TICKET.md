---
id: TICKET-00128
epic: EPIC-00022
title: Show feature architecture on demand in Pi
status: ready-for-agent
---

# Show feature architecture on demand in Pi

## Problem statement

Reading an unfamiliar feature or proposed change across many source files makes it difficult to form a clear
picture of class placement and runtime flow. A user needs an on-demand Pi skill that produces a focused,
source-linked Python Diagrams view of the requested scope.

## Solution and boundaries

Create a project-local show-me skill, invoked explicitly for a named feature, source area, or proposed change.
The skill reads the selected current implementation or proposed diff, chooses the smallest useful view, and
renders it using the shared Python Diagrams source and renderer contract from TICKET-00126. The interaction may
show class/layer ownership, dependencies, request or data flow, before/after shape, or system context where
relevant.

Render the focused image inline when the active Pi terminal supports it. Provide a usable browser preview when
inline display is unavailable or the image needs more room. Temporary diagram source and preview output stay
under the repository's ignored .runs scratch layout. The on-demand flow is read-only with respect to application
source and durable documentation; durable diagrams are maintained through their owning feature documentation
and the authorized Work flow.

Keep the scope to visual explanation of a selected architecture topic. It does not replace the existing
architecture analysis skill, generalize into whole-repository graph generation, or silently make design changes.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Show an existing feature | Explicit /skill:show-me invocation for a bounded feature or source area | Read the selected source, feature docs, current revision, and relevant ownership conventions | N/A — no application or workflow event is produced | Create temporary diagram source and rendered image in ignored scratch, then display the focused view |
| Show a proposed architecture change | Explicit /skill:show-me invocation with the proposed diff or accepted design context | Read the exact proposed scope and current source it affects | N/A — no application or workflow event is produced | Display a view labeled proposed and distinguish unchanged/current context from proposed relationships |
| Explain a rendering or evidence limit | Existing skill invocation | Check renderer availability, terminal image support, and source completeness | N/A — no application or workflow event is produced | State what is unavailable or uncertain and provide the supported browser fallback when possible |

## Validation and permissions

Qualify the skill in an actual installed Pi session. Verify local image-display behavior for the active terminal;
use a browser fallback if inline rendering is unsupported or unsuitable. Record the tested Pi version as evidence
only. Do not pin Pi to that version or promise future terminal support based on this qualification.

The skill must scope source reads to the requested feature or proposed change, use real file/class labels, include
source links or exact paths, and state uncertainty. If the renderer is unavailable, report that limitation rather
than silently installing machine-global dependencies, switching to an unapproved external renderer, or claiming
that an unrendered sketch is a verified diagram.

No application commands, queries, events, or permission model apply. The skill reads only the selected repository
scope and writes temporary diagram artifacts under ignored .runs scratch. It does not alter source, feature
documentation, pull requests, branches, commits, or external services. No source or generated diagram is sent to
an external service.

## Acceptance and evidence

- A project-local show-me skill exists, appears in the Pi skill index, and has a narrow explicit trigger for
  showing a bounded existing or proposed architecture view.
- It uses the Python Diagrams format and shared renderer established by TICKET-00126, selects only the useful
  class/layer, dependency, runtime/data-flow, before/after, or system context needed for the request, and links
  displayed relationships to source.
- An existing-feature invocation produces a rendered, readable diagram with source revision and uncertainty
  clearly represented.
- A proposed-change invocation labels proposed relationships and does not imply they exist in current source.
- A real Pi-session qualification shows the image inline when supported and exercises the browser fallback when
  needed. The recorded Pi version is an observation, not an equality requirement.
- Temporary outputs remain in ignored scratch; source, documentation, Git state, pull requests, and external
  services remain untouched by the on-demand skill.
- If the selected class/layer view cannot communicate ownership clearly using the qualified Python Diagrams
  building blocks, the skill reports that gap instead of displaying a misleading picture.

## Sequencing and TASK readiness

Consume the renderer, source convention, and feature-diagram vocabulary established by TICKET-00126. This
requirement can proceed independently from the pull-request guidance in TICKET-00127 after that shared contract
is in place. At TASK decomposition, qualify the interaction in Pi rather than inferring support from package
documentation alone.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00551](../tasks/00551-TASK.md) | Create and qualify a Pi skill for on-demand architecture diagrams | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Accepted as the on-demand Pi visual explanation requirement for EPIC-00022. It complements existing architecture
analysis and does not acquire implementation, documentation-publication, Git, or merge authority.
