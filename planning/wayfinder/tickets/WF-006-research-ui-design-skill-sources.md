# Research UI design skill sources

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Usable web application foundation](../usable-web-application-foundation-map.md)
**Depends on:** —

## Question

Which open or source-available design-skill examples and tools should inform Fight Agent OS UI design skills, and can OpenDesign fit this workflow?

## Must decide

- Whether OpenDesign can be installed and used locally in a repeatable way.
- Which open design-agent, design-system, UI critique, accessibility, and visual iteration examples are trustworthy inspiration.
- How a UI design skill should produce evidence: moodboards, component sketches, prototypes, screenshots, design tokens, accessibility checks, or implementation-ready notes.
- How to keep design exploration separate from production UI implementation.

## Resolution boundary

This ticket may produce cited research and recommendations for UI/design skills. It must not install OpenDesign or create durable UI code unless a later TASK authorizes that work.

## Resolution

[Research](../research/WF-006-research-ui-design-skill-sources.md) finds OpenDesign `v0.3.1` useful as MIT-licensed workflow inspiration but rejects an as-is installation. Its tagged static viewer works locally, and its skills fit the Agent Skills shape, but the stock workflow downloads from mutable `main`, may execute unpinned `npx`, serves the repository root without a loopback bind, uses an unsandboxed same-origin preview, writes outside `.runs/`, and assumes host form/subagent capabilities not present in this Pi workflow.

WF-002 should grill two project-owned capabilities: design/exploration and independent design review. They should preserve the existing `prototype` skill's disposable lifecycle, use W3C/Bootstrap/application requirements as authorities, and adapt only reviewed ideas from OpenDesign, Anthropic's Apache-2.0 design skills, and selected MIT community examples. Do not install OpenDesign, Superdesign, `baoyu-design`, or a broad skill pack through this decision.

Design evidence belongs under ignored `.runs/prototypes/<scope>/`: a brief, sourced/licensed references, meaningful alternatives, flows/states, semantic tokens when warranted, disposable fake-data prototype, deterministic responsive/state screenshots, actual automated/manual check results, and a verdict. Only accepted decisions and implementation requirements become durable planning. A later approved TASK must recreate the chosen design in the production React/Bootstrap architecture rather than promote prototype code.

The skill-creation handoff must harden any local viewer, pin browser/tool dependencies, add required third-party notices, and prove the workflow on one bounded UI question before authentication or dashboard implementation relies on it.
