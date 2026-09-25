# Define developer onboarding and operator guidance

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-014](WF-014-define-skill-trust-and-harness-distribution.md), [WF-016](WF-016-define-runner-dispatch-and-recovery.md), [WF-023](WF-023-define-local-runtime-and-shared-ingress-topology.md)

## Question

What versioned, browser-accessible guidance should help a new developer safely prepare Git, source-host credentials, Pi, the customized harness, repository checkouts, and an authorized runner?

## Must decide

- Information architecture for a durable end-user guide, contextual Dashboard help, official-documentation links, prerequisites, and troubleshooting.
- Supported operating systems and the commands or observable checks that establish compatible `git`, `gh` or other source-host tooling, Docker, Pi, and development-tool versions.
- Secret-safe SSH key creation and source-host enrollment guidance, including ownership, permissions, passphrases, agents/keyrings, verification, rotation, and revocation without collecting private key material.
- Pi installation, provider authentication, project trust, versioned package/bootstrap setup, repository-context verification, updates, rollback, and offline limitations according to WF-014.
- Runner enrollment, repository checkout mapping, capability checks, availability, cancellation/recovery expectations, and de-registration according to WF-016.
- Separation between documentation, copyable diagnostic commands, browser-triggered application operations, and setup mutations that always require explicit user authorization.
- Versioning, source citations, review cadence, stale-link/version detection, accessibility, and safe examples for less experienced developers.
- Whether platform-specific breadth warrants a focused child map without duplicating the accepted harness or runner contracts.

## Resolution boundary

This decision defines onboarding journeys, documentation ownership, safety boundaries, and a future planning handoff. It may use cited research and disposable content prototypes, but it must not install software, generate or upload credentials, change personal/global configuration, enroll a runner, register a repository, or write production guide pages.

## Preferences required

John must choose the initial supported platforms, expected experience level, desired balance between explanatory guidance and copyable commands, and whether the Dashboard should offer guided checks in addition to static documentation. The recommendation should begin with the actual personal installation path, link to primary vendor documentation, explain every credential boundary, and keep mutations explicit and reversible.

## Resolution

### Audience, platforms, and support claims

Target developers who are comfortable using a terminal but may be new to one or more prerequisites. Explain purpose,
credential boundaries, expected outcomes, and recovery without turning Agent OS guidance into a general shell, Git, or
Docker course. Link primary vendor and distribution documentation for deeper teaching.

Support Linux and macOS initially. Treat John's Omarchy/Arch installation as the verified Linux reference and claim
macOS verification only after John completes a real installation run on his Mac. Provide clearly labelled best-effort
Linux installation adapters for Arch-family `pacman`, Debian/Ubuntu-family `apt-get`, and Fedora/RHEL-family `dnf`.
Never present an untested platform, version, package-manager path, or command as verified. Promote another platform to
verified support only from recorded real-environment evidence.

This breadth remains one coherent onboarding handoff. Do not create a platform child map now. Open one later only if
real qualification exposes incompatible credential-store, Docker, networking, or runner behavior that cannot fit the
common contract.

### Authoritative guide and information architecture

Keep one versioned, reviewable repository documentation source and render it in the Dashboard. Dashboard prerequisite,
installation, runner, repository, update, rollback, and troubleshooting surfaces link directly to the applicable
section. Browser AI may explain or navigate the guide only while identifying its revision and primary citations; it
is not an uncited competing authority.

Organize the journey around tasks: identify platform and compatibility; check prerequisites; install missing tools;
prepare source access; install and authenticate Pi; install the exact Harness release; configure and bootstrap the
local runtime; register a repository; verify repository context and capabilities; then operate, update, recover, or
remove the installation. Each step gives a concise reason, copyable command where appropriate, expected result,
read-only verification, recovery or rollback, and primary-source link. Use semantic headings, lists, tables, command
labels, and warnings; preserve keyboard use, reflow, readable text, and meaning without color.

### Checks, installation, and mutation authority

Use a two-stage check model. Before bootstrap, a copyable read-only terminal diagnostic reports pass, warning, failure,
observed version, and safe remediation for operating system, architecture, Git, source-host tooling, Docker, Node,
npm, Pi, credential-store availability, and required connectivity. After bootstrap, the Dashboard shows authorized
application checks and runner-reported versions, capabilities, approved-root visibility, checkout availability, and
connectivity. The browser must not pretend it can inspect an unenrolled host.

