# Qualify per-user OpenRouter and allowance ownership

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Private production rollout](../private-production-rollout-map.md)
**Depends on:** [WF-043](WF-043-bound-private-release-and-environment-names.md)

## Question

Which supported OpenRouter facilities and protected ownership contracts can deliver browser Planning/help using
the logged-in user's own key, with honest usage and independently scoped allowance?

## Must decide

- Research current official key/model/generation/usage/limit facilities: credential-key versus account scope,
  supported units, costs, remaining/reset/window semantics, endpoint authentication and unavailable fields.
  Separate provider-reported cost, measured tokens and estimates; separate Agent OS usage from broader balance.
- Identify protected per-user connection management: submit over authenticated HTTPS, secret-safe status,
  server-side encrypted storage, external encryption-key ownership, replace/revoke/delete, audit/redaction,
  authority and retention. No browser persistence, secret readback, model-visible files or new Agent-key lifecycle.
- Bind initiating user, actual Agent/delegation, provider connection/configuration generation and model/budget
  choices to each admitted attempt. No user-supplied account identity, household-key fallback or admin subsidy.
- Determine how same-account multiple keys, per-key caps and parallel agents share actual allowance. Separate
  keys are not proof of independent account capacity. Explicit finite spending policy remains required.
- Map proposed amendments to [TICKET-00089](../../tickets/00089-TICKET.md), its existing provider TASKs and
  [TICKET-00104](../../tickets/00104-TICKET.md)/TASK-00428; check inherited browser Planning/instruction consumers.
  Preserve local Pi/development providers; no need to configure both provider paths for browser launch.
- Reuse [TICKET-00098](../../tickets/00098-TICKET.md)/[TASK-00399](../../tasks/00399-TASK.md). Identify the exact
  supported safe source and protected refresh responsibility, or retain the source/owner gap explicitly.
  No fabricated supplier ID, credential scraping, inferred local-usage subtraction or unavailable-only completion.
- Keep explicit bounded refresh separate from passive reads/reconnects. Preserve original operation identity,
  current connection fences, observation/report times, freshness/reset and stale/partial/unavailable outcomes.
  A local harness observation through API/MCP needs authenticated source/scope/currentness, not transport trust.
- Preserve one accounting owner across streaming cumulative/incremental reports, retries, reconnects and agents;
  unknown billing is not zero, a refund or permission to regenerate. Define later finite qualification evidence.

## Resolution boundary

Use current primary documentation and bounded repository inspection. Produce cited research distinguishing
supported facts, recommendations, unresolved product choices and unqualified behavior. Update this decision only
when its factual boundary is answered; ask the human for policy choices or contradictions rather than inventing
approval. No key enrollment/inspection, account reads, paid generation, provider qualification or owner amendment.

The [baseline amendment register](../research/private-production-rollout-baseline.md) is the starting ownership
trace, not current OpenRouter API qualification. Optional Jev screening may aid discovery; it does not authorize
application-provider calls. If no authoritative allowance facility qualifies, retain TASK-00399's hold while
allowing recorded usage and the browser journey to proceed independently.

## Resolution

Closed as factual research on 2026-10-23, not as implementation/source qualification or human approval of delivery
amendments. The [cited research and exact amendment proposal](../research/WF-044-per-user-openrouter-and-allowance.md)
answer the documented capability/ownership boundary:

- Ordinary per-user inference keys support the approved browser design. `GET /api/v1/key` documents key caps/usage,
  applicable budget layers, expiry and account-scoped free-model daily quota. Each figure needs its own units,
  scope, UTC/reset and observation/freshness labels. A positive key cap is not account balance or guaranteed
  capacity; null is not unlimited service, and parallel agents share applicable allowance.
- Account-wide `GET /api/v1/credits` requires an unscoped management key. Keep that balance unavailable initially;
  do not collect account administration credentials merely for visibility. Separate OpenRouter keys may share
  the same account/budgets. No subtracting Agent OS usage from a guessed balance.
- Recommend authenticated key submission to an encrypted protected server owner, external encryption-key custody,
  no secret readback/browser persistence, generation-fenced replace/disconnect and explicit provider-side revocation
  guidance. Original in-flight liabilities survive revocation; replacement does not silently rebind an attempt.
  This is not Fight Agent credential delivery or a new generic authentication platform.
- Precise proposed split: TASK-00343 remains nonsecret catalog/policy; amend TASK-00346's installation-key-only
  boundary for user-owned browser connections and a narrow non-generative `/key` adapter; TASK-00399 owns explicit
  refresh admission, sanitized observations and recorded queries/presentation. The per-user self-service lifecycle/
  UI supplier remains unallocated pending accepted owner amendment/decomposition. No future ID is invented.
- Preserve TASK-00399 `needs-info`: documented candidate source and proposed supplier are not an accepted mapping
  or actual qualification. TASK-00346 and TASK-00350's protected host/broker/storage holds remain. Numeric budgets,
  freshness configuration and first-journey/routing policy remain with WF-049/050 and later authorized owners.
- Amendments are also needed in TICKET-00089, TICKET-00104/TASK-00428 and inherited browser consumers. Carry exact
  user/connection generation through TASK-00344/00345 and existing conversation/snapshot owners. TASK-00396 is
  Workflow-oriented; browser-only usage cannot assume that scope or fabricate a Workflow. Reuse accounting,
  never add a competing ledger.
- Streaming final usage and original generation lookup are observations of the same work, not extra charges.
  Cancellation is upstream-specific and HTTP 200 may carry stream errors; unknown outcomes remain uncertain,
  not permission to retry or paid-fallback. OpenRouter defaults upstream fallback on and parameter requirements
  off: propose explicitly qualified restrictive routing rather than silently accepting those defaults.
- Optional OpenRouter PKCE can later deliver a user-funded API key without handling the user's password, but
  does not establish ChatGPT OAuth or a subscription-refresh lifecycle. Manual per-user key entry remains the
  first-pass proposal; local Pi/development providers are unchanged.

### Approved Fight API direction

After clarifying that `/api/v1/key` belongs to OpenRouter, John accepted a user-owned provider-connection resource
for Fight's API. Carry forward `/api/v1/me/provider-connections` and
`/api/v1/me/provider-connections/{connection_id}/allowance` as the route direction: each connection identifies its
provider/environment, allowing multiple providers and multiple connections to one provider. Keep upstream-specific
calls inside the selected adapter; the allowance read returns safe recorded information, never the secret key.
Explicit refresh remains a separate bounded operation, not a GET side effect. Exact methods, payloads and lifecycle/
refresh routes remain later owner-contract work. This approval does not amend delivery records, clear readiness
holds or reopen the closed research decision.

Only public documentation and bounded repository ownership were inspected. No keys/accounts/provider APIs,
generation, credentials, infrastructure or delivery records changed; no live source/model/host acceptance is
claimed. Later qualification evidence is listed in the research. No speculative new decision is needed: remaining
lifecycle allocation and configuration gaps are explicit existing-owner handoffs, not clearance to implement.

The next authored frontier is [WF-045 — Compare DigitalOcean hosting and operating costs](WF-045-compare-digitalocean-hosting-and-operating-costs.md).
Stop after this decision; do not begin hosting research automatically.
