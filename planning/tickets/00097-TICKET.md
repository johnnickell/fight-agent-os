---
id: TICKET-00097
epic: EPIC-00007
title: Inspect and control execution through the sessions Dashboard
status: ready-for-agent
---

# Inspect and control execution through the sessions Dashboard

## Problem statement

Humans need to find running or historical work, understand its exact outcome and evidence, and intervene through
real authorized operations. A transcript feed, phase-heavy card grid or locally invented status cannot provide that
control safely or at repository scale.

## Solution and boundaries

Deliver the accepted compact repository-scoped Workflow table using the existing authenticated application shell
and theme owner. Prioritize TASK/reference and Workflow identity, authoritative status, latest meaningful outcome
and next action, branch/actual PR reference, start/freshness, known active duration/recorded usage and visibility
action. No embedded phase lists, events, mini-timelines, transcript excerpts or per-row history polling. Narrow
layouts preserve the same meaning with accessible semantic records rather than document-level overflow.

Use TICKET-00096's whole-result server-side safe metadata search and stable snapshot cursor, with the accepted
25-record default page and Newer/Older navigation. Explicit refresh/search changes begin a new result. One labelled
Archived toggle replaces the entire Current result including heading/count/state/cursor with its independently
paginated archive; no combined All view. Explain projected count freshness and stale/partial/unavailable states,
never truncate older accessible history silently.

Own explicit revision-checked Archive/Restore visibility operations and their per-row controls. They change no
execution, claim, grant, approval or Planning lifecycle. Confirm active archive and keep that work visible through an
independent repository Running now summary with a detail link. If that safeguard cannot be guaranteed, reject
active archive. Restore changes visibility only. Above the table show permission-filtered Running now, Needs Human,
recent meaningful outcomes and recorded repository usage. Display qualified provider/account/key quota separately
with its broader scope through TICKET-00098, never as a repository balance or fake installed capability.

Provide four separately loaded detail sections:

- Summary prioritizes current status, meaningful outcome, next human action, exact immutable Git subject, freshness,
  role/phase/attempt/recovery metadata and known usage/time. Review is not yet executed in this EPIC: show Awaiting
  review/unavailable honestly and leave future verdict/publication facts to TICKET-00083.
- Changes exposes every file in the exact bounded syntax-highlighted base…head subject, lazily loads bodies and
  directs oversized/binary content to authorized Artifacts. Mark mutable worktree observations separately.
- Activity places dense phase/Agent timelines and bounded searchable event windows below the primary summary.
  Distinguish authoritative transitions from optional observations, allow selected-phase drill-down and expose
  only sanitized tool metadata/evidence links. No prompts, thinking, transcript or message excerpts.
- Evidence presents actual check/Git/Artifact references, provenance, limitations, sensitivity, holds and tombstones.
  Later Review/QA criteria/verdicts extend this same surface; do not invent them to fill an empty section.

Each section has independent loading, empty, denied, stale, partial, interrupted, cancelled, failed, purged and
unavailable states so a failed query cannot erase the dossier. Reconnect reloads durable state before live updates.
Planning conversations remain linked under Planning, not presented as execution history or exposed through this UI.

Expose authorized pause/cancel/resume/takeover and Runner drain/halt/recovery actions by calling existing semantic
operations from TICKET-00032/00093. Show expected revisions, consequences, remaining limits and actual outcomes;
resolve original-command uncertainty before another effect. Keep claim release dependent on confirmed quiescence.
Viewing, archive, disconnect and page refresh never start/cancel work. Reuse TASK-00205's accepted Ready-card/keyboard
start and TASK-00265's Pending start interaction, including their one-intent consent semantics; no duplicate Start
form, extra normal-route approval or generic status setter is introduced here.

