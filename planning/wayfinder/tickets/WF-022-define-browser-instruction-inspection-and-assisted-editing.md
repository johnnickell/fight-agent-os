# Define browser instruction inspection and assisted editing

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
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

Write this only when file scope, safe read/write boundaries, proposal ownership, confirmation, concurrency, snapshot invalidation, and downstream Git separation are approved.
