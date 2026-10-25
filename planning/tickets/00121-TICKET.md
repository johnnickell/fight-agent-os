---
id: TICKET-00121
epic: EPIC-00021
title: Build an immutable production application artifact
status: ready-for-agent
---

# Build an immutable production application artifact

## Problem statement

Development Compose, checkout mounts and development tooling do not supply a deployable production application.
John needs one complete, identifiable artifact that runs without a source checkout and can move unchanged from
staging to production, without embedding environment secrets or disrupting ordinary contributor workflows.

## Solution and boundaries

Build the actual production artifact through the existing GitHub CI path under
[EPIC-00021](../epics/00021-EPIC.md). Use the selected NGINX/PHP-FPM serving direction, required PHP/runtime
extensions and reproducibly locked application dependencies. Include browser assets, required private OpenAPI/
validation exports, the password corpus and its license, and required runtime dependencies. An internally required
export is not permission to publish documentation or enable Swagger in production.

Exclude development-only debug tooling, source-checkout mounts, credentials, execution scratch and dependence on
`.runs/`. Application-generated state uses the established runtime/private-storage contract, including ignored
`var/` where applicable. Declare writable locations and runtime/service requirements explicitly; immutable does
not mean silently writing generated state into the image. Retain licenses and artifact/source/build provenance.

Separate immutable application bytes from narrowly validated environment configuration. Public browser bootstrap
configuration follows ADR 0004's current allowlist and same-origin contract, not a dump of server variables. The
same artifact digest must work with staging and production configuration; no environment-specific rebuild at
promotion. Private dependency/build credentials, if needed, remain protected build inputs, not image layers,
public artifacts, ordinary logs or application runtime credentials.

Use application CalVer `YYYY.MM.DD`, with `.2`, `.3`, etc. for additional releases on the same UTC release date
and optional `-rc.N` candidate identifiers. The chosen release date need not equal the eventual deployment date.
Use `release/<version>` branches, for example `release/2026.10.24`; retained Git release tags are not required.
Bind each candidate/release record to the exact source commit and artifact digest(s), independently of mutable
branch names. An accepted release identifier cannot silently be rebound to different bytes. Promotion records
acceptance of the same candidate bytes without rebuilding or requiring a Git tag.

Preserve existing CI trigger filters; naming a release branch does not itself enable a release-push or tag trigger.
Creating/pushing/retiring branches and deleting existing tags remain separately authorized Git effects. This
application numbering choice does not change independently versioned package or Harness release contracts.

Do not select a mandatory new registry, packaging framework, prospective dependency version or Pi version gate.
Qualify currently supported capabilities and record exact tested/locked identities. Preserve existing local
wrappers, contributor defaults and native local checks.

Out of scope: provisioning, DNS/TLS enrollment, production secret configuration, automatic deployment, general
release orchestration, application feature replacement and product tests of packaging/configuration mechanisms.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Build a production candidate | Explicit CI build operation; no new Domain command | Source/lock/build identity and required inputs | CI outcome/provenance, not a new business event | Produce the immutable artifact and safe digest/provenance |
| Inspect a candidate | None; read-only artifact inspection | Included runtime/assets/licenses, digest and declared writable/service requirements | None from inspection | Report missing/unsafe contents without mutation or credential disclosure |
| Run the packaged application | Explicit authorized bounded runtime operation | Actual artifact identity, startup and representative application behavior | Existing application facts only | Serve through the production runtime without a source mount or development fallback |
| Hand off the candidate | Existing artifact publication/transfer within explicit authority | Exact digest, availability and environment-neutral configuration contract | Provider/CI receipts only | Make the same bytes available to the selected environment without deploying them |

## Validation and permissions

A successful CI job or artifact upload is not deployment approval. Builds run in the separate authorized build
context, never as candidate-code execution on staging/production application hosts. Bound registry/dependency
access and publication to existing authority; planning grants no account, purchase or publication permission.

Reject missing required assets, invalid runtime configuration and accidental development/secret dependencies.
Keep errors/provenance nonsecret. Preserve startup security checks; packaging cannot waive application permission,
origin, secret-strength or debug-off requirements. Failed/partial publication cannot be described as an available
verified candidate. Inspection performs no corrective build, service startup or remote mutation implicitly.

## Acceptance and evidence

- The actual GitHub CI path produces a complete identifiable artifact with the required assets, exports, corpus/
  license and runtime dependencies, excluding development-only tooling, secrets, checkout mounts and scratch.
- The packaged application is exercised through NGINX/PHP-FPM without a checkout; representative backend/browser
  behavior and required private asset consumption work rather than only image creation succeeding.
- Staging/production configuration can consume the same digest with safe validated public bootstrap data and
  separate runtime secrets/state. Writable locations and service requirements are explicit.
- Record exact source, lock, runtime/build and artifact identities, license obligations, warnings and incomplete
  hosted verification. A local substitute is not evidence that the required GitHub CI path ran.
