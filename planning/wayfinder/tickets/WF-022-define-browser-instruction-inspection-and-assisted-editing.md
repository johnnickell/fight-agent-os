# Define browser instruction inspection and assisted editing

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-009](WF-009-define-repository-context-and-policy-precedence.md), [WF-012](WF-012-define-browser-planning-and-conversation-ownership.md), [WF-016](WF-016-define-runner-dispatch-and-recovery.md)

## Question

How should the Dashboard let an authorized human inspect recognized repository instruction files and use a human or browser AI conversation to propose safe inline edits without becoming a general-purpose IDE or hiding filesystem, Git, and publication effects?

## Must decide

- The initial allowlist of instruction filenames and locations, discovery rules, repository/checkout/worktree selection, and behavior for missing, nested, overridden, untracked, ignored, or generated files.
- A safe read contract that identifies workspace, repository, checkout, worktree, branch/commit, relative path, content digest, encoding, size, source role, and snapshot relationship without following unsafe paths or exposing unrelated files.
- Authorization and runner availability required to view content, including path containment, symlink handling, file type/size limits, secret-sensitive content, audit evidence, and unavailable or stale checkout behavior.
- Separate human-inline and AI-assisted proposal journeys, including conversation ownership, requested intent, proposed content/diff, validation, expected digest, stale-write rejection, retry, acceptance, rejection, and recovery from disconnects or runner interruption.
- The exact confirmation required before a proposal writes to a checkout and how accepted edits make existing repository-context snapshots stale without rewriting their history.
- Separation among file proposal, filesystem write, repository configuration revision, validation, Git status, commit, push, PR, and publication; no earlier step silently authorizes a later one.
- UI explanation of which instructions Pi actually discovered, which same-directory override applies, and why a file is effective, shadowed, conflicting, changed, or unavailable.
- Whether editing remains limited to recognized instruction files or any later expansion warrants a separately approved repository-editor destination.

## Resolution boundary

This decision defines a focused instruction-file viewing and assisted-editing product contract plus a future planning handoff. It must not read unrelated user files, expose credentials, write an instruction file, create a checkout, start an agent, accept an AI proposal, commit, push, publish a PR, create production endpoints/components, or become a general repository file editor.

## Preferences required

John must choose the recognized file scope, which users may view or accept edits, whether human-authored saves use the same proposal step as AI edits, and how much source/diff/context appears by default. The recommendation should require an online authorized checkout, relative allowlisted paths, expected-digest writes, explicit confirmation, complete diff visibility, and separate later Git/publication actions.

## Resolution

### Focused Pi instruction scope

Provide one focused Dashboard editor for the Pi-native context files in a registered repository's designated local
checkout. V1 recognizes only these exact filenames at the checkout root:

- `AGENTS.override.md`;
- `AGENTS.md`;
- `AGENTS.MD`;
- `CLAUDE.md`; and
- `CLAUDE.MD`.

This matches Pi 0.87.1's documented native context-file names. Use the installed Pi discovery contract to say
which existing file Pi loads and why another recognized file is not loaded; for example,
`AGENTS.override.md` replaces ordinary context files in the same directory. Do not invent precedence or imply
that a convention affects Pi when the installed version does not recognize it.

Do not scan recursively. Nested context files, arbitrary Markdown, source files, `.pi/SYSTEM.md`,
`.pi/APPEND_SYSTEM.md`, Skills, prompts, settings, and other Harness resources are outside this editor. Parent and
user instructions may appear as bounded provenance when needed to explain context composition, but their content
is not exposed or edited through a repository Dashboard. Any future general repository editor or nested-working-
directory journey requires separate approval.

List only recognized root files and label each in direct language such as **Loaded by Pi** or **Not loaded because
AGENTS.override.md replaces it**. Open one file at a time in a Markdown form. Show a complete diff once content
changes and concise tracked, untracked, ignored, modified, branch, checkout-availability, and synchronization
facts. Pi may load an untracked or ignored root context file, so show that state and its publication consequence
without falsely calling the file ineffective. Do not infer that a file is generated. Keep digests, Runner identity,
context revision, and similar technical evidence behind a details disclosure.

Reject symlinks, non-regular files, unsupported text encodings, binary data, and excessive size through ordinary
implementation safety checks. Exact supported encoding and size limit remain downstream decisions. The page is
not a repository tree, arbitrary-file editor, terminal, or IDE.

### Designated checkout and deterministic Runner operations

Each registered repository has one designated local Checkout/Worktree for ordinary Dashboard file operations.
Project creation designates its new checkout. An existing repository designates one during registration or when
first needed, and an authorized explicit repository setting changes it later. Do not ask users to select among
clones or worktrees on each read. Other clones and Workflow-owned worktrees remain outside this editor.

The designated checkout must be online through an eligible Runner and still satisfy WF-008 identity, containment,
and Git evidence. When it is unavailable, moved, unsafe, or actively owned by another operation, explain that
editing is unavailable rather than silently choosing another checkout.

The Runner is a mechanical filesystem adapter. It lists recognized files, reads bounded content, computes a
content digest, applies an exact expected-digest write or delete, and reports the observed Git result. It neither
invokes AI nor chooses content, checkout, effective-file semantics, authorization, reconciliation, commit, push,
or publication. Exact request transport remains downstream implementation work.

### Authorization and browser assistance

Authorize each operation through Fight Access Control rather than role-name checks. Downstream permission design
must preserve these distinct capabilities:

- view repository instructions;
- update repository instructions;
- use browser AI assistance;
- publish repository changes;
- synchronize the designated checkout; and
- configure scheduled checkout synchronization.

