# Target project context for shared skills

Resolve this context before selecting work or running a delivery stage. The repository supplying a skill is
the **provider**; the repository receiving the work is the **target**. They may be the same checkout. A skill's
location never selects the target, and matching TASK IDs across repositories never establish ownership.

1. Start with the operator's named target/checkout, otherwise the invocation's working directory. Resolve its
   Git root, common directory, registered worktrees, branch and remote. Follow the target's checkout-choice
   rules before implementation. If the request and checkout disagree or ownership is ambiguous, resolve that
   choice before mutation; never fall back to the provider repository.
2. Read the target's `AGENTS.md` and its referenced project profile, capability binding and context documents
   when present. Follow those references rather than requiring every consumer to create a standard directory
   layout. Resolve the actual target planning conventions, Board, selected record/parents, applicable technical
   standards, gate commands, branch/base policy, completion semantics, PR template and runtime procedures.
   Record any required input that is absent; absence of an optional profile alone is not a blocker.
3. Resolve skill and shared workflow links relative to their actual provider resource supplied by the harness
   or operator. Keep those instructions at the provider. Apply the target's documented substitutions to
   source-application references, including product plans, paths, architecture, commands and runtime examples.
   Provider planning records, test receipts and managed-agent infrastructure are not target facts or prerequisites.
   Do not guess a provider installation, copy its catalog, create symlinks or change global configuration.
4. Use target-owned rules for the target's implementation, verification and completion, together with the shared
   workflow/report definitions and documented bindings. Apply explicit user decisions within their scope.
   Preserve independent review, QA and publication as distinct facts even if target `done` means implemented.
   An unresolved conflict or missing required resource holds only the affected action with a named owner and
   concrete missing input; do not silently import provider defaults or weaken accepted target requirements.

For delegated delivery, record a reusable **project context** in the private run ledger: target and provider
identities, exact resolved instruction/resource paths, target checkout/canonical base, planning/record paths,
applicable substitutions and exclusions, local gate/runtime/branch/completion policies and PR template. Pass
its absolute path to every fresh child alongside the owning provider skill and stage evidence. Each child reads
the context, target instructions and applicable binding before following provider references, verifies the Git
identity in its working directory and applies the same substitutions to nested references. Changed target,
binding or requirements require reconciling the context and evidence before continuing. Do not switch targets
mid-run or reuse another repository's ledger, reports or revision allowance merely because the TASK ID matches.

This is context resolution, not installation, runtime provisioning, a permission grant or independent acceptance.
