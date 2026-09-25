# WF-018 sessions browser prototype evidence

## Question

Which sessions-first information architecture makes live and historical coordinated TASK work understandable at repository scale without overloading the overview, hiding active work, conflating Planning or Pi conversations, or silently truncating detail?

## Disposable setup

A fake-data static prototype, alternatives, captures, checks, references, and complete verdict remain under ignored scratch at:

```text
.runs/prototypes/wf-018/
```

Run it from that directory with a loopback-only static server:

```bash
python3 -m http.server 43118 --bind 127.0.0.1 --directory prototype
```

The prototype is not production React, an API, query, schema, visual language, theme, or accessibility acceptance. Delete the scratch directory after this durable note no longer needs local inspection; do not promote its HTML, CSS, JavaScript, fake data, or assets into production.

The approved evidence is based on planning commit `a906e30e50e9af7ca5a6ad61272c2ddc66144194`. Its browser result SHA-256 is `a056824a840791b5a1c4a30a879a2c0e3865bf3ad811d5b803d0530f3f7ec2b0`; the local source/capture manifest SHA-256 is `a7cf698934a451f5c69b8b832dceea7618364bebed2d3143fe15f6a8ea34a964`.

## Alternatives

**A — compact table with task-oriented detail** placed one Workflow per row. It compared TASK and Workflow identity, authoritative status and Review state, latest meaningful outcome, branch/PR, start/activity, known duration/usage, and visibility action without embedded phases or event lists. Narrow layouts reflowed the same priorities into labelled records. Detail used separate Summary, Changes, Activity, and Evidence sections.

**B — operational card grid** grouped running, attention, and recent work and embedded a phase summary plus repeated usage/activity blocks in every card. It retained the strongest visual cue from the reference Factory but made fewer Workflows comparable per viewport and elevated process activity over current consequence.

John selected A. The card grid is rejected as the repository-scale history default; a card-like treatment may still be appropriate for a deliberately small Running now subset.

## Revised archive and shell evidence

The first prototype used Active/Archived/All tabs. John rejected the Archived tab. The accepted revision uses one labelled **Archived** toggle that replaces the entire current table, count, state, and cursor chain with an independently server-paginated archived result set. Switching resets to that side's first page. There is no combined All result in the initial interaction.

A running Workflow may be archived because archive changes visibility only. Confirmation says it does not pause, cancel, stop, approve, publish, or merge work. Archived running work remains in a repository Running now summary with a direct detail link. Restore returns it to current results without changing execution. If production cannot preserve that independent running indicator reliably, it must disallow archiving non-terminal work rather than hide it.

John preferred a collapsible sidebar over the prototype's large top navigation. The accepted structural evidence uses primary navigation in an open/close sidebar, a thin contextual utility bar, and a modest footer. Narrow layouts start with navigation closed. This approves shell structure only: final dimensions, colors, typography, icons, responsive treatment, footer content, and theme-switcher placement belong to the deeper design and independent review under [TICKET-00014](../../tickets/00014-TICKET.md), [TASK-00034](../../tasks/00034-TASK.md), and [TASK-00035](../../tasks/00035-TASK.md). Sessions inherit that one application-wide system/light/dark model rather than introducing another.

## Scale and states exercised

The fake repository reported 2,381 Workflows while rendering at most one 25-record overview page. Activity reported 12,840 observations while rendering one 50-record cursor window. The immutable Review subject exposed a complete 37-file fake `base…head` inventory with collapsed file bodies and a digest-identified patch Artifact.

Evidence covered multiple healthy and unavailable repositories; current and archived result sets; running, Needs Human, Review revision, accepted, PR open, provider-uncertain, interrupted, cancelled, terminal failure, and archived-running records; overview/detail loading, empty, partial, stale, unavailable, and permission-denied states; 1440×1000 and 390×844 primary viewports; bounded process output; role-specific AgentConversation metadata without transcript content; and current/unknown usage and duration facts.

## Verification

The final controlled run used existing Playwright 1.63.0 and system Chromium 152.0.7977.82:

- 43 of 43 browser assertions passed with no console or page error.
- Overview rendered no more than 25 rows and no embedded phase/event mini-history.
- Archived was absent from tab navigation; the toggle selected a separate paginated result set.
- Running archive confirmation, removal, announcement, archived visibility, and restore behaved without process-control claims.
- Sidebar collapse/reopen, narrow default-closed behavior, thin utility bar, and footer were present.
- Narrow overview and detail had no document-level horizontal overflow.
- Detail tab arrow focus and visible focus were exercised.
- Authority and observations were separately labelled; protected Pi transcript content was absent.
- All 37 fake diff files and 50 of 12,840 activity rows were discoverable through bounded views.
- Loading, empty, partial, unavailable, denied, and cancelled cases remained explicit.
- Sample text contrast exceeded 4.5:1 and the first row action exceeded the 24×24 CSS-pixel target floor.
- Static JavaScript syntax, static IDs/ARIA references, remote-runtime dependency absence, screenshot inventory, loopback-only viewer ownership, process/port cleanup, and browser-profile removal passed.

Eleven PNG captures remain in local scratch. No package, remote asset, global configuration, production source, API, database, container, or tracked application file changed during the prototype.

## Accessibility and evidence limits

No screen reader, braille display, voice control, keyboard-only manual session, forced-colors session, zoom matrix, text-spacing override, Firefox, Safari/WebKit, Windows browser, touch device, or low-power device was tested. CSS changed native table display on narrow screens; production must test that pattern with target browser/assistive-technology combinations or render a separate semantic list. Contrast checks sampled only key text. Diff syntax, very long lines, binary/rename/generated files, line targets, live-query latency, cursor expiry, Mercure reconnects, object storage, permission races, archive concurrency, and redaction remain production concerns. Screenshots prove composition only and establish no WCAG conformance.

## Accepted interpretation

- Use a compact server-paginated table with one Workflow per row and no phase/event history in the overview.
- Prioritize authoritative status/human action, latest meaningful outcome, Review/verification risk, branch/PR handoff, freshness/unknown warning, then duration and provider-reported usage.
- Use a current/Archived whole-result toggle, not archive tabs or an initial All view.
- Use stable opaque cursor snapshots with a Workflow-identity tie-breaker; Newer/Older remains inside the snapshot and explicit refresh starts another.
- Use Summary, Changes, Activity, and Evidence as separately loadable detail sections; preserve section-local loading, denied, partial, stale, empty, and retry states.
- Make the immutable full file inventory, bounded syntax-highlighted diff, findings, criteria, checks, Artifacts, attempts, interruptions, and sanitized observations reachable without exposing Pi transcripts or hidden reasoning.
- Use a collapsible sidebar, thin utility bar, and footer as the shell direction while deferring visual language and theme-switcher placement.
- Retain from Factory the distinction among Workflow/phase/Agent activity, useful live status, evidence-backed checks, cursor windows, and visible repository failure. Reject its SQLite/file authority, per-card polling, fixed-height truncation, phase-heavy history cards, archive-as-review semantics, and silent caps.

## Verdict

**Supported with stated limits.** The selected table and detail hierarchy remained understandable at repository scale, the revised archive toggle avoided tab-like secondary navigation, and the structural sidebar shell did not require a premature visual-language decision. Production work must recreate these decisions through separately approved EPIC → TICKET → TASK planning; the disposable implementation is not a production starting point.
