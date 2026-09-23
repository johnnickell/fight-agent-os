# Define new project creation and registration

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-009](WF-009-define-repository-context-and-policy-precedence.md), [WF-016](WF-016-define-runner-dispatch-and-recovery.md)

## Question

What confirmed, recoverable journey should let a user create a named project from an approved starter, optionally create a private or public GitHub repository, establish its first checkout and context, and register it in Agent OS without hiding partial side effects?

## Must decide

- The approved starter catalog, initially evaluating `johnnickell/project-symfony`, `project-slim`, `project-laravel`, `project-yii`, and `project-codeigniter`, including provenance, supported revisions, compatibility evidence, update ownership, and removal or deprecation.
- Required and optional inputs: project/display name, slug, starter, destination host and path, source owner, source repository name, visibility, description, and initial planning-authority mode.
- Whether creation uses a GitHub template, clone-and-detach, archive, package-manager, or another mechanism without accidentally retaining starter history, remotes, identifiers, credentials, generated artifacts, or project-specific content.
- The preview and explicit confirmations required before creating files, initializing Git, committing, creating a remote repository, pushing, installing dependencies, linking a checkout, or registering the repository.
- Private-by-default source creation, heightened confirmation for public visibility, least-privilege GitHub authentication, organization/owner selection, naming collisions, and branch/default-branch behavior.
- Idempotency, durable progress, retries, cancellation, and compensation when local creation, source-host creation, initial publication, checkout linking, context setup, or Agent OS registration succeeds only partially.
- How WF-008 repository identity and checkout matching, WF-009 context and planning mode, and WF-016 authorized runner/host operations constrain the journey.
- Observable completion evidence and the final handoff into the Dashboard, local checkout, or first planning session without automatically starting agent work.
- Whether starter qualification and provisioning orchestration warrant a focused child map with non-overlapping decisions.

## Resolution boundary

This decision defines the product journey, authority boundaries, failure semantics, and a future planning handoff. It may inspect starter repositories and use disposable prototypes or cited research, but it must not create a project, repository, checkout, credential, database record, production wizard, EPIC, requirement TICKET, or implementation TASK.

## Preferences required

John must choose the initial starter set, default destination and visibility, minimum customization, expected Git history, acceptable partial-failure recovery, and whether local-only creation is a first-class journey. The recommendation should default to private, show a complete preview, require confirmation before external or filesystem mutations, preserve recoverable evidence, and register only a successfully identified project without granting workflow authority.

## Resolution

Write this only when the starter contract, creation journey, explicit authority, recovery model, registration handoff, and any child-map boundary are approved.
