---
id: TICKET-00082
epic: EPIC-00018
title: Publish and maintain the immutable maintainer handoff
status: ready-for-agent
---

# Publish and maintain the immutable maintainer handoff

## Problem statement

Starter maintainers need the same identifiable, accessible reference snapshot, not mutable advice or evidence
silently carried across package/consumer changes. Agent OS must deliver its own qualified handoff while keeping
private material protected and starter adaptation, certification and release with each starter's maintainer.

## Solution and boundaries

Only after [TICKET-00080](00080-TICKET.md)'s complete coverage gate and
[TICKET-00081](00081-TICKET.md)'s actual backend/API/browser references qualify, assemble one coherent immutable
handoff: Markdown references plus a machine-readable identity manifest, packaged as a versioned archive and
delivered through an authenticated, access-controlled artifact channel. Internal draft preparation is allowed,
but no staged unqualified external guidance is presented as the final bundle. Select and verify the concrete
channel and recipient identities before publication; this is artifact delivery, not a new Agent OS product
endpoint, general publication service or public static-spec route.

Identify the actual AccessControl tag/source, installed Composer lock identity/reference, qualified Agent OS
source commit, verified OpenAPI source/content digests, bundle version/content digest, scope/dispositions and
per-capability scenario/receipt provenance. Distinguish the qualified implementation commit from any later
documentation/publication commit. A complete manifest must identify every delivered component and its immutable
content; use a clearly defined digest boundary rather than a self-referential checksum. Durable redacted evidence
must travel with the archive or resolve through retained immutable controlled references; local ignored `.runs/`
paths alone do not make an accessible handoff. Keep private specification access protected and explicitly
identify whether the bundle contains authorized private material or references its controlled location.

Deliver the same immutable snapshot, verification instructions and caveats to the maintainers of
`johnnickell/project-symfony`, `project-slim`, `project-laravel`, `project-yii` and `project-codeigniter`.
Verify that intended recipients can resolve the artifact and required references under the chosen access policy;
being a named recipient does not automatically grant access to an Agent OS deployment or its Swagger. Provide
setup/access prerequisites without credentials. Record actual delivery/access outcomes and safe failure/retry
state. Missing access is incomplete delivery, not permission to publish private content openly. No recipient
acknowledgement, adaptation, certification or release is required to complete Agent OS's handoff.

Preserve published versions and their identified bytes. For package, lock, consumer source or API changes, assess
affected claims, requalify them through their existing owners and create a newly identified complete bundle
before reusing those claims for the new subject. Unaffected evidence may be retained only with its explicit
provenance and justified applicability, never relabeled fresh. Do not replace an old artifact in place, auto-
publish on code changes or impose a shared five-starter update pipeline.

This does not modify starter source, open starter PRs, test/certify/tag/release starters or require Agent OS or
AccessControl v1.0.0. [EPIC-00009](../epics/00009-EPIC.md)'s independent per-starter immutable stable SemVer
`>=1.0.0`, manifest and snapshot eligibility remains unchanged. Publication/merge authority is a separate human
operation; approving this requirement does not upload artifacts, grant access or contact maintainers.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Assemble qualified snapshot | Explicit artifact packaging through owning tools, not a Domain command | Complete readiness, exact versions/hashes, reference content and redaction evidence | N/A — packaging has no application Domain event | One identified candidate archive and manifest; no external publication yet |
| Deliver final handoff | Explicit authorized upload and recipient handoff through the selected controlled channel | Recipient identity/access, uploaded digest and required reference reachability | Attributable publication/delivery receipt, not a new product event bus | Same immutable bundle accessible to all five intended maintainer destinations |
| Publish a successor | Explicit impact assessment, owning requalification and authorized new-version publication | Changed package/lock/source/API subjects and affected claims | New identified publication receipt; previous bundle remains unchanged | Newly qualified complete snapshot, without starter updates or in-place replacement |

## Validation and permissions

Require explicit publisher authority and verified recipient access appropriate to the material; do not infer
permission from repository visibility, a shared link or possession of a prior bundle. No credentials, raw
transcripts, sensitive personal data, private operator configuration or unapproved source/receipt bodies belong
in the artifact or publication logs. Private specs remain restricted even when surrounding guidance is safe to
share. The handoff does not relax current READ_SWAGGER or any endpoint's independent authority.

Validate exact source/content identity, complete manifest scope, safe archive contents and reference retention
with the owning tools. Never claim success from a partial upload, unavailable receipt or digest mismatch.
Reconcile an uncertain publication against the exact version/content before retry, without overwriting published
bytes or creating false delivery claims. This is a manual versioned artifact lifecycle, not a new application
transaction/outbox, automatic notification system or guarantee of exactly-once external delivery.

## Acceptance and evidence

- Final publication is held until complete qualified coverage and reference material exist; exclusions are
  approved, actual selected contract/version is labeled and no planned or unassigned capability masquerades as live.
- The archive/manifest identify exact package, lock, consumer, OpenAPI and evidence subjects; direct independent
  digest/source/link checks reproduce the stated identity without reliance on inaccessible local scratch.
- All five named maintainer destinations receive the same identified material with verified authorized access,
  verification instructions and honest caveats; no private-spec bypass or starter acceptance claim is introduced.
- A documented successor procedure requires impact assessment and affected requalification, retains old versions
  immutably and leaves adoption/release decisions with each starter owner.
- Direct packaging/redaction/access checks and `./bin/build` pass. Use owning tools, not product-suite tests of
  archives, Markdown, manifests, build mechanisms or intentionally seeded failures. Record incomplete delivery
  and verification explicitly; no automatic cleanup/archiving of Planning follows.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 3 of EPIC-00018's three-TICKET split, including the Markdown plus machine-readable manifest,
immutable archive and authenticated controlled-access distribution direction. Consume
[WF-034](../wayfinder/tickets/WF-034-set-reference-consumer-and-starter-handoff-contract.md) and
[EPIC-00018](../epics/00018-EPIC.md)'s post-qualification, independent-starter ownership boundary. No artifact
was assembled/published, recipient access changed or maintainer contacted during decomposition.
