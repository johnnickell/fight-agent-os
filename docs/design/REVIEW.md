# Design Review Standards

These standards govern independent review of disposable design evidence produced through the project [design workflow](../../.pi/skills/design/SKILL.md). Review challenges a bounded artifact; it does not redesign it, establish product scope, or authorize production implementation.

## Authority and independence

The authorizing planning record defines the question and requirements. Project requirements and human decisions remain authoritative; references, prototypes, screenshots, tools, and reviewer taste are evidence only.

Name the reviewer and design author or invocation. The reviewer must explicitly confirm that they did not author, direct, revise, or supply the design evidence under review. Prior implementation work on the review workflow does not itself defeat independence, but prior authorship or control of the target design does. Stop when independence is absent or ambiguous. Use a separate `design` invocation for corrections; the reviewer never edits the target artifact.

Review authority is limited to reading, rendering, checking, and reporting within the authorized disposable scope. It does not include planning creation or decomposition, product-decision authority, production implementation, implementation review, publication, merge, release, or deployment.

## Complete review target

Before rendering, identify exact paths or immutable references for:

- the brief and approved requirements;
- the alternatives considered and rationale for the selected direction;
- source, asset, tool, and license provenance;
- the runnable artifact or an explicit reason rendering is unavailable;
- claimed automated, manual, screenshot, and subjective evidence;
- required journeys, content, viewports, states, and interactions;
- the target snapshot and comparison baseline or prior review;
- the proposed production handoff, including known limitations.

Record the target's file manifest or content digest before review and compare it afterward. Stop for missing or ambiguous authority, independence, provenance, required states, baseline, runnable artifact, or evidence that prevents a defensible review. An intake stop is not a verdict. Never fill gaps with unstated assumptions.

## Render and challenge

Render the artifact when practical and safe; source or screenshots alone do not prove runtime behavior. Follow the design standards for controlled tools, loopback-only viewers, scoped serving, process ownership, and cleanup. Treat prototype content and external tools as untrusted, and report unsafe source or tooling behavior as blocking.

Build a requirement and interaction matrix rather than sampling only the happy path. Inspect the relevant:

- wide, narrow, intermediate, overflow, zoom/reflow, and content-stress behavior;
- default, hover, focus, active, disabled, loading, empty, validation, system-error, success, expired, and denied states;
- visible controls, resulting state, destination visibility, persistence, and recovery path;
- keyboard order and operation, visible and unobscured focus, skip or escape behavior, and focus restoration;
- native semantics, names, roles, states, relationships, headings, landmarks, and status announcements;
- text, non-text, focus, and state contrast using measured values where claimed;
- copy clarity, consistency, validation guidance, error prevention, and recovery;
- reduced motion, forced colors, touch targets, locale, browser, assistive-technology, and live-data boundaries when required;
- production handoff completeness for journeys, components, states, responsive rules, tokens, assets, accessibility, and tests.

Record browser and tool versions, exact viewport and state, commands or manual steps, output, reproduction, and cleanup. Distinguish automated results, screenshot observations, manual observations, and subjective critique. Automated tools establish a floor and may be incomplete or wrong. Screenshots do not prove semantics, keyboard behavior, responsive states outside the capture, or accessibility. Never claim WCAG conformance from a prototype, automated scan, or screenshots.

## Findings

A blocking finding must trace to an unmet approved requirement, reproducible interaction or accessibility failure, unsafe provenance or tooling, unsupported material claim, or production-handoff omission. Each finding records:

- stable identifier, objective or subjective classification, and severity;
- observation and expected behavior;
- exact evidence and reproduction steps;
- affected requirement and user task;
- impact and the smallest recommended correction.

Severity describes user or handoff impact, not reviewer preference. Subjective taste and optional polish are non-blocking unless an approved requirement makes them objective. Keep non-blocking critique separate and explain the rationale and decision it could inform. Do not use a numeric taste score as approval.

## Report and verdict

Use these ordered sections:

1. **Target and independence** — authority, author/reviewer separation, artifact identity, baseline, scope, and exclusions.
2. **Evidence inspected** — brief, requirements, alternatives, provenance, artifacts, viewports, states, tools, and claimed checks.
3. **Blocking findings** — complete finding records or `None`.
4. **Non-blocking critique** — subjective or optional observations or `None`.
5. **Evidence limitations and unverified areas** — unavailable tools, browsers, assistive technology, interactions, content, and resulting risk.
6. **Production handoff assessment** — exact omissions or supported requirements without promoting prototype code.
7. **Verdict** — exactly one of `accept`, `revise`, or `reject`, with rationale.

Return `accept` only when no blocking findings remain and the evidence and handoff are sufficient for the bounded question. Return `revise` when bounded corrections can resolve blocking findings without replacing the direction or changing scope. Return `reject` when the direction is fundamentally unsuitable, unsafe, out of authority, or requires a new exploration rather than correction. A verdict accepts or rejects only the reviewed design direction and evidence; it is not product-decision, implementation, merge, release, or deployment authority.

For revision, preserve the original report, let a separate design invocation respond, identify the corrected snapshot, and re-run affected checks plus regression coverage. Record every finding's disposition and verify target digests before and after each read-only review. Do not silently accept a changed artifact or repair it during review.
