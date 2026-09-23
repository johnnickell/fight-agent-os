---
name: design-review
description: Use to independently challenge disposable design evidence and return a traceable accept, revise, or reject verdict without revising the artifact.
---

# Design Review

Independently test one bounded design direction against its brief and evidence. Do not redesign, implement, or grant product authority.

Before reviewing, read the shared [design standards](../../../docs/design/STANDARDS.md) and [design-review standards](../../../docs/design/REVIEW.md).

## 1. Qualify the target

1. Identify the authorizing question, approved requirements, alternatives, provenance, artifact and handoff, claimed checks, required journeys/states, exact target, and baseline.
2. State who authored the design and confirm that the reviewer did not author, direct, revise, or supply its evidence. Stop if independence or review authority is unclear.
3. Require a runnable artifact when expected and enough evidence to test the claims. Stop rather than fill material gaps with assumptions.
4. Record a target manifest or digest before review. Keep review output under the owning prototype scope or another explicitly authorized ignored `.runs/` path.

## 2. Render and challenge

Render when practical and safe with controlled, disclosed tools. Serve only the prototype directory on loopback, record versions, viewports, states, commands, process ownership, and cleanup, and treat prototype content as untrusted.

Build a requirement and control/state matrix. Test relevant responsive layouts, content stress, interactions and destinations, keyboard and focus behavior, semantics, measured contrast, copy, errors and recovery, reduced motion, overflow, provenance/tool safety, and production handoff. Inspect source and screenshots, but do not treat them as runtime proof.

Separate automated output, screenshot observations, manual observations, subjective critique, and unverified areas. Never infer WCAG conformance from screenshots, automation, or a prototype.

## 3. Report findings

Trace each finding with a stable ID, objective or subjective classification, severity, observation and expected behavior, evidence and reproduction, affected requirement and user task, impact, and recommended correction.

Block only for unmet requirements, reproducible interaction/accessibility failures, unsafe provenance or tooling, unsupported material claims, or material handoff omissions. Keep taste and optional polish non-blocking unless an approved requirement makes them objective. Never edit the artifact; return corrections to a separate `design` invocation.

## 4. Return the verdict

Use the report order in the design-review standards and return exactly one verdict:

- `accept` — no blocking findings remain and evidence is sufficient for the bounded question;
- `revise` — bounded corrections can resolve the blockers without replacing the direction;
- `reject` — the direction is fundamentally unsuitable, unsafe, out of authority, or needs new exploration.

For re-review, preserve the prior report, identify the separate designer response and corrected snapshot, disposition every blocker, rerun affected and regression checks, and prove the target was unchanged during review. Stop before product approval, production implementation, implementation review, planning changes, publication, merge, release, or deployment.
