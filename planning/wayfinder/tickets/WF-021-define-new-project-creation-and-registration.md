# Define new project creation and registration

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-009](WF-009-define-repository-context-and-policy-precedence.md), [WF-016](WF-016-define-runner-dispatch-and-recovery.md), [WF-023](WF-023-define-local-runtime-and-shared-ingress-topology.md)

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

### Starter catalog and release contract

Begin with managed catalog entries for `johnnickell/project-symfony`, `project-slim`, `project-laravel`,
`project-yii`, and `project-codeigniter`. A starter is available when it has a stable semantic-version tag at or
above `1.0.0`. Offer only its newest stable release for new projects; exclude prereleases and do not present a
historical-version picker. None of the five inspected repositories currently has an eligible tag, so none is
available until its first qualifying release.

Treat the release tag as the maintainer's support declaration rather than adding an Agent OS certification
ceremony. Resolve it to an exact commit and tracked source archive, verify and retain the archive digest, and
install the immutable snapshot with the catalog. A catalog refresh makes a newer stable release the default for
future projects only. Existing generated projects never upgrade silently.

Release tags are immutable. If a known tag later resolves to different commit or digest evidence, fail catalog
refresh closed for that starter rather than replacing the trusted snapshot. Recovery requires a newer stable tag
or an explicit operator decision. Archiving or deliberately removing a catalog entry makes it unavailable for
new projects after refresh. Preserve snapshots required by active or failed creation attempts, but do not offer
them for new creation. Withdrawal never changes an existing generated project.

Installed snapshots let local creation proceed during a GitHub outage. Installation and catalog refresh still
require the relevant connectivity. If the selected snapshot is unavailable, report that fact rather than fetch
or substitute another revision silently.

### Workspace custom starters and development styles

An authorized Workspace administrator may add an accessible public or private GitHub repository to the
Workspace starter catalog. Custom starters need not use Fight libraries, PHP, CQRS, or any Agent OS architectural
convention. They use the same newest-stable-tag and immutable-snapshot rules as managed starters. Private source
uses the Workspace's protected server-side GitHub connection; source credentials never enter a snapshot or
generated project.

Each eligible release contains a small versioned generation manifest declaring its exact supported identity
substitutions and optional preparation command. This is an executable generation contract, not architecture or
quality certification. A release without a valid manifest is not offered because Agent OS cannot know safely
what to rename or execute. Exact manifest schema remains downstream planning.

WF-014 already permits authorized custom database-backed Skills and Agent profile templates. A Workspace
administrator may associate managed or custom starter entries with recommended Workspace-owned profile
templates. Show and record the selected development profile during creation. Skills and profiles remain
independently revisioned, repository content cannot silently add or activate them, and selecting one never grants
Permissions. Access Control still validates every actual Agent's authority.

### Inputs, paths, and generated identity

Require a human display name, editable machine-facing project slug, starter, parent directory, and independently
editable local directory name. Suggest the slug from the display name and the directory name from the slug, but
do not equate them: a lower-case remote such as `my-project` may live in a PascalCase `MyProject` directory.
Do not assume a Fight or other vendor subfolder.

Configure one or more runner-accessible project roots, suggesting `~/Projects` during onboarding without making
it universal. Permit arbitrary nested Workspace or vendor parents and independently named project directories
beneath those roots. Resolve containment and collisions before mutation. Choosing a location outside configured
mounts enters the separate explicit installation operation to add a project root and restart or reconfigure the
runner, then resumes creation; the browser does not alter mounts or create an unusable partial project silently.

For optional GitHub publication, ask for owner, independently editable repository name, private-by-default
visibility, and optional description. Suggest the authenticated personal owner and project slug while allowing
an authorized organization and another name. Do not ask ordinary application projects for a Composer
vendor/package identity. Generation removes or neutralizes the starter's source package identity only through
its exact manifest rules rather than broad text replacement.

Create from the tracked immutable snapshot, not a starter working tree. Ignored dependencies, caches, secrets,
and local state therefore do not enter the result. Preserve reusable source, instructions, architecture,
tests, and tooling according to the release manifest while removing starter Git identity and applying only
exact declared substitutions. A changed or unmatched transformation fails instead of guessing.

Every result is a fresh local Git repository with no inherited commits or remotes. Suggest `develop` as the
initial branch, allow the user to choose another name such as `main`, and create only the selected branch with
one generated initial commit. The selected branch becomes registered context and, when published, the remote
default. Local-only means no GitHub remote, not no Git repository.

### Preview and deterministic creation

Perform read-only preflight before presenting one complete preview. Validate the snapshot, canonical destination,
configured-root containment, names and collisions, runner capability, and selected GitHub identity and
availability when applicable. Preview the exact starter, paths and names, generation effects, initial branch and
commit, preparation selection, source owner/name/visibility/description, publication effects, selected development
profile, and database-authoritative registration. A material change invalidates the preview.

