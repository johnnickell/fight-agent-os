# Define developer onboarding and operator guidance

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-014](WF-014-define-skill-trust-and-harness-distribution.md), [WF-016](WF-016-define-runner-dispatch-and-recovery.md)

## Question

What versioned, browser-accessible guidance should help a new developer safely prepare Git, source-host credentials, Pi, the customized harness, repository checkouts, and an authorized runner?

## Must decide

- Information architecture for a durable end-user guide, contextual Dashboard help, official-documentation links, prerequisites, and troubleshooting.
- Supported operating systems and the commands or observable checks that establish compatible `git`, `gh` or other source-host tooling, Docker, Pi, and development-tool versions.
- Secret-safe SSH key creation and source-host enrollment guidance, including ownership, permissions, passphrases, agents/keyrings, verification, rotation, and revocation without collecting private key material.
- Pi installation, provider authentication, project trust, versioned package/bootstrap setup, repository-context verification, updates, rollback, and offline limitations according to WF-014.
- Runner enrollment, repository checkout mapping, capability checks, availability, cancellation/recovery expectations, and de-registration according to WF-016.
- Separation between documentation, copyable diagnostic commands, browser-triggered application operations, and setup mutations that always require explicit user authorization.
- Versioning, source citations, review cadence, stale-link/version detection, accessibility, and safe examples for less experienced developers.
- Whether platform-specific breadth warrants a focused child map without duplicating the accepted harness or runner contracts.

## Resolution boundary

This decision defines onboarding journeys, documentation ownership, safety boundaries, and a future planning handoff. It may use cited research and disposable content prototypes, but it must not install software, generate or upload credentials, change personal/global configuration, enroll a runner, register a repository, or write production guide pages.

## Preferences required

John must choose the initial supported platforms, expected experience level, desired balance between explanatory guidance and copyable commands, and whether the Dashboard should offer guided checks in addition to static documentation. The recommendation should begin with the actual personal installation path, link to primary vendor documentation, explain every credential boundary, and keep mutations explicit and reversible.

## Resolution

Write this only when the onboarding scope, safety model, ownership, freshness policy, and browser-help direction are approved. Link a focused child map only if newly exposed platform-specific questions cannot remain one coherent planning handoff.
