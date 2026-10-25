# Bound the private release and approve environment names

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Private production rollout](../private-production-rollout-map.md)
**Depends on:** —

## Question

What is the smallest useful private production destination, and which environment/provider boundaries should the
new map carry forward without making future capabilities prerequisites?

## Must decide

- First users, first browser provider and separation from local development provider access.
- Environment names that retain the identity of both distinct future Nickell applications.
- Required release outcomes versus optional providers, domain UI, workers and legacy replacement.
- Planning authority and existing-owner preservation.

## Resolution boundary

This decision may settle map scope and naming intent. It cannot select infrastructure, qualify an account,
configure DNS/TLS, grant runtime authority, amend delivery records or declare product readiness.

## Resolution

John approved the revised proposal after explicitly clarifying per-user OpenRouter and the two Nickell domains.

- Deliver a privately operated Fight Agent OS, initially for John, then likely his wife and son. First browser
  Planning/help uses each logged-in user's own OpenRouter API key, protected server-side. No shared installation
  payment identity, another user's key, silent provider substitution, paid fallback or automatic budget expansion.
- Preserve local Pi/development provider support independently. Remote Sign in with ChatGPT for each family
  member is desirable optional research, not a first-release requirement. Their intended subscription tiers do
  not establish eligibility, model access, credits, tool support or remote integration.
- First useful release needs one accepted browser Planning/assistant journey, recorded usage with honest
  unsupported/stale allowance, reliable local development, production-like staging and recoverable private
  production. Generic chat scope and exact journey remain WF-049, not an inferred new product.
- Production: `https://fight-agent.dev`; staging: `https://staging.fight-agent.dev`;
  local: `https://local.fight-agent.dev`. Task/worktree names use the actual task identity beneath that local
  suffix, adding checkout disambiguation as needed. No TASK is allocated by the naming convention.
- Future distinct applications: `https://local.nickell.app` and `https://local.nickell.financial`.
  Their own product/naming ownership is preserved; no implementation of either is authorized here.
- `.test` remains a valid earlier option, but shortening both Nickell names loses application identity.
  Earlier Fight alternatives remain reasoning history. Avoid `.local`; trusted HTTPS remains necessary.
- Optional local-domain UI/resolver automation, ChatGPT, candidate workers, managed deployment automation and
  legacy retirement are not launch prerequisites. Existing application routes and useful workflows continue.
  GitHub CI may still build release artifacts; candidate checks are a separate execution concern.
- Reuse existing owners, record proposed amendments and missing suppliers, preserve all Board priorities/holds
  and closed maps, and keep Markdown authoritative. This session is Wayfinder-only: no EPIC/TICKET/TASK changes,
  implementation, infrastructure/credential effects, deployment, commit or publication.
- Skip TASK-00510–00512; no TASK allocation is needed. Use the approved isolated worktree from local `develop`.
  Optional Jev relevance screening is explicitly permitted; application/provider qualification calls are not.

The approved stages are provider/hosting qualification; local/staging foundations; one accepted browser journey;
private release with recovery; independently selectable expansion/legacy retirement. These are decision and
rollout boundaries, not implementation reprioritization.

The [baseline](../research/private-production-rollout-baseline.md) records inspected sources and exact owner
conflicts. This closure records human scope approval only. No EPIC destination is yet authorized for creation;
the [map frontier](../private-production-rollout-map.md#frontier) is WF-044.
