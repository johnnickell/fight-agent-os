# Terminal execution authority handoff

Contract design for [TASK-00138](../../planning/tasks/00138-TASK.md), consumed by the
[terminal execution lifecycle](TERMINAL_EXECUTION.md). This is not an enrollment, a permission grant, a working
broker or permission to start TASK-00139. The qualification decision remains **not launchable**.

## Operator enrollment and package ownership

Use an existing active Fight Access Control User, not a UNIX account, Git author, Docker login or model-provider
identity. The console authentication adapter must call the package's public `AuthenticationService` for login,
refresh and logout, and resolve current authority through its public `CurrentPrincipalProvider`. Never construct
a principal from a supplied user ID or call the package's internal principal/permission resolvers directly.
The configured access-token verifier must validate signature, issuer, audience, type, expiry and session claims
before providing `AuthenticationContext`; package resolution then checks live User, session and permission state.

Enrollment binds that authenticated User to the installation, registered repository, approved source/worktree
roots and exact policy revision. It records who confirmed it, when, its revision and revocation state in PostgreSQL.
It is a narrowing resource authorization, not a second user database. Every command requires both current package
authority and current enrollment. Re-enrollment cannot silently broaden roots, profiles or repositories.

Credentials enter only through a protected, non-echoing console authentication flow, never command arguments,
Markdown, environment snapshots or logs. Retained refresh material belongs in an installation-selected host secret
store; PostgreSQL stores only an opaque reference and safe lifecycle metadata. Unavailable storage or authentication
means denial, not an environment-variable/global-credential fallback. Do not collect credentials in a probe.
There is no first-user bypass: if no active authorized User exists, stop for the separately guarded bootstrap flow.

Enrollment and Workflow operations need explicit permissions, not “Super Admin”/“Team Lead” name checks. Their
catalog identifiers and the host secret-store adapter must be reconciled with the managed permission/bootstrap
work before implementation acceptance. The package has authentication primitives; Agent OS has not yet delivered
this console composition. Shell access cannot stand in for that missing integration.

Managed roles use distinct package Agent identities and direct Permissions. The package's `AgentProvisioningService`,
`AgentCredentialLifecycleService`, `SignedAgentRequest` and `CurrentAgentPrincipalProvider` own provisioning,
rotation/revocation and HMAC authentication. The inspected v0.3.0 provisioning service accepts a maintainer actor ID;
it is not itself evidence that the caller may provision. Owned enrollment policy must authorize that operation
before calling it. Do not wrap its internally transactional operation in another Unit of Work. Persist a recoverable
enrollment intent, reconcile the resulting package identity, and keep incomplete enrollment disabled.

Agent HMAC secrets stay in the protected broker, not a Pi VM. That broker signs only typed, permitted application
operations after binding the request to the launched role/attempt; it is not an arbitrary-signature service. Package
nonce consumption/revision validation remains mandatory. Agent persistence, nonce storage and these adapters are
still missing; do not fabricate a local principal or replace package authentication to skip them.

### Enrollment qualification findings

Read-only tracing on 2026-09-27 used locked Access Control v0.3.0 (`22ffab6452b2278b82df9b147e8949630b97355f`)
and Common v1.2.0 (`a2cd615d9b5064c9c30e994655536176249cd73b`), not live operator credentials:

- `config/common/security.php` binds Common `JwtDecoder`. Its `decode()` validates `SignedWith`, not the complete
  expiry/issuer/audience/session profile. It cannot alone establish enrolled operator authority. The planned complete
  authentication adapter must precede current-principal resolution; do not treat the decoder binding as readiness.
- `config/common/persistence.php` binds User/Role/Permission/session/grant repositories, but not `AgentRepository`
  or `AgentRequestNonceConsumer`. Searches of `src/`, `config/` and `bin/` found no operator enrollment or managed
  Agent authentication composition. `config/common/command.php` has no registered handlers. No runtime authentication
  or enrollment denial was claimed from this source inspection.
- `AgentProvisioningService::provision()` generates an Agent ID inside its own transaction and returns secret-bearing
  material afterward. Its API accepts no enrollment/idempotency key; `AgentRepository` supplies no lookup by enrollment
  key. A crash after commit but before recording the returned ID/secret therefore cannot be declared safely retryable
  under the proposed intent alone. Stop in Needs Human; do not provision another Agent or correlate by display name.
  Recovery needs a separately qualified package-supported correlation/credential recovery design before enrollment
  can be called durable. Do not hide atomic enrollment writes in an audit adapter or nest package transactions.
