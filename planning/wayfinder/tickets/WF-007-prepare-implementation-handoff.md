# Prepare the implementation handoff

**Labels:** `wayfinder:task`
**Mode:** HITL
**Status:** Closed
**Map:** [Usable web application foundation](../usable-web-application-foundation-map.md)
**Depends on:** [WF-002](WF-002-shape-agent-skill-suite.md), [WF-005](WF-005-design-authentication-and-authorization-journeys.md)

## Question

What EPIC, TICKET, and TASK planning handoff should follow once the route to the usable web application foundation is clear?

## Must decide

- Whether the next handoff is one EPIC or a short sequence of EPICs.
- Which accepted decisions need ADRs before implementation.
- Which TICKETs should cover skills, dependency cleanup, authentication, authorization, session management, and dashboard UI.
- Which TASK frontier should be executable first.
- What verification evidence the first implementation TASKs must record.

## Resolution boundary

This ticket may create or link the final planning handoff after upstream decisions close. It must not skip directly into implementation.

## Resolution

The implementation handoff is the ordered sequence [EPIC-00002](../../epics/00002-EPIC.md) → [EPIC-00003](../../epics/00003-EPIC.md) → [EPIC-00004](../../epics/00004-EPIC.md). Decompose one EPIC at a time with `to-tickets`; decompose an accepted requirement TICKET with `to-tasks` only when its dependencies and decision gates are satisfied. Do not manufacture all downstream TASKs before upstream evidence can refine them.

### Sequence and concurrency

1. **Bootstrap execution capability with EPIC-00002.** Implement and prove `work` first under the current repository instructions as an explicit bootstrap exception. Then implement independent `review` and `land`. The `design` and `design-review` pair may proceed in parallel once their shared standards shape is stable, but both must be accepted before production authentication/dashboard visual work.
2. **Stabilize the application platform with EPIC-00003.** Adopt stable package lines, remove inherited support-campaign tests only as owned smoke coverage replaces them, and establish the project quality gate before broad feature work. Record the required ADRs, then establish PostgreSQL/migrations and deterministic test infrastructure, HTTP/API conventions, durable external-effect delivery, and the React/Storybook client foundation. Capability work may overlap only where it does not guess an unresolved ADR or persistence/transport contract.
3. **Deliver vertical journeys with EPIC-00004.** Design exploration and reviewed design language may run alongside backend managed-policy/bootstrap work after their EPIC-00002 dependencies close. Implement security controls with each journey rather than as a final hardening pass. Establish managed authority and invitation activation before ordinary login; establish login/refresh/logout and current-principal projection before protected dashboard, account, session, and Super Admin routes. Password recovery/change and self-service session revocation may proceed as separate vertical slices after the shared authentication transport is authoritative.

### Required ADR gates

Create concise application ADRs during EPIC-00003 TICKET/TASK planning, before affected production implementation:

- **Package ownership and application boundaries:** Fight Access Control Domain/Application ownership, allowed Agent OS orchestration, CQRS dispatch, Action–Domain–Responder delivery, and dependency direction. This gates structural application scaffolding.
- **PostgreSQL consistency and durable effects:** transaction ownership, repository/mapping strategy, concurrency controls, migration policy, and the selected recoverable at-least-once delivery mechanism for email/audit effects. This gates authoritative persistence and invitation delivery.
- **Browser authentication security profile:** access-JWT claim/signature validation, key/secret configuration and rotation expectations, refresh-cookie scope, CSRF/Origin/Fetch Metadata mechanism, refresh conflict/reuse handling, multi-tab coordination, and logout/invalidation behavior. This gates login, refresh, and protected API transport. It must explicitly close consumer gaps rather than assume signature-only JWT decoding or an interface-only login throttle is a production control.
- **Client authority and runtime state:** `/api/v1/me` as the authoritative projection, fail-closed route/component checks, API-cache ownership, memory-only credentials, refresh coordination, non-sensitive runtime configuration, and `system`/light/dark preference bootstrap. This gates the authenticated React shell.