One explicit confirmation authorizes the displayed plan rather than prompting before every stage. Public GitHub
visibility requires a heightened warning and acknowledgement. Execute these observable stages through one
durable user-authorized Create Project operation:

1. generate the tracked source deterministically;
2. initialize Git and create the initial commit;
3. optionally run the starter's declared dependency-install or preparation command, selected by default and
   visibly identified as potentially networked executable work;
4. optionally create and push the GitHub repository;
5. register the repository and checkout; and
6. resolve its first database-authoritative repository context.

The operation runs on an eligible authorized runner without an AI model choosing paths, names, visibility,
commands, or state transitions. Preparation may fail independently without invalidating successfully generated
source. A completed preparation must leave its declared tracked result observable rather than hiding changes.
Registration follows WF-008 identity and checkout reconciliation and WF-009 context rules; it grants no workflow,
commit, push, PR, or execution authority.

GitHub creation uses the existing authenticated `gh` identity without collecting or displaying its token. Verify
that identity and its permission for the selected personal or organization owner. Use the configured Git
transport, preferably SSH, for pushes. Organization policy, SAML, credential, Permission, scope, collision, and
connectivity failures stop visibly. This human-authorized project operation is distinct from managed Workflow
Publisher authority, and no Builder, Reviewer, or other managed coding Agent receives source-host credentials.

### Durable progress and recovery

Persist one creation attempt and verified intent/result evidence for each external or filesystem stage. A retry
revalidates completed effects and resumes at the first incomplete stage. It must not duplicate an initial commit,
create a second GitHub repository, overwrite unrelated destination content, or infer success from a disappeared
process.

Preserve truthful partial outcomes:

- preparation failure retains the generated Git repository for retry;
- GitHub creation failure retains local work and permits retry or deliberate continuation without publication;
- remote creation followed by push failure retains both sides and permits reconciled push retry;
- checkout or registration failure retains prior effects and permits registration retry; and
- cancellation stops before the next effect and retains everything already completed.

Never automatically delete a directory, commit, remote repository, snapshot, or registration as compensation.
Offer cleanup as separate, explicit, ownership-checked actions. Local or remote naming collisions fail visibly;
using existing content belongs to a separate link/register journey rather than Create Project.

Normal execution remains deterministic. On failure, an in-browser support Agent may receive structured,
secret-safe attempt evidence and the applicable versioned troubleshooting playbook. It identifies the playbook
revision, distinguishes observations from inference, explains partial effects, and recommends an exact command,
revised input, cleanup option, or retry plan. It cannot mutate files, change roots, authenticate GitHub, delete
resources, retry stages, or alter the attempt. The human confirms any correction before deterministic execution
resumes. Unsupported failures retain copyable safe evidence rather than soliciting speculative autonomous repair.

### Browser Agent provider boundary

Browser-hosted planning and support Agents must support OpenRouter API authentication without assuming that a
personal Codex subscription can authenticate a hosted Agent OS installation. Keep the OpenRouter key as an
application/operator secret on the server; never expose it to browsers, prompts, transcripts, generated projects,
runners, or coding-Agent credentials. Browser-Agent profiles choose from an explicit allowlist of inexpensive,
capable models with bounded per-turn and conversation budgets. Record provider, model, usage, and available cost
facts under WF-017 semantics.

Coding Agents running through Pi retain their separate provider and authentication boundary. OpenRouter failure
makes optional conversational assistance unavailable but does not block deterministic creation, retries,
cleanup controls, playbook viewing, or safe diagnostic export. Exact models, budgets, secret deployment, and
provider adapter remain downstream configuration choices.

### Completion and handoff

Completion requires a successfully identified and registered local Git checkout. Show a receipt containing the
human and system repository identities, local path, Checkout and Runner identities, starter source/tag/commit/
digest, initial branch and commit, preparation result, optional GitHub URL/owner/visibility/push result, selected
development profile, database-authoritative context revision, warnings, and deliberately skipped stages.

Open the registered repository Dashboard with deliberate actions such as opening the checkout or starting
Planning. Do not automatically open a planning conversation, create an EPIC, begin Agent work, grant Workflow
authority, or publish anything not present in the confirmed plan.

This scope remains one coherent future planning handoff. Do not create a child map now; open one later only if
implementation discovery exposes a distinct hosted-provider, trust, or multi-source starter problem. Exact
schemas, APIs, UI components, permissions, manifest fields, commands, event payloads, snapshot storage, profile
association representation, and provider/model configuration remain downstream planning details. This decision
creates no live project, repository, checkout, credential, Agent, database record, EPIC, requirement TICKET,
implementation TASK, schema, or production implementation.