- `AgentCredentialLifecycleService::rotate()` checks the expected credential ID and returns new secret material;
  losing that result also requires reconciliation, not blind rotation. `CurrentAgentPrincipalProvider` consumes the
  nonce in its own Unit of Work and caches the resulting principal per request. Use a fresh provider for each request;
  broker admission must additionally check the live attempt/grant and fence revocation races.
- The foundation catalog in [TASK-00036](../../planning/tasks/00036-TASK.md) explicitly has twenty-one permissions
  and excludes additions. Execution enrollment/start/inspection permissions need an explicit catalog extension and
  references, not an invented Super Admin bypass or an unrecorded change to that TASK.

Remaining enrollment evidence must cover valid login/confirmation, disabled/revoked/expired operator, wrong repository,
stale enrollment/permission revision, replayed Agent nonce, lost provisioning/rotation response, unavailable secret
store and concurrent revocation. These are future owned behavior/integration checks, not product tests of wiring.
No User, Agent, grant or provider credential was created by this inspection.

## Immutable eligibility and explicit grants

`TaskEligibility` consumes server-registered repository identity plus a selected immutable source snapshot. The
Markdown adapter returns a versioned manifest containing the full source commit OID, TASK and parent paths/byte
digests, statuses, dependency closure and digests, effective instruction/context digests, and the exact configured
base OID. Symlinks, path traversal, missing parents, cycles, contradictory identifiers, invalid records and unresolved
or dirty source authority reject selection. No filesystem path, branch name or eligibility boolean from Pi is trusted.

The operator confirms the manifest digest and a bounded grant: repository/TASK, source/base, role identities and
profile revisions, Harness/Pi/image digests, tools, provider/model/reasoning, time/cost/token ceilings, attempt limits,
cleanup/retention policy and expiry. Defaults come from versioned installation policy and can only narrow its limits.
A changed manifest or a widened limit requires a new confirmation; the Runner cannot “refresh” consent silently.

Before claiming, the adapter re-resolves the registered source and dependency closure under the trusted source
administration lock. PostgreSQL cannot atomically lock arbitrary human Markdown edits. Therefore production must
qualify a controlled source-publication protocol that all registered-source writers obey, or fail closed on that
source. A pre/post filesystem hash check alone does not prove an atomic latest-revision claim. The current checkout
is not thereby enrolled or write-locked. PostgreSQL may retain immutable observed manifests and consent, but does
not become Planning authority before the separately approved cutover.

## Command and persistence contracts

The following are use-case contracts, not classes to scaffold now. Safe results contain IDs, revisions, status,
reason codes and allowed next actions, never credentials, raw provider errors or host paths. All entry paths use
the same application policy. Authentication occurs before the command transaction; command-critical live authority
must be revalidated/fenced within it without nesting package transactions.

| Operation | Required input and checks | Atomic result or safe rejection |
|---|---|---|
| Start coordinated work | Current operator/enrollment; command ID and canonical request digest; confirmed eligibility/grant; qualified server-selected profile | In one shared PostgreSQL transaction: Workflow initial event, immutable grant/snapshot references, exclusive repository/TASK claim, command result and dispatch intent. Same actor/command/digest returns the same Workflow; different digest is an idempotency conflict. |
| Inspect execution | Current actor and repository/Workflow read authorization | Authoritative status, phase, owner, attempt generation, remaining limits, readiness/checkpoint age, wait reason and permitted recovery choices. No state mutation. |
| Reconcile preparation | Runner identity, committed effect ID, expected Workflow revision and current lease/fence | Observe/reuse only the exact owned resources/profile. Record verified effects or Needs Human; never create a replacement while an earlier effect is ambiguous. |
| Pause / cancel | Authorized actor, expected revision, reason, exact Workflow | Revoke new broker admission and persist stop intent. Pause retains claim/resources. Cancellation becomes terminal and releases claim only after complete owned-workload quiescence is verified. |
| Resume / takeover | Current authority, same Workflow, expected revision, checkpoint/profile/context and remaining budget | Advance fence, reconcile old effects/processes, then authorize at most one launch. Lease expiry alone neither releases the claim nor proves death. |
| Submit implementation | Current Engineer role/attempt, expected revision, full base/head OIDs, immutable artifact manifest and check results | Validate subject, ancestry, completeness and required evidence. A report is input, not authority; the PHP-owned transition to Awaiting review requires the current authorized coordinator operation. No approval/publication follows. |

