# Define the future local-domain management flow

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Private production rollout](../private-production-rollout-map.md)
**Depends on:** [WF-046](WF-046-define-optional-local-routing-trust-and-recovery.md), [WF-047](WF-047-bound-remote-harness-git-and-candidate-execution.md)

## Question

What optional Fight Agent OS screen and authenticated local component could manage a selected machine's project
routes with minimal manual work without giving the web application unrestricted host administration?

## Must decide

- Select project, existing/new checkout/worktree, hostname and destination. Detect conflicts and show a complete
  preview with current machine/component identity, route ownership and exact intended effects. No application or
  checkout instance is created merely because a hostname is selected.
- Separate plan, certificate preparation, proxy validation, administrator/hosts action, apply/reload and verified
  result. Provide a narrowly scoped administrator operation or copyable hosts change where required; never
  collect sudo passwords or give browser/Agent processes arbitrary host commands/Docker-socket access.
- Explain how production addresses the selected local machine through an enrolled authenticated local component:
  destination binding, current user/device authority, revocation, replay protection, explicit effect approval and
  offline/uncertain recovery. Cloud code cannot directly edit the Mac's hosts file or trust store.
- Report DNS, certificate/trust, proxy and application health independently. Persist safe desired/result facts
  under existing owners; keep machine inventory, paths, credentials, leaf/CA state and generated routing private.
- Bind previews to expected configuration/revisions and operation identity. Reconcile partial hosts/proxy/trust
  outcomes; support recovery if the dashboard, cloud or local component disconnects midway. Never infer success
  from an accepted command, or retry by silently creating duplicate routes/certificates.
- Remove an owned route without deleting app data, volumes or unrelated certificates. Shared proxy continues
  serving other projects during dashboard failure/upgrade/stop, with independent lifecycle/recovery commands.
- Decide which capabilities are generic installation seams versus private infrastructure integration, and which
  existing owner needs an amendment versus a future new destination. Preserve normal contributor startup and CI.
- Explicitly defer implementation until selected as a separate outcome. This screen and local resolver automation
  are not prerequisites for the first private production browser journey.

## Resolution boundary

Explore the workflow and ownership through Wayfinder interview; disposable design/prototype evidence would need
its own bounded authorization. This session authorizes no implementation, enrollment, certificate generation,
privileged host action, proxy/DNS change, Docker operation or new delivery record.

## Resolution

Closed on John's approved deterministic browser/Pi project-setup journey, including explicitly selected database
initialization/migrations, with WF-046/047's existing local authority/recovery boundaries carried forward. This is
an optional future product outcome, not implemented capability or authority to install dependencies, start
containers, migrate a database or change local routing now. Delivery remains separately selectable.

### Approved browser and Pi project-setup journey

John wants to select a project, or create one then select it, from either the Agent OS browser UI or a terminal Pi
session. A form/wizard walks through the proposed local hostname and application destination, then approval of
automated Composer installation, npm installation and Docker Compose startup. Agent assistance must not be
required: ordinary execution is scripted and deterministic, not a model selecting commands or fixing errors.

- Provide browser and terminal entry points to the same setup behavior and original operation/progress, not
  independent provisioning engines. The terminal Pi experience must work without an inference request or an
  available provider. Exact UI/extension/API implementation is not selected or claimed supported here.
- Select the explicit machine, project and checkout. Existing projects use their existing registration/link
  contracts; creating a new project reuses the approved Create Project journey, rather than silently generating
  files when a hostname is selected. Project selection alone starts no installation or application.
- Preview the hostname, destination and route effects using WF-046's approved naming, ownership and conflict
  rules. Separately show and obtain approval for the executable setup plan: applicable Composer/npm dependency
  steps and Docker Compose application startup, with the selected checkout, exact declared commands, relevant
  configuration and anticipated filesystem/network/service effects. Do not prompt again between unchanged
  approved ordinary stages. Preserve explicit local privileged setup where needed; do not collect sudo input.
- Use the repository/starter's declared supported setup recipe and repository wrappers, not universal hardcoded
  commands or AI guesses. Respect applicable lockfiles and configuration; unsupported/missing recipes produce
  an actionable explanation. Dependency installs can execute scripts/plugins; Compose can build images, mount
  files, create volumes/networks and expose ports. Approval is for those bounded effects, not arbitrary commands,
  dependency upgrades, missing-host-tool installation or production/staging access.
- Browser-originated work runs on the selected authenticated local component, not the application Droplet or an
  assumed browser loopback. Pi is another human-facing entry, not a grant of shell/Docker authority to managed
  coding Agents. Retain WF-047's protected delivery and current local-authority boundaries. Machine/root/runtime
  setup and its missing qualified supplier remain distinct from merely scripting the project commands.
- Reuse successful preparation from a new-project attempt instead of running Composer/npm twice. Preserve original
  intent and verified stage results, retain partial state, and offer explicit reconciled retry/cancel/cleanup;
  reconnect never launches a duplicate install or Compose project. No mandatory support Agent or paid fallback.
