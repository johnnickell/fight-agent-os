# Recover source conventions

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Usable web application foundation](../usable-web-application-foundation-map.md)
**Depends on:** —

## Question

Which conventions from `fight-software-factory` should become Fight Agent OS guidance for building the first usable web application foundation?

## Must decide

- Which Action-Domain-Responder conventions should be adopted, changed, or rejected.
- Which directory and namespace conventions should be used for Domain, Application, Adapter, HTTP Actions, Responders, tests, and fixtures.
- Which testing style examples should influence this repository.
- Which previous workflow concepts are useful reference material only and must not be imported.

## Resolution boundary

This ticket may produce a source-linked convention summary and recommendations. It must not create implementation TASKs or copy old Factory workflow files.

## Resolution

[Research](../research/WF-001-recover-source-conventions-research.md) found that the relevant source is the Fight-specific `feature/repository-workflows` branch at `75a59ffd82d8b065cc153d1d336da9ed31f5e4f9`, not the upstream SSSF content on `main`.

Adopt a reviewed project-local subset of its portable standards: DDD and `Adapter → Application → Domain` dependency direction, CQRS message semantics, Action–Domain–Responder HTTP boundaries, Fight naming/PHP conventions, behavior-oriented testing, independent review, and resource-conscious delivery. Keep the current `App\` PSR-4 root and top-level Domain/Application/Adapter paths. WF-004 will settle exact bounded-context/use-case nesting because the source does not define it completely.

Do not import SSSF/ADW machinery, `bin/fight-*`, Factory coordinators, prompts, model/provider policy, observatory/runtime code, planning history, approvals, or source-project stack/branch exceptions. Restate useful principles in fresh Agent OS skills. WF-003 owns the per-test cleanup decision; this ticket does not authorize deleting inherited tests.
