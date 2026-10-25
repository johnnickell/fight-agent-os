# Define optional local routing, trust and recovery

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Private production rollout](../private-production-rollout-map.md)
**Depends on:** [WF-043](WF-043-bound-private-release-and-environment-names.md)

## Question

How should approved production-matching local names integrate with existing shared infrastructure while keeping
normal contributor startup independent and every existing application recoverable?

## Must decide

- Reuse the private shared proxy's registration, validation, startup and recovery contracts before proposing
  replacement. The [baseline](../research/private-production-rollout-baseline.md) records configuration inspection,
  not live health. Recheck only relevant private sources and preserve unrelated projects/machine privacy.
- Separate name resolution, hostname/port proxy routing, application instance creation and TLS trust. Hosts entries
  have no wildcard, port-selection or redirect semantics. Fight's fixed `localhost:18443` authority/origin must
  be adapted through an explicit later application/ingress contract, not merely another hosts entry.
- Compare exact hosts entries with a narrowly scoped local resolver for generated task/worktree names. Its benefit
  is avoiding a hosts edit per worktree, not starting an application or generating proxy routes. Leave unrelated
  DNS resolution unchanged; decide missing-machine/wrong-machine and conflict behavior.
- Specify `local.fight-agent.dev` plus one-level task/checkouts beneath it, with actual TASK identity and deterministic
  concurrent-checkout disambiguation. Distinct `local.nickell.app` and `local.nickell.financial` retain their own
  project ownership; avoid nested names that exceed the chosen certificate scope.
- Cover both the base local hostname and `*.local.fight-agent.dev`. A wildcard alone covers neither its base nor
  arbitrary depth. Protect CA signing material separately from proxy leaf keys; enrollment, renewal, revocation
  and trust removal are deliberate operator actions, not implicit browser effects.
- Keep host/browser/test-container DNS and CA trust aligned. Container loopback is not the host. Preserve isolated
  worktree ports/data and contributor/CI usability without this workstation's domains, CA or private network.
- Define explicit preview/apply/reload/recovery and independent DNS/TLS/proxy/application health reporting.
  Preserve existing IPv4/IPv6/Herd coexistence unless separately authorized and qualified; configuration files do
  not prove actual listeners or startup health.
- Shared proxy service must survive dashboard stop/update/failure, with independent commands, lifecycle and
  maintenance-stop intent. Route removal does not delete app data or authorize CA/certificate retirement.

## Resolution boundary

Interview inside Wayfinder, backed by primary documentation and private configuration inspection as needed.
Set the architecture/qualification boundary and existing-owner amendment proposal, not an EPIC or implementation.
No hosts/resolver/DNS changes, CA enrollment/certificate generation, proxy reload, container startup or live health
tests in this session. Do not publish private machine inventory or generated routing state.

The optional resolver/private overlay is not a production-launch prerequisite. Reliable standalone local
operation remains required. Infrastructure ownership migration and legacy retirement remain WF-053, not an
implicit consequence of choosing route integration.

## Resolution

Closed on John's approvals in this interview: shared scoped workstation DNS, future project/worktree reuse,
fail-closed routing, workstation-only access, stable checkout naming, qualified existing-CA reuse, guided eventual
easy-install and independent shared-service lifecycle. Optional means consensual workstation enrollment and no
new first-browser launch gate, not an obscure unsupported add-on. This approves policy, not a resolver product,
installer, live capability, delivery-owner amendment or implementation. Remaining platform/protocol details and
actual suppliers are explicit later qualification/planning holds below, not additional speculative decisions.

### Approved direction and proposed multi-project contract

- Reuse one shared workstation resolver capability rather than require a DNS service per project. Each future
  project can explicitly register its own narrow local namespace and worktree names; exact input schemas and
  platform mechanisms still need implementation qualification. `local.fight-agent.dev`, `local.nickell.app` and `local.nickell.financial`
  remain distinct project namespaces, not a shared shortened identity or permission to implement Nickell apps.
- Preserve the approved local namespace and task/checkout disambiguation per project. No broad interception of
  `.dev`, `.app`, `.financial`, production/staging names or unrelated DNS. Wildcard DNS is not wildcard application
  creation or TLS coverage; one-level certificates still require matching hostname depth and separate base coverage.