- Report dependency preparation, application readiness, DNS, TLS and proxy status separately. Successful commands
  or a running container alone do not prove a working local application. Route removal must not delete application
  data, and independent infrastructure recovery remains available without the dashboard or Pi session.

### Existing-owner trace and proposed extensions

[WF-021](WF-021-define-new-project-creation-and-registration.md) already approves deterministic creation without
an AI model, one exact preview, recoverable stages and optional support. Reuse that closed decision rather than
reopen its starter, Git-history, visibility or publication choices. Relevant existing requirements were read:

- TICKET-00100 supplies fresh-project generation and declared preparation; TASK-00409's protected preparation
  supplier remains held. It explicitly excludes adopting existing content, so existing-checkout setup is a later
  proposed extension/allocation, not silently covered by this ticket.
- TICKET-00101 owns the browser creation preview and durable attempt; TICKET-00103 owns created-checkout
  registration/completion using TICKET-00024/00025. Terminal/Pi presentation, routing and ongoing app startup need
  explicit later coordination, not a second attempt/identity service or an assumed existing delivery commitment.
- TICKET-00023 retains approved-root, installation guidance and capability boundaries. WF-046's shared resolver,
  proxy and independent recovery direction remains; its existing local Planning stack is not automatically the
  hosted-collaborator laptop profile or a general per-project application provisioner.

No owner amendment, EPIC/TICKET/TASK creation, prototype, host operation, provider call or Pi implementation is
authorized. Existing holds and Board order stay unchanged. The wizard remains an optional separately selected
outcome, not a first-browser production gate.

### Approved optional database initialization and migrations

John agreed to offer database initialization and migrations as **explicitly selected wizard steps**, with
**destructive resets and seed/sample-data loading excluded by default**. Include the selected stages in the
executable plan and approval; unchanged approved stages run without repetitive prompts or an AI model.

Use the selected project's declared commands and its exact local database target. Show whether existing data is
present and the known initialization/migration effects; a migration label is not a guarantee of nondestructive
behavior. Unknown target/ownership, material drift or effects outside the approved plan require a visible stop,
not a guessed database, production/staging fallback or automatic reset. Do not expose credentials in previews/logs.
A reset or sample-data operation, if separately offered later, needs its own explicit selection and impact approval;
ordinary initialization/migration consent does not imply it.

Order stages according to the project's declared prerequisites, including starting database services before
commands that need them. Preserve completed effects and reconcile migration state before a retry; never infer
rollback from failure or blindly rerun an uncertain migration. Report database preparation separately from
container/application readiness. Skipping required initialization leaves that limitation visible, not a false
ready result. These are ordinary setup/recovery requirements, not a new certification or readiness project.

### Carried-forward local protocol and ownership boundary

WF-046/047 already settle the relevant architecture and change-safety policy; this closure does not reopen it:

- The hosted service coordinates with the explicitly selected authenticated local participant through its
  approved outbound connection. No inbound laptop administration, public/LAN development exposure or browser
  access to a Docker socket is granted. Current human/machine/project/checkout authority is checked at effects,
  not inferred from a hostname, profile, queued message or prior sign-in.
- Bind the preview to operation identity and expected configuration; reconcile the original operation across
  disconnect/restart and reject stale/replayed effects. Offline/unavailable/uncertain is not success. Existing
  revocation and interruption rules remain; reconnect is not new consent or an automatic retry.
- Keep privileged resolver/trust/root setup explicit and independent of ordinary project stages. Reuse qualified
  existing workstation CA custody and project leaf coverage; neither browser nor Agent receives signing keys or
  sudo credentials. Source commands cannot expand the confirmed permissions through a setup manifest.
- Carry forward fail-closed routing, separate DNS/TLS/proxy/application observations, persistent infrastructure
  maintenance stop and independent recovery. Remove only owned routes; never silently remove data, other projects'
  routes or shared trust. Store only authorized necessary progress/configuration facts, not secret/raw machine
  inventories in publishable planning or unrestricted diagnostics.
- Generic project selection, creation, setup planning and progress belong to existing product owners through later
  explicit amendments. OS-specific resolver/trust/proxy integration remains the installation/shared-infrastructure
  seam; John's private settings are not a distributable implementation. Contributor startup and CI remain independent.

Exact transport/API schemas, script manifests, Pi UI integration, OS support and protected execution suppliers are
later implementation/qualification details, not additional speculative human gates. The existing-owner trace
above identifies the reuse and scope gaps without claiming those owners already deliver these extensions. No new
EPIC destination or EPIC/TICKET/TASK is approved or created; all existing source/runtime holds remain in force.

Closure records planning only. No package installation, container start, database operation, DNS/trust change,
account/credential access, prototype, application build or runtime probe occurred. Independent Review/QA and real
ordinary delivery checks remain later work. WF-053 retains legacy ownership and retirement; no transition follows
from this wizard decision.

Next: `/skill:wayfinder work WF-053` — bounded legacy capability/replacement inventory; not automatically started.
