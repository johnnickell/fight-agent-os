# Proposal: Recoverable Agent credential operations in Fight Access Control

**Status: proposed upstream contract, not implementation authority.** John approved this direction on 2026-09-27
for [TASK-00138](../../planning/tasks/00138-TASK.md), independent review finding R1. This document prepares a
separately scoped package handoff. Upstream acceptance, EPIC → TICKET → TASK planning, implementation, release
and Agent OS adoption remain separate. No upstream record ID or release version is allocated here.

## Outcome and scope

An authorized consumer can retry Agent provisioning or rotation after losing a response and recover delivery of
that operation's credential to its originally authorized protected destination, without another Agent, another
rotation or unrestricted secret retrieval. Access Control owns operation correlation and credential/delivery
invariants; consumers own caller authorization, persistence adapters, encryption keys, protected storage and runtime.

This proposal does not implement Agent OS enrollment, change its permission catalog, select a host secret store,
settle Markdown publication, qualify a sandbox/broker or unblock TASK-00139. R1's remaining prerequisites and
R2–R3 stay open. No vendor patches, display-name correlation, nested transactions, internal-package calls or
consumer replicas of package lifecycle policy are permitted.

## Upstream inspection and duplicate-work check

Inspected the clean local `fight-access-control` checkout on 2026-09-27 at `develop`
`60e2ff7aeb77e89a661bb6cc4230b3eb27847dae` ([pinned tree][upstream]). No fetch or remote issue/PR inventory was
performed; these findings concern that local source and its live/archive Markdown planning, not unseen work.
Agent OS still locks v0.3.0 at `22ffab6452b2278b82df9b147e8949630b97355f`.

| Inspected boundary | Finding |
|---|---|
| [AgentProvisioningService][provision], lines 51–86 | Accepts actor/name only; generates Agent and credential IDs inside its Unit of Work, commits Agent plus audit, triggers `AgentProvisioned`, then returns the raw-secret result. No caller operation identity. |
| [AgentCredentialLifecycleService][lifecycle], lines 52–105 | Rotation checks an expected credential ID and uses repository compare-and-replace, but has no operation correlation or delivery recovery. Event dispatch also precedes return, after commit. Both services are byte-identical to the installed v0.3.0 files. |
| [AgentRepository][repository] | Current upstream adds Permission-tier contracts compared with v0.3.0, but still has no operation-key correlation. ID/credential lookup and paginated enumeration do not identify a lost provisioning result. |
| [Provisioning tests][provision-tests] | Cover issuance, safe output, rollback/failure and post-commit ordering. They do not supply a lost-response retry contract or a provisioning success-event-publication-failure recovery test. |
| [Lifecycle tests][lifecycle-tests] | Cover rotation, stale replacement, revocation and failure paths; a revocation event-publication-failure test exists. It is not provisioning/rotation response-loss recovery proof. No tests were executed during this inspection. |
| [ADR 0004][adr], [WF-002][wf], TICKET-00002, completed TASK-00019/00020/00025 | Existing contract returns raw material once, permits unwrapping for authentication, and leaves delivery to consumers. Agent names are non-unique. Recoverable delivery requires an explicit amendment, not reinterpretation of these completed records. |
| [TICKET-00007][delivery], EPIC-00006, completed TASK-00037–00039 | Recoverable delivery covers activation, password reset and email change only. Reuse its public patterns where suitable; do not claim Agent HMAC delivery already exists or append this scope to completed work. |
| [Board][board], EPIC-00008 and live/archive planning search | Board has no active/ready TASK; TASK-00035 concerns MCP authorization. TASK-00041–00045 are recorded done and concern Permission tiers, not issuance recovery. Parent closeout/release state is separate. No matching Agent operation-recovery work was found in the inspected planning. |

**Source-derived failure:** both issuance services catch exceptions around transaction, post-commit dispatch and
return. A success-event dispatcher failure can produce failure evidence and throw after the Agent mutation committed.
A repeated provision can then create another Agent; repeating rotation with its original expected credential ID is
stale and cannot recover the lost secret. This is a source trace, not a new executed regression receipt. A consumer
must not infer rollback from an exception or use events as the sole outcome/delivery authority.

## Required public contract

Names below describe use cases and fields, not approved PHP class names. Upstream maintainers should fit them to
existing public services and aggregate ownership rather than add a parallel identity subsystem.