- DNS directs names toward the local ingress only. Application instances, proxy registration, certificates/trust,
  isolated data/ports and explicit privileged enrollment remain separate owners/effects. Ordinary contributor/CI
  startup stays independent; shared services must be recoverable without a working Fight dashboard.

### Approved local failure policy

John explicitly approved fail-closed local routing: unknown/unregistered worktree names or stopped/unhealthy
targets report local unavailability (or a DNS/TLS/connection failure where no safe page can be served), never
production/staging, another checkout or a catch-all application. A resolver outage must not intentionally fall
through to a public application. Exact platform handling and later verification remain required; this approval
is not evidence that installed DNS/proxy configuration already enforces the policy.

### Approved workstation-only scope

John selected workstation-only access. Each participating machine explicitly enrolls its own resolver, trust and
routes to its own local instances. No LAN DNS service, public DNS records, remote proxy exposure or cross-machine
interpretation of a loopback address is included. Containers require their own qualified reachability/trust;
they cannot treat their own loopback as the workstation. LAN/VPN access to another machine's development instances
is outside this scope and would need separately approved naming, routing, authentication and trust. This does not
limit ordinary authenticated use of the separately planned production/staging installations.

### Approved primary alias and task/checkout naming

John accepted the naming direction after clarifying the short checkout fingerprint. Bind each project's base local
name to an explicitly selected primary checkout. Task worktrees use one DNS label containing the actual TASK ID
plus the first ten lowercase hexadecimal characters of SHA-256 over the stable registered Checkout identity,
written schematically as `<task-id>-<checkout-fingerprint>.local.fight-agent.dev`. Keep the full identity internally;
the suffix is a display/routing identifier, not authentication or a full UUID/hash in the hostname. Never derive it
from the moving Git commit or filesystem path. Use the suffix from first registration, not only when a concurrent
checkout appears, and preserve the name across restarts. Identity encoding, registration and collision checks still
need implementation qualification; ten characters do not guarantee uniqueness. Reject conflicting registrations,
never silently take over or retarget an existing route. Switching the base alias is an explicit previewed operator
action. Other projects use the same pattern within their own namespaces, with no extra subdomain depth.

### Approved certificate trust direction

Read-only recheck of the existing shared infrastructure README documents leaf preparation with the existing local
CA, immutable proposals, validation before apply and recovery outside any dashboard. This corroborates a reusable
workflow, not current certificate validity, protected key custody, installed trust or live router health. No key,
certificate store, generated routing inventory or runtime was inspected or changed.

John approved reusing the existing workstation development CA only after qualification rather than creating a new
CA per project. Keep that CA and signing key workstation-local and protected outside proxy/application containers.
Each explicitly enrolled project receives a separate leaf certificate/key covering its base local name plus its
one-level wildcard; the proxy receives only needed leaf material. This avoids per-worktree certificate issuance,
not project/route registration or authorization. A shared trusted CA still has broad local signing authority;
project-specific leaf certificates do not remove the shared-CA compromise risk. Do not copy the CA signing key to
other machines. Host/browser/container trust enrollment and renewal/removal remain explicit bounded operator
operations. Removing one project's route/leaf must not remove trust needed by other projects. Exact certificate
lifetime, rotation/revocation, trust consumers and failure recovery require later qualification.

### Approved easy-install onboarding and collaboration use case

John explicitly approved guided reusable onboarding with explicit privileged steps and independent recovery.
He clarified that the beneficial local-domain experience belongs in the normal eventual product easy-install
path, not an obscure manual add-on. Enrollment remains consensual because it affects local DNS/trust/privileges;
this does not make the supported easy-install path merely an undocumented advanced overlay. John's private
configuration is not a distributable installer, and CA reuse never means sharing his signing key.

The intended collaboration use case is a deployed installation centralizing Planning and task management while
John's son, or another authorized contributor, works on individual tasks in their own laptop checkouts. Local
preview domains remain workstation-only. Do not infer that every collaborator must run a duplicate authoritative
Planning server or expose laptop services over the LAN. The exact laptop profile, authenticated server connection,
enrollment/revocation, per-user/task/repository authority and execution boundary remain WF-047; no transport,
credential sharing, runtime grant or candidate-worker capability is approved by this onboarding requirement.
Standalone/self-hosted operation and ordinary contributor startup remain independent supported planning concerns.

