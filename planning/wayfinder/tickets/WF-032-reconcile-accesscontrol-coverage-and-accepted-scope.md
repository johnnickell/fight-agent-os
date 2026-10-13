# Reconcile AccessControl coverage with accepted Agent OS scope

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete AccessControl consumer adoption](../accesscontrol-consumer-adoption-map.md)
**Depends on:** —

## Question

Which supported AccessControl journeys must Agent OS own as product UI, which belong to a protected console or
worker, and which may remain explicitly non-UI or excluded? Who can amend accepted scope before a new EPIC is
written? Use the [versioned capability matrix](../research/WF-032-accesscontrol-consumer-capability-matrix.md),
not a method-to-endpoint mapping.

## Must decide

- Does “complete adoption” require an authorized *own-email change* journey now, despite [EPIC-00004](../../epics/00004-EPIC.md)'s
  explicit deferral, and does it include cross-user email-change administration? **Recommend:** include own-email
  request/verification/cancel/recovery with explicit approval to amend that deferral or create a separately linked
  EPIC; keep cross-user change disabled until a distinct managed permission, target scope, audit and callback are
  approved. No general user profile mutation follows from email change.
- Which Feature lifecycle is useful for this product? **Recommend:** offer safe admin read/preview/confirmed
  enablement and permission-binding UI for declared Features only after complete scanner/preparation and real
  runtime enforcement; keep declaration/provisioning and cleanup in protected operations. If no actual Feature
  is identified for activation, document the full package capability as non-UI/non-live for now rather than
  pretending a Feature manager or turning on a dummy Feature.
- Should the accepted EPIC-00004 browser foundation be amended, or should human email and Feature administration
  form a linked new EPIC after its foundation prerequisites? **Recommend:** a separate EPIC for new deferred
  product journeys, with a narrow explicit EPIC-00004 cross-reference/owner approval only if required; retain
  TICKET-00030/00031 and their existing TASKs without reallocating them.
- Are any package journeys intentionally excluded from the new destination? **Recommend:** retain no public
  registration, generic Permission CRUD, arbitrary profile mutation and raw-secret endpoints; treat worker claims,
  ciphertext, nonce use and key erasure as explicit *non-UI* support (not unassigned), and ask the owner for any
  further exclusion rather than declaring comprehensive coverage by omission.

## Resolution boundary

Record approved coverage classes and owning accepted artifact or proposed amendment/separate EPIC destination for
each disputed family. No EPIC rewrite, Feature activation, new permission grant, UI endpoint, or TICKET/TASK is
authorized by this decision alone. The closed vision map remains closed; ADR 0001/0002 and scoped EPIC-00006/00009
continue to govern their accepted parts.

## Resolution

Approved in the WF-032 interview:

- **Email change is in scope:** include self-service request/cancel/confirmation and Super Admin request/cancel for another user, with new-mailbox confirmation and no direct administrator override. The package calls `EmailChangeAdministrationAuthorization` for actor != target; Agent OS currently denies via `UnavailableEmailChangeAdministration`. Replace that denial only under a distinct managed `SUPER_ADMIN_ONLY` Permission initially granted to `ROLE_SUPER_ADMIN`, with current actor/target checks on every entry path, secret-safe provider delivery/recovery and audit. The role name alone does not authorize the action. The current denial is a consumer policy, not a missing upstream feature or an external approval gate.
- **Feature Flags UI is in scope:** a Super Admin can safely list/read Features, create one with an immutable name, select/change its testing Permission, switch among the package's `OFF`, `PREVIEW`, and `ON` states, and remove a Feature after confirmation only when complete CURRENT-code discovery shows it is unreferenced. Referenced, stale or incomplete-discovery removal fails safely; no force-delete. Use a distinct managed `ADMIN_SAFE` Feature-management Permission, initially granted **only** to `ROLE_SUPER_ADMIN`. Later eligible delegation is possible under the tier but not automatically granted to custom Roles or Agents. The testing Permission gates PREVIEW for eligible principals; it is not management authority. Validate expected revisions, complete candidate preparation, registered runtime checks and separate action authorization before enablement; do not seed a fake Feature to justify the screen.
- **Ownership and accepted-scope conflict:** approve **two separate, linked new EPIC destinations**—one for deferred self/cross-user email-change journeys, one for complete Feature Flags management and runtime lifecycle—dependent on EPIC-00004's secure authentication, managed policy and entry authorization foundation. This is an explicit *later extension* of EPIC-00004's email-change deferral, **not** a retroactive rewrite or implicit amendment to EPIC-00004/TICKET-00030/00031 or their existing TASKs. Future EPICs must cite the deferral and this approval. Exact EPIC writing, TICKET/TASK decomposition and scheduling remain separate operations after the map's remaining decisions. Existing approved human/session/role journeys retain their existing owners.
- **Complete coverage means disposition, not browser parity:** every relevant supported current package capability must have an owned consumer journey or explicit protected non-UI treatment; no supported public path is silently dropped. Keep credential delivery/expiry/recovery, Feature discovery/provisioning/preparation, direct worker claims, key/cohort/maintenance and other sensitive internals in guarded console/worker/service paths when appropriate, with safe UI intent/status where needed. Do not invent unsupported public signup, generic Permission CRUD, broad profile mutation, direct email override or raw-secret endpoints; no one-endpoint-per-package-method requirement. The Agent operation/surface split is reserved for [WF-033 — Define live Agent operation adoption and safe surfaces](WF-033-define-agent-operation-adoption-boundary.md); the starter handoff remains [WF-034 — Set the Agent OS reference-consumer and starter handoff contract](WF-034-set-reference-consumer-and-starter-handoff-contract.md).

No EPIC, TICKET, TASK, endpoint, Feature activation or credential issuance is created by this resolution.