### Caller-scoped, request-bound operation identity

- The caller generates an opaque operation ID with the owning ID factory or a UUID library and retains it before
  invocation. Package uniqueness is scoped to a trusted consumer/authority namespace and authenticated caller
  type plus stable caller ID. The scope is supplied by the authorized application boundary, never accepted from
  an untrusted payload. Operation IDs, Agent IDs and display names are not authentication or recovery capabilities.
- Authenticate and authorize every invocation, status read and delivery recovery, including retries/no-ops, against
  current caller, target and destination authority. Consumer policy owns those decisions at every entry path;
  the package enforces exact recorded scope/request matching. A worker requires an explicit, current delegated
  delivery authority for that scope, not a claimed actor string or possession of an operation ID.
- Bind the scoped ID to a package-canonical, versioned request: operation kind; normalized Agent name for provision;
  target Agent and expected credential ID/revision for rotation; immutable authorized delivery-destination identity
  and revision; and any other outcome-affecting options. The package computes the comparison/digest rather than
  trusting a caller-supplied hash. No secret belongs in this request or its digest inputs.
- Same scope/ID and same request resolves the original committed outcome. Different request, kind, target,
  predecessor or destination under that key is an operation conflict with no mutation or credential exposure.
  Another caller cannot discover or recover the first caller's outcome without explicit delegated authority for
  that original scope. A worker authenticates as itself; delegation selects the recorded originating scope, never
  rewrites it to the worker's ID. Equal opaque IDs in different scopes are separate operations, not cross-caller
  deduplication.
- Preserve the original rotation request on retry; never replace its expected predecessor with the current
  credential to make the retry succeed. A genuinely new rotation needs a new operation ID and fresh authorization.
- Define bounded input sizes, outstanding-operation admission and delivery retention. Retain a secret-free
  operation outcome/tombstone so expiration or cleanup cannot turn an old committed key into fresh issuance.
  Exhausted operation storage fails closed; silently evicting deduplication state is not acceptable.

### Atomic committed outcome and safe status

One package-owned Unit of Work commits the scoped operation identity/request binding, Agent mutation, credential
identity/revision, encrypted delivery material/reference, recoverable delivery state and safe audit evidence together.
All participating consumer repositories use the same transactional connection. This is not an outer consumer
transaction wrapping the currently transactional package services or an audit-adapter correlation side effect.

Concurrent identical attempts have one committed winner. Unique-key/CAS losers resolve that committed outcome after
rollback; they cannot leave a second Agent, audit mutation or credential generation authoritative. An uncertain
commit returns an indeterminate safe outcome; retry/query uses the same operation identity after storage recovery,
never guesses from missing events or attempts another ID automatically.

Expose secret-free operation status with original Agent ID, credential ID/revision, issuance result and separate
current delivery/lifecycle disposition. Distinguish pending delivery, delivered, superseded, revoked, expired,
retryable storage/key-access failure, terminal delivery failure and indeterminate outcome. A superseded operation still identifies its original credential;
it never resolves to the replacement secret. Status and retry responses expose no raw secret, ciphertext, key-store
path, provider error or general decryption handle.

### Delivery identity, ordering and receipt binding

These identities have different lifetimes; none is an authentication capability:

| Identity | Owner and invariant |
|---|---|
| Scoped operation key | Trusted consumer namespace, originating caller type/ID and caller operation ID; binds the canonical request and committed issuance outcome |
| Delivery ID | Package-generated globally unique UUID from the owning factory/library, persisted with issuance; one per committed delivery, unchanged across retries, restarts, claim takeover and key rewrapping; distinct even for equal operation IDs in different scopes |
| Claim fence | Package-persisted monotonically advancing attempt fence plus opaque claim token and lease deadline; every takeover changes it, but never changes the delivery ID or external idempotency key |
| Credential identity/order | Original Agent ID plus credential ID/revision; rotation advances the Agent revision across different operation IDs; a retired credential never becomes current again |
| Destination binding | Server-registered protected slot identity and immutable binding revision, scoped to its consumer authority; not a caller URL/path. Binding changes require fresh authorization and a new operation |
| Destination write order | Strictly increasing version allocated under the slot's transactional fence for each new delivery across all operations/callers using that slot; retries retain it. Rebinding/reuse must not reset ordering or erase its high-water mark |

