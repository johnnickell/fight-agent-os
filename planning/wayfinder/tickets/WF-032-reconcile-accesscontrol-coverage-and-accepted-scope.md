# Reconcile AccessControl coverage with accepted Agent OS scope

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
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

Pending maintainer interview and approval.
