# Private production rollout: bounded ownership and configuration baseline

## Question

Which existing requirements and inspected configuration own the requested private rollout, and what must be
amended or qualified without creating a duplicate delivery backlog?

## Short answer

Existing owners cover protected generation, durable conversations, browser Planning, bounded assistance, recorded
usage, installation, Agent authority, security, credential delivery, observability and managed deployment. They
are not proof of a working production installation. John's approved **per-user OpenRouter browser credentials**
conflict with explicit installation-key language in TICKET-00089 and TICKET-00104. Local Pi generation remains
independent. TASK-00399 still lacks a qualified allowance source and mapped protected refresh supplier.

This is a bounded source/configuration inspection on 2026-10-23, not a full code audit or external provider/hosting
qualification. The approval lives in [WF-043](../tickets/WF-043-bound-private-release-and-environment-names.md);
[the map](../private-production-rollout-map.md) owns the selected frontier.

## Findings

### Agreed requirements, not implementation evidence

- First users: John, then likely his wife and son. Each browser user supplies their own OpenRouter key, held in
  protected server-side storage. No key/account sharing is implied. Local development keeps existing providers.
- Remote ChatGPT OAuth is optional; neither local Pi authentication nor a subscription tier proves eligibility,
  model/tool support, allowance visibility or remote integration. No current ChatGPT eligibility finding has
  been verified in this session.
- Production/staging/local names retain their complete production identity. Two different future Nickell
  applications must not collapse into the same shortened local name. Approved names are in WF-043.
- No source/planning-owner changes, infrastructure effects, account operations or production go-live are authorized.

### Existing ownership and proposed amendment register

| Existing source | Current contract / evidence | Proposal for later authorized planning |
|---|---|---|
| [TICKET-00089](../../tickets/00089-TICKET.md) | Installation-held OpenRouter key; local Pi `openai-codex`; explicit choices, bounded disclosure/spend, protected original outcomes | Add per-user browser connection ownership and selection without deleting local development support. Reassess TASK-00343–00346 consumers; do not rewrite them now |
| [TICKET-00104](../../tickets/00104-TICKET.md) | Creation-specific advisory help explicitly uses an installation-held OpenRouter server key | Align this browser consumer and TASK-00428 with per-user connections; retain deterministic help/recovery without a model |
| [TICKET-00107](../../tickets/00107-TICKET.md) | Bounded instruction suggestions reuse provider/conversation owners; no autonomous save | Verify inherited connection/user isolation after provider amendment; do not broaden into general chat |
| [TICKET-00090](../../tickets/00090-TICKET.md), [TICKET-00091](../../tickets/00091-TICKET.md), [TICKET-00092](../../tickets/00092-TICKET.md) | Existing durable conversation, proposals and browser Planning owners | Bind exact initiating user/connection/configuration to attempts and linked continuations; choose one journey without a second engine |
| [TICKET-00098](../../tickets/00098-TICKET.md), [TASK-00399](../../tasks/00399-TASK.md) | Recorded usage separate from independently scoped account/key allowance; qualified source/protected refresh mapping still missing | Identify supported source, units/reset/freshness and safe refresh owner; propose exact supplier mapping only when known. Do not clear `needs-info` or infer balances |
| [TICKET-00023](../../tickets/00023-TICKET.md) | Installation-owned runtime, approved roots, guidance, capability checks; explicit human-run privileged effects | Determine generic configuration seams versus optional private overlay and local component. Do not duplicate installation authority |
| [TICKET-00061](../../tickets/00061-TICKET.md) | Current authenticated Agent tool use, protected broker/first-party MCP, per-use authority and replay protection | Qualify remote harness/local-machine binding and exact missing participants. MCP is transport, not permission or authoritative allowance proof |
| [Tool-mediated execution](../../../docs/engineering/TOOL_MEDIATED_EXECUTION.md), [terminal contract](../../../docs/engineering/TERMINAL_EXECUTION.md) | Trusted application owns Git/check operations; scoped Agents do not receive shell, host Docker socket or provider credentials | Decide trusted Git location and separately qualified candidate-check locations; retain production state/policy ownership and resource/secret separation |
| [TICKET-00102](../../tickets/00102-TICKET.md) | Initial GitHub publication uses protected source-host authority; prefers SSH; GitHub API/CLI operations remain distinct | Reuse appropriate adapters, but do not call initial publication a general fetch/push/PR/check owner. Resolve dedicated SSH scope versus login keys and GitHub API rights |
| [TICKET-00017](../../tickets/00017-TICKET.md), [TICKET-00022](../../tickets/00022-TICKET.md) | Authentication recorded `done`; integrated production security envelope remains a separate accepted requirement | Requalify actual deployment ingress/origin/cookies/proxy/secrets, not infer production acceptance from login |
| [TICKET-00011](../../tickets/00011-TICKET.md) | Completed recoverable credential effects exclude production enrollment and scheduling | Identify production mail adapter and protected scheduling/operator suppliers; use existing package queue/recovery, not another outbox |
| [TICKET-00058](../../tickets/00058-TICKET.md) | Managed project-specific deployment requires qualified runbook, exact candidate/effect and health/recovery approval | Separate Fight's first operator-run deployment from completion of the entire managed Release Manager product; identify actual runbook supplier |
| [TICKET-00117](../../tickets/00117-TICKET.md) | Installation/recovery of accepted diagnosis capability; independent health, finite resources and qualification gates | Map launch operations into existing owners; no silent scope reduction of full observability requirements or claim that Loki is already deployed |

