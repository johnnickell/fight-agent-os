---
id: TICKET-00005
epic: EPIC-00002
title: Establish disposable product-design exploration
status: ready-for-agent
---

# Establish disposable product-design exploration

## Problem statement

Fight Agent OS needs an evidence-first way to answer bounded UI, workflow, and visual-language questions before production implementation. Unstructured mockups, unreviewed taste, or disposable code promoted directly into the application would create unsupported design commitments.

## Solution and boundaries

Create concise shared design standards and a `design` skill that inspects product context, frames a brief, records source provenance, explores meaningful alternatives with fake data, and keeps every artifact under `.runs/prototypes/<scope>/`. The skill must capture responsive and interaction states when warranted, record accessibility and behavior checks, and finish with a verdict or implementation handoff rather than production code.

For visual-language work, support a disposable Bootswatch-like specimen covering typography, colors, spacing, controls, focus and form states, navigation, cards, tables, alerts, logo/artifact comparisons, alternative notes, and recommended semantic-token and Bootstrap-variable mappings. External tools and references provide evidence or inspiration; project requirements and human decisions remain authoritative.

Out of scope: production React/component implementation, durable prototype source, final design acceptance, planning decomposition, hosted design-agent dependency, and wholesale installation of OpenDesign or other skill packs.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Frame a bounded design question | N/A — the skill coordinates research and prototype tools | Read approved product requirements, architecture, existing UI, assets, constraints, and source licenses | N/A — no application domain event is produced | A brief defines audience, journeys, states, constraints, alternatives, and evidence needed |
| Explore meaningful alternatives | N/A — disposable file/browser/tool operations | Inspect cited references and rendered alternatives | N/A — no application domain event is produced | Fake-data prototypes and comparison notes are created only under `.runs/prototypes/<scope>/` |
| Evaluate responsive and interaction behavior | N/A — rendering, capture, and accessibility checks are workflow effects | Query rendered states at defined viewports and interactions | N/A — no workflow domain event exists | Deterministic screenshots and honest keyboard, focus, semantics, contrast, and state observations are recorded |
| Produce a design handoff | N/A — documentation is a workflow effect | Reconcile brief, alternatives, checks, and known limitations | N/A — no application domain event is produced | Verdict identifies accepted/rejected ideas, unresolved questions, and production requirements without promoting prototype code |

## Validation and permissions

The skill must require a bounded question, source/provenance records, fake or safely sanitized data, meaningful alternatives unless explicitly inapplicable, and a clear separation between observed evidence and subjective preference. It must not expose credentials or private production data, claim accessibility from screenshots alone, run mutable/unpinned tooling without disclosure and approval, bind an unsafe viewer beyond loopback, or serve the repository root unnecessarily.

All scratch, dependencies, servers, screenshots, and handoff artifacts remain owned by the prototype under `.runs/prototypes/<scope>/`. Production files and planning authority are read-only unless a separately approved TASK authorizes durable documentation.

No application permission model, commands, queries, or domain events apply because this is a disposable design workflow. Filesystem, browser, network, and process effects require explicit scope and ownership; external-source licenses and attribution must be recorded.

## Acceptance and evidence

- `.pi/skills/design/SKILL.md` exists and loads concise project-owned design standards.
- The skill covers context inspection, brief creation, sourced references, alternative exploration, fake data, disposable lifecycle, responsive/state capture, accessibility checks, verdict, and implementation handoff.
- A visual-language path supports the specimen categories and semantic-token/Bootstrap recommendations required by EPIC-00002.
- Viewer/tool behavior is loopback-scoped, dependency/tool versions are pinned or explicitly controlled, and prototype resources remain under `.runs/prototypes/<scope>/`.
- A bounded demonstration produces a brief, provenance record, alternatives, rendered state evidence where warranted, limitations, and a verdict without changing production UI.
- Evidence distinguishes automated checks, manual observations, subjective critique, and unverified claims.
- Third-party use complies with [WF-006 research](../wayfinder/research/WF-006-research-ui-design-skill-sources.md) and required notices.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00005](../tasks/00005-TASK.md) | Establish and prove disposable product-design exploration | in-progress |
<!-- /planning:children -->

## Decisions and progress

Implements the `design` boundary approved by [WF-002](../wayfinder/tickets/WF-002-shape-agent-skill-suite.md) using the source qualification in [WF-006](../wayfinder/tickets/WF-006-research-ui-design-skill-sources.md). It may proceed alongside implementation-review and landing work after the shared standards structure from [TICKET-00002](00002-TICKET.md) is stable.
