---
id: TICKET-00023
epic: EPIC-00005
title: Operate the local Planning installation
status: ready-for-agent
---

# Operate the local Planning installation

## Problem statement

An operator needs the accepted local runtime, approved project-root access, private Planning evidence storage, and
task-based guidance before an authenticated registered Planning workspace can work reliably. Hidden host mutation,
platform overclaims, or a second runtime path would undermine every later repository and Planning use case.

## Solution and boundaries

Deliver the EPIC-00005 portion of the installation-owned Compose topology and shared HTTPS ingress by extending,
not replacing, the EPIC-00003/00004 application foundation. Provide the application, PostgreSQL, private
S3-compatible Planning evidence storage, approved checkout-root access, Mailpit where the existing authenticated
foundation requires it, and capability reporting needed by this EPIC. Reserve later Agent runner consumers,
Workflow execution, provider traffic, and Execution Artifact behavior for later EPICs.

Render one versioned task-based operator guide in the Dashboard. Before application availability, provide explicit
read-only terminal preflight; afterward, report current capabilities through the application. Installation,
package-manager, privileged, credential, certificate, DNS, mount, network, backup, and service changes remain
explicit human-run operations with commands, expected results, verification, and recovery.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inspect prerequisites before startup | N/A — read-only terminal checks are operator tooling | Inspect supported OS, architecture, Git, Docker/Compose, ports, DNS/TLS, storage, and credential-store availability | N/A — no application event | A copyable secret-safe report distinguishes pass, warning, unsupported, and not-yet-checkable facts without mutation |
| Start or update the Planning runtime | N/A — explicit installation operation, not a Domain command | Inspect declared services, versions, health, migrations, ingress, mounts, and durable volumes | N/A — infrastructure changes are not Domain events | The approved bounded stack starts through repository tooling and reports every explicit host or service effect |
| Configure an approved project root | N/A — installation/mount operation precedes application use | Inspect canonical path, containment, mount and checkout-access capability | Record safe configuration/audit evidence where application state changes; do not invent a filesystem event | An explicitly selected root becomes available after required runtime reconfiguration; no arbitrary home-directory mount is added |
| View contextual operating guidance | N/A — documentation view is read-only | Query guide revision and current installation capabilities | N/A — viewing guidance emits no Domain event | The Dashboard renders the applicable task, verified platform status, expected result, recovery, and primary-source links |
| Diagnose unavailable capability | N/A — read-only diagnostic operation | Query bounded service health, version, migration, root-access and storage facts | N/A — observations do not become authority | The operator receives copyable secret-safe facts without automatic upload or corrective mutation |

## Validation and permissions

Support Linux and macOS honestly. Initially verify Omarchy/Arch and the maintainer-tested macOS version; label
`pacman`, `apt-get`, and `dnf` installation guidance and other distributions according to actual evidence. Never
request or retain passwords, sudo input, tokens, private keys, passphrases, credential-store contents, or plaintext
Harness/provider credentials.

Canonicalize configured roots before persisting or mounting them, reject ambiguity and symlink escape, and never
mount a complete home directory merely for convenience. The guide is visible to authenticated users with the
appropriate installation-information authority; installation settings, root changes, backups, and upgrades require
separately authorized operator capability and cannot be performed by client visibility alone.

Offline cached capabilities may remain visibly usable, but setup, downloads, authentication, updates, migration,
and repository registration that require live dependencies fail with a direct reason. Do not silently start,
upgrade, reconfigure, or repair services from a read-only check.

## Acceptance and evidence

- The existing application foundation runs through one installation-owned Compose project and the accepted shared
  ingress/network boundary without an alternate development architecture.
- Required application, PostgreSQL, private Planning evidence storage, HTTPS, persistence, and approved-root
  capabilities report truthful health and version facts.
- Adding or changing a root is explicit, canonicalized, contained, reversible where applicable, and unavailable to
  the application until the runtime actually exposes it.
- The Dashboard guide is versioned, task-based, platform-qualified, and linked to current capability results.
- Preflight and diagnostics are read-only, copyable, secret-safe, and make unsupported or incomplete checks clear.
- Operator guidance is reviewed and exercised directly on the verified platforms. Do not add product tests for
  prose, setup wrappers, Compose/package-manager mechanics, configuration syntax, or the quality gate itself.
- Test only owned application behavior and important integration contracts introduced by this requirement; record
  manual checks, warnings, unsupported platforms, and unverified recovery honestly.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00072](../tasks/00072-TASK.md) | Establish the installation-owned Planning runtime | ready-for-agent |
| [TASK-00073](../tasks/00073-TASK.md) | Establish contained approved-root access | ready-for-agent |
| [TASK-00074](../tasks/00074-TASK.md) | Expose authorized installation capabilities and diagnostics | ready-for-agent |
| [TASK-00075](../tasks/00075-TASK.md) | Deliver the guided Planning installation journey | ready-for-agent |
| [TASK-00076](../tasks/00076-TASK.md) | Qualify supported local Planning operation | ready-for-human |
<!-- /planning:children -->

## Decisions and progress

Implements the EPIC-00005 operating boundary from
[WF-020](../wayfinder/tickets/WF-020-define-developer-onboarding-and-operator-guidance.md) and
[WF-023](../wayfinder/tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md). EPIC-00003 and
EPIC-00004 are prerequisites; this requirement must reuse their runtime, HTTPS, authentication, and Dashboard
foundation rather than compete with it. TICKET-00024 depends on the approved-root capability delivered here.
