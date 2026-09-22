# Research UI design skill sources

## Question

Which open or source-available design-agent skills, design systems, accessibility standards, critique practices, and visual-iteration tools should inform Fight Agent OS UI skills? Can OpenDesign be used locally and repeatably without collapsing disposable design exploration into production implementation?

This matters before WF-002 names the first design skills and before WF-005 defines registration, login, session, role, permission, and dashboard experiences. A skill that produces attractive HTML but omits states, accessibility, provenance, or a clean production handoff would create false confidence.

## Short answer

Use **OpenDesign as reviewed inspiration, not as an installed upstream plugin**. Its MIT-licensed `v0.3.1` skills provide useful patterns: context-first intake, structurally distinct wireframes, HTML prototypes, explicit design-system discovery, a separate verifier, and a handoff warning that prototype HTML is not production code. Its basic viewer works locally with only static files and Python/Chromium.

The stock workflow is not safe or reproducible enough for this private repository. It downloads a viewer from mutable `main`, can run an unpinned `npx --yes serve`, binds Python's server without a loopback restriction, serves the entire repository root, embeds previews in an unsandboxed same-origin iframe, writes to a durable top-level `opendesign/` directory, and assumes host features Pi does not currently expose here, such as structured forms and verifier subagents.

Create project-owned Pi skills through planning instead. Combine:

- OpenDesign's context-first exploration and prototype/handoff separation.
- Anthropic's Apache-2.0 `frontend-design` discipline: brief-specific direction, token planning, restraint, screenshots, and self-critique.
- The MIT `ux-ui-agent-skills` project's strongest pattern: objective browser gates establish a quality floor, while adversarial visual critique judges what cannot be reduced to a score.
- W3C WCAG 2.2, WAI-ARIA APG, Bootstrap documentation, and application behavior as authorities—not community prompt claims.
- USWDS, GOV.UK, Carbon, and Bootstrap as pattern/process references, not a visual identity to copy.

Design work should produce a traceable evidence bundle under ignored `.runs/prototypes/<scope>/`: brief, references and licenses, alternatives, semantic tokens when warranted, a disposable prototype, desktop/mobile/state screenshots, automated and manual check results, and a verdict. Only the accepted decisions and implementation requirements move into authoritative planning. Production code begins later under an approved TASK and is recreated in the real React/Bootstrap architecture; prototype code is never promoted.

## Findings

### 1. Source evaluation