Issuance atomically reserves the destination write version and records the delivery ID, original credential tuple
and destination binding/order with the operation. A slot has one authoritative owner/credential binding; assignment
or reassignment shares its fence with issuance and delivery admission. Agent revisions order credentials of one
Agent; destination versions order writes even when different Agents or caller scopes target the same slot. UUIDs,
claim fences and scoped operation IDs are not substitutes for either ordering. Consumer persistence implements
these package contracts on the same connection; the package does not infer ordering from sink arrival time.

The sink's external idempotency key is the globally unique **delivery ID alone**, not a claim token or unscoped
operation ID. It binds that key to the exact Agent/credential ID/revision, destination identity/binding revision,
destination write version and original secret bytes. Reuse with a different tuple or bytes rejects without writing.
An opaque, non-secret durable receipt attests that exact binding; it is not a secret-read handle or permission to
activate. A mismatched, missing or unverifiable receipt cannot complete delivery. Do not expose a secret digest
in public receipts or status merely to compare bytes.

The sink atomically stages immutable versioned entries and advances a per-slot high-water mark without lowering it.
An older version arriving after a newer one cannot overwrite, select or reactivate material; an existing identical
entry may return its original receipt without changing the selected version. Equal versions with different bindings
reject. Keep deduplication/high-water tombstones across cleanup so delayed calls cannot recreate retired entries.
A newly committed package rotation may not yet be known to the sink: a late predecessor write can then only stage
inert bytes. The consumer must select the exact currently authorized package/destination tuple, never the sink's
latest-arrival pointer. No receipt alone activates enrollment or authorizes signing. Unsupported sinks fail closed.

### Atomic authorization and sensitive-invocation admission

Consumer policy owns caller, target, delegation and destination authorization. The package coordinates its checks
through an injected consumer-implemented authorization capability, not Permission names, a supplied boolean, an
actor string or an outer consumer transaction. The capability participates in the **package-owned** Unit of Work
on the same transactional connection and neither begins nor commits a transaction. It reads authoritative consumer
records and holds their authorization/version fences until package commit. Consumer authorization mutations, grant
revocation, destination reassignment and all package credential mutations must honor those same fences.

A worker claim only reserves work; it is not permission to decrypt. Immediately before sensitive materialization,
one short package transaction validates and records admission for the exact delivery and current unexpired claim:

- The authenticated invoking caller, original operation scope and any current delegation permit this exact target
  and destination. Record the authorizing revisions/epochs and expiry, distinguishing worker from originating actor.
- The destination owner, binding revision and reserved write version remain authoritative.
- The exact Agent credential ID/revision is active, the delivery is pending and within retention, and no terminal
  cancellation or supersession has won.

**The successful admission commit is the linearization point.** Read authority and trusted time at the final
admission decision; the recorded deadline is the earliest claim, authorization and delivery expiry. Do not invoke
or materialize on an uncertain admission commit: resolve its durable outcome first and re-admit if necessary under
a fresh valid claim. Issuance uses the same authorization participation in its mutation transaction; for rotation
it checks the current predecessor there. Authorized same-key resolution reads the recorded result instead of
reapplying predecessor mutation checks. Status/discovery reads also enforce current scope authority and never
reveal another scope through conflicts or existence checks.

This recommended model requires transactional consumer authority. An external identity/permission provider cannot
be represented by an unfenced cached allow decision. The consumer must supply an authoritative local grant whose
mutations participate in this protocol, or leave the integration unsupported pending a separately accepted protocol.
Network authentication may precede the transaction; live command-critical authority must still be fenced inside it.

If revocation/reassignment/supersession commits before admission, admission denies and no secret is materialized.
If it commits after admission, an already admitted materialization or external call may finish and stage bytes at
only the fixed sink; database revocation cannot recall bytes or atomically stop a remote call. Check the recorded
deadline after admission and before materialization/invocation; an admission already expired when commit returns
cannot start either. Expiration during an in-flight call may still allow inert staging. A check just before calling
is an additional guard, not the ordering authority or a cross-system rollback guarantee.

