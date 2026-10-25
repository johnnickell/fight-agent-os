# Assess optional household ChatGPT OAuth

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Private production rollout](../private-production-rollout-map.md)
**Depends on:** [WF-043](WF-043-bound-private-release-and-environment-names.md)

## Question

Can each household user use their own ChatGPT subscription through supported Sign in with ChatGPT for a private
remote Fight Agent OS installation, and is that optional integration worth pursuing now or explicitly deferring?

## Must decide

- Recheck current primary eligibility documentation for MIT/open-source applications, local applications and
  personal self-hosted VMs. Distinguish a private household installation from hosting for others or a paid product;
  open-source branding alone is not verified account approval. No inference from existing local Pi Codex support.
- Research the supported Sign in with ChatGPT flow, local authorization/remote-VM setup, protected per-user OAuth
  storage/renewal/revocation and supported remote runtime. Never copy/repurpose personal Codex credentials, inspect
  Pi auth files, store tokens in browsers/logs/model context or invent a parallel Agent credential lifecycle.
- Qualify account-specific model discovery, function/custom tool support, application-side MCP handling and preview
  restrictions against actual planned roles. Missing capability blocks that integration, not OpenRouter launch.
- Distinguish app-specific sharing limits, broader subscription limits and Codex displays. Determine supported
  authoritative observations, freshness/reset/coverage and unavailable cases; MCP transport proves none of these.
- John's intended higher-tier subscription and his family's likely entry paid plans are user context, not verified
  current pricing, credits or eligibility. Research plan-specific behavior without assuming unlimited access;
  parallel agents share applicable allowances and renewal needs remain explicit.
- Reuse per-user protected connection, explicit continuation/switching and original accounting owners. Each user's
  OpenRouter remains independently usable; no automatic paid fallback, account switch or expanded allowance.
- Recommend pursue, bounded follow-up qualification or explicit deferral. Record exactly which account/runtime
  evidence would later be needed under separate authorization, not a successful-integration claim.

## Resolution boundary

Optional public-source research or explicit human-approved deferral only. No OAuth login/enrollment, tokens,
account reads, provider requests, protected credential transfer or existing-owner amendment. Resolve or defer this
branch without making it a release prerequisite; the initial browser provider is per-user OpenRouter.

## Resolution

Closed on public-source research and John's explicit decision: **defer optional ChatGPT integration until after
first production**. The first production milestone remains database-backed browser Wayfinder using each initiating
user's protected OpenRouter connection. ChatGPT is neither a launch dependency nor an approved implementation.

[Research observed 2026-10-24](../research/WF-051-chatgpt-plan-usage-and-private-hosting.md) establishes a documented
Sign in with ChatGPT (SIWC) plan-usage route for eligible Plus/Pro users, including an explicit self-hosted VM
procedure. It does not establish this exact shared household website's approval or working runtime. The checkout
is MIT-licensed, but hosted/partner eligibility is not inferred from that license or local Pi/Codex support.

### Findings and recommendation

- Open-source dynamic OAuth uses a local `127.0.0.1` callback, followed by protected transfer of that tool's own
  selected registration/session to the VM. The VM owns refresh; transferred sessions lack host-specific usage
  attribution/revocation. A remote HTTPS callback cannot simply replace loopback. This is a real mismatch with
  the accepted browser-centered onboarding unless a suitable approved hosted client or an explicitly accepted
  protected local bootstrap is supplied. Do not copy personal Codex auth caches or add a device/terminal ceremony.
- The website/hosted partner flow is separately registered and restricted; public documentation also explicitly
  supports OSS self-hosted VMs. Exact household-service classification needs bounded clarification if pursued,
  not a blanket claim that all self-hosting is forbidden or all MIT apps are approved.
- Account-specific model discovery and public Responses inference are documented. Function/custom tools can
  support application-mediated MCP; hosted MCP is unsupported. Preview prompt/history/output-cap constraints
  need an actual adapter/admission mapping, not a generic API compatibility assumption.
- Plus shares a five-hour limit across participating apps; Pro lacks that particular limit but is not unlimited.
  App sharing/weekly limits and optional credits remain distinct. The inspected SIWC docs do not supply a general
  allowance-read schema. Use honest recorded usage and a ChatGPT settings link; Codex rate-limit buckets are not
  proven SIWC app allowance, and missing allowance is not zero or a new global Planning prohibition.
- Reuse existing per-user protected connections, explicit continuation and original accounting. OAuth refresh,
  revocation, host delivery and new-provider allocation require later authorized owner work; no existing hold is
  cleared. No shared family key, implicit paid credits, account cycling or automatic OpenRouter fallback.

**Accepted deferral:** John chose to defer until after first production rather than pursue eligibility/onboarding
qualification now. Preserve this research and its concrete gaps as the named follow-up. Revisit only after that
milestone and a later explicit selection; deployment does not automatically start research, enrollment or delivery.
Recheck then-current documentation and the actual account/runtime route before treating these dated findings as
current capability. Existing local Pi/Codex development providers are unaffected. No separate readiness TASK,
new EPIC destination, provider implementation, account enrollment or spending follows from this closure.

### Evidence and limits

Twenty-four unauthenticated public-documentation requests produced nineteen successful snapshots and five HTTP-308
failure/redirect observations; current redirected product docs supplied the relevant Codex comparison. See the
research note for citations, exact snapshot provenance, scope and gaps. No live account, OAuth, model request,
credential inspection/transfer, paid evaluator call, deployment, full application build or independent Review/QA
occurred. Planning checks are not runtime/eligibility evidence. Existing owners/holds and Board order remain.

Next: `/skill:wayfinder work WF-052` — define the optional future local-domain management flow. This is the next
unblocked authored frontier, not an automatically started interview or first-production implementation gate.
