# Define live Agent operation adoption and safe surfaces

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete AccessControl consumer adoption](../accesscontrol-consumer-adoption-map.md)
**Depends on:** [WF-032 — Reconcile AccessControl coverage with accepted Agent OS scope](WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md)

## Question

What first working Agent OS journey adopts the installed AccessControl v0.5.0 Agent credential,
operation and cohort contract, and which actions are human-facing versus protected worker/broker operations?
Which accepted owner may expand [EPIC-00006](../../epics/00006-EPIC.md)'s Agent/Harness destination, versus a
linked new EPIC? Use the [matrix](../research/WF-032-accesscontrol-consumer-capability-matrix.md) and installed
`vendor/johnnickell/fight-access-control/docs/agent-integration.md` and `agent-operation-cohorts.md`.

## Must decide

- First *working* Agent and reusable integration: start with an instantiated managed Explorer using its own
  holder-bound credential to perform real exploration through authenticated tools, including lifecycle/recovery.
  Follow with Project Manager and additional Agents through the same qualified boundary; do not gate each Agent
  on a new standalone proof or make completed Workflow automation a prerequisite for manual Harness use.
- Surface split: **recommend** authorized admin UI for safe Agent directory/detail/name, direct Permission preview/
  change, intent to provision/rotate/revoke, and operation status; self-service profile Tools only from an
  authenticated Agent. Use protected console/worker for keys, destination enrollment, cohorts, delivery,
  receipt reconciliation, maintenance, cleanup and restore; no browser endpoint for raw material, secret reads,
  lease admission or privileged worker mutation.
- Operation readiness and evidence gate: **recommend** the *full* v0.5.0 current-only operation repository,
  original-operation correlation, shared transaction/cohort fence and independent restoration witness before
  live enablement. Qualify actual PostgreSQL/writer/old-binary exclusions, sink slot ordering/receipts, keys,
  recovery and continuing use. Keep `getOperationContract()` unavailable until that evidence exists. No mock
  old-version backfill, upstream change, starter release or all-five-starter prerequisite.
- Destination ownership: choose an EPIC-00006 amendment or a separate linked Agent-credential EPIC; retain
  TICKET-00034 templates and TASK-00126 storage-only acceptance as-is. Determine whether eventual AI-assisted
  custom-template authoring belongs to that same destination or a later linked EPIC so it does not block the
  first working Agent. Do not silently displace EPIC-00006's browser Planning/terminal milestones.

## Resolution boundary

Decide product acceptance, ownership, access surfaces and qualification standard, not schemas, key services,
worker implementations, detailed endpoint count, operation handlers, implementation TASKs or deployment. Preserve
ADR 0001 package ownership and ADR 0002 shared transactions/durable recovery; a storage-only repository or
package fixture pass cannot be called live consumer qualification.

## Resolution

Approved in the WF-033 interview:

- **First working Agent, then iterate:** instantiate a real Explorer Agent from its versioned template and use it
  for actual exploration through authenticated tools, then follow promptly with Project Manager and other Agents.
  Other Agents may be created and manually exercised through the Harness as authenticated support becomes
  available, **without** waiting for their complete automated Workflows. Dogfood and refine local Skills before
  MCP distribution; revisions remain explicit and instructions cannot select identity, grant Permissions or
  substitute for authorization. No disposable Explorer, separate Explorer-only test harness, or formal per-template
  certification ceremony. EPIC-00006 retains its Explorer/Project Manager commitment; EPIC-00007/00008 retain
  their accepted automation/independence milestones unless a separately approved change explicitly extends them.
- **Safe surfaces:** authorized Agent-management UI for safe list/detail/name, provision/rotate/revoke intent and
  operation status; preview/confirmation of direct Permissions only through reviewed, complete managed-template
  desired sets, not ad hoc overrides. Separately authorized self-profile Tools require the actual authenticated
  Agent. Protected console/services/workers own holder/destination enrollment, keys, fixed sink, claim/admission,
  delivery/receipt/recovery, cohort/restoration and maintenance; no raw-secret, lease or arbitrary key browser
  interface. Each actor/target/destination/Permission decision uses current server-side authority, not the
  template's title, a signed request alone or a pinned profile snapshot. The later EPIC must explicitly reconcile
  [WF-014's](WF-014-define-skill-trust-and-harness-distribution.md) historical one-time raw-secret-return wording
  with the installed v0.5.0 fixed protected-sink contract; do not turn the old wording into a browser secret route.
- **Integrated adoption gate, not waterfall:** implement the current-only locked AccessControl operation contract
  as part of owned product work; leave `getOperationContract()` unavailable until real PostgreSQL shared-fence/
  writer and original-operation correlation, independent witness/restore control, cryptographic key, ordered
  fixed-sink/receipt, worker recovery, authentication/activation/use and revocation checks have actual consumer
  evidence. Include denial of incompatible/bypassing writers and safe handling of uncertainty/restart. Qualify
  actual components and owned behavior with their tools; package modeled fixtures and TASK-00126's storage-only
  tests cannot qualify live support, nor can manual dogfooding alone. There is no separate test product or old-
  format migration, and no per-template gate after the shared boundary is qualified. Unsupported composition
  stays unavailable. No package API change or starter release is required by this decision.
- **Ownership:** approve one **new bounded linked EPIC destination** for the shared AccessControl Agent credential
  lifecycle/operation integration, Agent-management and separate named-Agent creation UI, and the first working
  Explorer. Link it as a prerequisite/capability supplier to EPIC-00006's browser Planning Agent use and to later
  execution without rewriting EPIC-00006/TICKET-00034 or TASK-00126's accepted storage-only scope. Define its
  implementation slices later; Project Manager and subsequent Agent instances reuse the shared integration.
  Approve a **separate later linked EPIC destination** for AI-assisted browser template authoring, so this UI
  does not block first live use. Eight managed templates are a starting catalog, not a ceiling: new managed
  definitions remain reviewed/versioned; authorized Workspace/repository custom templates remain versioned.
  The later editor proposes a persona-based system prompt and desired Permission set, lets a human edit/approve
  both before Create, and checks actual grant eligibility/current actor authority independently of AI output.
  A template is a Harness resource, not an AccessControl Role or an Agent identity; an Agent-creation flow selects
  a template, names a distinct Agent, and provisions its protected holder-bound credential without raw disclosure.
- **Team selection has its own decision:** product-level Teams associated with one or more Repositories and
  role-matching availability-based assignment at Workflow start are wanted, but their ownership, membership,
  conflict, concurrency and recovery choices belong to
  [WF-035 — Define project-linked Teams and available Agent selection](WF-035-define-project-teams-and-agent-selection.md).
  Do not silently modify the closed vision/triage maps or EPIC-00007/00008, and do not make Teams a prerequisite
  for first Explorer/Project Manager use. The server's authorized Workflow start remains the execution gate.

No EPIC, TICKET, TASK, credentials, Agent, Team, runtime activation or implementation is created here.
