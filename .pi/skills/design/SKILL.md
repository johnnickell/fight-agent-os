---
name: design
description: Use to explore one bounded UI, workflow, interaction, or visual-language question as disposable, sourced evidence before production implementation.
---

# Design

Turn one approved design question into evidence and a production handoff, not production code or final design acceptance.

Before creating artifacts, read the existing [prototype lifecycle](../prototype/SKILL.md) and the shared [design standards](../../../docs/design/STANDARDS.md). Project requirements and human decisions remain authoritative.

## 1. Bound the exploration

1. Read the authorizing record, product context, architecture, existing UI/assets, constraints, and prior evidence.
2. Confirm the audience, job, journey, states, fidelity, decision needed, alternatives, evidence, exclusions, and authority limits in a brief.
3. Work only beneath `.runs/prototypes/<scope>/`. Use invented or explicitly sanitized data; never use credentials or private production data.
4. Stop for ambiguous authority, unsafe tooling, mutable unapproved dependencies, unclear asset rights, production-file writes, or a question too broad for one verdict.

## 2. Explore

- Record each reference, exact source/version when available, lesson, intended use, license, and asset provenance.
- Choose only artifacts that answer the question; do not create folders or outputs ceremonially.
- Compare meaningful alternatives that differ in structure, flow, density, interaction, or visual direction. Explain when alternatives are inapplicable.
- Use the lowest useful fidelity. Mark every artifact disposable and non-authoritative.
- For a visual-language question, apply the specimen and token-mapping requirements in the standards.

## 3. Render and evaluate

When rendering is warranted, use controlled disclosed tools and named states/viewports. Keep any viewer prototype-scoped, bind it to `127.0.0.1` on a selected free port, record its PID and port, and stop it after capture.

Record separately:

- automated results and tool versions;
- manual observations, including keyboard, focus, semantics, contrast, overflow/reflow, motion, and interaction where relevant;
- subjective critique and preference;
- unverified behavior and evidence limitations.

Screenshots and automated checks are evidence, never complete accessibility proof.

## 4. Conclude and hand off

Write a verdict that names the selected and rejected ideas, supporting evidence, unresolved risks, and exact production requirements. Include component/state inventory, interaction and copy rules, accessibility requirements, token mappings where relevant, and source/license obligations.

Require production work to recreate accepted decisions under a separately approved TASK. Never promote prototype code or assets. Record process ownership and cleanup, then stop before independent design acceptance, production implementation, planning decomposition, publication, merge, release, or deployment.