Rows identify candidate amendments or qualification needs, not approved owner edits. Existing TASK priorities,
`needs-info` holds, dependencies and closed-map approvals are unchanged. Foundation summaries contain historical
snapshots; current owning record metadata, not README prose alone, supplies the observed status above.

### Inspected local configuration

The private shared Local Development project's README documents domain registration, certificate preparation,
NGINX validation, recovery and login startup. It describes shared IPv4/IPv6 routing on `127.0.0.2` and `::1`, with
Herd retaining its separate `127.0.0.1` listener. The managed hosts block inspected in this session matches the
previously reported existing legacy entries. Installed user startup configuration names the shared supervisor at
30-second intervals; installed system configuration names the alias keeper at 60-second intervals.

Those files establish **documented/installed configuration**, not a loaded/healthy service, actual listener
ownership or successful recovery. No Docker, browser, TLS, route, startup or network health test was run. Earlier
Docker unavailability was not retested. Machine-specific source paths, live inventory and generated state are
intentionally omitted from this publishable note; private operator sources are not repository dependencies.

Fight's inspected repository configuration is concrete:

- [compose.yaml](../../../compose.yaml) defines optional NGINX/PHP-FPM HTTPS published on loopback port 18443,
  plus separate development/test PostgreSQL and an opt-in mail catcher. This is not a selected production topology.
- [bin/https](../../../bin/https) fixes `https://localhost:18443`, generates a localhost leaf through explicit setup
  and invokes CA trust installation. None of its setup/start/status commands was run here.
- [default.conf](../../../etc/docker/https/default.conf) rejects other HTTP authority and fixes FastCGI host,
  name, scheme and port. A new hosts entry or proxy alias alone cannot adapt the application security envelope.
- A hosts file maps exact names to addresses, not ports or redirects. NGINX routing, local resolution and
  certificate trust are independent responsibilities. Generated names require either exact entries or a
  separately qualified, narrowly scoped resolver; resolver automation does not create applications/proxy routes.
- The intended certificate contract covers `local.fight-agent.dev` **and** `*.local.fight-agent.dev`.
  A one-level wildcard alone covers neither its base name nor deeper nested names. Keep CA signing keys separate
  from proxy leaf keys; future qualification must include browser/test-container routing and trust.
- Docker loopback belongs to the container. No browser or test-container route/trust configuration is qualified by
  inspecting host configuration. Ordinary contributor startup and CI must not require this private setup.

### Naming source and recommendation history

