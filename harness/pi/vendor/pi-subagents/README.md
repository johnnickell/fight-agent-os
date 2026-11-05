# Fight-maintained pi-subagents

This ordinary source directory is part of `@fight/agent-os-pi`, not a separate install, Git submodule or npm release.
Its MIT-licensed engine comes from [tintinweb/pi-subagents](https://github.com/tintinweb/pi-subagents), upstream
0.23.1 at `50460b5df6934c4237983a504808a04ee0f8a0a0`. Preserve [LICENSE](LICENSE) and upstream attribution.
The package name is retained for the engine's extension identity/exclusion logic; `private: true` prevents publication.
Fight's [subagent runbook](../../SUBAGENTS.md) owns installation, supported settings and qualification limits.
The [original upstream README](https://github.com/tintinweb/pi-subagents/blob/50460b5df6934c4237983a504808a04ee0f8a0a0/README.md)
is reference documentation, not our installation procedure or evidence that its optional features are enabled.

## Maintained delta

- Awaited host-only `beforeSessionStart(session, signal)` before child extension binding/session_start and prompting,
  with rejection/cancellation disposal and queued-start protection. Factories load before this hook; it is not a sandbox.
- Registry/RPC spawn derives API, context and root id from the bound parent; forged caller context cannot grant identity.
  Capability flags, stale-parent checks and fourteen real-session regressions are in the source and [RPC contract](docs/rpc.md).
- Child resource-loading context uses one async-local store across Pi module-loader copies, preventing child factories
  from mistaking a native parent's process-launcher environment for their own. Owning regression and installed-SDK
  qualification reproduce the former duplicate-store failure before repair; no shared environment role mutation.
- Remove upstream's extra EOF blank lines in `agent-types.ts` and `enabled-models.ts` so the delivered source passes
  Git whitespace checks. These two formatting-only deviations are deliberate; the original base remains above.
- Harness packaging owns runtime dependencies and loads this source entry once. Host Pi peers use capability-based
  ranges; the inherited development lock records tested dependency identities, not an allowed Pi-version list.
- Native `.mts` test config and task-private temporary/cache output allow the suite to run with read-only source.
  The canonical gate limits the suite to two workers and includes lint, typechecking and the full upstream tests.

## Ownership and maintenance

Fight owns stage policy, admission and observation. This component owns SDK execution; no second session engine,
scheduler, worktree owner or compatibility facade is introduced. The supported profile disables upstream scheduling,
workflows, nesting and automatic worktree management. Package installation does not apply that profile automatically.
Do not load a separate standalone companion alongside this bundled source.

Update deliberately from an inspected upstream revision, review its changes against this small delta, and run the
component and Harness gates plus private installed qualification before adoption. See the Harness's [verification
instructions](../../README.md#verification-and-packaging) for explicit platform-matched dependency setup.
Keep source, tests, fixtures, examples required by the tests and API reference together; runtime packaging excludes
development files and installed dependencies. Do not import upstream contributor instructions as Fight policy.

No upstream maintainer acceptance or completed TASK-00560 acceptance is claimed. Fight now uses the setup boundary
for its opt-in Engineer bootstrap and has controlled installed-SDK admission evidence. Real permitted Engineer work,
inspected native Herdr activity and independent Review remain separate from bundling/scripted qualification.
