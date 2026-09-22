---
name: prototype
description: Use to create a disposable artifact that answers a design, UI, workflow, or state-model question before committing to production implementation.
---

# Prototype

A prototype is disposable evidence for a decision. It should be easy to run, visibly non-production, and linked back to the planning question it answers.

## Choose the prototype type

- Logic or state model: prefer a single runnable artifact that exposes state transitions and edge cases.
- UI or workflow: prefer a small route or static mock with multiple variants and clear navigation.
- API or integration: prefer the smallest script or fake adapter that proves the contract risk.

If the type is ambiguous, ask the user or record the assumption.

## Rules

- Put scratch and generated experiments under ignored `.runs/` unless the user explicitly wants a throwaway branch artifact.
- Mark prototype files and notes as disposable.
- Avoid production abstractions, persistence, broad refactors, and full test suites.
- Show internal state and assumptions directly.
- Capture the verdict: what question was answered, what changed in the plan, and what remains unknown.
- Do not merge prototype code into production. Promote only the validated decision through normal TICKET/TASK planning.

## Handoff

Link the prototype evidence from the relevant EPIC, TICKET, TASK, or Wayfinder decision. Include run instructions and cleanup expectations.
