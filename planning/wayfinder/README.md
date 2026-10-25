# Wayfinder

<!-- planning:maps -->
| Map | Status |
|---|---|
| [accesscontrol-consumer-adoption-map](accesscontrol-consumer-adoption-map.md) | Closed |
| [complete-agent-os-vision-map](complete-agent-os-vision-map.md) | Closed |
| [hierarchical-agent-memory-map](hierarchical-agent-memory-map.md) | Closed |
| [operational-observability-map](operational-observability-map.md) | Closed |
| [private-production-rollout-map](private-production-rollout-map.md) | Closed |
| [sdlc-intake-triage-map](sdlc-intake-triage-map.md) | Closed |
| [usable-web-application-foundation-map](usable-web-application-foundation-map.md) | Closed |
<!-- /planning:maps -->

## Current routing

Use the [Roadmap's current planning handoff](../ROADMAP.md#current-planning-handoff) and
[blended delivery strategy](../ROADMAP.md#approved-blended-delivery-strategy) after the planning merges.
Existing maps retain their accepted scope and historical approvals; closed maps have no decision frontier.
Consult the [Board](../tasks/BOARD.md) for active work and executable priority, not historical map snapshots or
retired planning-worktree paths. A missing implementation supplier remains a hold, not a reason to reopen every map.

## Private production rollout handoff

[Private production rollout, local environments and provider access](private-production-rollout-map.md) is **Closed**
on decisions/research, not implementation or deployment. WF-043–053 are resolved; no open decision frontier remains.
[WF-043 — Bound the private release and approve environment names](tickets/WF-043-bound-private-release-and-environment-names.md)
is closed on John's approval: per-user OpenRouter for first browser support, independent local development
providers, production-matching local names and optional later ChatGPT.
[WF-044 — Qualify per-user OpenRouter and allowance ownership](tickets/WF-044-qualify-per-user-openrouter-and-allowance-ownership.md)
is closed as cited public-source research: ordinary keys expose scoped limits/budgets/free quota, not account
wallet totals. The later approved owner reconciliation maps TASK-00346's protected `/key` adapter to TASK-00399;
TASK-00520/00521 now allocate lifecycle/settings, while TASK-00399/00346/00350 actual qualification holds remain.
[WF-045 — Compare DigitalOcean hosting and operating costs](tickets/WF-045-compare-digitalocean-hosting-and-operating-costs.md)
is closed as dated comparison, not hosting/budget selection: see its research for $87.10/$77.30 pre-tax comparable
floors, incomplete full operations costs and the changing Standard PostgreSQL HA boundary. Subsequent WF-048
policy selections are recorded below; WF-050 retains full-cost feasibility and launch/recovery acceptance.
[WF-046 — Define optional local routing, trust and recovery](tickets/WF-046-define-optional-local-routing-trust-and-recovery.md)
is closed on policy approval: scoped workstation-only multi-project DNS, stable checkout names, protected CA/
project leaves, guided eventual easy-install, independent lifecycle including sleep/wake/reboot and persistent
maintenance stop. Suppliers and actual platform/container/runtime qualification remain held.
[WF-047 — Bound remote harness, trusted Git and candidate execution](tickets/WF-047-bound-remote-harness-git-and-candidate-execution.md)
is closed on architecture/policy: hosted coordination, optional human TASK responsibility, pinned `develop`
context, shared central numbering and read-only hosted Git. Local Agent bash checks remain until qualified
MCP/browser replacement. Browser provisioning integrates protected harness credential delivery/activation without
another sign-in; actual bootstrap, transport, custody and execution suppliers remain held. Existing recovery
safeguards carry forward without repeated confirmation.
[WF-048 — Define staging and artifact promotion](tickets/WF-048-define-staging-and-artifact-promotion.md)
is closed on policy and owner/evidence handoffs: separate SFO3 Droplets/databases, smaller parity staging, non-HA
production with verified recovery, sub-$100 combined monthly infrastructure preference, exact-artifact operator
promotion, optional project-specific UAT and independent per-environment provider budgets. Full-cost feasibility,
production packaging/runbook, real mail/scheduling and actual storage/runtime/security qualification remain open;
no spending, implementation readiness or delivery-owner amendment is inferred.

[WF-049 — Select the first browser journey and switching behavior](tickets/WF-049-select-first-browser-journey-and-switching-behavior.md)
is closed on browser Wayfinder, model/default/switching policy and usage/owner handoffs. Target the database
transition plus useful in-browser Planning for first production deployment; no readiness TASKs, policy tests or
production-exists prerequisite. Use ordinary checks/agreed recovery precautions, then assess actual production use
and request concrete bugfixes. Current OpenRouter models use on-load/light-cache discovery and default updates;
existing conversations change models only explicitly. Conversation usage, environment limits and provider allowance
stay distinct. Workflow-only usage owners do not silently become standalone conversation suppliers; exact extensions
remain later authorized work within the existing provider/conversation/browser owners.

[WF-050 — Define private launch readiness and recovery](tickets/WF-050-define-private-launch-readiness-and-recovery.md)
is closed on policy and the existing-owner handoff, not deployment acceptance. John accepts preferred one-hour data-loss recovery and restoration within 24 hours of
discovery, with human-operated recovery. He is open to a daily-backup alternative if needed and accepts 30 days
of daily recovery points conditional on reasonable cost. John requests Spaces pricing and small initial Droplets.
John selected managed PostgreSQL for production, self-managed PostgreSQL on staging and Spaces-only backups,
superseding WF-048's second managed cluster while preserving environment isolation. Plan 2 GiB RAM / 50 GiB SSD
application Droplets for both environments; staging needs room for PostgreSQL alongside PHP and workers. The
[updated estimate](research/WF-050-backup-storage-and-restore-costs.md) is $56.35/month before remaining costs/tax,
not a measured capacity result or spending authority. Staging targets one worker if possible, at most one projector
if needed and one cron container, with 512 MB requested for PHP. Exact memory/concurrency settings remain ordinary
configuration. John selected Bitwarden for a human-held recovery kit, not runtime/application vault access; the
recovery path must work without Fight and avoid circular vault recovery dependencies. Resend Free over HTTPS is
selected for automated email, while Gmail remains John's inbox; real setup/delivery and current limits still need
ordinary integration work, with no automatic paid upgrade. John approved application-only rollback within an
approved deployment when compatible with the current database; database restore needs his explicit approval.
The combined below-$100/month preference includes tax and recurring services; temporary recovery needs a separate
quote and approval, with no automatic reserve. Complete actual cost/capacity and runtime/recovery suppliers remain
ordinary delivery work, not a new readiness project. Optional ChatGPT/domain UI and legacy replacement do not gate
first production. Existing owners/holds and Board priority remain unchanged; no
EPIC/TICKET/TASK amendments, immediate Planning cutover or deployment are authorized.

[WF-051 — Assess optional household ChatGPT OAuth](tickets/WF-051-assess-optional-household-chatgpt-oauth.md) is
**Closed: John explicitly deferred integration until after first production**. Preserve the
[official research observed 2026-10-24](research/WF-051-chatgpt-plan-usage-and-private-hosting.md) and unresolved
household eligibility/onboarding/runtime/usage gaps for a later explicitly selected follow-up. OpenRouter remains
the first-release provider; existing local development providers are unchanged.

[WF-052 — Define the future local-domain management flow](tickets/WF-052-define-future-local-domain-management-flow.md)
is **Closed** on a scripted browser/Pi project-selection/creation wizard: hostname/destination approval, then
approved Composer/npm preparation, Docker Compose startup and explicitly selected database initialization/migrations.
No Agent is required; destructive resets and sample data are off by default. Reuse existing Create Project/local
infrastructure contracts, with exact extensions and qualified suppliers requiring later authorized delivery.
This optional wizard remains outside first-production requirements; no host/database effect occurred.

[WF-053 — Inventory legacy capabilities and retirement gates](tickets/WF-053-inventory-legacy-capabilities-and-retirement-gates.md)
is **Closed** as a [bounded source inventory](research/WF-053-legacy-capabilities-and-retirement-boundaries.md).
Preserve Local Development, Omphalos/homepage, Ideaverse/personal workflows, `$aios`, direct `$fight` and the optional
Factory runtime. No live replacement or continuity acceptance is claimed. Ownership transfer, route/data migration,
entry-point replacement and retirement remain separately authorized transitions, not first-production requirements.

The [approved first-production handoff](private-production-rollout-map.md#approved-first-production-planning-handoff)
now has its sole new EPIC written and approved:
[EPIC-00021 — Deploy and recover private Fight Agent OS in staging and production](../epics/00021-EPIC.md).
The approved [existing-owner requirement reconciliation](research/private-production-owner-reconciliation.md) is
recorded. Models/defaults/continuation and standalone conversation usage now have explicit existing TASK scopes;
complete per-user connection lifecycle/storage/API/UI is now accepted as TASK-00520 under TICKET-00089, with
TASK-00521 adding optional allowance settings. John separately approved the [five-TICKET deployment split](../epics/00021-EPIC.md#approved-ticket-decomposition-and-current-handoff),
now written as TICKET-00121–00125: artifact, isolated environments, real mail, coherent recovery and approved
promotion. This completes the selected map's TICKET phase, not its TASK phase or implementation. The two
lifecycle/settings TASKs are written. TICKET-00121's artifact/CI split is also accepted as TASK-00522/00523, carrying
application CalVer and release branches without mandatory Git tags or changed CI triggers. TICKET-00122's
staging/operations/production split is accepted as TASK-00524–00526, preserving supplier holds and early mail/backup
interfaces without reverse dependencies. TICKET-00123's mail split is accepted as TASK-00527/00528; TASK-00284
retains shared transport ownership and actual holds with Resend Free HTTPS selected, and TASK-00524 gains it as
a real startup prerequisite. TICKET-00124's staging/managed-production recovery split is accepted as TASK-00529/00530,
reusing existing restoration/Planning/mail owners without metadata changes or cleared holds. TICKET-00125's release
split is accepted as TASK-00532/00533, completing all selected map planning phases. TASK-00104 remains the real
human-operated cutover owner; no final journey reverse-blocks early suppliers. TASK-00344 gains TASK-00520's real
lifecycle edge. Existing statuses/orders/holds and EPIC-00020 priority remain unchanged; neither that edge nor
the earlier TASK-00399 supplier mapping is runtime qualification. Optional expansion/legacy replacement remain excluded, with no operational or
spending authority.

Next: `/skill:next` from the selected planning checkout for read-only Board-based selection.
No decomposition or implementation starts automatically.

## Observability planning handoff

[Operational logs and failure diagnostics](operational-observability-map.md) is **Closed**, with WF-036–042 resolved
and no open decision frontier. John approved the complete live-and-archived diagnosis release, explicit adjacent-
product deferrals and one [EPIC destination](operational-observability-map.md#approved-epic-handoff):
[**EPIC-00019 — Diagnose Agent OS operations and managed runs with live and archived logs**](../epics/00019-EPIC.md).
The separately confirmed grill wrote/approved this sole EPIC after rebasing onto fetched `develop` at `ac41c28` and
checking live/archive IDs across branches/worktrees. Its [seven-TICKET split](../epics/00019-EPIC.md#approved-ticket-decomposition-and-current-handoff)
is now approved/written as TICKET-00111–00117 after another numbering check. EPIC/TICKET phases are complete;
[TICKET-00111's four-TASK split](../tickets/00111-TICKET.md#approved-task-decomposition-and-current-handoff) is now
accepted/written as TASK-00451–00454; [TICKET-00112's six-TASK split](../tickets/00112-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00455–00460; [TICKET-00113's six-TASK split](../tickets/00113-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00461–00466; [TICKET-00114's seven-TASK split](../tickets/00114-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00467–00473; [TICKET-00115's four-TASK split](../tickets/00115-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00474–00477; [TICKET-00116's three-TASK split](../tickets/00116-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00478–00480; [TICKET-00117's seven-TASK split and completed handoff](../tickets/00117-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00481–00487. **All map planning phases are complete**: one EPIC, seven TICKETs and
thirty-seven unranked TASKs. Sixteen receiving TASKs now name early installation suppliers, retaining qualification
gates. TASK-00487 owns composed operator/workload acceptance, not an extra unrecorded parent gate.
Upstream TICKET-00110's local terminal log pane remains independent; no existing TICKET/TASK IDs or priorities changed.

The handoff retains conditional Loki, native Super Admin-only Logs, independent local verified archives and all
mandatory collection/storage/authority/browser/resource qualification gates. General metrics, tracing, external
alerting and error grouping are deferred; independent health/loss visibility remains required. No implementation,
permission assignment, production deployment or ongoing runtime grant follows from planning closure.

Next: `/skill:next` from the current checkout for read-only Board-based work selection.
No implementation, runtime experiment or publication is invoked. Recheck upstream/IDs before future allocation;
TASK priorities and qualification holds are unchanged. Earlier branch/worktree snapshots record planning history,
not the current delivery route.