- Provide one documented guided workstation setup/enrollment workflow with read-only preflight, previewed effects,
  explicit human-run privileged steps, verification and independent removal/recovery. It must work without a live
  Fight dashboard, must not collect sudo passwords, and must not repair or install anything from a read-only check.
- On each machine, reuse that operator's qualified local development CA or explicitly create/enroll a new one;
  never distribute John's CA private key or private machine inventory. Establish narrowly scoped DNS/proxy and
  container reachability/trust through supported platform-specific mechanisms, not copied workstation assumptions.
- Keep project registration declarative and repeatable across different projects; subsequent task/worktree
  registrations reuse approved namespace/certificate coverage but do not silently start apps or grant authority.
  Project identity/upstream/configuration inputs still require validation and explicit apply.
- Document and directly qualify the supported macOS/Linux paths and clean-machine/container onboarding; mark
  unqualified platforms unsupported rather than claim universal one-command support. Public projects' standalone
  startup and CI remain usable without this optional overlay, private source checkout or John's configuration.
- Reuse TICKET-00023's relevant operator-guide/capability contracts and existing shared-infrastructure ownership.
  Its complete local Planning stack is not automatically the lightweight hosted-collaborator laptop profile.
  Exact reusable packaging/distribution, OS integration, laptop profile and protected enrollment suppliers remain
  later planning gaps; this approval authorizes no extra installer framework, external-project edit or implementation.

This is an approved eventual adoption requirement, not a packaged installer, new EPIC/TICKET/TASK or a new first
private-browser launch gate. Exact platform/container support and supplier mapping require qualification; the
complete shared-runtime and easy-install experience is not claimed delivered.

### Approved shared-service lifecycle, including sleep/wake and restart

John approved automatic enrolled-infrastructure startup/recovery with persistent maintenance stop, and explicitly
asked that laptop startup, sleep and shutdown transitions be covered. Carry this as a required lifecycle contract,
not evidence of implemented OS hooks:

- After laptop startup/reboot, start enrolled DNS/proxy services once the supported login/session and required
  runtime are ready. Existing macOS Docker Desktop evidence supports after-login readiness, not a pre-login boot
  guarantee. Qualify actual support separately on each selected OS/runtime; do not promise sleeping/offline service.
- During sleep, services are unavailable or paused by the OS/runtime. Do not block sleep, keep the laptop awake,
  change power settings or treat ordinary sleep as an intentional maintenance stop. On wake, recheck resolver,
  addresses, network, runtime, proxy and route health; recover approved running intent with bounded retries.
- Shutdown/logout may stop services through their OS/runtime lifecycle. Use supported graceful termination where
  available, but do not depend on a successful shutdown/sleep hook or a final callback. Persist desired running/
  maintenance state durably and safely reconcile partial/uncertain state after wake, login, reboot or runtime restart.
- Recovery after a crash, Docker/runtime restart or missed callback must verify actual owned state and avoid
  duplicate listeners/supervisors. Reuse and qualify the existing independent supervisor/startup contracts; preserve
  IPv4/IPv6 and Herd coexistence. Failure/timeout stays visible instead of an endless restart or hidden repair loop.
- A deliberate maintenance stop persists across wake, reboot and runtime restart until explicit resume. Ordinary
  OS suspension/termination does not erase running intent. Only explicitly enrolled infrastructure starts or recovers
  automatically; application/worktree startup remains separately selected and owned.
- DNS/proxy continue independently of Fight dashboard stop/update/failure. Independent read-only status distinguishes
  resolver, proxy, TLS and application readiness. Starting a container is not proof of correct routing/trust; no
  read-only health check may apply changes, install trust, issue certificates or collect privileged credentials.

### Carry-forward change-safety and qualification boundary

The approved guided workflow reuses the existing infrastructure's documented immutable proposal, validation,
explicit apply and independent recovery contracts. It does not grant a browser or Agent host administration:

- Preview exact target project/checkout/hostname/upstream and intended DNS, proxy, certificate, trust and startup
  effects. Validate input, canonical Checkout identity, current ownership and conflicts before apply. Changed
  configuration invalidates the prepared plan; preserve the previous known-good configuration and unrelated routes.
