---
name: work
description: Implement or revise an approved TASK on a feature branch, verify it, and hand the committed work to independent review.
---

# Work

Read the TASK, parent and [execution standards](../../../docs/engineering/EXECUTION.md). Work owns implementation,
focused verification and a truthful handoff. Independent review owns the full quality assessment. Load the
relevant sections of [engineering standards](../../../docs/engineering/STANDARDS.md) when an implementation
decision needs them; do not preload every architectural checklist or reproduce the review matrix.

1. **Bound and place.** Check accepted scope, blockers, Board, branch, ancestry and status. Preserve unrelated changes. Ask whether to use the main checkout or an isolated worktree unless chosen. For a new TASK use `feature/task-NNNNN-<slug>` from `develop`; preserve a pre-existing authorized TASK branch and keep scratch under `.runs/`.
2. **Read the handoff.** Resolve the canonical base worktree through Git metadata and inspect `.runs/reviews/<TASK-ID>/review.md`. A first implementation may have none; a revision requires the canonical `revise` report and traces every blocker. Version-2 and version-3 reports are usable when their target identity and recorded snapshot match. If the branch has moved, trace changes to know whether findings remain applicable. When the only remaining findings require hosted evidence and no implementation correction, follow the [evidence routing](../../../docs/engineering/EXECUTION.md#review-handoff-intake); do not manufacture edits or repeat passing gates. An accepted implementation with applicable QA satisfied routes to `land`, which may reconcile an advancing base; do not use `work` solely to rebase an unchanged accepted implementation.
Also inspect `<base-worktree>/.runs/qa/<TASK-ID>/qa.md` when present. A current QA FAIL is repair input even if
the older technical review says accept: apply [QA intake](../../../docs/engineering/EXECUTION.md#qa-handoff-intake),
revalidate each finding, and preserve its ID. INCOMPLETE routes to prerequisite/evidence recovery, not speculative
code edits. After acceptance, apply [QA applicability](../../../docs/engineering/QA.md#applicability): changed
behavior without applicable QA routes to `qa` before ordinary `land`, including non-UI work.

3. **Implement the accepted behavior.** Follow the TASK's dependencies and applicable local rules; design clear
interfaces for human maintainers. Add meaningful behavior tests at the narrowest useful boundary. For each test,
know the contract, plausible defect and independently derived expectation; use the [test guidance](../../../docs/engineering/QUALITY.md#tests-that-protect-contracts)
when a test's value is unclear. Preserve required coverage when replacing tests. Ordinary feature work need not
follow TDD; confirmed bugs require a failing regression before repair. Aim for meaningful 100% unit coverage,
report gaps honestly, and validate tooling with its owning tools rather than product tests of tools.

   Keep directly affected docs accurate; use [Writing for Agents](../writing-for-agents/SKILL.md) for instruction
   edits. Record evidence for each TASK outcome and unresolved decisions for the reviewer. The reviewer applies
   the full architecture, code, test and documentation checks; this division does not waive applicable standards
   or known defects. Integrate current `develop` when needed, regenerate planning views from authoritative records,
   inspect conflicts and effective changes, and preserve other TASKs' work. Record any behavior/scope change from
   reconciliation for review. Never force-update a published branch.

4. **Verify and record.** The local gate is mandatory; hosted CI is optional unless explicitly required by the target's accepted policy or TASK. Disclose unavailable optional checks without blocking completion on them. Run focused checks, `./bin/planning-check` when planning changed, `git diff --check`, and `./bin/build`. Inspect the final diff/status. Record actual results, counts, warnings, omissions, files changed and remaining risks in the TASK; keep review and PR status truthful.
5. **Commit and hand off.** Stage only owned files, inspect staged changes, and commit. Return branch/head, scope, evidence, risks and unrelated work preserved; request independent review. Stop before push, PR, approval or merge without separate authority.

A failing required check or unsupported criterion is incomplete work, not a green handoff.
Any authorized TASK closure includes [automatic parent completion](../../../planning/CONVENTIONS.md#automatic-parent-completion)
and refreshed planning views in the same change; never send the user to a separate TICKET/EPIC closeout step.

Finish with the explicit `Next:` line from the [handoff rules](../../../docs/engineering/HANDOFFS.md): after a
verified committed implementation or repair, recommend `/skill:review TASK-NNNNN` using the actual TASK ID and
name the independent-session requirement. For incomplete work, state its real continuation or prerequisite.
