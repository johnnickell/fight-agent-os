# WF-026 — Agent memory context loading

## Question

Should a Fight Agent's system prompt and task handoff require loading memory up front, or guide it to retrieve
its own private and authorized shared memory as needed? This informs [WF-026 — Define sandbox memory retrieval
and MCP access](../tickets/WF-026-define-sandbox-memory-retrieval-and-mcp-access.md), not Pi implementation or a
new memory permission.

## Short answer

The sources do **not** establish a universal requirement to preload memory. A simple baseline for Fight is a
short instruction explaining when to consult memory, plus an authoritative task handoff and on-demand,
authorization-checked discovery/read tools. Measure whether this misses useful context before adding a small
preloaded index or selection; never preload a whole memory bank by default. John accepted the on-demand
baseline in WF-026; neither this research nor that planning decision qualifies a particular Pi/MCP implementation.

## Findings

- Anthropic recommends a minimal but sufficiently explicit system prompt, a small set of clearly described
  tools and the smallest set of high-signal context tokens that supports the task. Its context-engineering
  guidance describes **just-in-time retrieval** through lightweight pointers and progressive disclosure;
  it also recognizes **hybrid** up-front plus on-demand retrieval when task characteristics justify it.
  More retrieval steps cost latency and can miss relevant material if tools or guidance are inadequate.
  [Anthropic, *Effective context engineering for AI agents*](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents).
- Claude Code is a concrete hybrid example, **not** evidence that all memory must be loaded: it loads a
  bounded `MEMORY.md` index at session start (first 200 lines or 25 KB) but reads detailed topic files on
  demand. Its project instructions and auto memory are context, not enforced configuration; Fight must keep
  access control in the service rather than treating prompt prose as security policy.
  [Claude Code, *How Claude remembers your project*](https://code.claude.com/docs/en/memory).
- GitHub Copilot Memory is a different pattern: repository facts are cited to code and checked against the
  current branch when relevant; user preferences are scoped to that user. This supports checking retrieved
  memories against live sources rather than trusting a remembered fact, but GitHub's own automatic retention
  policy is **not** a precedent for Fight: WF-025 rejected age-based expiry and deferred routine removal.
  [GitHub Docs, *About GitHub Copilot Memory*](https://docs.github.com/en/copilot/concepts/agents/copilot-memory).
- MCP distinguishes model-invoked Tools from read-only Resources, which an application retrieves and selects
  for model context. A Resource does not imply automatic injection into every prompt, nor that the model can
  discover a resource through every client. WF-026 selected MCP tools for memory; their client delivery still
  requires qualification. [MCP, *Understanding MCP servers*](https://modelcontextprotocol.io/docs/learn/server-concepts).
- A controlled study of long-context question answering and key-value retrieval found performance can
  degrade when relevant information sits in the middle of long inputs. This is a caution against indiscriminate
  preloading, not a benchmark of Fight's chosen model or proof that a particular retrieval scheme is best.
  [Liu et al., *Lost in the Middle*](https://arxiv.org/abs/2307.03172).

## Application to WF-026 (accepted baseline, with later qualification)

- Start the Agent with a short, clear memory-use rule and its role-specific handoff; tell it to consult its
  own private and currently authorized shared memory when the task calls for prior learning, prior attempts,
  handoff context or a correction. Do **not** make a memory fetch or bulk memory preload a universal startup
  gate. Task criteria and required evidence still come from their owning contracts, not memory.
- Offer typed Jev questions over permitted memory scopes and exact, revision-identifiable reads on demand
  through the trusted first-party service. Authorization and safe disclosure apply before exposing titles, snippets or counts;
  prompts cannot substitute for service-side checks. Keep rejected promotion proposals and retracted claims
  out of ordinary guidance under WF-025. Do not copy hidden reasoning, raw transcripts or other Agents'
  private working memory into the handoff.
- Compare this baseline with a small **server-selected, access-checked** starting index or selection only if
  evaluation shows agents miss useful context or spend too long searching. A short pointer/index is different
  from loading entry contents; neither would grant permission or override revocation. Qualify the client path,
  latency and context costs before selecting a hybrid.

## Sources

- [Anthropic, *Effective context engineering for AI agents*](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents)
- [Claude Code, *How Claude remembers your project*](https://code.claude.com/docs/en/memory)
- [GitHub Docs, *About GitHub Copilot Memory*](https://docs.github.com/en/copilot/concepts/agents/copilot-memory)
- [Model Context Protocol, *Understanding MCP servers*](https://modelcontextprotocol.io/docs/learn/server-concepts)
- [Liu et al., *Lost in the Middle*](https://arxiv.org/abs/2307.03172)

## Remaining qualification

- Can the selected Pi/MCP client deliver the accepted on-demand memory tools securely and conveniently to the
  sandbox? Do not infer tool availability from a proposed MCP Resource.
- Compare usefulness, missed context, latency, spend and disclosure for on-demand use versus an optional small
  starting index if evidence later warrants it. No preload, memory-specific Jev threshold or cache is required
  by accepting this baseline; the WF-026 resolution governs the lookup contract.