[RFC 6761 §6.2](https://www.rfc-editor.org/rfc/rfc6761.html#section-6.2) reserves `.test` from ordinary registration,
permits private use and warns that meanings may differ across networks. Thus the concern here is application-name
aliasing when shortening both Nickell production domains, not an imminent public `.test` allocation collision.
The RFC's official text was fetched during this session; no resolver or DNS change was made.

John approved production-matching `local.` names after the `.test` discussion. Preserve `.test` as a valid earlier
alternative and avoid `.local` because of multicast DNS/Bonjour. Current proxy/certificate/resolver mechanisms
remain decisions, not consequences of naming approval.

## Sources

- Repository sources are linked at their owning rows above; root [AGENTS.md](../../../AGENTS.md),
  [planning conventions](../../CONVENTIONS.md) and [Wayfinder instructions](../../../.pi/skills/wayfinder/SKILL.md)
  govern this planning session.
- Private operator sources: shared Local Development README, managed hosts block and installed startup plists,
  inspected read-only. Not shipped, linked into runtime or reproduced as machine inventory.
- [RFC 6761, Domain Name Reservation Considerations for test](https://www.rfc-editor.org/rfc/rfc6761.html#section-6.2).
- User's initial destination, subsequent per-user-provider/naming clarification and explicit approval are the
  authority for scope decisions, not evidence of working integrations.

## Open questions

- [WF-044](../tickets/WF-044-qualify-per-user-openrouter-and-allowance-ownership.md): supported OpenRouter facilities,
  per-user protected connection/refresh ownership, account/key semantics and precise proposed amendments.
- Hosting prices, regions, database editions, staging/backup/HA and operational costs have **not** been researched;
  the initial Droplet recommendation is not a selection. No Kubernetes need is established.
- Eligibility, OAuth lifecycle, preview tool/model restrictions and allowance semantics for remote ChatGPT remain
  optional research. User-mentioned subscription prices are not independently verified plan receipts.
- Exact delivery blockers, legacy capabilities/replacements and private shared-proxy operating health require
  further bounded evidence. Do not substitute historical evidence or an installed plist for live acceptance.

## Later verification, not authorized or executed here

- Finite, separately authorized OpenRouter key storage/rotation/revocation, user-isolation, model/usage and safe
  allowance refresh checks; duplicates, retries, cumulative streaming, uncertainty and account-scope denials.
- Local resolver isolation, proxy conflict/apply/reload/recovery, CA/base/wildcard and container trust/routing;
  contributor/CI startup without the optional infrastructure; preservation of every existing route.
- Staging parity, environment-isolated cookies/data/secrets/budgets, real credential-email delivery in production,
  migration/rollback rehearsals, backup restoration, application/data recovery and explicit go/no-go evidence.
- Authenticated local-component/harness revocation, protected Git credentials and isolated candidate checks if
  that optional capability is selected; no cloud browser host administration or Docker-socket access.

Optional Jev discovery screening is now explicitly permitted. Eight evaluator calls before that clarification
reported approximately $0.001097; the assistant disclosed the initial boundary mistake in-session. This is not
application-provider qualification, and authorization for screening does not authorize generation/account calls.

## WF-044 follow-up

The baseline above preserves what was known at charting. Subsequent
[WF-044 primary-source research](WF-044-per-user-openrouter-and-allowance.md) documents ordinary-key `/key`
limits/budget layers/free quota and management-only account credit totals. It proposes TASK-00346 as protected
credential/key-info supplier and TASK-00399 as refresh/observation owner, with TASK-00343 remaining nonsecret.
These mappings/amendments are not accepted delivery changes; self-service lifecycle/UI and protected runtime
participants still need allocation/qualification, and existing holds remain. WF-044 closes factual research,
not source acceptance or owner amendment. At WF-044 closure the map frontier was WF-045.

## WF-045 follow-up

The baseline's earlier hosting unknowns are now bounded by
[WF-045's dated public comparison](WF-045-digitalocean-hosting-and-costs.md). Separate Droplets/Managed PostgreSQL
are recommended for discussion, not selected. Comparable reduced-staging floors are $87.10/month Droplet and
$77.30 App Platform USD pre-tax, excluding unknown complete operations/recovery costs. Standard DB standby
eligibility is changing; actual Advanced shared/HA quote, accepted full diagnosis capacity and complete launch
cost remain unqualified. WF-048 must obtain platform/region/staging/budget selection; WF-050 retains recovery,
full operating cost and go/no-go evidence. No owner amendment, provisioning or budget is approved. At WF-045
closure the map frontier was WF-046; the baseline above preserves charting-time evidence rather than live health.

## WF-046 follow-up

[WF-046](../tickets/WF-046-define-optional-local-routing-trust-and-recovery.md) closes John's approved local-operation
policy: shared scoped workstation-only DNS across registered project namespaces, fail-closed routing, stable
TASK/ten-hex Checkout fingerprints, protected per-machine CA custody and project base/wildcard leaves. Guided
onboarding belongs in eventual easy-install, with explicit privileged steps and independent recovery. Enrolled
infrastructure startup/recovery covers login/readiness, sleep/wake, shutdown/reboot and runtime restarts; durable
maintenance stop persists until explicit resume. No automatic startup of every app or sleep/pre-login availability
is promised. The shared infrastructure README was rechecked as documentation, not live qualification.

Reuse existing installation/security/shared-infrastructure owners through later authorized amendments. Exact
resolver/OS, distribution/laptop-profile, protected apply and TLS/container/lifecycle suppliers remain unallocated
or unqualified. Hosted Planning/task management with contributor laptop checkouts is an intended use case; exact
authenticated connection and authority remain WF-047. Local previews are not LAN exposure or authority. No
implementation, runtime change or new delivery record follows; at WF-046 closure the frontier was WF-047.

## WF-047 follow-up

[WF-047](../tickets/WF-047-bound-remote-harness-git-and-candidate-execution.md) closes architecture/policy with
hosted coordination, optional human TASK responsibility, pinned latest-`develop` source context, central identity/
reference allocation and read-only hosted Git. Contributors retain feature/PR publication and local Agent bash
checks until a qualified MCP/browser replacement. Browser provisioning integrates protected recipient binding
and credential delivery/activation; MCP authenticates each request without another sign-in. Existing recovery
requirements carry forward without a separate approval ceremony.

Actual remote source retrieval, first-recipient proof, outbound delivery/receipts and bash-safe storage/signing
remain unqualified, with existing owners retained. Human-assignment amendments and future MCP/browser CI supplier
allocation require later authorized planning; no delivery records, runtime, credentials or current Markdown
authority changed. Dedicated managed workers and managed Git writes remain deferred. At WF-047 closure the
frontier was WF-048 for real hosting/staging cost and promotion tradeoffs, not repetitive safety-policy confirmations.

## WF-048 follow-up

[WF-048](../tickets/WF-048-define-staging-and-artifact-promotion.md) closes the policy/owner handoff: separate SFO3
application Droplets and Managed PostgreSQL clusters, smaller parity staging, non-HA production conditional on
verified backups/recovery, and a combined below-$100/month infrastructure/operations preference if feasible.
The earlier $200 discussion figure is superseded, not approved; no temporary reserve or complete quote exists.
Operator approval promotes the exact staging-validated artifact. Optional human UAT is project-specific and does
not replace technical Review/QA. Controlled live staging tests use per-user connections and independently set
environment provider budgets, likely higher in production, with no numeric caps or spending authorized.

Existing-owner re-reads confirm production packaging/operator runbook allocation is missing: local TASK-00072
excludes production deployment, TASK-00063 excludes public TLS/proxy enrollment, and completed TICKET-00011 excludes
real-provider enrollment/scheduling. TASK-00212/00215 consume rather than supply the concrete package/runbook;
TASK-00222's target qualification and managed certification holds remain. TASK-00374 retains private SeaweedFS;
TICKET-00117 retains physical isolation/capacity/supervision/recovery holds. TASK-00344/00345 preserve admission and
original accounting; TASK-00346/00350/00399 retain protected runtime/source holds and proposed amendments.

The inspected development Compose PHP server, source mounts, PHP 8.5.12 FPM/Xdebug/local configuration and
PostgreSQL 17 are not a production artifact. WF-048 records required asset/export/runtime parity, isolation and
exact promotion/migration/health evidence, none freshly qualified here. Sub-$100 feasibility remains unknown;
WF-050 owns complete cost and restore/key/object/RPO/RTO acceptance, without silently removing diagnosis scope.
No new delivery records, runtime effects or independent acceptance occurred. Planning checks are the verification,
not an application build or live-provider/hosting qualification. At WF-048 closure, WF-049 was the next frontier.

## WF-049 follow-up

[WF-049](../tickets/WF-049-select-first-browser-journey-and-switching-behavior.md) closes browser Wayfinder Planning
as the first journey, paired with the file-to-database transition for production rollout. John explicitly rejects
new readiness TASKs/tests or a demand for production to exist before implementation/first deployment. Preserve
ordinary checks, actual migration approval and agreed recovery precautions; assess real use and handle observed
defects as requested bugfix work, not speculative certification.

Select current OpenRouter models through server-side on-load/light-cache discovery with explicit refresh and
scope-authorized default updates. Defaults affect future conversations; between-turn model changes use the existing
linked continuation with retained history and proposal revalidation. Model IDs, numeric caps/reset windows and
suggested cache TTL remain configuration, not fixed names or new spending approval.

Usage presentation carries existing honest accounting into conversation summaries/details: reported, estimated,
partial and unknown quantities remain distinct; application environment budgets are not provider allowance.
Original attempts preserve liability through cancellation/reconnect/switching without duplicate stream totals.
Missing allowance is visible/unavailable, not a blocker on useful Planning or a claim that TASK-00399 is complete.

Owner re-reads confirm TASK-00396 and TASK-00391 cover Workflows, not standalone Planning usage. Proposed later
allocation reuses TICKET-00090/00092 safe conversation queries/UI with TASK-00345 original facts and TICKET-00098
coordination; no dummy Workflow or second ledger. TASK-00343 remains nonsecret catalog policy, TASK-00346 is the
proposed protected discovery/key-info adapter, and exact default/lifecycle surfaces remain owner work. Existing
TASK-00346/00350/00399 holds remain; TICKET-00028 retains real atomic cutover, not a newly invented migration gate.

No new research/provider/screening call, implementation, test, credential, migration or runtime effect occurred.
Only planning validation supplies fresh verification here. WF-050 is the next practical cost/recovery/operator
frontier; no production-readiness framework or decomposition is started.

## WF-050 cost and hosting follow-up

John selected a hybrid database arrangement: production retains Managed PostgreSQL; staging runs PostgreSQL on its
own app Droplet. This explicitly supersedes WF-048's two-managed-cluster illustration while preserving environment
isolation, database major/extensions/schema alignment and exact-artifact promotion. Staging's managed-operation
parity is limited and must be represented honestly, not hidden behind a new readiness framework.

Backups use **Spaces-only**; an additional B2 copy is not selected. John accepted the explained shared-provider/
account exposure for simplicity. Thirty-day daily recovery history remains cost-conditional; native seven-day
production recovery and boot snapshots do not supply that complete database/files/key history by themselves.

Plan **2 GiB RAM / 50 GiB SSD app Droplets for both environments**. John favors the larger staging option to allow
for PostgreSQL alongside PHP, workers and other services. The [updated comparison](WF-050-backup-storage-and-restore-costs.md)
calculates **$56.35/month** including one managed DB, boot backups, Spaces and registry, excluding remaining costs
and tax. The historical $48.55 hybrid figure used 1 GiB staging. These are planning estimates, not deployment or
spending authority. John requests **512 MB for PHP** and bounds staging to **one worker if possible, at most one
projector if needed, and one cron container**. Idle processes can retain memory; exact PHP limits/web concurrency
and OS/PostgreSQL/service headroom remain ordinary configuration, not a tuning-approval or speculative capacity
project. Production worker counts are not selected by this staging preference.

Subsequent selections complete WF-050: Bitwarden for the human-held recovery kit, Resend Free via HTTPS for
transactional mail (Gmail remains John's inbox), compatible application-only rollback within approved deployment,
and explicit John approval for database restore. The combined sub-$100/month preference includes tax and recurring
services; temporary recovery needs a separate quote/approval, not an automatic reserve. No complete tax-inclusive
quote or workload/recovery qualification follows from the $56.35 pre-tax subtotal.

[WF-050's final practical handoff](../tickets/WF-050-define-private-launch-readiness-and-recovery.md#final-practical-owner-handoff)
closes policy, retaining existing owners and concrete production package/public TLS/mail/schedule/runtime/storage/
diagnosis/recovery suppliers as ordinary delivery work. John remains the exact-candidate production/recovery
operator. No readiness project, new EPIC destination approval, delivery amendment or Board change is inferred.
No fresh source fetch or runtime activity accompanied closure; actual implementation/verification still remains.

Next: `/skill:wayfinder work WF-051` — optional research or explicit deferral; not automatically started.
