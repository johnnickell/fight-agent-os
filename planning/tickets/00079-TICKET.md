---
id: TICKET-00079
epic: EPIC-00017
title: Use published custom templates for real manual Agent work
status: ready-for-agent
---

# Use published custom templates for real manual Agent work

## Problem statement

A new template saved in the browser is not a working Agent, and the initial eight roles are not the limit of
useful roles. Super Admin needs to instantiate an eligible published custom role and learn from real bounded
manual work without fabricating a Workflow or bypassing existing Agent, Team and credential safeguards.

## Solution and boundaries

Extend [TICKET-00063](00063-TICKET.md)'s existing named-Agent creation journey to eligible published templates
from [TICKET-00076](00076-TICKET.md), including newly authored roles. Select an exact published revision, a
distinct Agent name, one Team and allowed registered-Repository scope. Preview and explicitly confirm the
complete current eligible desired Permission set, resource/model constraints and scope. The actual authenticated
Agent is distinct from the template/persona and is created through the same qualified package operation,
holder-bound credential, protected sink and recovery path used for managed Agents.

Preserve original creation-operation correlation and truthful issued, delivered, activated and usable states;
never create another Agent to hide an uncertain result or present a credential as proof of usability. Retain the
selected template revision and exact resolved Harness/context snapshot. Later template publication cannot change
that Agent or live session. Missing provider/tool/resource/host readiness keeps work visibly unavailable.

Once usable, let the human initiate a small real assignment appropriate to the new role through already qualified
Harness tools and current delegated authority. Record the actual role/template/Agent/Team/Repository/session and
provider/model context and inspect the outcome to inform a separately reviewed revision. Scope and host/sandbox
controls remain independent enforcement, not prompt promises. The assignment must be genuine authorized work,
not a seeded demo, proof-only product or disposable fake Workflow. No mandatory per-template certification ceremony
is added; shared capability qualification remains required.

Absent Workflow support stays explicitly unavailable. A later Workflow owner may deliberately recognize a stable
role/template identity and appropriate version, but a matching title, prompt or model claim never establishes
dispatch eligibility. Do not implement a second Agent repository, credential flow, manual-work engine, Team
administration surface or Workflow router. Automatic TASK selection, cross-Team delegation, scoped administrator
Roles and generic Role/Permission CRUD are excluded. This integration does not block EPIC-00014's initial eight
managed roles or replace their separately owned product journeys.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Select an eligible published template | N/A — selection/preview is read-only | Exact published revision, current desired set, Team/Repository scope and resource readiness | N/A — preview creates neither Agent nor grant | Complete human-reviewable creation candidate |
| Create a distinct custom-role Agent | Existing confirmed Agent creation intent and package provision operation | Fresh actor/target/template/Permission checks and original operation status | Existing package issuance and attributable creation facts after commit | One Team-assigned Agent with protected holder, pinned revision and truthful lifecycle state |
| Perform real bounded manual work | Existing authorized Harness/tool operations under explicit human assignment | Current Agent/direct Permissions, delegated scope, snapshots and host/provider readiness | Only the owning operation/session facts on actual effects | Real scoped outcome; no automatic Workflow eligibility or template publication |

## Validation and permissions

Agent creation requires its distinct current managed `SUPER_ADMIN_ONLY` management Permission and separately
required assignment authority; template publication permission alone is insufficient. Use the authoritative
managed Super Admin Role's effective Permissions, never a role-name shortcut. Validate every entry path, exact
published revision, current eligible `ADMIN_SAFE` Permission IDs and complete set, Team membership and Repository
applicability. Reject drafts, stale previews, protected/missing Permissions and scope expansion rather than
silently downgrading the reviewed candidate. Preserve current expected-state and transaction/reference fences.

After creation, Agent work uses independent live authentication/authorization and delegated grants; the Agent
cannot inherit its creator's management rights or gain authority from instructions. Credentials remain in the
shared protected holder/sink, never browser/Pi-child output, model context, transcript or ordinary history. Reuse
existing recovery for uncertain issuance/delivery and authorization denials. Host/sandbox qualification and
provider disclosure controls must be real; do not launch outside approved limits because a role is manually used.

## Acceptance and evidence

- Super Admin can select a newly published eligible template and create a distinct named Agent through the
  existing journey, with exactly one Team, correct Repository scope and complete confirmed direct Permissions.
- Draft/stale/ineligible/out-of-scope candidates and unauthorized direct creation are denied. Credential
  uncertainty retains original correlation and never appears as a usable Agent or duplicate creation.
- A real new role performs a small authorized manual assignment through qualified tools. Record actual identity,
  revision, provider/model, current authority and outcome; saved templates, fixtures and old receipts are not proof.
- Subsequent template edits leave the Agent/session unchanged; current Permission revocation still denies tool
  use. Workflow remains unavailable unless separately designed and authorized by its actual owner.
- Owned PostgreSQL, API, client and Harness integration checks, actual bounded-use evidence and `./bin/build`
  pass with exact package/source/lock and operational limitations recorded; no separate proof product is created.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 5 of EPIC-00017's five-TICKET split. Depends on
[TICKET-00076](00076-TICKET.md)'s publication, [TICKET-00063](00063-TICKET.md)'s creation journey,
[TICKET-00062](00062-TICKET.md)'s Team scope, [TICKET-00059](00059-TICKET.md) /
[TICKET-00060](00060-TICKET.md)'s qualified operation/credential boundary and
[TICKET-00061](00061-TICKET.md)'s authenticated use. Existing EPIC-00006 Harness and relevant host/Runner
owners retain their implementations; no whole-Workflow prerequisite is invented for qualified manual work.
No Agent, credential, assignment or Workflow was created by decomposition.