- Use owning build/image tools directly for packaging. Test only owned application behavior/integration contracts;
  do not add product-suite tests for Dockerfiles, wrappers, generated files, CI or quality tooling.
- Full `./bin/build`, independent Review/QA and applicable authorized artifact probes accompany later delivery;
  publication, target operations and deployment remain separate effects.

## Coordination

[TICKET-00122](00122-TICKET.md) consumes the artifact and early runtime requirements;
[TICKET-00125](00125-TICKET.md) consumes its exact digest for promotion. Supply configuration/storage/service seams
early without waiting for a completed browser journey or production environment. Runtime/security application
owners retain their behavior; missing capabilities return to those owners rather than becoming packaging bypasses.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00522](../tasks/00522-TASK.md) | Package a runnable immutable production application | ready-for-agent |
| [TASK-00523](../tasks/00523-TASK.md) | Build and deliver the exact production candidate through GitHub CI | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this as slice A of EPIC-00021's five-TICKET split. Source decisions are WF-048/050 through the
[approved map handoff](../wayfinder/private-production-rollout-map.md#approved-first-production-planning-handoff).
Follow [ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md). At requirement approval, TASK decomposition remained
pending; the separately approved split below now completes it. Implementation and operational acceptance remain incomplete. No build, purchase, publication or runtime effect is
authorized by this record. EPIC-00020's priority and all existing supplier holds remain.

John subsequently accepted application CalVer and `release/<version>` branches without a retained Git-tag
requirement. He subsequently approved the two-TASK split below. Numbering and decomposition approval start no
branch/tag, build, CI or publication operation.

### Approved TASK decomposition and current handoff

| Slice | Accepted TASK | Blockers | Delivered outcome |
|---|---|---|---|
| A | [TASK-00522 — Package a runnable immutable production application](../tasks/00522-TASK.md) | None unfinished | Complete NGINX/PHP-FPM artifact, required assets/exports/corpus/licenses, exact identity, runtime configuration/state contract and real checkout-free packaged behavior |
| B | [TASK-00523 — Build and deliver the exact production candidate through GitHub CI](../tasks/00523-TASK.md) | TASK-00522 | Existing GitHub Build integration, canonical gate and packaged probes, safe delivery, exact provenance and independently verified retrieval without rebuilding or deployment |

A reuses completed frontend, corpus, validation/OpenAPI and security owners; it does not wait for TASK-00072's
complete local-installation transition, production provisioning or final browser Planning. B preserves existing
push/PR filters and protects build/publication credentials; naming `release/<version>` does not add a trigger.
CalVer and exact release-to-commit/digest records require no retained Git tag. Public/private artifact visibility,
retrieval and retention remain explicit; no new registry/account purchase or publication permission is implied.

Both are unranked `chore` TASKs, `ready-for-agent` with the actual dependency above. Each includes its direct owning-
tool validation, relevant application behavior checks, full build and independent Review/QA; no standalone
certification TASK or product-suite tests of Docker/CI/wrappers are added. Actual hosted evidence is required for
B but does not block A's local work. TICKET-00122 and TICKET-00125 consume early artifact/runtime/identity seams;
completed target delivery never reverse-blocks packaging.

Before allocation, checked live/archive TASK filenames across seventeen local/cached refs and nine registered
worktrees, including untracked files; highest observed was TASK-00521. TASK-00522/00523 are local allocations,
not a global reservation. No existing TASK metadata, priority or qualification hold changes accompany this split.

This TICKET's accepted requirements are fully decomposed, not implemented. The selected map's TASK phase is now
complete after TICKET-00122's TASK-00524–00526, TICKET-00123's TASK-00527/00528, TICKET-00124's TASK-00529/00530 and
TICKET-00125's subsequently accepted TASK-00532/00533; TICKET-00089's
lifecycle/settings additions are already accepted. Work remains
uncommitted/unpublished, with no operational effects or acceptance claimed.

#### Planning verification

`./bin/planning-check --write` and `./bin/planning-check` passed: **668 records, 512 active**, generated views current.
`git diff --check` and full changed/untracked scope/whitespace checks passed across **59 planning paths** including
earlier uncommitted work; 4,011 relative link targets were checked.

The pre-decomposition receipt under ignored `.runs/notes/production-artifact-decomposition/before.json` covers
776 planning Markdown files and already includes the accepted CalVer amendment to this TICKET, TICKET-00125 and
EPIC-00021. Against it, the decomposition changes exactly twelve existing handoff/generated-view paths and adds
TASK-00522/00523. All existing frontmatter and TASK files remain byte-identical, preserving statuses, orders,
dependencies, actual holds and EPIC-00020 priority. The only added dependency is new TASK-00523 on new TASK-00522.

These are planning checks only. No full application build, independent Review/QA, hosted CI, image build, artifact
publication, credentials, branch/tag mutation or deployment was performed. No commit or push occurred.

TICKET-00122's subsequent environment decomposition has its own verification receipt; the counts above remain
historical artifact-decomposition evidence.

Next: `/skill:next` for read-only Board-based selection after completed selected-map planning. No automatic implementation.