Acknowledgement requires fresh transactional validation of the exact claim and admission's authorizing revisions/
expiry, current credential and destination. An old claimant cannot acknowledge after takeover, revocation, expiry
or authority change, even if a permission is later regranted. A new authorized claim may reconcile an existing
receipt under a new admission; it cannot make an obsolete credential/destination current. Enrollment activation
requires confirmed delivery of that exact tuple and a fresh consumer authorization transaction, not an old receipt
or delivery admission reused as permission. The broker checks current consumer use authority and exact package
credential/destination authority at each use. Staged bytes
and late receipts are unusable when that authority has expired or been revoked; neither a receipt nor a once-valid
admission grants continuing access. Already admitted external effects are not promised reversible. Bound cleanup
of inert retired entries and retain safe fence/correlation evidence.

### Credential-delivery recovery — recommended design for upstream acceptance

**Recommend package-owned recoverable delivery to one consumer-provided protected sink, not repeated raw-secret
returns.** Amend ADR 0004/WF-002's issuance-only return boundary explicitly. Preserve one active credential,
immediate rotation and terminal revocation. Use a public sensitive invocation port and secret-free operation/status
results; do not add `getSecret(agentId)` or make an operation-status query return credentials.

1. Commit encrypted, operation-bound delivery material and the identities above with issuance. Unavailable
   encryption/key access before commit rolls everything back. The delivery copy is distinct in lifetime and
   cryptographic binding from the Agent authentication envelope; neither has a general consumer-facing read query.
2. Claim and admit through the protocol above, then invoke the protected sink outside database transactions.
   During recovery, raw material exists only in a short-lived non-serializable sensitive invocation, never a
   model/VM/ordinary response.
   Bind encryption integrity to the delivery/credential/destination tuple; swapped or corrupt material fails closed.
3. A lost sink response or lost outcome commit retries the same delivery ID and original secret bytes. Reconcile
   durable receipts before unnecessary sensitive invocation where supported. Competing admitted calls may repeat
   the external effect: this is at-least-once delivery, not exactly-once secret disclosure. Claim takeover does not
   change the external key; a stale receipt cannot complete the new claim without its own authorized reconciliation.
4. Commit delivery completion only for the exact current admitted claim and verified receipt binding. Retire the
   delivery ciphertext on acknowledged completion or terminal expiration/revocation/supersession; keep safe
   correlation. The Agent authentication envelope follows its existing lifecycle, not delivery-copy cleanup.
5. Unavailable protected storage or transient decryption/key access after issuance leaves bounded, discoverable
   pending/retryable work. Preserve ciphertext and key-version metadata until recovery or terminal retention;
   never regenerate the secret or use the authentication envelope as a delivery fallback. Corrupt ciphertext,
   permanent key loss or exhausted retention yields safe terminal failure requiring human reconciliation. Cipher
   adapters return sanitized classifications, not arbitrary errors. Key rewrapping preserves delivery identity,
   binding and original plaintext; key retirement must account for every pending copy.
6. All rotation/revocation paths atomically fence/cancel outstanding predecessor delivery with the Agent mutation.
   Missing sink/decryption access cannot undo committed issuance, restore the predecessor, emit raw fallback
   material or mark enrollment ready. Preflight availability is advisory, not delivery proof. Overlapping admitted
   effects and unusable late receipts follow the authorization and sink-ordering rules above.
7. Recovery after acknowledged delivery is a safe receipt/status lookup, not renewed secret access. If the sink
   subsequently loses retained material or terminal retention expires, fail closed for human reconciliation; an
   authorized new rotation is a new explicit operation. Revoked Agents stay terminal: a new Agent needs separately
   authorized provisioning, not revival or automatic retry with a different ID.

### Breaking replacement and consumer migration

**Recommend a separately versioned breaking replacement of legacy raw-return issuance APIs**, not an additive
non-recoverable escape path. Upstream chooses the release number/SemVer classification and accepts the ADR amendment
separately; no release version is allocated here. Provision and rotation require the new operation/delivery contract
and return safe metadata. Remove or explicitly reject old signatures rather than silently inventing operation IDs,
destinations or authority. Every retained public rotation/revocation entry point, including direct non-HTTP calls,
must enforce the shared admission fence, atomic cancellation and terminal lifecycle invariants. A retained signature
is not permission to keep an old mutation implementation. Agent OS adopts only the accepted released public contract.

