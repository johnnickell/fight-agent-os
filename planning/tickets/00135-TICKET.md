---
id: TICKET-00135
epic: EPIC-00023
title: Correct existing identity, security, and delivery use-case ownership
status: ready-for-agent
---

# Correct existing identity, security, and delivery use-case ownership

## Problem statement

A correct Project aggregate alone would leave the rest of the application inconsistent. Existing invitation,
bootstrap, authentication, password, session, user/catalog, admission, mail and credential-delivery code must have
clear owners, correct entry boundaries and canonical upstream integration rather than command-shaped services,
Console concepts in Domain or misleading injected names.

## Solution and boundaries

- Account for the remaining existing PHP capability families outside the Project correction and shared transaction/
  error owners. Inventory actual Domain/Application/Adapter classes, application entry/composition callers and
  current tests/docs. Assign every boundary to its real capability or another named EPIC-00023 owner; no file may
  disappear into an audit-only or unowned catch-all. Correct findings, not just report them.
- Keep Fight Access Control's business behavior and canonical messages/Views/contracts visible. Use its public
  commands/queries directly where they express the use case; delete local aliases and forwarding services with
  no independent responsibility. Genuine Agent OS-owned intent belongs in its Domain message and registered
  Application handler, not a union-dispatch lifecycle service with a command-shaped name.
- Align existing Console invitation, role-assignment and bootstrap coordination with explicit owned use cases and
  configured bus dispatch. Move Console parsing/options/attribution formatting/output out of Domain. Split input
  helpers that combine transport handling with business outcome policy, leaving each rule with its real owner.
- Apply the same ownership to existing HTTP invitation, session, user/catalog, password and authentication paths.
  Adapters own trusted actor acquisition, operation permission/target-scope admission, validated input mapping,
  dispatch and presentation. Remove redundant Application admission services and ordinary revocation/commit
  serialization expectations without weakening actual aggregate transitions, uniqueness or business consistency.
- Preserve justified synchronous non-serializable secret-bearing package methods. Group actual authentication,
  session, activation, password/reset, CSRF and other owned collaborators by meaningful capability; do not add a
  broad replacement facade or serialize raw credentials merely to send everything through a command bus.
- Keep genuine handler coordination, transaction boundaries and required audit explicit. Retain truthful partial
  outcomes where existing Console operations intentionally commit separate steps; do not claim atomicity by
  wrapping package handlers in a nested consumer transaction. Preserve current recoverable credential delivery,
  purpose separation and external-effect ordering while removing redundant implementation layers.
- Produce safe typed owned read results where a real projection/coordination responsibility exists. Consume package
  Views directly otherwise. HTTP Data/Responders own JSON field mapping; Console owns output/exit behavior.
  Expected absence and internal-fault distinctions follow qualified shared contracts rather than unchecked casts,
  Action-level type assertions or generic local exceptions that hide missing upstream guarantees.
- Correct capability namespaces, descriptive property/local/parameter names, inward dependencies, composition and
  directly affected callers/tests/docs. Use canonical shared registrations and bus pipelines, not parallel service
  graphs or Application/Domain service location. Remove superseded types after replacement verification.

Excludes new administration endpoints, authentication features or delivery providers, implementing pending product
journeys, rewriting frontend/Pi architecture, and copying upstream workflows. [TICKET-00133](00133-TICKET.md) owns
the complete Role audit replacement; [TICKET-00134](00134-TICKET.md) owns Project; [TICKET-00136](00136-TICKET.md)
owns shared failure/error presentation. Frontend callers change only when an affected contract requires it.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Trusted operator invites/bootstrap-assigns or adds an eligible Role | Existing justified owned intents plus package commands through buses | Existing package authority/outcome reads | Actual package/owned committed facts | Preserve retained-state and partial-outcome meanings with Console concerns outside Domain |
| User manages existing invitations/sessions or reads directory/catalogs | Existing owning package or justified owned messages | Existing safe list/detail/status queries | Existing mutation facts; none invented for reads | Adapter admission and direct dispatch, correct scope and safe typed presentation |
| User activates, authenticates or sets/changes/resets a password | Existing synchronous sensitive methods where required | Existing non-mutating reads | Existing redacted/committed facts | Preserve actual security transitions, secret handling, audit and session effects |
| Internal runner recovers/expires credential delivery | Existing package recovery/expiry commands | Existing bounded discovery/status queries | Existing package outcome facts | Preserve committed claims, external invocation ordering and actual recovery without duplicate orchestration |

