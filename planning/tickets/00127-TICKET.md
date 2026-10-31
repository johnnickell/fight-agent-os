---
id: TICKET-00127
epic: EPIC-00022
title: Review material architecture changes with diagrams in pull requests
status: ready-for-agent
---

# Review material architecture changes with diagrams in pull requests

## Problem statement

Pull requests can change class placement, dependency direction, or important runtime paths in ways that are hard
to assess from a prose summary alone. Reviewers need the relevant maintained diagram beside the change and enough
source context to challenge whether it reflects the implementation.

## Solution and boundaries

Update the existing pull-request and Work/Review guidance so a change that materially affects architecture
includes the relevant rendered diagram and a link to its canonical Python Diagrams source. The author identifies
the diagram's source revision and changed responsibilities or flows. The independent reviewer checks the picture
against the exact diff and source, including any omitted or uncertain edges.

Keep the default pull-request experience brief for changes without an informative architecture view. An optional,
conditional architecture-diagram field may be used when it makes the expectation clear. The diagram supplements
the existing ownership, behavior, security, evidence, and documentation review; it does not replace any of them.

This requirement consumes the artifact and renderer contract from TICKET-00126. It does not create a separate
review verdict, automated score, build gate, or publication permission.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Prepare a material architecture change for review | No application command; author the authorized pull-request description | Read the effective diff, maintained diagram source/image, affected feature docs, and source revision | N/A — no application or workflow event is produced | Pull-request description presents the relevant image, source link, changed boundary/flow, and known omissions |
| Review the architecture view | No application command; use the existing independent Review workflow | Compare the diagram to the exact pull-request head, changed source, dependencies, and feature documentation | N/A — the reviewed application's events are outside this workflow | Reviewer records whether the view accurately represents the scoped change and traces concerns to source |
| Handle a change where a diagram adds no value | N/A — no new workflow command | Assess whether the change materially affects ownership, dependency direction, or runtime flow | N/A — no application or workflow event is produced | PR remains concise and does not include an irrelevant diagram |

## Validation and permissions

The author guidance must apply diagrams only when class ownership, dependency direction, or runtime flow changes
materially. Verify that a sample architecture-changing PR can display its rendered image and link to the source
file, and that the source and image identify the same revision. Verify that an inapplicable example can omit the
diagram without implying incomplete work.

The reviewer checks claims against source rather than treating a rendered image, successful renderer command, or
PR status as proof. Existing independent review, behavioral QA, and ordinary human merge authority remain intact.
Where the image does not render in the PR viewer, provide a usable repository link and report the limitation.

No application commands, queries, events, or application permission model apply. Authoring and review use the
existing repository and pull-request permissions. This requirement grants no push, publication, approval, merge,
or deployment authority.

## Acceptance and evidence

- Existing author guidance explains when a diagram is expected and how it must identify the changed architecture,
  source revision, canonical source, rendered image, and known omissions.
- Existing independent Review guidance checks an applicable diagram against the exact diff and implementation
  without duplicating or weakening its architecture criteria.
- The pull-request description can present the feature diagram to a reviewer and link to its editable Python
  source and owning feature documentation.
- A material architecture example includes and renders the appropriate view; an example with no meaningful
  architecture change does not require one.
- Review records distinguish an accurate, inaccurate, incomplete, or unavailable diagram and trace any finding
  to the relevant source. The image is context, not acceptance evidence by itself.
- No unrelated review, QA, hosted-check, approval, or merge policy changes accompany the diagram guidance.

## Sequencing and TASK readiness

Use the source/image and renderer conventions accepted by TICKET-00126. This requirement can be implemented
independently from the Pi show-me workflow in TICKET-00128 after the shared artifact contract is settled; no
whole-TICKET dependency on the Pi skill is required.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00550](../tasks/00550-TASK.md) | Add conditional architecture diagrams to PR authoring and review guidance | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Accepted as the pull-request and review requirement for EPIC-00022. The relevant diagram is required for material
architecture changes only; existing independent review, QA, and merge boundaries remain authoritative.
