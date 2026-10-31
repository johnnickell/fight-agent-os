---
id: TICKET-00126
epic: EPIC-00022
title: Maintain source-verified Python diagrams beside feature documentation
status: ready-for-agent
---

# Maintain source-verified Python diagrams beside feature documentation

## Problem statement

Feature documentation often explains behavior in prose, while the source code spreads class ownership and
important interactions across several files. Reviewers need a concise visual view that stays connected to the
implemented feature and can be regenerated when that design changes.

## Solution and boundaries

Create a shared standard and supported local rendering path for feature diagrams authored with Python Diagrams.
Keep each diagram's Python source and its rendered image beside the feature documentation that owns it. The
standard explains how to select a useful view, label ownership and arrow meaning, reference current source, and
state omissions or uncertainty without implying that the diagram proves correctness.

Qualify the standard and renderer on one representative existing PHP feature. Include a useful class/layer
ownership view and any additional flow or system view that improves understanding. Show the relevant
Domain/Application/Adapter placement with clear labels and groups. If the chosen diagram building blocks cannot
communicate that placement, record the gap for an explicit decision instead of presenting an unclear result.

This requirement owns the canonical diagram source/image format and documentation placement used by the
pull-request and Pi workflows. It does not require automatic graph extraction from all source files or a separate
architecture catalog.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Create a feature diagram | No product command; use the supported local renderer established by this requirement | Read the selected feature documentation, current source files, ownership conventions, and revision | N/A — no application or workflow event is produced | Add a focused Python Diagrams source file and rendered image beside the owning feature documentation |
| Update a diagram after an architecture change | No product command; run the same supported local renderer | Compare the prior view with the changed source and the exact current revision | N/A — no application or workflow event is produced | Update only the affected diagram source/image and its explanatory links |
| Understand the diagram | N/A — ordinary documentation reading | Follow class/file links and labels to confirm the illustrated ownership and interactions | N/A — no application or workflow event is produced | Reviewer can identify scope, direction, omissions, and source references without inferring hidden behavior |

## Validation and permissions

Document and qualify a repeatable local renderer that includes the necessary Python Diagrams and Graphviz
capabilities without assuming global machine installations. Validate rendering with the owning tools and inspect
the resulting image; do not add product-suite tests for the renderer or generated assets.

Each diagram must name the source revision it describes, use real class and file names, and distinguish static
dependencies from runtime interactions. Proposed architecture must be labeled proposed. Verify local links,
rendered output, and correspondence with source directly. If an environment cannot render or a class view is
unclear, record the limitation rather than inferring success.

No application commands, queries, events, or permissions apply. The work reads only the selected feature context
and writes the authorized documentation, diagram sources, and rendered assets. Do not upload private repository
source to a third-party renderer. Do not move or create application classes to fit the illustration.

## Acceptance and evidence

- One concise shared diagram standard describes when a diagram helps, applicable view choices, source links,
  static versus runtime arrow meanings, revision/proposal labels, omissions, and limits of diagram evidence.
- Feature documentation owns both editable Python Diagrams source and the rendered image for each maintained view.
- A supported local rendering path regenerates the image from that source without relying on an unrecorded global
  package setup.
- A representative existing PHP feature has a readable view of the actual Domain, Application, and Adapter class
  placement, with its relevant dependencies or runtime flow labeled and linked to source.
- The rendered view matches the source revision it claims to describe. A second reader can trace each displayed
  class and important arrow to the implementation or can see the stated uncertainty.
- The result identifies any unsupported or unclear class-diagram representation rather than claiming UML-level
  detail or source-derived proof that the renderer does not provide.

## Sequencing and TASK readiness

This requirement establishes the canonical Python source, rendered-image, documentation-location, and renderer
contract consumed by TICKET-00127 and TICKET-00128. The pull-request and Pi requirements can proceed independently
once that contract is available. At TASK decomposition, keep the renderer attached to the real documentation use
case rather than creating a renderer-only infrastructure demonstration.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00549](../tasks/00549-TASK.md) | Establish source-verified Python diagrams for a documented PHP feature | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Accepted as the documentation and shared renderer requirement for EPIC-00022. One representative existing PHP
feature qualifies whether the selected Python Diagrams nodes and clusters communicate class/layer ownership.