No new product operation or permission is introduced by this correction. TASK plans must name the exact existing
messages/methods and business guarantees for their capability, not treat this table as a new API specification.

## Validation and permissions

Each actual entry path proves its own authentication and permission/scope admission and supplies trusted identity.
The bus is trusted in-process infrastructure. Do not reintroduce blanket Application/bus permission gates or
revocation serialization under new names. Domain invariants remain Domain-owned; package sensitive contracts and
unknown/internal failures retain their real meaning. Keep secrets out of messages, Views, audit and diagnostics.
Planned MCP/HMAC contracts may be aligned in planning but are not implemented or claimed qualified here.

## Dependencies and sequencing

Use [TICKET-00131](00131-TICKET.md)'s authoritative rules. Consume actual needed canonical contracts from
TICKET-00133 and shared failure mappings from TICKET-00136, with precise per-capability supplier holds rather than
waiting for every upstream refactor. [TICKET-00132](00132-TICKET.md) owns future-plan changes; existing product
TICKETs retain unfinished features. During TASK decomposition, assign coverage of all remaining existing PHP
capabilities explicitly and coordinate shared composition edits once. Start bounded independent corrections when
their own prerequisites are satisfied; this TICKET does not imply a monolithic refactor PR.

## Acceptance and evidence

- The refreshed whole-application accounting assigns every relevant existing PHP boundary to a correction or a
  justified unchanged contract, including supporting services/adapters. Tests and implementation prove corrections;
  a sampled audit or renamed folder cannot establish completion.
- Commands/queries use configured buses and genuine owned handlers; direct package contracts remain visible.
  Console concerns and raw transport representations are absent from Domain; shallow aliases and broad forwarding
  services are removed after verification. Synchronous sensitive use cases remain appropriate exceptions.
- Every supported entry path retains actual validation/authentication/admission and safe output. Redundant inner
  permission gates and solely revocation-race locks/tests are removed deliberately, while important business
  invariants, current authorization inputs and required package transitions remain correct.
- Existing credential/session/password/activation/invitation/bootstrap behavior, partial outcomes, required audit,
  business atomicity and real recovery hold through corrected entry paths. No new grant, endpoint, provider or
  feature is smuggled into a cleanup, and no generic retry guarantee replaces the approved ordinary-create contract.
- Safe typed reads and adapter presentation agree with current callers/docs and shared failure intent. Callers
  document known query-handler results with accurate `@var` annotations; no new ignores or local bus copies.
- Each correction slice owns meaningful behavioral coverage, affected docs, focused checks, canonical gate with
  honest fresh/reused receipts and warnings, independent Review and applicable QA. Validate static architecture,
  composition and generated/tooling concerns with their owning tools, not product tests of source/configuration.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00576](../tasks/00576-TASK.md) | Correct trusted Console invitation assignment and bootstrap | needs-info |
| [TASK-00577](../tasks/00577-TASK.md) | Correct HTTP invitation issuance status and recovery | needs-info |
| [TASK-00578](../tasks/00578-TASK.md) | Correct directory authority catalogs and existing Role administration | needs-info |
| [TASK-00579](../tasks/00579-TASK.md) | Correct authentication session and CSRF ownership | needs-info |
| [TASK-00580](../tasks/00580-TASK.md) | Correct activation and password capabilities | needs-info |
| [TASK-00581](../tasks/00581-TASK.md) | Correct credential recovery expiry and mail ownership | needs-info |
<!-- /planning:children -->

## Decisions and progress

John approved this fifth requirement area to make the whole PHP application conform, then approved six complete
correction slices, their capability-specific supplier holds and orders `13`–`18`:

| Slice | TASK | Outcome | Local blockers |
|---|---|---|---|
| A | [TASK-00576](../tasks/00576-TASK.md) | Trusted Console invitation, additive assignment and Super Admin bootstrap | TASK-00564 |
| B | [TASK-00577](../tasks/00577-TASK.md) | Existing HTTP issuance, status and explicit invitation recovery | TASK-00564, TASK-00576 |
| C | [TASK-00578](../tasks/00578-TASK.md) | Directory projection, authority catalogs and current custom-Role operations | TASK-00564 |
| D | [TASK-00579](../tasks/00579-TASK.md) | Authentication, self-service sessions and CSRF | TASK-00564 |
| E | [TASK-00580](../tasks/00580-TASK.md) | Activation, password acceptance/change and reset | TASK-00564 |
| F | [TASK-00581](../tasks/00581-TASK.md) | Credential recovery/expiry and mail/provider ownership | TASK-00564 |