AI assistance does not require update authority. A user may request and inspect a suggestion without being able
to submit it. An updater may save local changes without publication authority. Publication and synchronization
always revalidate their separate live authority. Managed roles may bundle compatible Permissions without making
a role name part of application policy.

The human selects one recognized file and describes the desired change. A browser Agent reads that file and uses
a versioned Harness instruction-writing Skill. It may discuss intent, ask clarifying questions, and put suggested
content and its diff into the same editable form. It cannot submit the form, write a file, publish, synchronize,
or expand the product into an arbitrary-file tool. A human with update authority reviews and submits the current
form content.

Create the instruction-writing Skill during later planning, not in this decision. Intend it to adapt, with license
compliance and attribution, Matt Pocock's MIT-licensed
[`writing-for-agents`](https://github.com/mattpocock/skills/tree/main/skills/productivity/writing-for-agents)
material. Make the resulting Harness resource reusable for browser and terminal instruction editing and later
Skill-authoring workflows. Record the Skill revision used for an AI suggestion. Exact prompts, Skill contents,
model interaction, and conversation choreography remain downstream work. Browser-based Skill creation is not
part of WF-022.

Retain the bounded assistance conversation and proposal under WF-012 conversation rules so a browser disconnect
does not turn an Agent result into a filesystem effect or lose an already durable proposal. Direct unsaved form
state retains WF-012's accepted v1 behavior; this decision does not add autosave or simultaneous co-editing.

### Human-confirmed update and deletion

Human-authored and Agent-assisted drafts use the same **Save changes** operation. Show the complete file diff and
submit the allowlisted relative path, exact proposed content, and expected current digest. Creating a missing
root `AGENTS.md` uses expected absence. Revalidate the current file and checkout before one deterministic write.
A successful operation returns the new digest and Git status.

Reject the write when the file changed, appeared, disappeared, or became unsafe after the form's base observation.
Preserve the draft and show current content beside it so the human may reconcile manually or ask the browser
Agent for a revised suggestion. Do not silently overwrite, last-write-win, merge, or ask AI to resolve and apply
a conflict automatically.

Offer a separate **Delete file** action for a recognized file. Show the complete deletion diff and which remaining
file, if any, Pi will load afterward; require explicit confirmation and the current expected digest; then delete
only that file. Creation, update, and deletion do not stage, commit, push, create a pull request, or reload Pi.

An effective instruction-set change marks relevant repository-context snapshots and Agent sessions stale under
WF-009. Preserve their immutable context and history; never reload or rewrite an existing session silently. A
change to a file Pi does not load updates observed repository evidence but does not stale sessions unless the
effective set changes. New or explicitly continued sessions resolve current context through WF-009.

Record accepted filesystem mutations and AI-assistance requests with initiating user, acting Agent when present,
Skill/context revision, intent, result, and safe evidence. Ordinary authorized reads use standard security and
request logging rather than a domain event for every page view.

### Separate Git publication

Saving never authorizes Git publication. After a local change, make **Create pull request** the default separate
action. Require no unrelated checkout changes, then preview the generated branch, editable commit message,
selected instruction-file diff, remote, and registered default base branch. One explicit confirmation performs
the deterministic branch, selected-file commit, push, and GitHub pull-request sequence. Do not pass GitHub
credentials or publication authority to the browser Agent.

Preserve and report each partial outcome if commit, push, or pull-request creation fails. After successful
publication, return the designated checkout to its clean default branch rather than leaving it on the proposed
branch. A repository policy may separately permit **Commit and push current branch**, but the user must invoke
and confirm that action explicitly.

Show the resulting pull-request number, title, source and target branches, publication and merge state, recovery
action when applicable, and an **Open pull request on GitHub** link that opens in a new tab. Creating a proposal,
saving a file, committing, pushing, opening a pull request, merging, and synchronizing remain distinct facts and
authorities.

### Default-branch synchronization

Keep the designated Dashboard checkout on the registered default branch. Provide an explicit **Sync checkout**
action and a scheduled remote check that is enabled by default and configurable by an authorized repository
administrator. The scheduled operation may update the checkout automatically only when all of these facts hold:

- the checkout is online, eligible, and still matches its registered identity;
- it is on the registered default branch;
- its working tree and index are clean;
- it has no unpushed local commits or divergence;
- no active Workflow or publication operation owns it;
- its upstream matches the registered source; and
- the remote default branch is strictly ahead and can be integrated by fast-forward only.

Fetch and apply only a fast-forward update. Never auto-stash, reset, rebase, integrate divergent history, discard
changes, switch a dirty checkout, or resolve conflicts. If any condition fails, make no filesystem mutation and
show the update availability and blocking reason. The manual action rechecks the same conditions and previews
incoming commits; it does not unlock destructive recovery behavior.

A synchronized instruction change makes an open browser form stale and follows the expected-digest rejection
path. This keeps merged pull requests flowing into a clean designated checkout without letting background work
rewrite local or divergent work.

### Follow-up boundary

Do not create a focused child map. This is one bounded product journey whose exact schemas, routes, React
components, permission identifiers, runner transport, content limit, atomic-write mechanics, Git command
sequence, scheduler and polling interval, provider/model choice, prompts, Skill text, and evidence representation
belong to later planning. Open a new Wayfinder decision only if implementation discovery exposes a genuinely
separate general repository editor, nested Pi working-directory model, or Git-publication product.

This decision creates no instruction file, checkout designation, Runner operation, Skill, Agent session, Git
branch, commit, push, pull request, scheduled job, EPIC, requirement TICKET, implementation TASK, schema,
database record, or production implementation.