Exclude a new shell/theme, client eligibility/queue algorithm, Workflow engine, browser transcript viewer, coding
command executor, review/QA execution, publication/merge or demonstration rows. Final visual treatment and actual
accessibility qualification belong with production delivery, not inherited prototype screenshots.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Find current or archived work | N/A — navigation/search is read-only | Whole-result authorized search, stable cursor and summary | N/A — browsing changes no Workflow state | Safe useful overview with honest freshness |
| Inspect exact work and evidence | N/A — separately bounded section reads | Summary/Changes/Activity/Evidence and authorized Artifact access | N/A — viewing cannot approve or execute | Exact subject and known/unknown evidence, never transcript disclosure |
| Archive or restore visibility | Explicit expected-state Archive/Restore | Current visibility, authority and independent running safeguard | Committed visibility facts, not execution transitions | Result-set membership changes; running work continues |
| Intervene in execution | Existing intent-specific lifecycle/Runner commands | Fresh grant/claim/attempt, limits, consequence and original result | Existing confirmed authoritative outcomes only | Bounded pause/cancel/resume/control effect or truthful hold |
| Follow an existing start/pending intent | Existing TASK-00205/00265 operations only | Authoritative original intent/Workflow status | Existing owner facts, no client-generated status | Same accepted start or withdrawable pending intent, no second claim |

## Validation and permissions

Use current server intent-specific viewing/evidence/visibility/control authority and Repository scope, not Role names
or UI visibility. Apply ADR 0003/0004 authentication, volatile state, no-store, strict decoding, invalidation and
late-response fences. Cancel/retire requests and clear private views on authority/context change; a live message or
stale button never authorizes a command. Guard loaders/actions as well as rendering, revalidate expected state on
server mutations and never automatically replay an uncertain business request after refresh.

Validate bounded filters/cursors, escape all untrusted metadata/diffs, preserve private Artifact access, and keep
raw keys, sensitive payloads and hidden reasoning out of browser/logging. Keyboard actions must match pointer
semantics with accessible status/focus and reduced-motion behavior. Unknown availability disables unsupported
controls truthfully rather than adding fixtures to production.

## Acceptance and evidence

- Actual production Workflow results populate compact searched/paginated Current and Archived views, with useful
  responsive navigation and independent detail sections. No per-row history fetch or silent older-history cap.
- Archive/Restore cannot change execution; active archived work stays independently visible or active archive is
  rejected. Permission changes and stale/racing commands never leak data or produce false local success.
- Exact immutable changes/evidence remain distinguishable from mutable observations; oversized/binary/purged data
  is explicit. Phase detail and all search routes exclude transcripts, excerpts and unsafe payloads.
- Real operational commands preserve original request identity, current authority, quiescence and pending outcomes.
  Browser start reuses existing owners; detach/reload is not cancellation or duplicate execution.
- Owned API/client behavior tests, actual browser keyboard/focus/responsive/light-dark evidence and `./bin/build`
  pass on the real TASK path. Prototype/historical receipts are not fresh accessibility or runtime acceptance;
  do not duplicate browser/library behavior or create a proof-only Dashboard.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 5 of EPIC-00007's remaining six-TICKET split. Applies
[WF-018](../wayfinder/tickets/WF-018-prototype-sessions-first-browser-experience.md),
[WF-017](../wayfinder/tickets/WF-017-define-execution-history-and-event-authority.md), EPIC-00007's search/metrics/
phase-detail amendments, [ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Consume real shell/authentication, TICKET-00032/00093 commands, TICKET-00094 evidence, TICKET-00096 queries and
TICKET-00098 usage/forecast facts. Decompose the visibility operation early where projections need its contract,
without reversing producers onto completed UI. [TICKET-00052](00052-TICKET.md)/[TICKET-00064](00064-TICKET.md)
retain start/pending ownership; TICKET-00083 extends later managed phases. Browser and forecast delivery are not
new blockers of the terminal-first milestone. No TASK, runtime UI, visibility change or operational effect is
created by this approval, and existing supplier readiness holds and Board priority remain unchanged.
