# Define skill trust and harness distribution

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-009](WF-009-define-repository-context-and-policy-precedence.md), [WF-013](WF-013-research-mcp-skills-and-pi-support.md)

## Question

How should the customized Pi harness discover, verify, approve, pin, load, record, update, and distribute portable skills and its own shared customization?

## Must decide

- Skill identity as origin plus URI and content/version information; collision handling among project-local, personal, packaged, and MCP-served skills without silent shadowing.
- Inspection and approval flow, per-file verification, allowed-tool treatment, code-execution gating, supporting-resource resolution, and behavior for dynamic or unverifiable skills.
- Caching, immutable session snapshots, updates, re-approval, rollback, server removal, offline behavior, and audit evidence for the exact skill revisions used.
- Boundary between portable skill instructions, separately retrieved repository context, and rules enforced by Agent OS application operations.
- Packaging of shared theme, extensions, commands/prompts, agent roles, and bootstrap behavior with reproducible versioning and project trust.
- Personal installation across repositories: compare versioned Pi packages, pinned Git/npm sources, local package references, and symlinks; copied divergent resources must not be the default.
- Separation of committed shared configuration from credentials, provider authentication, machine paths, and personal preferences.

## Resolution boundary

This decision selects the trust and distribution architecture informed by WF-013. It must not install a package, modify personal/global Pi configuration, connect an MCP server, approve remote content, or implement the harness. Repository business rules remain Agent OS concerns even if transport primitives are reusable.

## Preferences required

John must choose the acceptable approval frequency, offline expectations, and preferred personal-install experience. The recommendation should favor one versioned package/source of shared resources, explicit origin-aware skill approval, immutable per-session revision records, and local project instructions remaining visible rather than silently replaced.

## Resolution

The decisions below use the protocol and implementation evidence in [WF-013 MCP Skills and Pi support research](../research/WF-013-mcp-skills-and-pi-support-research.md). They select the product boundary without claiming that Pi, the Harness package, the MCP adapter, database resources, or permission-filtered tool discovery have been implemented.

### Harness bounded context

Create **Harness** as the bounded context that owns versioned resources and settings used to shape an agent host before Execution begins. Its initial domain areas include Skills, Themes, prompt templates, Agent personas, Agent profile templates, trusted MCP origins, Harness releases, user Harness profiles, and deterministic session resource snapshots. An Agent persona is a prompt-related resource that shapes behavior or presentation; it is not an authenticated Agent, an Access Control Role, or a grant of authority. An Agent profile template may select one.

Workspace continues to own repository identity and repository policy. Fight Access Control owns authenticated principals, credentials, and permission decisions. Execution references the exact Harness snapshot and resource revisions used by a session or run. Planning remains separate. Use current aggregate state plus immutable revisions rather than event sourcing Harness by default.

Do not force every resource into one generic aggregate. Skills, Themes, prompts, personas, profile templates, origins, and releases may retain type-specific invariants inside the same context. Repository identities and Access Control identities remain typed references rather than duplicated Harness records.

### Source, package, and deployment shape

Keep `fight-agent-os` as the canonical monorepo and local self-hosted development environment. A top-level `harness/` directory will contain the independently versioned Pi npm package source, including its extension, reserved bootstrap skills, prompts or commands, themes, and other packaged resources. The application repository may also contain the Docker-based local MCP Skills environment and managed database-resource definitions.

Publish exact Harness npm versions from that subdirectory. Routine users install the package once in personal Pi configuration; registered repositories verify compatibility rather than reinstalling it after every clone. Loading `harness/` directly from the checkout is an explicit and visibly mutable development mode. Cloning this repository is a prerequisite for developing or locally self-hosting Agent OS, but a future hosted-team user may install only the released Harness package and connect to a hosted Agent OS domain.

The package never contains credentials, provider authentication, machine paths, repository context, authorization policy, or personal preferences. Repository-specific instructions and `.pi/skills/` remain committed and visible separately. The local Docker MCP server remains a separately identified origin and gains no implicit trust merely because it shares the repository or Compose environment.

### Connection and user profiles

Distinguish two profile concepts:

- A machine-local **connection profile** contains the profile name, Agent OS endpoint, expected installation identity, credential-store handles, environment label, and released-package or local-development mode needed to find and authenticate to one installation.
- A revisioned, database-backed, user-owned **Harness profile** contains non-secret preferences and selections such as themes, enabled resources, aliases, trusted-origin references, prompts, personas, Agent defaults, and update preferences.

