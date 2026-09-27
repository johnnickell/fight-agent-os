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
  Another caller cannot discover or recover the first caller's outcome. Equal opaque IDs in different scopes
  are separate operations, not cross-caller deduplication.
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
retryable storage failure and indeterminate outcome. A superseded operation still identifies its original credential;
it never resolves to the replacement secret. Status and retry responses expose no raw secret, ciphertext, key-store
path, provider error or general decryption handle.

### Credential-delivery recovery — recommended design for upstream acceptance

**Recommend package-owned recoverable delivery to one consumer-provided protected sink, not repeated raw-secret
returns.** Amend ADR 0004/WF-002's issuance-only return boundary explicitly. Preserve one active credential,
immediate rotation and terminal revocation. Use a public sensitive invocation port and secret-free operation/status
results; do not add `getSecret(agentId)` or make an operation-status query return credentials.

1. Commit encrypted, operation-bound delivery material with issuance. A server-selected destination binding is
   immutable for that operation and is not an arbitrary caller URL/path. Unavailable encryption/key access before
   commit rolls everything back. The operation's delivery copy is distinct in lifetime from the Agent's encrypted
   authentication envelope; neither is readable through a general consumer-facing query.
2. Package delivery claims are bounded, leased and generation/fence-bound. Recheck current delegation, destination
   and exact active Agent credential before sensitive invocation. Discovery/status remain secret-free. Invoke the
   protected sink outside database transactions; a short-lived non-serializable sensitive value exists only for
   that invocation. No model, VM or ordinary command response receives it.
3. The sink accepts the stable operation/delivery generation as its idempotency key and returns a safe durable
   receipt/reference. A lost sink response or lost delivery-outcome commit retries that same key and exact bytes,
   never overwrites a newer destination version, rotates or creates another Agent. Competing/expired-lease
   invocations may repeat the external call: this is at-least-once delivery, not exactly-once secret disclosure.
   The public adapter contract must require deduplication and generation fencing; unsupported sinks fail closed.
4. Commit a delivered outcome only for the exact current claim, credential and destination generation. An obsolete
   claimant cannot acknowledge or reopen delivery. Retire the delivery ciphertext on acknowledged completion or
   terminal expiration/revocation/supersession; keep safe correlation. The Agent authentication envelope follows
   its existing lifecycle, not the delivery-copy cleanup policy.
5. Unavailable protected storage after issuance leaves recoverable pending/uncertain delivery, with bounded retries
   and safe status. It must not undo a committed rotation, restore its predecessor, expose raw material in a fallback
   response/log or mark enrollment ready. Preflight availability is advisory, not proof that delivery will succeed.
6. Rotation or revocation cancels outstanding delivery for the predecessor under the shared authority fence. If
   those changes commit before delivery admission, no secret is materialized. If they overlap an already admitted
   external invocation, bytes may already have reached the fixed sink; no cross-system rollback is promised. Late
   receipts cannot activate enrollment or overwrite newer storage. The sink stages versioned material inertly,
   and the consumer broker checks current package credential authority before use; retired staged entries require
   bounded cleanup. Qualification must prove these races, not rely on a check-before-call alone.
7. Recovery after acknowledged delivery is a safe receipt/status lookup, not renewed secret access. If the sink
   subsequently loses its retained material or terminal retention expires, fail closed for human reconciliation;
   an authorized new rotation is a new explicit operation. Revoked Agents stay terminal: any new Agent requires
   separately authorized provisioning, not revival or automatic retry with a different ID.

This design intentionally changes the raw-return API for the recoverable path. Upstream must decide whether to
replace the legacy API in a breaking release or add an explicit recoverable path while labeling the legacy one
non-recoverable. Agent OS must use only the accepted recoverable public path. Final port names, retention limits,
fencing/receipt contracts and migration strategy require upstream acceptance before implementation.

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
| Success event publication throws after commit, for both provision and rotation | Persisted outcome and delivery survive; same-key retry/status resolves them. Also fail failure-event publication; it must not destroy or disguise the recoverable outcome. Assert committed state, not just exception type or call order. |
| Pre-commit audit, encryption or persistence failure | No partial Agent/operation/delivery/audit survives; retry cannot encounter a false committed success. |
| Same key, changed name/target/predecessor/kind/destination | Conflict with no partial mutation, generation or disclosure. Test canonical equivalent requests separately. |
| Wrong caller/namespace, revoked authorization, expired delegation | No access to another operation or secret; retries and status cannot bypass current authorization. Test authorized identical opaque IDs in distinct scopes without cross-scope result reuse. |
| Concurrent identical and conflicting requests | One committed winner per scoped key; losers resolve or conflict. Different operation IDs racing rotation against one predecessor produce at most one successor. |
| Store unavailable; store accepts then response is lost; delivery outcome commit fails | Pending/uncertain work remains recoverable, same sink key is reused, and delivery does not report success without a durable receipt. No automatic issuance/rotation or fallback secret output. |
| Concurrent delivery retry and lease takeover | Stable external idempotency key, exact-generation sink fencing and stale receipt rejection; no newer secret is overwritten. |
| Revocation/supersession before and during invocation | No new admission for retired credentials; late sink completion cannot activate enrollment, revive authority or acknowledge stale work. Original operation reports its own retired outcome, never a newer secret. |
| Delivered-secret loss, retention expiry and replay after tombstoning | Safe terminal status; no unrestricted re-read, revived old delivery or duplicate issuance. Recovery requires separately authorized action. |
| Safe representations and sensitive-value lifetime | No raw/ciphertext credential in serializable messages, status, events, audit, debug output or failure diagnostics; sensitive invocation only reaches its fixed destination. |

Unit tests must assert persisted outcomes and relevant interleavings. Real consumer-adapter conformance must prove
transaction atomicity, uniqueness and generation fences under concurrency; an in-memory fake alone is not proof of
PostgreSQL behavior. Preserve failures and distinguish package tests from later Agent OS adoption evidence.

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