Existing Agents keep their current authentication authority and envelope; upgrade alone neither rotates nor
redelivers. Mark the absence of historical operation correlation explicitly as legacy/non-recoverable. **Never
manufacture operation, request, delivery or receipt bindings for old credentials**, infer them from names/audit rows,
or copy their authentication envelope into delivery storage. For a known active Agent, recovery needs an explicitly
authorized new rotation against its current credential. An ambiguous historical provisioning result needs human
reconciliation, not automatic provisioning. Revoked Agents remain terminal. Backfilling safe schema/version markers
is not backfilling a recovery outcome.

Required consumer deployment order:

1. Inventory active/legacy Agents, pending work, keys and all lifecycle/authorization writers. Rehearse backup and
   restore plus the owning migration tools; consumer adapters/schemas remain consumer-owned, not package production
   Adapter code. Quiesce and fence old issuance, rotation/revocation and delivery workers before switching contracts;
   drain or fence already admitted calls. Maintenance must also prevent old authority writers bypassing new fences.
2. Apply additive storage for operation/request versions, delivery identities, receipts, claims/admissions and
   destination/authorization fences, with uniqueness and version constraints. Preserve existing Agent envelopes.
   Install compatible repositories, cipher/sink/authorization adapters on one connection and qualify conformance
   before enabling the replacement package path. Missing storage/capabilities reject startup/admission, not fallback.
3. Switch every affected writer and worker as one controlled compatibility cohort. Mixed legacy/new lifecycle or
   delivery writers against the same authority are unsupported; persisted contract-version checks must deny old or
   incompatible consumers, including restarted workers. Fence retired writer credentials/access at the storage or
   trusted admission boundary; a version check implemented only in new code cannot stop an old binary. Deployments
   spanning canonicalizer versions must all read historical versions and agree on the version for new keys.
   Enable scheduled discovery, then admission only after
   migration, sink fencing, key access and contract-version readiness are verified. No zero-downtime claim is made.
4. After the first new-contract mutation, do not roll back to legacy writers or drop operation/deduplication/fence
   records. Prefer forward repair. Rollback is allowed only to a release preserving this exact persisted contract,
   historical canonicalizers and fences, with writers quiesced. Restoring an older database alone is unsafe: sink
   writes/receipts may already exist. Recovery must reconcile those external effects and preserve tombstones/high-water
   marks before admission; otherwise remain unavailable for human reconciliation. Before any new-contract effects,
   an exclusively fenced legacy cohort may resume only after proving no new state/external effects exist.

Persist each operation's canonical-request version and sufficient secret-free canonical request to compare retries.
Look up the authorized scoped key **before choosing a canonicalizer**: existing keys use their recorded version,
including tombstones, not today's normalization. Preserve old readers/canonicalizers for the retained key lifetime;
never rewrite a binding/digest with newer rules. Unknown versions fail closed without mutation, secret access or
falling through to new issuance. Only previously unseen keys use the cohort's selected creation version. An old
request that was equivalent remains equivalent; a conflicting request remains conflicting after upgrade. A package
upgrade cannot silently change destination meaning or make a completed/expired key reusable.

### Post-commit event-publication failures

The durable operation/delivery records, not event delivery, determine success and recovery. A post-commit publisher
fault must not relabel the issuance as rolled back, discard pending delivery or cause the same operation to issue
again. Recommended public result: committed operation metadata with a safe publication warning; pre-commit failures
remain failures. If upstream retains throwing after commit for compatibility, document the ambiguous response and
require the same-key public resolution path. Never include secret-bearing results or arbitrary exceptions in events.

Retries do not repeat the Domain mutation or its audit as new facts. Any notification retry must retain a stable
fact identity and documented duplicate semantics. Scheduled operation/delivery discovery must recover when success
and failure event publication both fail; this scope does not require a new generic event bus/outbox framework.

## Required behavior evidence before package acceptance

These are future owned behavior/integration tests, not tests implemented or run by this proposal. Use package
Domain/Application tests and consumer-bindable persistence/sink conformance contracts, not tests of wiring or wrappers.
Generate fixture identities with the owning ID factories.