Repository content cannot add or redirect a trusted endpoint. Credentials remain outside both repository content and the database-backed profile. A local-development and hosted-team connection can coexist. Switching connection profiles creates a new linked Pi session with newly resolved repository and Harness snapshots; it never rewrites an active session's identity, authority, or history.

Show a persistent textual profile and environment indicator. Profiles may choose distinct packaged or database-backed themes, and profile creation or editing warns when another enabled connection resolves to the same theme or light/dark theme pair. The warning is overridable for accessibility or preference; color is never the only indicator.

### First-party MCP scope and origin trust

V1 accepts Skills only from the explicitly configured first-party Agent OS MCP origin associated with the active connection profile: either the registered local development installation or an authenticated hosted installation. `localhost` by itself is not trusted, and a hostname is connection evidence rather than origin identity. Use the host-assigned installation/origin identity throughout the registry, cache, model context, and evidence.

Repository content cannot register an origin. Arbitrary third-party or unfamiliar MCP origins and their approval tiers are deferred. Trust is established once for the first-party origin. Its new static Skill revisions do not require a separate per-revision content prompt, but every file is still checked against the advertised size and digest and every `SKILL.md` frontmatter object is verified exactly. Reject dynamic or unverifiable Skills in v1.

Retrieve files lazily. Resolve supporting resources only against the held manifest and originating server. Cross-origin reads, unapproved nested activation, manifest extension through directory results, and silent origin substitution remain prohibited.

### Skill catalog, identity, and collision behavior

Reserve an `agent-os-*` namespace for package-provided Harness bootstrap operations so they do not compete with useful software-development Skills. General development Skills and repository-scoped custom Skills are database-authoritative and served through MCP.

Separate stable logical identity from immutable revision identity. MCP publication preserves the host-assigned origin, URI, complete manifest, and revision information. Human names are aliases, never identity. Normal commands remain short, such as `/skill:work`; internal IDs and digests appear in detail and evidence views rather than routine command syntax.

Personal `~/.pi/agent/skills/` and project `.pi/skills/` resources win the bare alias over an MCP resource with the same name. Surface and document the collision, and retain access to the MCP version through a readable picker or rare qualified form such as `work@team`. A personal-versus-project filesystem collision requires a picker or saved repository/profile selection instead of silently relying on Pi's discovery order. Repository-scoped Skill creation validates the effective catalog and requires deliberate resolution of collisions before publication.

### Database authority and managed resources

The Agent OS database is runtime authority for MCP-served Harness resources. General resources may be workspace-available; repository-scoped resources refer to one stable `RepositoryId`. Readable managed Skill bundles live under `database/skills/`; future managed resource types may receive equivalent database directories.

Distinguish managed and custom resources. An idempotent managed-resource reconciler resolves a stable managed key, normalizes the authored bundle and manifest, no-ops when its digest is current, appends an immutable revision when it changes, and atomically selects that revision as current. It never overwrites or deletes a custom resource. Absence from a later managed source set does not silently delete a resource; retirement is explicit.

Authorized Harness commands create and revise custom database-backed resources, including repository-scoped Skills and future Themes, prompts, or personas. The database remains authoritative after import; committed managed definitions are reviewed reconciliation inputs rather than a second runtime writer.

Retain authoritative revision bytes until an explicit authorized purge. A future purge may remove only unreferenced bytes and must preserve identity, digest, provenance, references, and tombstone metadata. This supports exact evidence and rollback without inventing event sourcing.

### Session snapshots, updates, cache, and rollback

Resolve one exact `HarnessSnapshot` for each Pi session alongside the WF-009 repository-context snapshot. Record the connection and Harness-profile revisions, package source/version/digest, selected resources and aliases, origin identities, Skill URIs, manifests, and immutable resource revisions. Active sessions never move silently to later content.

An exact npm release changes only through an explicit package update. Managed reconciliation and custom editing append database revisions that apply to later snapshots. Mutable local-package or effective resource drift marks authority-bearing work stale under WF-009 and requires a linked continuation.

Keep MCP cache entries origin-separated, outside all filesystem Skill discovery, immutable in a host-only-writable location or rehashed on every access, and populated only on demand. Cache is disposable and never authority. Offline sessions may use already loaded or verified cached content, while unavailable resources fail visibly and Agent OS operations follow WF-009's established availability rules. Removing an origin removes its credentials and cached bytes while retaining secret-free evidence.

