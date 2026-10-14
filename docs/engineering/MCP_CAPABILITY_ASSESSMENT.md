# Agent OS MCP needs versus Fight Common

Date: 2026-09-27. Discussion proposal only; no upstream scope acceptance or implementation authorization.
Fight Common was inspected read-only at `6977533` on `feature/task-00105-guarded-mcp-http`, with active uncommitted
TASK-00105 work. Those changes are provisional and were neither tested nor modified here. Agent OS's baseline is
`3136608` after restoring the four approved checkpoints. Graph queries supplied orientation; live source and
canonical planning supplied the findings. Derived graphs are not evidence that a capability exists or is current.

## What already fits

Fight Common EPIC-00006 and TICKET-00023–00027 separate reusable MCP mechanics from consumer policy. TASK-00104
provides the capability registry and semantic foundation. Current TASK-00105 code adds the guarded PSR HTTP
endpoint. TASK-00106–00113 plan tool discovery/invocation, progressive requests/cancellation, framework streaming
qualification, input-required interactions and resource-server authorization. These are useful Agent OS dependencies.
They are not all implemented, released or installed in Agent OS.

The inspected `McpCapability` extension point and `McpCapabilityRegistry` allow additional method handlers and
advertised extension maps. Recognition of Resources/Prompts method names is metadata validation, not an implemented
Resources or Prompts capability. The current endpoint is JSON-only; actual progressive SSE is later work.
Its invocation guard is not a complete principal/permission/Workflow authorization layer. Agent OS must compose that
from Access Control and its own domain rules.

## The missing dependency

Common's EPIC explicitly defers Resources, Prompts and Skills. Agent OS EPIC-00006 already promises first-party,
revisioned Skills over MCP. Completing Common's current Tools slice alone cannot deliver that promise.

The official [Skills extension](https://modelcontextprotocol.io/extensions/skills/overview) uses
`skills/list` and `skills/get` for discovery and `resources/read` for files. It advertises Resources plus
`io.modelcontextprotocol/skills`; directory reads are optional. Metadata preserves frontmatter and complete
file manifests with byte sizes and SHA-256 digests. Identity includes the originating server and URI. Loading is a
host action, distinct from reading. This aligns with Agent OS's planned immutable manifests, trusted origins and
lazy loading; it does not supply the Agent OS release catalog or approval policy.

## Proposed planning additions

| Workstream | Owner | Proposed acceptance boundary |
|---|---|---|
| Resources foundation | Fight Common, a separately approved requirement | Reusable resource providers; correct list/read schemas, opaque URI handling, pagination, cache metadata and bounded content responses. Consumer authorization applies equally to discovery and direct reads. Never expose arbitrary host paths from a URI. |
| Official Skills extension | Fight Common, separate requirement depending on Resources | Discovery/get contracts, complete atomic manifests, exact bytes/frontmatter and capability truthfulness. Validate interaction of multiple extension declarations. Use pinned protocol/conformance examples and decide explicitly whether directory reads are supported. |
| Trusted catalog and snapshots | Agent OS Harness context | Trusted first-party origin; released immutable revisions backed by the authorized catalog; repository/user visibility; stable revision-specific URI and digest binding; precise revocation/retention behavior for active sessions. Preserve Markdown authority until approved cutover. |
| Pi consumption | Agent OS Harness package | A real client loader with retained manifest, origin-aware identity, lazy reads, verification before use, explicit activation and approvals, safe cache and collision handling. Prove compatibility with the pinned Pi runtime; serving MCP alone does not make Pi consume Skills. |
| Shared access policy | Agent OS + Access Control integration | Same principal/repository/Workflow rules for HTTP and MCP. Direct lookup cannot bypass filtered listing. Tool execution still checks current authority, revision, idempotency and attempt fences. Common supplies mechanisms, not Fight permissions. |
| Operations and observability | Agent OS with Common transport hooks | Correlate MCP request, Agent, Workflow and attempt; record admission, denial, cancellation and outcome without credentials or sensitive bodies. Bound concurrency, request/response bytes, deadlines and retained progress/artifacts. Disconnect is not proof of durable cancellation. |

For Common, prefer a separate small Resources-and-Skills EPIC, then independent TICKET decomposition. Keep ongoing
TASK-00105 focused. Do not allocate IDs until checking current live and archived upstream records. Its release and
Agent OS dependency upgrade require their own verified checkpoints; a branch implementation is not availability.

For Agent OS, decompose the already approved Harness outcomes into serving and consuming requirements, rather than
creating a second skill system. Before implementation, agree the catalog DTOs and URI/revision mapping, error/cache
semantics, visibility rules, content bounds, client approval lifecycle and source-of-truth transition. Start with
static manifests and one trusted origin; dynamic content and arbitrary remote origins remain outside v1.

A consumer compatibility journey should serve an authorized immutable skill, load it in the pinned Harness, retrieve
one supporting file on demand, and reject changed bytes, unknown/unlisted authority, wrong origin, stale approval,
cache tampering and name collisions. Exercise paginated discovery and direct authorized lookup separately. These
are owned behavior/integration checks, not tests of planning files or tool wrappers.

## Other MCP features and boundaries

- Tools: expose selected semantic Planning operations and later authorized execution/status/cancel commands through
  application use cases. Never expose unrestricted SQL, shell, filesystem or provisioning as an Agent OS shortcut.
- Resources: safe read models can serve approved plans, snapshots and evidence with explicit visibility/redaction.
  Artifact links need the same authorization and size/retention rules as their originating request.
- Prompts/completions: add only for a named product interaction; Harness prompt templates do not automatically require
  the MCP Prompts primitive. No demonstrated blocker for the first terminal slice.
- MCP Tasks, subscriptions/listChanged and Apps: defer until a concrete consumer needs them. MCP request progress is
  not PostgreSQL Workflow state, and an MCP task handle must never become a competing execution authority.
- Streaming/auth: consume the existing Common plan rather than duplicating it. Qualify the actual Agent OS framework
  path, backpressure and disconnect behavior; authorization metadata alone does not establish a human or Agent identity.
- Sandbox gateway: Docker's [architecture documentation](https://docs.docker.com/ai/sandboxes/architecture/) places its
  MCP gateway outside the guest and permits host-side tool execution. Proposed terminal profile: disable arbitrary
  gateway/server registration and allow only separately qualified first-party endpoints. Remote Skills content cannot
  grant host tools or alter sandbox policy.

## Source trail and decision

Inspected Common paths: `planning/epics/00006-EPIC.md`, `planning/tickets/00023-TICKET.md` through `00027-TICKET.md`,
`planning/tasks/00104-TASK.md` through `00113-TASK.md`, `src/Application/Mcp/McpCapability.php`,
`McpCapabilityRegistry.php`, and the uncommitted `src/Adapter/Http/Mcp/McpRequestHandler.php`.
Agent OS source: [Harness EPIC](../../planning/epics/00006-EPIC.md),
[terminal authority](TERMINAL_AUTHORITY.md), and [R2/R3 qualification](R2_R3_QUALIFICATION_FOLLOWUP.md).

Recommended next decision: approve separate upstream Resources/Skills planning and corresponding Agent OS Harness
TICKET decomposition. This document does not make that decision or edit either project's canonical scope.