| Scenario | Required observable outcome |
|---|---|
| Normal provision and rotation | One committed Agent or one credential revision advance, exact operation correlation, audit and discoverable delivery; no raw response secret on the recoverable path. |
| Commit succeeds, response is lost, then caller/service restarts | Retry original provision key returns the same Agent/credential; retry original rotation request returns its committed successor even though the predecessor is no longer current. No second mutation or secret generation for the retry. |
| Indeterminate issuance commit, for both provision and rotation | Inject connection loss at commit with separate committed and rolled-back outcomes. Return safe indeterminate status, then reconcile on authoritative storage after restart with the same scoped key. Committed means original outcome/no second mutation; rolled back means no partial state and at most one later issuance under that same key. No automatic new key or inference from missing events. |
| Indeterminate admission or delivery-outcome commit | No decryption/invocation on unconfirmed admission. Resolve durable state; an absent/expired admission requires fresh authorization. An outcome that committed does not redeliver; an outcome that rolled back reconciles the same delivery ID/receipt under a current claim. |
| Success event publication throws after commit, for both provision and rotation | Persisted outcome and delivery survive; same-key retry/status resolves them. Also fail failure-event publication; it must not destroy or disguise the recoverable outcome. Assert committed state, not just exception type or call order. |
| Both publishers fail and caller never retries | Terminate the initiating process after committed provision/rotation, with neither publication working. A restarted authorized scheduler discovers and delivers pending work and expired claims without caller retry or an event; mutation/audit remain single facts and notification retries retain fact identity. |
| Pre-commit audit, encryption or persistence failure | No partial Agent/operation/delivery/audit survives; retry cannot encounter a false committed success. |
| Same key, changed name/target/predecessor/kind/destination | Conflict with no partial mutation, generation or disclosure. Test canonical equivalent requests separately. |
| Wrong caller/namespace, revoked authorization, expired delegation | No access to another operation or secret; retries, status and discovery cannot bypass current authorization. An explicitly delegated worker authenticates as itself and resolves only its authorized original scope. |
| Equal opaque operation IDs across scopes at one sink | Distinct globally unique delivery IDs prevent receipt/secret reuse across callers/namespaces, even at equal claim counts. Unauthorized slot ownership rejects; authorized sequential reassignment advances destination write order, not arrival order. |
| Caller/delegation/destination authority changes around admission | Interleave revocation, permission removal, delegation expiry and destination reassignment before claim, between claim and admission, before materialization, during invocation and before acknowledgement/activation. Pre-admission loss denies with no materialization; post-admission loss permits only inert fixed-sink effects, no stale acknowledgement/activation/use. Exercise expiry during the call and revoke/regrant ABA with changed epochs. |
| Authorization and lifecycle writers share the transaction fence | Real consumer conformance proves issuance/admission versus each authority mutation has one ordered winner, including non-HTTP entry paths. Capability failure rolls back admission/mutation; no nested transaction or external cached allow grants admission. |
| Concurrent identical and conflicting requests | One committed winner per scoped key; losers resolve or conflict. Different operation IDs racing rotation against one predecessor produce at most one successor. |
| Store unavailable; store accepts then response is lost; delivery outcome commit fails | Pending/uncertain work remains recoverable, same sink key is reused, and delivery does not report success without a durable receipt. No automatic issuance/rotation or fallback secret output. |
| Concurrent delivery retry and lease takeover | Delivery ID/bytes stay stable while claim token/fence advances; expired or replaced claims cannot acknowledge. New authorized admission can reconcile an existing matching receipt without granting authority to the stale claimant. |
| Different operations deliver out of order | Delay an admitted predecessor until a newer credential/destination version is committed, and separately until the sink has accepted the newer version. Old arrival cannot overwrite/select newer material; before sink learns the new version it can only stage inert bytes that broker authority rejects. Cover rotation to another slot and authorized slot reassignment across Agents/scopes. |
| Receipt or idempotency binding mismatch | Swap delivery IDs, credential IDs/revisions, destination identities/binding revisions/write versions or secret bytes. Sink rejects key reuse with changed binding/bytes; package rejects wrong/missing/unverifiable receipts. No delivered outcome, secret read or enrollment activation follows. |
| Revocation/supersession before and during invocation | No new admission for retired credentials; late sink completion cannot activate enrollment, revive authority or acknowledge stale work. Original operation reports its own retired outcome, never a newer secret. |
| Delivered-secret loss, retention expiry and replay after tombstoning | Safe terminal status; no unrestricted re-read, revived old delivery or duplicate issuance. Replay delayed sink calls after cleanup and prove deduplication/high-water marks survive. Recovery requires separately authorized action. |
| Decryption/key access fails after issuance | Temporary failure retains discoverable ciphertext/key version and safe retry state; restored access delivers the same bytes/ID. Permanent key loss, corrupt/swapped ciphertext and retention exhaustion fail closed for reconciliation, without authentication-envelope fallback or new issuance. Rewrapping/key rotation preserves pending work and identity. |
| Every retained lifecycle entry point races delivery | Rotate/revoke through each public/direct path before admission and during an admitted call. All paths fence/cancel predecessor delivery atomically; retired credentials cannot acknowledge or activate, regardless of event delivery. Old raw-return signatures reject rather than generate implicit operation bindings. |
| Existing-Agent upgrade and incompatible consumers | Existing active credentials still authenticate without manufactured recovery bindings/delivery; only an authorized new rotation creates recovery state. Revoked Agents stay terminal and ambiguous historical provision needs reconciliation. Missing contracts and incompatible/restarted old workers cannot mutate new authority. |
| Canonical-request versions across upgrade/restart | Retry stored and tombstoned old keys with equivalent and conflicting requests after normalization changes. Recorded-version equality/conflict remains unchanged; unknown versions deny, never issue. Compatible workers agree on new-key creation version; incompatible cohorts are fenced. |
| Rollback/restore after external acceptance | A pre-delivery database snapshot cannot authorize duplicate issuance, reset destination order or resurrect a retired key after the sink has accepted bytes. Compatible recovery reconciles existing receipts/tombstones; an incompatible rollback remains unavailable. |
| Safe representations and sensitive-value lifetime | No raw/ciphertext credential in serializable messages, status, events, audit, debug output or failure diagnostics; sensitive invocation only reaches its fixed destination. |

