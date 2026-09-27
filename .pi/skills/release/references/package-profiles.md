# Package policy discovery

These are source-inspected discovery hints from 2026-09-27, not a replacement for a target repository's current
rules. Read the actual checkout before use. Never depend on a sibling checkout being installed.

## Fight Common

Read `AGENTS.md`, the delivery/testing/review standards, `release/README.md`, `bin/release`, the current certification
record implementation under `release/src/`, and accepted release decisions in `planning/adr/`.

- `./bin/release certify <version>` owns certification only. Its exported dependency lanes preserve their resolved
  versions using the certifier's documented mode; do not run a broad dependency update over a selected lowest lane.
- The inspected output lives under `.runs/handoffs/release-<version>-<full-commit>/`. Validate the actual record's
  schema, candidate/version, command results, record/artifact digests and required consumer/package-surface evidence
  against its owning implementation. Do not copy a field/count checklist from an older release.
- ADR 0027 supersedes ADR 0016's conflicting environment, sequence and asset clauses. It requires fresh certification
  of the exact remote `main` release merge before signing; an earlier candidate with the same tree is insufficient.
  Verify applicability of version-specific decisions rather than silently generalizing them to a new release.
- That decision permits a release with **zero uploaded assets**. Do not invent a zip/notes/evidence upload requirement
  from an old summary. Record the actual asset inventory and independently inspect immutable-release postconditions.
- Its per-effect human approvals and operator-held signer are part of the inspected publication contract.
  Packagist projection and installed-consumer qualification remain separate facts.

## Fight Access Control

Read `AGENTS.md`, `docs/engineering/standards/Delivery.md`, testing/review standards, `README.md`, `CHANGELOG.md`,
`bin/release` and current accepted release decisions/planning.

- The inspected `./bin/release certify <version>` requires a clean checkout and a dated changelog heading.
  It performs planning, full package quality and OpenAPI consumer-composition checks, binding the result to `HEAD`.
- Output lives under `.runs/release-<version>-<full-commit>/`; an existing directory causes refusal. Inspect it as
  possible partial evidence. Preserve it; use the target's supported retry procedure or a fresh approved worktree,
  never delete it simply to force a rerun.
- Its inspected JSON has top-level `version`, `commit`, `changelog_heading`, `clean_checkout` and `checks` log paths.
  It is not Fight Common's digest-bearing certification schema. Check the owning command's successful direct exit,
  logs, candidate identity and actual outputs; hash the handoff evidence without pretending the package emitted
  fields it does not have. A fabricated JSON file or isolated “passed” line is not a certification receipt.
- Delivery requires explicit version signoff, signed annotated `vX.Y.Z` tags, the exact current-line release merge
  on `main` (or the appropriate maintenance-line release commit), a human terminal signing script, publication
  verification, merge-back and owned cleanup. Certification itself performs none of those external effects.
- Follow its library maintenance/support policy when applicable; do not apply an application's hotfix/deployment
  path to a library or infer support from the mere existence of a branch.

## Other repositories or changed contracts

Derive an equivalent profile from current authority and tooling. Missing certification or publication policy is
a concrete gap to report and plan, not permission to invent one. Keep approved exceptions explicit. Do not change
global skill/configuration settings, dependencies or release tooling just to make this skill fit.