Local HTTPS tooling, concrete rate thresholds, mail-provider selection, cookie names, Adapter coverage thresholds, and denylist corpus selection normally belong in accepted TICKET/TASK requirements unless investigation reveals a durable architectural trade-off warranting another ADR.

### Proposed TICKET boundaries

These are decomposition boundaries, not TICKET records or pre-approved implementation slices.

**EPIC-00002 — execution and design skills**

1. Establish shared execution standards and the `work` skill, including one bounded proof.
2. Establish independent `review` with adversarial findings and evidence expectations.
3. Establish `land` with planning finalization, resource ownership, PR handoff, and human merge control.
4. Establish disposable evidence-first `design` exploration and the visual-language specimen workflow.
5. Establish independent `design-review` and prove the paired workflow on one bounded UI question.

**EPIC-00003 — application architecture foundation**

1. Adopt stable Fight package constraints and an owned build/test baseline while retiring inherited support receipts safely.
2. Record and enforce package ownership, layering, CQRS, and composition boundaries.
3. Establish isolated PostgreSQL, Doctrine migrations, transactional repositories, and concurrency-focused integration testing.
4. Establish versioned JSend HTTP delivery, validation, safe exception mapping, correlation, OpenAPI, and minimal functional smoke coverage.
5. Select and establish durable recoverable delivery for required external effects, beginning with mail/audit seams.
6. Establish the React/TypeScript route/layout/page/component and API-client foundation with Vitest and Storybook.
7. Complete the owned-code quality gate with static analysis, dependency rules, honest coverage policy, production install, and dependency audit.

**EPIC-00004 — invite-only authenticated shell**

1. Explore, review, and accept the responsive authentication/dashboard design language and semantic light/dark tokens.
2. Reconcile managed roles/permissions and provide guarded audited Super Admin bootstrap and trusted console invitation.
3. Deliver recoverable invitation, delivery, activation, resend, and pending-email-correction journeys.
4. Deliver secure login, remember-me, rotating refresh, logout, CSRF/origin controls, throttling, and multi-tab coordination.
5. Deliver authoritative current-principal projection, fail-closed client authorization, protected routing, and the honest dashboard shell.
6. Deliver the common-password capability plus password reset and authenticated password-change journeys.
7. Deliver self-service active-session listing and revocation.
8. Deliver permission-controlled Super Admin user/invitation UI, elevated-role confirmation, and audit evidence.
9. Verify the integrated security/accessibility baseline, canonical HTTPS behavior, security headers/CSP, critical smoke journeys, and safe failure/recovery states without replacing lower-level evidence with broad browser automation.

`to-tickets` may merge or refine a proposed boundary when acceptance remains independently coherent, but it must preserve the dependency direction and must not silently convert a TICKET boundary into a TASK.

### First executable TASK frontier

After the first EPIC-00002 TICKET is created and accepted, the first TASK should be **Implement and prove the project-local `work` skill and its minimum shared execution standards**. It is a bootstrap TASK executed under `AGENTS.md` and current planning conventions because the skill does not exist yet. Its acceptance evidence must include:

- the exact approved requirement scope and changed files;
- a bounded dry run or disposable demonstration showing TASK intake, checkout/worktree choice, branch and scope controls, focused checks, canonical build, evidence recording, and implementation handoff;
- fresh focused verification plus `./bin/build`, with test counts and every warning/incomplete check reported honestly;
- confirmation that scratch evidence remained under ignored `.runs/`, unrelated work was preserved, and no planning authority or merge authority was granted to the skill;
- an independent review after the `review` capability becomes available, or an explicitly recorded human bootstrap review before relying on `work` for production application changes.

Subsequent TASKs must record observable behavior and boundary-specific evidence: deterministic skill demonstrations for EPIC-00002; PostgreSQL-backed migration/constraint/concurrency evidence and API safety checks for EPIC-00003; and permission, session race/reuse, credential-redaction, accessibility, responsive-state, and minimal critical-browser evidence for EPIC-00004. Focused checks supplement but never replace the repository `./bin/build` gate.