- DNS, trust and proxy updates are distinct operations, not a claimed cross-OS atomic transaction. Apply only
  reviewed effects with explicit operator authority, validate before reload, verify afterward and expose partial
  success/failed recovery. A failed change attempts only owned restoration; it cannot silently report success,
  take over another listener, delete application data or retire another project's trust/certificates.
- Keep the shared network limited to enrolled ingress/upstreams and bounded service reachability; databases,
  credentials and administrative interfaces stay private. Adapt Fight's current fixed `localhost:18443` HTTPS/
  FastCGI/origin/cookie contract through generic per-instance configuration, not a hosts/DNS change alone.
- Host/browser/test-container paths must reach the same intended instance using its approved HTTPS authority and
  verified certificate. Container loopback is not the host. Enroll only needed public CA trust in named consumers,
  never the CA signing key; qualify the explicit container-to-ingress route without broad host/Docker authority.
  Optional-overlay failure must be explicit; it must not silently use production, another checkout or insecure HTTP.
- Exercise ordinary contributor/CI startup without this private setup as well as clean-machine easy-install,
  enrollment/remove/renew/recovery, same-TASK concurrent checkouts, collision rejection, DNS/TLS/proxy/app failures,
  IPv4/IPv6 coexistence, wake/reboot/runtime restart and sticky maintenance stop. Existing documented configuration
  and historical checks are not fresh qualification. Validate tooling directly; do not add product tests of the
  installer, OS hooks, scripts, Compose or planning text.

### Proposed existing-owner handoff, not accepted amendments

| Existing owner | Later authorized planning/qualification boundary |
|---|---|
| TICKET-00023 / TASK-00072 | Reuse the installation-owned runtime and generic custom-name seams; preserve standalone defaults, private services and shared-ingress separation. Do not turn the existing complete local Planning stack into a mandatory hosted-collaborator laptop stack |
| TICKET-00023 / TASK-00075 / TASK-00076 | Reuse guided operation, truthful capabilities and direct supported-platform qualification. Propose easy-install profiles, explicit enrollment, clean-machine/container and sleep/wake/restart evidence; exact reusable packaging/distribution and scoped-resolver supplier remain unallocated |
| TICKET-00022 / TASK-00063 | Qualify canonical HTTPS/FastCGI/origin/cookies, base/wildcard trust, exact consumers, CA custody, expiry/renewal/revocation/removal and safe failures; no production-envelope acceptance from local configuration |
| Existing private shared-infrastructure owner | Preserve documented independent registry, validation/apply/recovery and startup/maintenance contracts. Any resolver extension, adoption/distribution or ownership migration needs separate owner authorization; this map changes no external project |
| WF-047 / TICKET-00061 | Resolve hosted-server/laptop identity, authenticated enrollment/revocation and per-use task/repository/execution authority. DNS enrollment, a trusted local certificate or an SSH login grants none of those capabilities |
| WF-052 / WF-053 | Future domain UI consumes qualified protected operations; it is not required for bootstrap/recovery. Infrastructure migration and legacy retirement remain separate decisions and authority |

Rows are proposed reuse/amendment boundaries, not delivery edits, new suppliers declared delivered or approval of
future EPICs. Exact resolver/OS integration, installer/profile ownership, machine lifecycle hooks, protected apply
participant and certificate policies must be mapped and qualified before affected implementation is called ready.
No new WF is needed solely to repeat those concrete delivery gaps. Preserve Board priority, existing holds and
TASK-00510–00512; no unrelated closed map is reopened.

### Verification and stopping point

Only planning and relevant documentation/configuration were inspected; the private shared-infrastructure README
was rechecked without reading credentials, generated inventory or certificate stores. No DNS/hosts, certificate,
trust, proxy, startup, Docker, runtime, build, provisioning, deployment or application-provider effect occurred.
`./bin/planning-check --write`, `./bin/planning-check`, tracked diff and all changed/new Wayfinder whitespace/scope
checks passed; 645 delivery records, 496 active, generated views current. Existing delivery records remain unchanged.
No full application build, live lifecycle/route/trust/container qualification or independent acceptance was run.
All work remains uncommitted in the isolated planning checkout.

The next authored frontier is [WF-047 — Bound remote harness, trusted Git and candidate execution](WF-047-bound-remote-harness-git-and-candidate-execution.md).
Stop after this decision; do not begin the remote-authority interview automatically.

Next: `/skill:wayfinder work WF-047`
