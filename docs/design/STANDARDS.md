# Design Standards

These standards specialize the project [prototype skill](../../.pi/skills/prototype/SKILL.md) for disposable product-design exploration. A prototype answers a bounded question; it does not establish production code, authoritative product scope, or final design acceptance.

## Authority and brief

Begin from an approved planning question. Inspect its requirements, architecture, existing UI and assets, implementation constraints, prior evidence, and relevant licenses before proposing a direction. External references and tools supply evidence or inspiration; project requirements and human decisions remain authoritative.

The brief records:

- scope identifier, owner, non-authoritative status, and decision sought;
- audience, job, journey or workflow, content, and relevant states;
- fidelity and why it is sufficient;
- implementation and accessibility constraints;
- meaningful alternatives to compare and the evidence needed to choose;
- explicit exclusions, unresolved assumptions, and production handoff target.

Stop or narrow the work when the question cannot produce one useful verdict, authorization is unclear, or the requested fidelity would become production implementation.

## Disposable evidence bundle

Keep every brief, note, dependency, copied asset, source file, capture, check result, process record, and handoff beneath `.runs/prototypes/<scope>/`. Mark the scope README and rendered pages as disposable and non-production. Choose the smallest useful subset of:

```text
README.md
brief.md
references.md
flows/
variants/
tokens/
prototype/
screenshots/
checks/
verdict.md
```

Do not generate empty or irrelevant artifacts to satisfy the shape. Use the lowest fidelity that resolves the risk: written flow or state model before wireframe, and wireframe before interactive or high-fidelity output.

Use invented, realistic fake data. Do not use credentials, secrets, private records, production data, production APIs, persistence, or live authentication. Visible controls required by the question should work; omit or clearly label intentionally inactive controls.

## Sources, assets, and licenses

For each external reference or asset, record its exact URL or local origin, version or retrieval date, lesson used, intended treatment, author or owner when known, and license or rights status. Check fonts, icons, images, logos, and copied code separately from a parent project's license.

Prefer citing principles over copying material. Do not copy ambiguous-provenance content or brand trade dress. When material is copied or adapted, preserve required notices and modification attribution in the durable repository where the license requires it. The source qualification and exclusions in [WF-006 research](../../planning/wayfinder/research/WF-006-research-ui-design-skill-sources.md) apply; do not install OpenDesign, Superdesign, `baoyu-design`, or a broad community skill pack as part of an exploration.

## Alternatives and states

Alternatives must differ along a decision-relevant axis such as information architecture, flow, hierarchy, density, interaction model, or visual direction. Recoloring the same composition is not an alternative. State why an alternative was rejected and which evidence supports the comparison. If only one direction is appropriate, justify why comparison would not answer the question.

Choose states and viewports from the actual question. Consider default, hover, focus, active, disabled, loading, empty, validation error, system error, success, expired, and denied states without manufacturing irrelevant screens. Name viewport dimensions and capture critical narrow and wide behavior when layout can change. Make data, time, animation, browser, fonts, locale, and capture conditions deterministic or disclose their variability.

## Visual-language exploration

A Bootswatch-like specimen is an evidence surface, not a production theme. When visual language is the question, include the relevant examples of:

- typography hierarchy and readable body text;
- semantic color roles and non-color cues;
- spacing, layout rhythm, borders, radii, shadows, and motion;
- controls, buttons, links, and visible interaction states;
- labels, help, validation, focus, disabled, and form states;
- navigation and selected/current state;
- cards, tables or dense data, and responsive overflow behavior;
- alerts, status, empty, loading, error, and success feedback;
- candidate logo or local asset treatment with provenance;
- meaningfully distinct variants and comparison notes.

Start with project semantics, then recommend explainable Bootstrap mappings. Record at least token name, meaning, light/dark values when applicable, contrast or other evidence, and target Bootstrap variable. Prefer mappings such as `color.action.primary → --bs-primary`, `color.text.default → --bs-body-color`, `color.surface.default → --bs-body-bg`, `color.border.default → --bs-border-color`, and `focus.ring → --bs-focus-ring-color`; do not force a mapping when Bootstrap has no equivalent. Cover typography, spacing, radius, shadow, and motion tokens when the question needs them. Prototype values are recommendations until independently accepted and recreated in production.

## Safe tools and viewing

Use existing controlled tools or dependencies pinned by exact version, lock, image digest, or reviewed system version. Record tool and browser versions. Do not fetch mutable scripts, packages, fonts, viewers, or assets during a run. Disclose and obtain approval before introducing an unpinned or networked tool.

A local viewer must:

- serve only the prototype scope, never the repository root;
- bind only to `127.0.0.1` on a selected free port;
- record command, working directory, port, PID, start result, and owner;
- prevent path traversal and avoid giving untrusted prototype content access to repository files;
- sandbox untrusted previews when a viewer embeds them;
- be stopped after capture, with cleanup and port/PID verification recorded.

Keep caches, dependencies, browser profiles, output, and logs inside the prototype scope. Do not leave an unowned process or use destructive cleanup based only on a name match.

## Evaluation and evidence limits

Record claims in four distinct classes:

1. **Automated results:** exact command, version, target, output, exit result, and incomplete findings.
2. **Manual observations:** actual keyboard, focus, zoom/reflow, text spacing, semantics, contrast, forced-colors, reduced-motion, assistive-technology, overflow, content, and interaction checks performed.
3. **Subjective critique:** aesthetic or usability judgment, its rationale, and the decision it informs.
4. **Unverified claims:** skipped, unavailable, inconclusive, or out-of-scope behavior and the risk it leaves.

Use the subset relevant to the brief and say what was not checked. Prefer native HTML before ARIA and compare custom widget behavior with the WAI-ARIA Authoring Practices. Automated checks establish a floor and can be wrong or incomplete. Screenshots do not prove semantics, keyboard operation, responsive behavior outside the captured state, or accessibility. Never claim WCAG conformance from a prototype, an automated scan, or screenshots alone.

## Verdict and production handoff

The verdict records the question answered, selected and rejected ideas, evidence and limitations, unresolved risks, and whether the direction is ready for independent design review or needs another bounded exploration. It never self-approves final design direction.

The production handoff identifies exact journeys, copy, interactions, components, states, responsive rules, semantic tokens and Bootstrap mappings, accessibility requirements, asset provenance and license obligations, and required application tests. State prominently that prototype source and assets are reference evidence, not shipping code. A later approved TASK must recreate accepted decisions in the production React/TypeScript/Bootstrap architecture and verify them against production behavior.