Rollback explicitly reinstalls an earlier npm package release or selects a retained database resource revision. Never manufacture rollback by mutating historical bytes or silently falling back to filesystem content.

### Skills and tool authority

A Skill never grants authority. Preserve `allowed-tools` as an experimental, non-authoritative capability request that may describe or narrow expected tools. Effective MCP tools are the intersection of the authenticated Agent's permission-filtered discovery result, server-side authorization, and current session or runner constraints. An unknown or denied requested tool remains unavailable with an understandable diagnostic.

A generally available Skill loading or reading tool may be backed by a managed Permission assigned to every appropriate Agent. The Permission—not the requesting Skill—makes it discoverable. MCP content cannot widen Pi's local tool set. Explicit Skill invocation or an authorized bounded workflow records Skill-selection intent; local tool availability still comes from Pi, session, and runner configuration. Live permission revocation applies immediately despite a pinned Harness snapshot.

The permission-filtered MCP tool seam is accepted future integration, not current Agent OS capability. Harness must consume that authority rather than create a competing permission system.

### Agent profile templates and credentials

Harness owns semantic **Agent profile templates**. They may choose Skills, prompts, personas, models or other preferences, operating constraints, repository applicability, and a requested direct-Permission set. They are not Fight Access Control `Role` entities and cannot grant authority themselves. Exact initial templates—potentially Explorer, Planner, Builder, Reviewer, Publisher, and Coordinator—and their authority split remain for WF-015.

Support managed templates and authorized custom workspace- or repository-scoped templates. Record template ancestry and immutable revisions. Provisioning an actual Agent validates every requested Permission through Access Control and records the template revision used. Browser-delegated authority remains capped by both the initiating user's current permissions and the acting Agent's direct permissions.

An Agent created from a managed template remains managed-bound unless explicitly detached. Reconciliation first applies managed Permission definitions and template revisions, then idempotently reconciles existing managed-bound Agents to the complete desired direct-Permission set with revisioned, secret-free evidence. This includes reviewed additions and removals. Direct drift is rejected or requires explicit detachment.

Custom templates do not silently acquire new authority. When a managed ancestor changes, Harness prepares a three-way proposal showing inherited changes, custom differences, and affected Agents. An authorized user may keep the current custom revision, update it for future Agents, update it and reconcile linked Agents, or review Agents individually. Publishing a template revision and reconciling existing Agents remain distinct effects.

Use the accepted Fight Access Control Agent model: an actual Agent has direct Permissions and exactly one active HMAC credential. HMAC uses a safe credential identifier and one shared secret, not a public/private key pair. Provision separate Agent identities and credentials per real credential-holder boundary rather than sharing one Harness secret across functions, machines, or runners.

The Pi Harness owns the client-side credential experience through an injected credential-store capability. It can provision, securely store, verify, select, rotate, and remove an **Agent key** without asking the user to copy or view it. The server returns raw secret material once; the Harness stores it and verifies a signed request before retaining the safe credential handle. Raw keys never enter the npm package, repository, database-backed profile, transcript, session file, log, event, audit evidence, or `.env`. Exact operating-system keyring, hosted secret-manager, runner provisioning, fallback, and recovery choices remain for their later onboarding and runner decisions.

### Boundaries and follow-up

Portable Harness resources instruct or configure the host but do not replace the separately resolved `RepositoryContextSnapshot` and cannot override application-enforced policy or authorization. The Harness npm package is a distribution adapter for the bounded context, not the domain itself. MCP transport and wire DTOs remain official SDK concerns rather than Fight Common responsibilities; reconsider only a proven policy-neutral wrapper after another Fight consumer exists.

WF-015 owns the concrete coordinator, worker, reviewer, and publisher responsibilities and therefore the first exact managed Agent profile catalog. WF-016 owns runner credential delivery and recovery. WF-020 owns the guided install and credential-store journey. Exact schemas, API routes, package name, npm registry, release automation, UI components, transport authentication mapping, cache paths, and purge policy are deliberately deferred.

This resolution creates no EPIC, requirement TICKET, implementation TASK, schema, database record, package, credential, MCP connection, trusted origin, global configuration change, or production implementation. WF-019 retains proof selection and EPIC sequencing.