| Source | License / provenance | Useful evidence | Recommendation |
|---|---|---|---|
| [OpenDesign `v0.3.1`](https://github.com/manalkaff/opendesign/tree/v0.3.1) (`0b531ae3`) | MIT | Small, readable set of ten Agent Skills; context-first intake; wireframe/prototype/design-system routing; local HTML viewer; explicit prototype-to-development handoff | Adapt concepts only. Do not install upstream as-is. If its viewer is reused later, vendor the reviewed tagged file with its MIT notice and harden it. |
| [Anthropic skills](https://github.com/anthropics/skills/tree/34040c9c568585f6929bedeaad110ad08f079624) (`34040c9c`) | Each relevant skill carries Apache-2.0 terms | Official `frontend-design` skill uses brief-specific aesthetics, a compact token plan, responsive/focus/reduced-motion quality floor, screenshots, and critique; `webapp-testing` demonstrates Playwright reconnaissance and screenshot evidence | Primary agent-skill inspiration. Adapt narrowly and record Apache attribution/modification notices if text is copied. |
| [`ux-ui-agent-skills` `v2.8.0`](https://github.com/plugin87/ux-ui-agent-skills/tree/v2.8.0) (`c445a08b`) | MIT | Separates measurable correctness from taste; includes real-render contrast, state, keyboard, overflow, reduced-motion, RTL, target-size, axe, token, and screenshot-oriented gates; openly records limits of its own evidence | Mine the evidence model and independently validate any script before reuse. Do not import its full theme, 138-system corpus, or 44-gate suite into the first application. |
| [Designer Skills Pack](https://github.com/Owl-Listener/designer-skills/tree/9a6930cf84a822eb458624bd11c61aac5bbdf224) (`9a6930cf`) | MIT | Useful capability taxonomy: research, strategy, UI, interaction, systems, prototype testing, critique, and handoff; concise output structures for component specs and critiques | Taxonomy/reference only. Many skill bodies are high-level and uncited; they are not standards or sufficient audit procedures. |
| [`baoyu-design` `v1.2.0`](https://github.com/JimLiu/baoyu-design/tree/v1.2.0) (`d4a95c4c`) | Repository declares MIT, but its own provenance says substantial prompt/starter material was extracted from bundled `claude.ai/design` assets | Rich local artifact workflow, version-pinned design-system copies, offline `.fig` decoding, and browser-backed exports | Do not copy or install. The repository's root license does not resolve upstream provenance clearly enough for this project. Similar useful patterns are available from less ambiguous sources. |
| [Superdesign skill](https://github.com/superdesigndev/superdesign-skill/tree/f9f05cd988c247dce6c072eaf9ac6b162f2ffc4b) (`f9f05cd9`) | MIT skill text; operation depends on the authenticated Superdesign service and CLI | Strong context/resume model, branch-vs-replace distinction, and explicit source payload budgeting | Exclude from the local baseline. It invokes `@superdesign/cli@latest`, requires login, transmits project context to a hosted service, and may spend credits. Reconsider only after privacy, retention, terms, version pinning, and cost review. |

Popularity was not used as a trust proxy. The useful sources above were judged by readable instructions, primary provenance, licensing, explicit limitations, and reproducible evidence.

### 2. OpenDesign is technically local but only qualified for adaptation

Pi `0.87.0` implements the Agent Skills format, recursively discovers directories containing `SKILL.md`, and warns that skills may instruct the model to take arbitrary actions or run executable code. OpenDesign's ten skill files have the required `name` and `description` frontmatter, and its own validator passed against the reviewed snapshot. A manual copy of a pinned skill directory would therefore be discoverable by Pi; no marketplace integration is required.

The basic OpenDesign viewer also works locally. A disposable WF-006 smoke test copied the reviewed viewer into `.runs/notes/WF-006-opendesign-smoke/`, served only that directory on `127.0.0.1:8289`, loaded its manifest and sample page successfully, and captured a Chromium screenshot. This proves the viewer's small static-file mechanism, not the quality of generated designs.

The upstream setup/run instructions are unsuitable unchanged:

1. `setup-opendesign` fetches `viewer.html` from the `main` branch at runtime with no commit pin or integrity check. OpenDesign's own `0.3.1` notes document that an earlier mutable URL could have been silently hijacked after a repository rename.
2. `run-opendesign` serves the **project root**, not only the design artifacts. Python's default bind is not restricted to loopback, potentially exposing a private source tree to other reachable hosts.
3. Its Node fallback is `npx --yes serve` without a package version or reviewed lock.
4. The viewer loads generated pages in an iframe without `sandbox`; because the viewer and prototype share an origin, prototype script receives more access than a review viewer needs.
5. Generated work belongs in top-level `./opendesign/`, conflicting with this repository's rule that disposable experiments stay under ignored `.runs/` folders.
6. The workflow assumes structured question forms, subagent dispatch, and a silent verifier. This Pi session has file/shell tools but no dedicated browser, form, or subagent tool.
7. The interactive-prototype skill explicitly leaves out production-grade accessibility audits. Authentication and authorization surfaces cannot defer accessibility until implementation.

**Decision:** do not install OpenDesign. A future skill-creation TASK may adapt its MIT-licensed ideas into project-local Pi skills. If a viewer is useful, either build a tiny project-owned index or vendor the exact tagged viewer with attribution, serve only the artifact directory on `127.0.0.1`, remove dynamic downloads and `npx`, sandbox previews, and validate manifest paths against traversal.

### 3. Authoritative design guidance must come from primary sources

Community skills can organize work, but standards and the selected implementation stack own correctness:

- [WCAG 2.2](https://www.w3.org/TR/WCAG22/) is the current W3C Recommendation and W3C advises its use for future applicability. Start with **Level AA** as the application baseline; do not claim blanket AAA conformance from an agent audit.
- [WAI guidance on evaluation tools](https://www.w3.org/WAI/test-evaluate/tools/selecting/) says tools cannot automatically check all accessibility aspects, may produce false or misleading results, and cannot determine accessibility by themselves. Human judgement and real user experience remain necessary.
- [WAI-ARIA Authoring Practices](https://www.w3.org/WAI/ARIA/apg/) provides functional patterns, keyboard behavior, roles, states, and properties for common widgets. Prefer native HTML before ARIA and test the selected pattern in application context.
- [Bootstrap 5.3 accessibility guidance](https://getbootstrap.com/docs/5.3/getting-started/accessibility/) says project accessibility depends on author markup, styling, and scripting, and warns that some default color combinations can fail WCAG contrast. Bootstrap is a foundation, not an accessibility certificate.
- [Bootstrap CSS variables](https://getbootstrap.com/docs/5.3/customize/css-variables/) provide an implementation bridge for theme colors, type, focus styling, component values, and color modes. Design evidence should map to semantic project tokens before mapping to `--bs-*` variables.
- The [Design Tokens Format Module 2025.10](https://www.designtokens.org/TR/2025.10/format/) is a Final Community Group Report for exchanging platform-agnostic tokens. It is useful for JSON token shape, types, groups, and aliases, but it explicitly is **not a W3C Standard**.

For authentication specifically, WCAG 2.2 adds Accessible Authentication (Minimum), Focus Not Obscured, and Target Size (Minimum). WF-005 should treat keyboard flow, visible/unobscured focus, clear validation and recovery, password-manager/paste support, and non-cognitive authentication alternatives as journey requirements rather than late audit findings.

### 4. Open design systems are evidence libraries, not a brand shortcut

Trustworthy systems demonstrate mature component behavior and process:

- [USWDS accessibility guidance](https://designsystem.digital.gov/documentation/accessibility/) documents automated checks on every change, manual specialist testing, screen-reader/browser testing, and user testing. It explicitly says accessible components do not guarantee an accessible service. Most USWDS-authored work is public domain/CC0, but bundled fonts/icons and normalization code retain separate licenses documented in its [license](https://github.com/uswds/uswds/blob/develop/LICENSE.md).
- [GOV.UK Design System accessibility guidance](https://design-system.service.gov.uk/accessibility/) likewise says using its system does not immediately make a service accessible. Its [proposal process](https://design-system.service.gov.uk/community/propose-a-component-or-pattern/) asks first for evidence of user need and discourages spending time on a specific design or code before that evidence exists. GOV.UK Frontend is MIT licensed, but Crown branding is not the Fight visual identity.
- [Carbon accessibility guidance](https://carbondesignsystem.com/guidelines/accessibility/overview/) and Apache-2.0 component source are useful references for dense application/dashboard states and accessible data-heavy UI.
- Bootstrap remains the likely implementation base from `planning/FOUNDATION.md`; the other systems should inform behavior, terminology, state coverage, and review criteria—not add competing runtime libraries.

The skill should inspect patterns across systems only when a real design question needs them. It should cite the exact component/page and license, explain the borrowed principle, and avoid copying brand assets, proprietary product screens, or trade dress.

### 5. Recommended capability split for WF-002

WF-002 should decide names, but the first suite needs two distinct design capabilities rather than one skill that designs and approves its own output:

1. **Design/exploration capability**
   - Frame the user, job, constraints, content, fidelity, implementation context, and decision to be made.
   - Inspect existing planning, UI, Bootstrap conventions, tokens, assets, and prior design evidence before drawing.
   - Produce alternatives only along meaningful axes: information architecture, flow, density, interaction, or visual direction—not recolors.
   - Use the lowest fidelity that answers the question: written flow or ASCII sketch before wireframe; wireframe before interactive/high-fidelity prototype when uncertainty warrants it.
   - Produce a disposable artifact and explicit verdict; stop before production implementation.

2. **Independent design-review capability**
   - Review in a separate invocation/session or agent context, because the current harness has no verifier-subagent tool.
   - Render the artifact rather than reviewing source alone.
   - Compare against the brief and application requirements, then inspect responsive layouts, states, keyboard operation, focus, semantics, contrast, reduced motion, overflow, copy, and error recovery.
   - Separate objective failures from subjective critique. Each finding should include observation, evidence, affected user/task, severity, and recommended change.
   - Never use a numeric “taste score” as approval. Return a verdict such as accept direction, revise, or reject, with blocking findings explicit.

The existing `prototype` skill should remain the general disposable-artifact rule. A UI design skill can invoke or specialize it rather than creating a contradictory second prototype lifecycle.

### 6. Required design evidence

Use evidence appropriate to the question; do not generate every artifact ceremonially. A complete high-fidelity feature exploration should normally create:

```text
.runs/prototypes/<planning-id-or-scope>/
  README.md                 # question, non-production warning, run/cleanup instructions
  brief.md                  # audience, task, constraints, content, fidelity, success criteria
  references.md             # URLs/files, what was learned, copyright/license/asset provenance
  flows/                    # journey/state diagrams or written state model
  variants/                 # structurally distinct sketches/wireframes
  tokens/                   # semantic token source when a system decision is being tested
  prototype/                # self-contained fake-data HTML/CSS/JS or small React prototype
  screenshots/              # deterministic desktop/mobile/state evidence
  checks/                   # commands, versions, raw summaries, manual checklist
  verdict.md                # chosen direction, rejected alternatives, unknowns, handoff
```

Evidence expectations:

- **Moodboard/reference board:** only when visual direction is undecided. Every reference needs its source, rights/provenance, and a sentence naming the principle being considered. It is not a collage of assets to copy.
- **Wireframes:** multiple structurally distinct options for unresolved layout/flow questions. Low fidelity must remain visibly low fidelity.
- **Prototype:** fake data, no production API, credentials, secrets, persistence, or authentication. Visible controls needed for the selected journey should work; dead controls must be marked or omitted.
- **Tokens:** begin with a small primitive → semantic mapping for color, typography, spacing, radius, shadow, focus, and motion. Component tokens appear only when repeated component needs justify them. Record a later Bootstrap mapping; do not scatter unexplained hex/spacing values.
- **State matrix:** at minimum relevant default, hover, focus, active, disabled, loading, empty, validation error, system error, success, and session/permission denial states. Include narrow/mobile and wide layouts; add dark mode and RTL only when requirements select them.
- **Screenshots:** capture the accepted/rejected variants and critical states at named viewport sizes in a fixed browser/container. Playwright warns screenshots vary by OS, browser, settings, hardware, fonts, and headless mode, so baseline environment/version is part of the evidence.
- **Checks:** record actual tool output. Never infer a contrast ratio or accessibility pass by looking at a screenshot.
- **Verdict/handoff:** state what was learned, which direction is accepted, remaining risks, exact interaction/copy/accessibility requirements, component inventory, asset provenance, and what production work must recreate. State prominently that prototype HTML is reference evidence, not shipping code.

Only the decision and requirements become durable planning text. Scratch remains ignored and disposable unless a later TASK explicitly promotes a reviewed token/asset/document into a production-owned location.

### 7. Accessibility and visual verification need layers

A practical evidence ladder is:

1. **Source/static checks:** semantic structure, labels, token aliases, hard-coded values, documented states, asset provenance.
2. **Rendered automated checks:** axe-core, measured contrast, target size, overflow/reflow, focus visibility/order where measurable, keyboard interaction, reduced-motion behavior, and console/runtime errors.
3. **Visual regression:** Playwright screenshots for critical viewports/states with animations, clocks, and data made deterministic. Review baseline changes rather than blindly updating them.
4. **Manual checks:** keyboard-only task completion, zoom/reflow, text spacing, forced colors, reduced motion, and screen-reader smoke tests for critical flows.
5. **Usability/accessibility research:** real users, including people using assistive accommodations, for consequential workflows when the project reaches that maturity.

[Axe-core](https://github.com/dequelabs/axe-core) reports that it finds about 57% of WCAG issues automatically and marks uncertain cases incomplete. [Storybook's accessibility testing](https://storybook.js.org/docs/writing-tests/accessibility-testing) uses axe as a first line and preserves manual review for incomplete findings. These tools are valuable after `client/` and its component states exist, not proof that an HTML mockup is accessible.

For early static prototypes, system Chromium plus a pinned browser script can capture screenshots and run selected checks. When the React client exists, [Storybook](https://storybook.js.org/docs/) can expose components/states and [Playwright](https://playwright.dev/docs/test-snapshots) can capture application flows. Tool versions and browsers should be locked through the future client build, not downloaded ad hoc by a skill.

### 8. Exploration must remain physically and procedurally separate from implementation

The design skill must enforce these boundaries:

- Write only under `.runs/prototypes/` (or another ignored `.runs/` subfolder authorized by the planning record), never `client/`, `src/`, production assets, package manifests, or migrations.
- Mark every page and README as disposable/non-production.
- Use invented but realistic fake data; never copy local secrets or production records into artifacts or screenshots.
- Serve only the artifact directory on `127.0.0.1`; use a task-selected free port, track the PID, and stop it after evidence capture. Do not serve the repository root.
- Do not fetch mutable scripts, viewers, fonts, images, or packages during a run. Vendor/pin reviewed tools and preserve applicable licenses, or use labeled placeholders.
- Do not reference private repository files from prototype HTML. Copy only explicitly selected, reviewed assets into scratch.
- Treat external HTML and generated prototypes as untrusted content. Isolate/sandbox previews and do not give them same-origin access to repository content.
- End with a planning verdict, not a pull request containing UI code.
- Begin production only from an approved TASK. Recreate the accepted design using the actual React/TypeScript/Bootstrap conventions, then add application-owned component, accessibility, interaction, and screenshot tests. Never move prototype code wholesale.

A full collaborative canvas is unnecessary for the first foundation. [Penpot](https://github.com/penpot/penpot) is an MPL-2.0 open-source, self-hostable design/prototyping option if later collaboration requires a durable visual editor, but operating a service and synchronizing its source of truth would be disproportionate now. Local text, HTML, screenshots, and planning decisions are simpler and auditable.

### 9. Licensing and attribution implications

No third-party skill was copied into the durable repository during this research, so `docs/legal/THIRD_PARTY_NOTICES.md` does not need an immediate change.

A future skill TASK must:

- cite inspiration without copying when possible;
- add the OpenDesign/other MIT notice if substantial MIT text or viewer code is incorporated;
- include Apache-2.0 terms and prominent modification notices for adapted Anthropic files;
- preserve MPL-2.0 obligations if axe-core source is modified or distributed rather than merely consumed as a package;
- inspect licenses of fonts, icons, images, and design-system assets separately from the parent repository;
- avoid `baoyu-design` material unless upstream rights are independently established.

## Recommended handoff

WF-006 can close with these constraints for downstream decisions:

- **WF-002:** grill the exact names and boundaries of a project-owned design/exploration skill and an independent design-review skill; keep the existing `prototype` lifecycle authoritative; authorize separate implementation planning for the selected skill files and any pinned browser helpers.
- **WF-004:** choose the React/Bootstrap component documentation, test, token, screenshot, and browser-tooling architecture before adding those checks to `./bin/build`.
- **WF-005:** require complete authentication/authorization state and accessibility coverage in the journeys before any visual design is accepted.
- **Implementation planning:** do not install OpenDesign, Superdesign, or a broad community skill pack. Create a small adapted Pi-native workflow, attribute it, and prove it on one disposable dashboard/authentication design question before relying on it.

## Sources

### Local project and Pi

- [Prototype skill](../../../.pi/skills/prototype/SKILL.md)
- [Project architecture](../../../ARCHITECTURE.md)
- [Foundation scope](../../FOUNDATION.md)
- Pi `0.87.0` skill documentation: `/home/john/.local/share/mise/installs/pi/0.87.0/pi/docs/skills.md`
- [Pi skill documentation upstream](https://github.com/earendil-works/pi/blob/main/packages/coding-agent/docs/skills.md)

### Agent/design skills

- [OpenDesign `v0.3.1`](https://github.com/manalkaff/opendesign/tree/v0.3.1)
- [OpenDesign entry skill](https://github.com/manalkaff/opendesign/blob/v0.3.1/skills/opendesign/SKILL.md)
- [OpenDesign setup skill](https://github.com/manalkaff/opendesign/blob/v0.3.1/skills/setup-opendesign/SKILL.md)
- [OpenDesign run skill](https://github.com/manalkaff/opendesign/blob/v0.3.1/skills/run-opendesign/SKILL.md)
- [OpenDesign interactive prototype](https://github.com/manalkaff/opendesign/blob/v0.3.1/skills/interactive-prototype/SKILL.md)
- [OpenDesign handoff](https://github.com/manalkaff/opendesign/blob/v0.3.1/skills/handoff-to-claude-code/SKILL.md)
- [Anthropic frontend-design skill](https://github.com/anthropics/skills/tree/34040c9c568585f6929bedeaad110ad08f079624/skills/frontend-design)
- [Anthropic webapp-testing skill](https://github.com/anthropics/skills/tree/34040c9c568585f6929bedeaad110ad08f079624/skills/webapp-testing)
- [`ux-ui-agent-skills` `v2.8.0`](https://github.com/plugin87/ux-ui-agent-skills/tree/v2.8.0)
- [Designer Skills Pack snapshot](https://github.com/Owl-Listener/designer-skills/tree/9a6930cf84a822eb458624bd11c61aac5bbdf224)
- [`baoyu-design` `v1.2.0`](https://github.com/JimLiu/baoyu-design/tree/v1.2.0)
- [`baoyu-design` extracted-source provenance](https://github.com/JimLiu/baoyu-design/blob/v1.2.0/skills/baoyu-design/references/upstream-sync/provenance.json)
- [Superdesign skill snapshot](https://github.com/superdesigndev/superdesign-skill/tree/f9f05cd988c247dce6c072eaf9ac6b162f2ffc4b)

### Standards and design systems

- [WCAG 2.2](https://www.w3.org/TR/WCAG22/)
- [WAI-ARIA Authoring Practices Guide](https://www.w3.org/WAI/ARIA/apg/)
- [Selecting Web Accessibility Evaluation Tools](https://www.w3.org/WAI/test-evaluate/tools/selecting/)
- [Design Tokens Format Module 2025.10](https://www.designtokens.org/TR/2025.10/format/)
- [Bootstrap accessibility](https://getbootstrap.com/docs/5.3/getting-started/accessibility/)
- [Bootstrap CSS variables](https://getbootstrap.com/docs/5.3/customize/css-variables/)
- [USWDS accessibility](https://designsystem.digital.gov/documentation/accessibility/)
- [USWDS license](https://github.com/uswds/uswds/blob/develop/LICENSE.md)
- [GOV.UK Design System accessibility](https://design-system.service.gov.uk/accessibility/)
- [GOV.UK component/pattern proposal process](https://design-system.service.gov.uk/community/propose-a-component-or-pattern/)
- [Carbon accessibility](https://carbondesignsystem.com/guidelines/accessibility/overview/)
- [Penpot](https://github.com/penpot/penpot)

### Verification tools

- [Playwright visual comparisons](https://playwright.dev/docs/test-snapshots)
- [Storybook accessibility testing](https://storybook.js.org/docs/writing-tests/accessibility-testing)
- [axe-core](https://github.com/dequelabs/axe-core)

## Open questions

- WF-002 must choose exact skill names, invocation boundaries, and whether UI design specializes or calls the existing `prototype` skill.
- WF-004 must decide the client package manager, exact Bootstrap/React setup, Storybook or equivalent component-state surface, browser container, and where durable tokens live.
- WF-005 must decide whether dark mode, RTL, responsive navigation, recovery flows, and assistive-technology user testing belong in the first usable foundation or a scheduled follow-up.
- The skill-implementation TASK must choose whether to create a tiny project viewer or adapt OpenDesign's tagged viewer, and then threat-model the chosen preview path.
- The project needs a retention policy for large ignored screenshots/prototypes and a durable way to attach accepted visual evidence to planning or pull requests without making generated experiments production source.
