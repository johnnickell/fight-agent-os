# Qualify dependency baseline

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Open
**Map:** [Usable web application foundation](../usable-web-application-foundation-map.md)
**Depends on:** —

## Question

What dependency baseline should the application use before authentication and UI feature planning begins?

## Must decide

- How to pin or qualify Fight Common `1.2` for this repository.
- How to constrain Fight Access Control `0.*` while allowing expected iteration.
- Which inherited tests were only present to satisfy Fight Common support qualification and can be removed or replaced.
- What the canonical build gate should require after framework-support and lowest-lock artifacts were removed.

## Resolution boundary

This ticket may recommend composer constraints, gate changes, and test cleanup. It must not make dependency changes directly without a TASK handoff.

## Resolution

Write this only when the decision is closed. Link supporting research and the resulting implementation-planning artifact.
