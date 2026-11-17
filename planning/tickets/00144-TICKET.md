---
id: TICKET-00144
epic: EPIC-00027
title: Release the shared frontend and adopt it in independent framework skeletons
status: ready-for-agent
---

# Release the shared frontend and adopt it in independent framework skeletons

## Problem statement

A library that only runs inside Agent OS does not yet unblock new applications. The first usable skeleton
must install its distributable package, implement the backend contract and support its own branding/screens.
Waiting for the complete EPIC-00018 reference bundle or all five frameworks would delay the initial projects.

## Solution and boundaries

First qualify project-symfony with the packed package candidate after complete human administration, branding
and tables. Symfony is selected because nickell-financial's current foundation names it; Agent OS already
provides a Slim-based consumer. Preserve Symfony as the backend/shell owner while adopting React/Bootstrap
for application UI. Record that this supersedes a Twig-only frontend assumption for the suite, not Symfony
or server-side Twig shell rendering. creatively-social and nickell-home retain their unselected backend
choices until their owners choose a ready starter; do not silently assign them a framework.

Publish an independently versioned npm package after the two real consumers qualify. Document public exports,
branding/composition, typed list/URL state and backend/API/session/permission contracts, tested versions and
known limits. Include the scoped human-account capability matrix and required safe setup/migration/mail
instructions. Reuse applicable actual evidence; no second complete certification campaign or public private-spec
dump. A release is not proof of all Access Control capabilities or production readiness.

Working name `@fight/react` needs verified namespace/name availability, registry visibility, publisher access
and version policy before publishing. Keep that external effect in TASK-00599; it does not block local package
work or the packed-candidate Symfony integration. Prepare the exact reviewable artifact first, and obtain
publication authority only for that candidate/destination when needed. Do not create accounts, spend money,
upload, contact maintainers or grant access from planning approval.

Then adopt the same release independently in project-slim, project-laravel, project-yii and project-codeigniter.
Each gets its own TASK/target PR and local checks. No common five-skeleton release gate; no framework-specific
fork of the shared frontend. Laravel removes existing Tailwind/Vite-plugin inputs and actual utility styles
after replacing needed behavior. Vite or another existing build tool can remain; Bootstrap adoption is not a
reason to force Agent OS's build pipeline into every repository.

Each starter must implement real compatible account/admin APIs and persistence composition using canonical
packages, framework request admission, cookies/CSRF/session behavior and safe wire results. An installed Composer
or npm dependency alone is insufficient. Follow the target's own engineering rules and qualify supported
framework admission adapters from the chosen Access Control release. Backend projection/admission may differ
by framework while the browser/API contract stays shared. Avoid creating another shared PHP package without
demonstrated need; report actual missing supplier capabilities rather than bypassing them.

All receiving skeletons keep free-text search disabled for Access Control features, including Users, Roles
and Permissions. The first library release still includes optional search controls, callbacks and URL-state
integration for application-specific queries. Each application owns its real searchable queries and fields;
no Access Control search endpoint/projection is required or permitted by this plan. Qualify the reusable
search contract with a representative consumer fixture and guidance without claiming application search backends exist.

Use local captured mail for development acceptance. Real inbox delivery, production enrollment, tenant/product
policy and application data migration remain separate project owners. Keep package version, skeleton source/tag
and EPIC-00009 generator eligibility distinct. A new application may integrate the qualified skeleton without
waiting for Agent OS's automated Create Project workflow or unrelated stable starter releases.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Install and extend a skeleton | Owning dependency/install/build tools | Actual package/API compatibility | N/A — delivery work | Real local branded account/admin journeys and host-owned business page |
| Publish one package | Registry publication after exact-candidate authority | Name/access/version and retrieved artifact identity | Registry outcome only | Immutable identified package available to intended consumers |
| Adopt in another framework | Existing canonical backend commands | Safe shared-contract list/account queries | Existing package events | Same UI with independently qualified server enforcement |

## Validation and permissions

No browser-only authorization or copied secret configuration. Use per-installation secrets and explicit
first-admin setup. Verify direct API denial and cross-user/self-service separation, not merely hidden controls.
Registry publication and repository PR/merge remain separate actions; no release secret appears in artifacts.
Use actual packed/published dependencies rather than symlink-only success or imports into Agent OS internals.

## Acceptance and evidence

- TASK-00598 qualifies Agent OS and a real Symfony skeleton using the same identified packed candidate.
- TASK-00599 publishes/retrieves the authorized version and verifies its content and usable integration guide.
- TASK-00600–00603 independently qualify their named skeleton with React/Bootstrap and no Tailwind.
- Each target checks human account/admin journeys, branding, table/deep-link/history behavior and server denials
  with its owning checks. Reuse unchanged shared-library evidence; test real framework integration differences.
- State what remains unqualified: other skeletons, production/mail and later Agent/HMAC features are never
  implied by one passing consumer. First release does not depend on the later four adoptions.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00598](../tasks/00598-TASK.md) | Integrate the shared frontend candidate into the Symfony skeleton | ready-for-agent |
| [TASK-00599](../tasks/00599-TASK.md) | Publish the first shared frontend package and scoped skeleton handoff | needs-info |
| [TASK-00600](../tasks/00600-TASK.md) | Adopt the shared React frontend in the Slim skeleton | ready-for-agent |
| [TASK-00601](../tasks/00601-TASK.md) | Adopt the shared React frontend in the Laravel skeleton | ready-for-agent |
| [TASK-00602](../tasks/00602-TASK.md) | Adopt the shared React frontend in the Yii skeleton | ready-for-agent |
| [TASK-00603](../tasks/00603-TASK.md) | Adopt the shared React frontend in the CodeIgniter skeleton | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

2026-11-17: first Symfony integration, package publication and four independent receiving-skeleton TASKs are
planned separately. TASK-00598 owns the first real consumer; TASK-00599 owns registry/handoff; TASK-00600–00603
each own one remaining framework. Cross-repository implementation follows the named target's instructions and
explicit checkout selection. No target source or target planning records were changed in this planning update.
These are concrete suite adoption outcomes, not a claim that remote repositories have accepted PRs or releases.