The execution Domain owns grant/transition/claim policy. Application coordinates Fight Common's public messaging,
event-store and `TransactionalUnitOfWork` contracts. Persistence adapters use the same DBAL connection for expected
stream-version append, command uniqueness, active claim uniqueness, audit and dispatch intent. A lost response is
recovered by command identity; a uniqueness loser reads the winning command/claim, not a second Workflow. External
Git, VM, provider and queue effects happen after commit. A dispatch retry is at least once, not a new authorization.

`Unauthenticated`, `NotPermitted`, `EnrollmentRevoked`, `SourceUnavailable`, `RevisionChanged`, `Ineligible`,
`ClaimConflict`, `CommandConflict`, `ProfileUnavailable`, `BudgetExhausted` and `EffectUncertain` describe distinct
safe outcomes. Reveal another Workflow owner only to an actor allowed to inspect it. Database or broker unavailability
fails closed. An unsupported profile rejects start without dispatch; temporary Runner absence may leave a valid,
qualified start queued. Neither case permits unsandboxed work.

## Provisioner and role isolation

The installation-owned provisioner alone holds host sandbox/engine authority. Its input is a persisted effect ID,
Workflow/role owner, generation, approved source digest and profile ID. It resolves limits, images, paths and network
rules server-side. Reject arbitrary host command strings, worktree-authored host Docker/Compose policy, host mounts,
mutable VM bootstrap images, host namespaces/privilege flags and unregistered owners. Return observed IDs, effective
policy and bounded readiness evidence; names/labels alone are not ownership proof.

On 2026-09-27 John approved Engineer sudo/root and private Docker-engine access inside its dedicated mountless VM.
The entire guest is Engineer-controlled, including privileged containers, Pi, runtime/Harness files, guest cgroups,
Git objects and check output. Non-root/read-only/no-private-engine requirements are superseded for this profile;
the VM/hypervisor and external control plane, not an inner container or trusted guest process, enforce isolation.
Guest root may administer its own Docker resources but cannot reconfigure host VM policy or create host/sibling VMs.
Pinned input digests attest what was supplied, not continuous guest integrity. Local tool restrictions are advisory;
external tool permissions, provider limits and acceptance remain enforced by the host authority.

Never put Team Lead, Engineer or future reviewer authority in the same Engineer-writable boundary. Allocate distinct
role boundaries and grants; hand off only immutable, bounded artifacts through the host authority. A role switch
requires a new authorized launch binding, never an in-session prompt. Canonical refs, broker credentials, independent
check receipts and acceptance remain outside the Engineer boundary under the approved private-engine amendment.
Worker check output is untrusted evidence until the appropriate independent phase validates it.

## Broker authority and cancellation

Public Pi placeholders are compatibility input, never credentials. The host proxy/broker must authenticate an
unforgeable launch binding established by the provisioner, not worker-supplied Workflow/role/account headers. Any
host-injected scoped credential authenticates only that VM binding; it cannot authorize arbitrary provider requests.
A sibling VM presenting identical placeholder bytes must still be denied. Missing substitution must deny, including
when a global provider credential exists. Only the protected broker can contact the selected upstream with real tokens.

For each request the broker checks current grant, Agent credential/permission revisions, Workflow state, role,
profile, Pi session, attempt generation and budget. It derives provider URL/account headers; allowlists method/path,
model/reasoning and tool capabilities; bounds encoded and decoded payloads, concurrency, stream bytes and duration;
and reserves cost/token budget before dispatch. Unknown cost or a lost response consumes a conservative reservation
until reconciled; do not refund uncertain usage as zero. Retries cannot bypass limits or silently change provider.
Dependency traffic is a different allowlist/cache capability with no model credentials; deny redirects to arbitrary
hosts, local/private/metadata addresses and unapproved registries. Prefer offline verified lockfile artifacts.

