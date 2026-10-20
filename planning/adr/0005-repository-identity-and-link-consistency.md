# ADR 0005: Define repository identity and recoverable checkout linking

- **Status:** Accepted by John on 2026-10-19
- **Date:** 2026-10-19
- **Owners:** Fight Agent OS maintainers; prepared under [TASK-00077](../tasks/00077-TASK.md)

## Why we need this

Fight needs to recognize a repository even when someone renames it, moves a checkout or adds a worktree.
A path or remote URL cannot do that reliably: two clones can have the same remote, and a copied directory
can carry the same local marker. We also need an honest answer when linking stops halfway through.
PostgreSQL and Git configuration cannot commit together.

[WF-008](../wayfinder/tickets/WF-008-establish-repository-identity-and-registration-boundaries.md) already
sets the product meaning. This decision supplies the identity, matching and recovery rules; it does not
register anything or implement the future [approved-root capability](../tasks/00073-TASK.md).

## The concepts, with an example

- **Workspace:** the installation's collaboration space. Start with one explicit Workspace.
- **Repository:** the intended collaboration/publication target in that Workspace, not a directory.
  For example, the team's `fight-agent-os` project is one Repository; its fork is a different Repository.
- **Host:** an explicitly enrolled machine, not a hostname supplied by a browser.
- **Checkout:** one local Git common directory on one Host. Cloning the project onto a laptop twice creates
  two Checkouts of the same Repository, with different Checkout IDs.
- **Worktree:** one working tree belonging to a Checkout. `git worktree add` creates another Worktree sharing
  that Checkout's common directory, not another clone. Each linked Worktree has its own ID.

## Six accepted decisions

### 1. Give each concept its own stable identity

Agent OS generates typed, COMB-backed IDs for Workspace, Repository, Host, Checkout and Worktree.
Paths, names, slugs, remotes, provider IDs and Git markers remain changeable evidence, never identity.
A confirmed move can preserve IDs; a fresh clone gets new Checkout/Worktree IDs.
**Trade-off:** we must retain link history and reconcile moves instead of treating a familiar URL as proof.

### 2. Keep registration state and its consistency rules in Workspace

Workspace owns separate Repository, Host, Checkout and Worktree records, authoritative current state and
append-only audit. Bootstrap explicitly creates the initial Workspace and local Host together, once.
Use the existing shared PostgreSQL connection and transaction authority, expected revisions and database
constraints for active Workspace slug/source uniqueness, relationships and physical-link reservations.
State, required audit and original command result commit together; competing writers cannot both win a claim.
[ADR 0001](0001-application-ownership-and-orchestration.md) and
[ADR 0002](0002-postgresql-consistency-and-durable-effects.md) still govern ownership and transactions.
**Trade-off:** serialized registration writes favor understandable consistency over maximum throughput;
owned current-state/audit records avoid imposing event sourcing or a generic job engine on this domain.

### 3. Use source information conservatively

Normalize only explicitly supported source types and approved equivalences. Fork/upstream relationships are
not equivalence. Provider-native IDs can strengthen verified evidence but never replace Repository identity.
An unchanged confirmed link resolves its existing IDs. A unique source match only proposes a first link;
multiple matches need human selection. Missing or contradictory evidence never silently reassigns a link.
Copied/conflicting markers refuse automatic use; an ambiguous move needs old-location reconciliation.
**Trade-off:** some legitimate aliases and moves require human work, rather than a broad matcher guessing wrong.

### 4. Confirm the exact physical link, not just the project name

First links and ambiguous relinks require a currently authorized human. Confirmation binds actor, IDs,
Host, approved canonical paths, common directory, marker/source observations and relevant revisions, with
an expiry. Any material change invalidates it; retry cannot silently approve new evidence.
Check containment and access for the working tree, Git administrative/common directories and config targets.
Unavailable or unqualified filesystem capability blocks linking, not viewing authorized registration metadata.
**Trade-off:** confirmation can become stale and need repeating, but it cannot authorize a different location.

### 5. Make incomplete linking visible and recoverable

Commit a durable database intent and path reservations first; conditionally write/re-read only the owned
shared Git marker outside a business transaction; then finalize database state, audit and original result.
Until finalization commits, report pending, held or refused—not linked success. Retries inspect/resume the
same operation and generation. Unknown/conflicting markers are never overwritten; unrelated config,
branches, remotes and working files stay untouched, and the marker is never committed or treated as authority.
Reservations do not time out beneath a paused writer. Recovery or cancellation must exclude the old writer;
if that cannot be proved, keep the operation held. Cleanup is explicit compensation, not cross-resource rollback.
**Trade-off:** this avoids an undiscoverable partial link, but an unavailable Host can require operator recovery
and hold reservations indefinitely. The marker and local locks are not protection against a hostile file owner.

### 6. Preserve history and keep authority separate

Source changes, relinks, archive and restore preserve identities/history. Archive releases active slug/source
claims but retains files/links; active dependent work or pending links must be resolved first. Restore must
reclaim the exact slug/source set or fail without a partial restore. Designation selects a linked Worktree;
an unavailable designation never means “use another directory.”
View, register, metadata/source change, link/relink, designation and lifecycle each need server-side authority.
Registration/linking grants no Repository access, Planning cutover, Execution, Agent, Runner or Git publication
permission. Dashboard and context consumers use these rules; they do not own or bypass them.
**Trade-off:** retained history and explicit conflicts complicate lifecycle handling but prevent silent reuse
or accidental transfer of access and Planning/Execution history.

## Detailed contract and evidence

The [supporting technical note](../../docs/engineering/REPOSITORY_LINK_CONSISTENCY_PROPOSAL.md) contains the complete
source and matching tables, confirmation binding, marker shape, interruption matrix, fencing/recovery,
permissions/lifecycle rules, inspected contracts and downstream evidence requirements. It is part of this
same accepted decision, not a second ADR or optional safety appendix.

Exact URL grammars, the ten-minute confirmation default, marker encoding/key spelling and lock protocol are
**accepted implementation details** in that note, not the architectural reasons for these six choices.
They remain fully specified and unchanged by this editorial split; amendments must be explicit, not silent
implementation substitutions. Package/Git observations support the proposal, not proof of a working link writer.
TASK-00078/00079/00080 own later implementation; physical writers also require approved-root/platform qualification.

## Human acceptance

John replied exactly **“approved”** on 2026-10-19 after reading the shortened ADR and linked technical note
(coordinator observation: 23:02:10 UTC). He accepted both without amendments. Exact pre-recording SHA-256 subjects:

- This ADR: `5d16b0119ff8ae9355e2e232b913883953e4c282a23f7e204cb8c212ccdbadc9`
- Supporting note: `6d74369ce8ad3322f0304ce249ddaaf9dbfbfaf8355322f407fefdd4295fc3aa`

Design acceptance is not a gate waiver, independent Review/QA or TASK completion. TASK-00077 remains incomplete
pending required verification after Storybook OOM; dependent TASK prerequisites and physical-writer qualification remain.