Unit tests must assert persisted outcomes and relevant interleavings. Real consumer-adapter conformance must prove
transaction atomicity, uniqueness and generation fences under concurrency; an in-memory fake alone is not proof of
PostgreSQL behavior. Preserve failures and distinguish package tests from later Agent OS adoption evidence.
Validate actual schema deployment/rollback and mixed-version rollout with owning migration/deployment tools, not
product tests of migration files or configuration. Product tests cover the observable compatibility, authority and
recovery behaviors above; direct rehearsals prove deployment order and restoration safety.

## Proposal-review disposition and remaining decisions

This revision responds to the independent **proposal-only** review of checkpoint `ff3e0f1`, not the canonical
TASK-00138 review. John requested these revisions; disposition below means addressed in the proposed contract,
**not independently accepted, implemented or verified at runtime**. Another reviewer must challenge this checkpoint.

| Finding | Disposition in this proposal | Required next evidence |
|---|---|---|
| 1 — Delivery identity versus claim and cross-operation fences | Separated globally unique immutable delivery ID, changing claim fence, Agent credential revision and destination write order; bound receipts and specified immutable staging/high-water rules | Cross-scope collisions, lease takeover, mismatched receipts and late different-operation writes in package/sink conformance |
| 2 — Atomic caller/delegation/destination admission | Defined package-owned admission commit with consumer authorization participating on the same connection; declared deadlines, revocation ordering, inert late effects and fresh acknowledgement/use authority | Real authorization-writer/admission interleavings, expiry and revoke/regrant races, including non-HTTP callers |
| 3 — Legacy coexistence and migration consequences | Recommended separately versioned breaking replacement; all retained lifecycle paths fence delivery; prohibited old-credential recovery backfill; specified deployment cohort, rollback limits and recorded-version canonicalization | Upstream compatibility acceptance, behavioral version tests and consumer-owned migration/restore rehearsals |
| 4 — Missing recovery evidence | Added both uncertain-commit outcomes, scheduler-only recovery, post-commit key failure and findings 1–3's interleavings | Future package behavior tests plus real consumer persistence/authorization/sink conformance; no tests are claimed executed here |

**Decisions still requiring upstream acceptance before implementation:** the breaking-release/API amendment and
release classification; the public capability/receipt representation and exact fence participation contracts fitting
existing package ownership; bounded input/admission, claim duration, retry/retention and sink cleanup limits; and the
recommended safe publication-warning result rather than throwing after commit. Their implementations must preserve
the invariants above, not reopen an unfenced compatibility bypass. The transactional local-authority requirement is
a proposed integration constraint; consumers unable to satisfy it remain unsupported unless a separate protocol is
explicitly accepted and qualified. No external-provider consistency or sink capability is assumed from this document.