Revocation closes admission immediately and requests termination of all active streams for that binding. Set and
measure an installation-owned maximum termination interval before declaring the profile ready. An upstream may
already have processed/charged a request; record that uncertainty rather than promising rollback. Halt/pause/cancel
cannot be declared quiescent from deletion of a substitution secret. If termination or provider state is uncertain,
retain the claim and show Needs Human. Host renewal of OAuth material is serialized and secret-safe; worker placeholder
renewal must revalidate its live launch binding. No real access/refresh token or account secret goes into a worker.

The [VM-boundary probe](DOCKER_SANDBOX_QUALIFICATION.md#engineer-controlled-vm-and-broker-probe) now demonstrates
that removing a scoped substitution secret does **not** stop an admitted stream. The external fixture stopped its
own stream explicitly; this is not measured production cancellation. VM-scoped canaries distinguished identical
placeholders from two VMs, but durable role/attempt/Agent authorization and real-provider renewal remain unqualified.

## Git and resource admission

Keep one private Git store per Workflow, seeded from approved immutable objects. Export one named handoff ref plus
exact base/head and byte digest. The trusted importer first enforces regular-file containment and byte limits, then
validates in a fresh disposable quarantine with scrubbed environment, no hooks/global config/replacements, no network,
no checkout/filters/submodules and strict object checks. Seed prerequisites only from approved objects; verify exact
refs, object types and ancestry before any canonical ref update. Use compare-and-swap against the approved destination
OID under the Git administration lock. Reject unrelated history, extra refs, malformed input and resource excess.
Treat imported tree content as untrusted even after structural verification; never execute it on the host.

The [Git probe](DOCKER_SANDBOX_QUALIFICATION.md#controlled-git-probe) establishes only the listed small-fixture
checks. Compressed size is not an expansion bound. Production quarantine needs enforced CPU/memory/PID/disk/time,
object-count/expanded-byte limits, path/tree validation and timeout recovery outside canonical storage.

Host admission must reserve worst-case logical writable disk capacity for every active **and paused** VM, plus
bounded template/cache/log/export overhead and a host safety reserve. Guest free space does not reduce a reservation.
Serialize reservation with creation intent; reconcile orphan allocations and refuse new VMs if ownership/accounting
is uncertain. A free-space sample alone is not a hard quota and must not qualify the profile. Apply separate caps to
broker streams, logs and exported evidence; workers cannot select them. Retirement releases reservations only after
owned processes and backing files are proved absent. Keep interrupted/failed evidence under retention policy.

For the pinned local template the observed writable disks are 20 GiB workspace plus 2 GiB Docker per VM. Reserve
at least 22 GiB per retained VM, not its currently allocated sparse blocks; three Workflows retaining both Lead and
Engineer VMs would require at least 132 GiB **before** immutable caches, logs, artifacts and safety reserve. The
fresh two-VM sample had 44 GiB logical backing but only 1,667,997,696 allocated bytes. Neither figure is an enforced
aggregate host quota. Inventory reservations, cache/retention caps and crash recovery are still unimplemented.

Externally bound vCPU/memory/disk allocation, host VM/proxy process resources, network/request rates, disk I/O and
export/log volume. Guest root may remove guest PID/cgroup controls; their absence is not a profile failure now, and
their presence is not proof of a host limit. Qualification must show bounded host impact and sibling availability
under controlled guest stress without exhausting the actual host. Stop new admissions on unknown allocations or
resource exhaustion, retain ownership/claims and use external VM stop for quiescence; guest acknowledgments alone
cannot prove it. No process/fork bomb or host-capacity exhaustion is authorized by this requirement.

## Required closeout

The [qualification decision table](DOCKER_SANDBOX_QUALIFICATION.md#qualification-closeout) is the authoritative
summary of observed versus missing proof for this TASK. The Engineer-controlled VM choice is approved, but before
implementation begins resolve enrollment/catalog/secret-store integration and controlled Markdown publication. Before any
live launch, also qualify real-provider host-only OAuth/renewal/cancellation, complete resource accounting, denied
network/provisioning paths and the production PHP lifecycle. Native Linux is unqualified and must be unavailable;
this local macOS evaluation cannot certify it. No product tests or PHP startup implementation were added here.