All six are `kind: chore` and initially `needs-info`, not executable merely because requirements/decomposition are
accepted. TASK-00577 consumes TASK-00576's corrected transport-neutral installation/assignment policy. The other
five do not depend on one another. There is no blanket Project, DBAL, Role-audit, delivery-runner or complete
portfolio/upstream gate; orders do not add unstated technical dependencies or change other TASK priorities.

### Precise supplier holds

The current lock selects Common v1.4.1 (`a482c220d312d2ef7762c3f0d85d9fb76a7021fa`) and Access Control v0.8.0
(`319738255e682d587642b67856039f882b9588d6`). Common v1.5.0 is the released capability baseline to qualify;
its local tag resolves to `06fb5cd3720af9cf8295198f74040d95b64c5155`. This planning update changes no dependency.

John selected Common's existing error categories and safe boundary as the shared approach, with only genuinely
application-specific additions. Remove superseded local workarounds rather than retain parallel classifiers.
TASK-00582 already adopted the HTTP boundary; these slices integrate their feature failures with it, not replace it.
Common's QueryBus intentionally returns `mixed`. Callers use the known query-to-handler result contract and accurate
`@var` annotations, including generic element types. Neither a generic bus redesign nor removal of `mixed` is a
supplier prerequisite. An annotation documents a qualified contract; it does not establish the handler's behavior.

Access Control develop `0878fc24e616a4b777e211b1c237ff720d64403c` contains breaking changes and accepted unfinished
plans. John intends to complete its planned work before the next release; no future version number is selected.
Use these existing supplier owners, then qualify the actual tagged release and consumer behavior:

- TASK-00576: managed-policy and required User/Role/Permission reads; AC TASK-00131/00135/00140.
- TASK-00577: explicit invitation status absence, generation/address/recovery outcomes and intentional failures;
  AC TASK-00133/00137/00138 and related audit work. Resolve the invitation-correction permission-timing mismatch
  described in TASK-00577 before adoption; never satisfy a required port with a no-op.
- TASK-00578: required detail reads, exact page/identity contracts and Role mutations; AC TASK-00131/00132/00140.
  TASK-00573 owns explicit Role audit. Preserve unavailable Agent operation behavior while updating moved contracts.
- TASK-00579: session owner/current-session/page contracts and direct synchronous authentication/session operations;
  AC TASK-00132/00139 and audit TASK-00127. GetCsrfProof needs no upstream bus redesign.
- TASK-00580: intentional activation/password refusal versus internal faults, synchronous secret handling and atomic
  security effects; AC TASK-00139 and related audit work.
- TASK-00581: exact due/expired/status purpose/generation/progress/absence and worker/provider failures;
  AC TASK-00133/00138 and related audit work.

AC TASK-00132's older Common result-validation hold must be reassessed against released `ResultSet::strict()`.
It validates construction-time bounds; AC still owns request/page agreement, exact elements and session scope.
The shared mutable list is not a snapshot. No additional Common feature requirement was demonstrated by this sample.

Each affected TASK owns selection and qualification of actual supported tagged APIs, source/signatures, transitive
requirements, deliberate manifest/lock changes and real Agent OS composition. Recheck supplier availability before
advancing readiness; no future version, sibling change, vendor patch, local bus/result copy, assertion/suppression
or package-update-only prerequisite is authorized. Missing capabilities hold their actual consumers only. Full
shared failure presentation belongs to TICKET-00136, integrated through one error owner rather than duplicated.

### Remaining PHP coverage and shared edits

The following is approved responsibility allocation, not an executed exhaustive audit or permanent per-file backlog.
At implementation, refresh actual source/entry/composition/caller inventories, assign every boundary a primary owner
and correction or justified-unchanged disposition, including additions/moves/removals. Keep file-level evidence under
ignored task `.runs/` folders; combine the six outcomes for TICKET acceptance without a seventh audit-only TASK.