**Downstream work, not missing proposal implementation:** separately authorized upstream planning, Domain/Application
and public capability changes, behavioral tests and release; then consumer schema/adapters, protected-sink/key policy,
authorization-writer fences, deployment rehearsals and broker/enrollment qualification. Concrete SQL/lock order,
provider selection and rollout commands belong there. Agent OS's other R1 prerequisites and R2–R3 remain open.

## Separate upstream handoff and Agent OS dependency

1. Upstream maintainers review this proposal against the pinned source and recheck live/archive work before
   allocating records. Accept/amend the recovery model and ADR 0004/WF-002 boundary explicitly. Approval of the
   direction is not acceptance of every proposed API or permission.
2. Plan a separately scoped EPIC for recoverable Agent credential operations, then TICKETs and executable TASKs
   through the owning repository's normal operations. Do not reopen completed email-delivery or HMAC work, reuse
   another project's IDs, or silently fold this into the Permission-tier release. This proposal creates no records.
3. Implement and test only after that acceptance and checkout/worktree authorization. Package owns Domain policy,
   Application coordination and public persistence/delivery contracts; no production Adapter/key-vault code.
4. Independently review, document compatibility/migration and qualify a separately authorized package release.
   The current local `develop` is not a tagged release or an Agent OS dependency upgrade.
5. Agent OS then separately qualifies that released public contract, PostgreSQL adapters, protected sink and
   authorized enrollment integration. No Composer/vendor change is part of this proposal. Existing v0.3.0
   enrollment remains unavailable; there is no temporary local substitute.

**R1 dependency:** the package's accepted, implemented, released and consumer-qualified operation/delivery contract
is required for durable enrollment. Preparing or accepting this proposal alone does not resolve R1. Operator
authentication/enrollment, the execution permission-catalog extension, host secret-store selection/composition and
controlled Markdown publication still require decisions and evidence. R2's profile/broker/resource qualification
and R3's root isolation/private-Git/independent-evidence qualification are unchanged. TASK-00138 remains in progress
and not launchable; independent review is still outstanding for any revised implementation.

[upstream]: https://github.com/johnnickell/fight-access-control/tree/60e2ff7aeb77e89a661bb6cc4230b3eb27847dae
[provision]: https://github.com/johnnickell/fight-access-control/blob/60e2ff7aeb77e89a661bb6cc4230b3eb27847dae/src/Application/AccessControl/Agent/Security/AgentProvisioningService.php
[lifecycle]: https://github.com/johnnickell/fight-access-control/blob/60e2ff7aeb77e89a661bb6cc4230b3eb27847dae/src/Application/AccessControl/Agent/Security/AgentCredentialLifecycleService.php
[repository]: https://github.com/johnnickell/fight-access-control/blob/60e2ff7aeb77e89a661bb6cc4230b3eb27847dae/src/Domain/AccessControl/Agent/AgentRepository.php
[provision-tests]: https://github.com/johnnickell/fight-access-control/blob/60e2ff7aeb77e89a661bb6cc4230b3eb27847dae/tests/Application/AccessControl/Agent/Security/AgentProvisioningServiceTest.php
[lifecycle-tests]: https://github.com/johnnickell/fight-access-control/blob/60e2ff7aeb77e89a661bb6cc4230b3eb27847dae/tests/Application/AccessControl/Agent/Security/AgentCredentialLifecycleServiceTest.php
[adr]: https://github.com/johnnickell/fight-access-control/blob/60e2ff7aeb77e89a661bb6cc4230b3eb27847dae/planning/adr/0004-agent-hmac-credential-lifecycle.md
[wf]: https://github.com/johnnickell/fight-access-control/blob/60e2ff7aeb77e89a661bb6cc4230b3eb27847dae/planning/wayfinder/tickets/WF-002-agent-credential-revocation-lifecycle.md
[delivery]: https://github.com/johnnickell/fight-access-control/blob/60e2ff7aeb77e89a661bb6cc4230b3eb27847dae/planning/tickets/00007-TICKET.md
[board]: https://github.com/johnnickell/fight-access-control/blob/60e2ff7aeb77e89a661bb6cc4230b3eb27847dae/planning/tasks/BOARD.md