Provide tested platform-specific install commands where stable, alongside official instructions and explicit GUI
steps such as Docker Desktop. Do not silently install software, run opaque bootstrap pipelines, normalize `curl | sh`,
obtain host privileges, edit mounts, trust paths, or mutate personal/global configuration as a consequence of a
check. A human runs every setup mutation in a terminal and responds directly to operating-system prompts. Commands
are explicit, scoped, reversible where possible, and report partial effects honestly.

### Source access, Pi, and Harness

Prefer SSH keys for GitHub Git operations and authenticate `gh` separately. Document HTTPS through `gh` as a supported
alternative. Teach key ownership, permissions, passphrases, key agents or keyrings, verification, rotation, and
revocation without collecting or displaying private keys, tokens, or passphrases.

Use Pi's npm installation path and verify its Node prerequisite. Explain Pi's provider-neutral `/login` journey, with
John's `openai-codex` setup as the first reference to qualify rather than a required provider. Agent OS never receives
or inspects provider credentials. A check may identify the selected provider and model but not token values.

Install one exact released Harness npm version according to WF-014. Explain project trust before accepting project
packages or executable resources, verify the active connection and repository/Harness snapshots, and make updates and
rollback explicit. A local `harness/` checkout remains visibly mutable development mode rather than the routine user
path.

### Runtime, runner, and repository boundary

Do not invent a separate runner-pairing ceremony for the initial installation. The explicit installation bootstrap
configures approved workspace roots and creates the durable Runner identity and credential; `bin/up` starts the
containerized `agent-runner`; recreating its container preserves that identity. The Dashboard reports health,
capabilities, versions, availability, and recovery state.

Repository registration is separate. It associates a repository and checkout with available approved roots and
validates identity, containment, Git evidence, context, and runner visibility without granting execution or migration
authority. Runner disablement, credential rotation, de-registration, checkout remapping, and installation removal are
explicit operator actions with preserved history and recovery guidance.

### Credential and connectivity boundaries

Keep Git SSH keys under normal user SSH/key-agent ownership and provider credentials under Pi/provider ownership.
Store interactive Harness Agent credentials only through macOS Keychain or a compatible Linux Secret Service; fail
closed with setup guidance if neither is available. Do not provide a plaintext fallback. Installation-owned container
secrets may use the already accepted narrowly permissioned ignored files where host keychain access is impractical,
and Compose mounts each secret only into services that need it.

Initial installation, authentication, package retrieval, updates, and repository registration require their relevant
network services. Existing installations may use only the cached capabilities accepted by WF-014 while offline.
Explain each connectivity dependency separately and never imply that cached Pi or Harness resources make provider or
server-authoritative operations available.

### Troubleshooting, freshness, and verification posture

Produce a detailed, copyable, secret-safe text diagnostic report that a user may paste into any AI harness. It may
include commands, versions, pass/warn/fail outcomes, relevant non-secret configuration state, and remediation context.
It excludes credentials, key material, credential-store contents, raw environment dumps, arbitrary logs, and
unnecessary personal paths. Nothing uploads automatically.

Version the guide with a compatibility matrix, source citations, and explicit last-verified platform/tool evidence.
Review affected sections whenever supported Agent OS, Harness, Pi, Docker, Node, GitHub CLI, Linux, or macOS assumptions
change. Compare observed versions with declared ranges and show unsupported or unverified status honestly. Manually
review primary links and real commands during relevant release work rather than adding documentation tests or a link
checker to the application gate.

Test owned onboarding application behavior and important integration contracts only. Review documentation directly
and qualify commands on real supported environments. Do not add product-suite tests for documentation, setup wrappers,
installation scripts, architecture, dependency rules, container or configuration wiring, generated files, migrations,
diagnostics, build/planning/static-analysis mechanisms, or test infrastructure. Do not manufacture missing-tool or
seeded-failure fixtures to test checkers. Keep onboarding diagnostics out of `./bin/build` unless a phase directly
validates repository-owned source or behavior; inspect and execute infrastructure with its owning tools.

Exact compatible versions, command names, UI components, documentation file layout, diagnostic implementation,
credential-store adapter, and release evidence format remain downstream planning details. This decision installs
nothing, creates no credential or Runner, registers no repository, writes no production guide, and creates no EPIC,
requirement TICKET, implementation TASK, or child map.