| Primary owner | Existing capability and supporting boundaries |
|---|---|
| TASK-00576 | Console invitation/assignment/Super Admin bootstrap, owned intents/eligibility/evidence/inspection and their actual Console/runtime/persistence/composition callers; no Workspace bootstrap |
| TASK-00577 | HTTP invitation creation/status/retry/resend/correction, invitation mode/limits/evidence, actual correction-authorization integration, feature presentation and composition |
| TASK-00578 | Directory/catalog/custom-Role operations, installation managed policy/admission, unused authenticated-assignment graph, User/Role/Permission/Agent/Feature persistence organization and shared reference/DBAL helpers |
| TASK-00579 | Authentication/access tokens/request-local principals, shared HTTP admission/permission plumbing, self-service sessions/device presentation and session persistence, CSRF, shared HTTP/runtime/private-doc/public-shell support |
| TASK-00580 | Password policy/hash/checker, activation/change/reset entries and guards, validation capabilities/metadata/discovery and affected presentation; not generic field-error classification |
| TASK-00581 | Internal credential runner/options/reports, delivery providers/mail/templates/links/ciphers/clocks/subscribers, grant delivery persistence/hydration/transition/discovery/expiry helpers |
| Existing EPIC owners | TASK-00564 governing rules/callers; TASK-00565–00571 unfinished plans; TASK-00572 generic transaction/ORM selection; TASK-00573 explicit Role audit; TASK-00574 Workspace/reference; TASK-00575 Project; TICKET-00136 generic failures/safe error and field-error presentation |

For shared persistence, the primary structural owner coordinates semantic changes with the real use-case owner:
User/authority/reference structure with TASK-00578, authentication/session consistency with TASK-00579, credential
origin/password semantics with TASK-00577/00580 and grant-delivery lifecycle with TASK-00581. Preserve actual business
fences, audit and credential consistency; remove only solely ordinary permission-race serialization.

Assign shared registration edits once by responsibility: Console/eligibility to TASK-00576, invitation authorization/
limiting to TASK-00577, installation/catalog/reference to TASK-00578, request-principal/session/CSRF to TASK-00579,
password/activation/reset/validation to TASK-00580 and grant-delivery/provider/worker to TASK-00581. Coordinate shared
manifest/lock/composition deltas against the actual selected baseline; first real adoption owns its required change,
not a separate upgrade-only TASK. Do not create parallel service graphs, generic facades or silent feature expansion.

The first adoption of the breaking Access Control release must update every existing affected caller in the same
working candidate, not just the feature that first needs the dependency. Before changing manifest/lock, inventory
removed classes, moved imports, named arguments, repository methods, result/absence shapes, audit bindings and
failure catches across all live entries and fixtures. Allocate necessary edits to the owners above; coordinate
TASK-00573 where its audit contract changes. Include the minimum required compatibility corrections in that adoption
and verify all affected existing features together. Do not leave login, workers or another entry broken until its
later cleanup TASK. This permits only required release adoption changes across owners, not unrelated feature work.

In particular, replace AuthenticationService with the package's direct authentication/session/password owners,
update AgentCredentialOperation imports/contracts, handle explicit CredentialDeliveryAbsent and origin-bearing
status Views, and update error handling together with service calls. Expired/invalid credentials must remain a
sign-in-required outcome rather than a server error; unknown faults remain server failures. Commit/publication
uncertainty must not claim rollback or trigger automatic replay. Update owned OpenAPI/client contracts deliberately.

No old/new application modes, forwarding facades or obsolete data-format support are required. Remove replaced
code after verifying its callers. Pre-production does not authorize deleting unknown local data: record any actual
needed-data preservation or explicitly authorized disposal separately. Later architectural cleanup remains with
its existing TASK; release adoption does not complete those TASKs or waive independent Review/QA.

Do not finish another capability merely to move a shared helper: preserve inward dependencies and an actually live
existing path under its named correcting owner until its consumers migrate, without adding aliases or forwarding
replicas. The last consumer removes the dead path and reconciles the shared inventory; do not call it obsolete or
claim complete removal while real users remain. Unrelated engineering tooling/frontend/Pi architecture is not a
new correction scope; retain and name its real owner where needed.

Each TASK owns meaningful behavioral tests, actual affected callers/docs, obsolete-path cleanup, direct owning-tool
checks, normal canonical gate with honest fresh/reused evidence, independent Review and applicable QA. Needed
identities, access meaning and audit/secret-free history remain preserved; data disposal requires explicit authority.

TICKET-00135's approved TASK decomposition is complete. Inventory, supplier qualification and corrections remain
execution work, not accomplished by these records. No source, dependency/schema/data/grant, upstream, product-check,
Review, QA, commit or publication change begins here. Remaining TICKET planning and the next target are owned by
[EPIC-00023's current handoff](../epics/00023-EPIC.md#progress).

Planning-only verification passes: `./bin/planning-check --write` and `./bin/planning-check` report 740 records,
542 active and current generated views. Tracked/new-record whitespace checks pass; all six approved TASKs appear
in the Board's needs-info section with their real local blockers. No product build was run. These checks establish
planning integrity, not source conformance, supplier availability, runtime behavior or independent acceptance.
