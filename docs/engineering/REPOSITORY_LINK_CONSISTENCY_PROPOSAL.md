# Repository identity and checkout linking — accepted technical contract

**Supporting note for [ADR 0005](../../planning/adr/0005-repository-identity-and-link-consistency.md), not a separate ADR or decision authority.**
Prepared under [TASK-00077](../../planning/tasks/00077-TASK.md) and [TICKET-00024](../../planning/tickets/00024-TICKET.md), 2026-10-19.
John accepted the ADR and this complete technical contract without amendments on 2026-10-19; see the ADR's
[human acceptance record](../../planning/adr/0005-repository-identity-and-link-consistency.md#human-acceptance) for the exact subjects.

Read the ADR for the six architectural choices and trade-offs. Read this note when evaluating the complete
source/matching, confirmation, marker, recovery, lifecycle, permission and evidence contracts. These required
safety guarantees are part of the same accepted decision, not optional guidance or decisions deferred to implementation.
The eight numbered sections retain the detailed contract from the original draft; they are not eight new ADRs.

Exact URL grammars and profile versions, the ten-minute confirmation default, JSON encoding/key spelling and
specific database/Host/Git lock mechanics are **accepted implementation details**, distinct from the ADR's core
commitments. They remain specified below, not left unspecified. Acceptance changes none of them or their required
guarantees: deterministic equivalence, exact expiring confirmation, conditional owned-key writes, durable
reservations and proved writer exclusion. Any amendment must be explicit in the same ADR decision;
an implementation cannot silently weaken these guarantees or substitute different details.

## Context and decision boundary

[WF-008](../../planning/wayfinder/tickets/WF-008-establish-repository-identity-and-registration-boundaries.md)
settles the product meaning: one Repository is one intended collaboration/publication target in one Workspace.
Paths, remotes, provider IDs and Git markers are evidence, not system identity. This decision makes those
semantics implementable without claiming that PostgreSQL and Git configuration can commit together.

[ADR 0001](../../planning/adr/0001-application-ownership-and-orchestration.md) continues to govern ownership and dependency direction;
[ADR 0002](../../planning/adr/0002-postgresql-consistency-and-durable-effects.md) governs PostgreSQL, the shared connection,
transaction authority, constraints and external effects. ADR 0005 adds no package identity authority, credential
outbox, generic job platform or event store. It selects no production migration, endpoint, UI or permission
assignment. Everything below is part of ADR 0005's accepted contract.

The planned [approved-root capability](../../planning/tasks/00073-TASK.md) is a physical-link prerequisite, not something
this design TASK implements. Registration metadata still requires completed TASK prerequisites; actual link writers
must also wait for that capability and qualify the host-specific filesystem guarantees below.

## Detailed accepted contracts

### 1. Identity and aggregate ownership

All five identities are distinct Agent-OS-owned readonly types extending Fight Common `UniqueId`:
`WorkspaceId`, `RepositoryId`, `HostId`, `CheckoutId`, and `WorktreeId`. Normal creation calls the owning typed
`generate()` factory internally; that factory uses `Uuid::comb()`. PostgreSQL UUID primary keys enforce uniqueness.
COMB improves locality; it is not an ordering, secrecy, authorization or collision-impossibility guarantee.
Normal callers never choose these IDs. Preserved-ID imports and exceptional manual references require separately
validated paths, not a relaxed normal-create endpoint.

| Aggregate/root | Owned state and invariant |
|---|---|
| Workspace | One initial durable Workspace; membership/multi-workspace isolation is deferred. No singleton assumption becomes a cross-workspace identity rule |
| Repository | Workspace reference, display name, active slug, lifecycle, primary/equivalent sources, explicit fork/upstream relationships, metadata/source/lifecycle revisions, designated Worktree reference and designation revision |
| Host | Workspace reference and installation-enrolled machine identity, lifecycle and revision. Enrollment is explicit; hostname, container name and browser input cannot mint or select host authority |
| Checkout | Repository/Host references, confirmed common-directory observations and locator history, link generation/revision, active/pending/held link state. One linked common directory belongs to one Checkout on one Host |
| Worktree | Checkout reference, own ID, canonical working-tree and Git administrative-directory observations, locator history, revision and availability. Sharing objects or a branch name does not merge Worktree identities |

Workspace is the bounded-context owner, not a giant aggregate that loads every Repository or Worktree.
Repository/Host/Checkout/Worktree are separate aggregate roots; cross-root constraints belong to the owning
Application transaction and database constraints. Planning and Execution reference `RepositoryId`; they never
recreate its aggregate or use a remote/path foreign key. Access Control retains User/Agent identity, Roles and
Permissions. No Access Control command/entity alias is introduced.

An explicit installation bootstrap transaction in TASK-00078 creates the one initial Workspace, its audit
record and original result. A uniquely constrained installation bootstrap slot prevents two winners. Retry reads
the same committed Workspace ID; startup/query failure never creates another Workspace. Explicit initial local
Host enrollment remains TASK-00079, with its own atomic state, audit and original result and idempotent retries.
This is not Runner enrollment, a browser auto-registration flow or a multi-workspace access model.

**Approved amendment, 2026-10-20:** During TASK-00078 intake John replied exactly **“approved”** to the
coordinator's proposal to preserve Workspace bootstrap in TASK-00078 and explicit Host enrollment in TASK-00079.
[ADR 0005 records the exact question](../../planning/adr/0005-repository-identity-and-link-consistency.md#approved-bootstrap-amendment--task-00078).
This replaces only the original joint-bootstrap requirement; original acceptance history and every other source,
authority, uniqueness, transaction, recovery and per-operation atomicity/idempotency guarantee remain intact.
No Host/Checkout/Worktree persistence or physical observation/linking, Planning or Execution effect is added.

Use authoritative **current state**, independent monotonic expected revisions for the fields being changed,
append-only owned audit and immutable operation results. Owned events such as `RepositoryRegistered`,
`RepositorySourceChanged`, `CheckoutLinked`, `WorktreeLinked`, `CheckoutRelinked`, `WorktreeDesignated`,
`RepositoryArchived` and `RepositoryRestored` describe committed facts, not previews or filesystem observations.
A pending link intent is not `CheckoutLinked`. Post-commit in-process dispatch may be lost; it is not recovery
or a claim of durable event delivery. Planning's future event sourcing does not require Workspace event sourcing.

The inspected package audit model only accepts User/Agent subjects and a closed context vocabulary. Do not
smuggle Repository IDs into that model. Workspace owns its typed Repository/Host/Checkout/Worktree audit records
on the same connection. They contain verified actor type/ID, action, target typed IDs, operation ID, predecessor/
result revisions, server time and bounded reason/result codes. Recovery records both the initiating human and
trusted recovery executor. State, required audit and operation result commit or roll back together.

### 2. PostgreSQL consistency and competing writers

Use the existing container-scoped EntityManager and its exact DBAL Connection, with Fight Common
`DoctrineTransactionalUnitOfWork::commitTransactional()` as the sole business transaction authority. No nested
transaction, second write connection, independent repository flush, reflection hydration or request-time schema
change is permitted. Use explicit supported reconstitution and targeted DBAL comparisons/locks where needed.
All new schema is introduced through Doctrine Migrations in the later implementation TASKs.

| Invariant | Authoritative boundary and race outcome |
|---|---|
| Active slug | Partial unique claim on `(workspace_id, canonical_slug)` for active Repositories. Slug is lowercase ASCII, 1–80 characters, alphanumeric ends and internal single hyphens; reject invalid input rather than silently rename. Display name is separate |
| Approved source identity | Each active Repository owns unique `(workspace_id, descriptor_version, source_key)` claims for every approved primary/equivalent locator and verified provider-native key. One source key cannot be owned twice in the Workspace |
| Relationship integrity | Typed UUID primary keys and foreign keys, including same-Workspace/Repository/Host relationship constraints. No source claim or Worktree designation can reference another Repository accidentally |
| Physical link reservation | Unique active or pending claim on `(host_id, canonical_common_directory)` and separately on `(host_id, canonical_worktree_path)`. Observed filesystem identity is an additional conflict signal, never the primary key |
| Designation | One nullable designated Worktree reference per Repository. The target must be a linked Worktree of that Repository; unavailable targets remain visible but cannot serve filesystem operations |
| Idempotency | Unique `(workspace_id, verified_actor_type, actor_id, command_kind, idempotency_key)` plus immutable canonical request digest and original typed result. Reusing a key for different intent is a conflict |
| Revision | Conditional writes compare the complete relevant expected state, lifecycle and exact expected revisions, then increment the owned revisions. No last-write-wins or blind retry against new state |

Claim keys use canonical bytes, not a lossy hash alone. A digest may index observations but must not substitute
for equality of their stored canonical representation. Schema constraints have stable names. Translate only
recognized named violations into safe slug/source/link/stale conflicts; unknown driver errors remain sanitized
internal failures, not broad SQLSTATE-based conflicts.

Use READ COMMITTED and a Workspace-scoped transaction fence for bootstrap, slug/source claims, lifecycle and
link admission/finalization. Under it, take participating aggregate row locks in stable typed-ID order. This
intentionally favors a simple bounded installation over maximum write throughput. Every related writer,
including source change, designation and archive/restore, participates; a query's precheck is not a fence.
Unique constraints remain the final race authority. No database transaction or transaction advisory lock is held
across Git reads/writes, waiting for confirmation or host I/O. Separate recovery phases use the same canonical
transaction capability, never a nested transaction. Admission and finalization integrate the current Access Control
reference/authority fences for their actor and Permissions on that connection; a concurrent revocation must
serialize before the protected write or after its commit, not slip between an unchecked permission read and mutation.

Two registrations for the same source or slug yield one winner and one safe conflict, not two IDs returned as
success. A source change atomically replaces the claim set, revision, audit and original result; a conflict rolls
back the whole change, retaining the old set. Host/Repository retirement or other conflicting mutations cannot
pass a pending physical-link reservation.

### 3. Closed source descriptors and equivalence

The descriptor union is versioned and closed. Unknown versions, schemes, fields and malformed inputs are rejected.
No remote lookup, fetch, DNS resolution, credential helper, SSH command, Git URL rewrite or provider call happens
as a side effect of normalization or preview. Sources are optional; the empty set means no approved source.
Remote names (`origin`, `upstream`) carry no precedence or identity.

| Type | Canonical fields and normalization | Equality/evidence |
|---|---|---|
| `git_network_v1` | `transport` is `https` or `ssh`; ASCII DNS/punycode host lowercased; explicit integer port 1–65535 with default 443/22 normalized; HTTPS forbids userinfo; SSH has an explicit case-preserved username. Path uses ASCII segments `[A-Za-z0-9._~-]+`, excluding `.`/`..`, empty segments, query, fragment, percent escapes, control bytes and backslashes. Preserve path case and literal `.git` suffix | Exact canonical tuple only. HTTPS paths are absolute. SSH URL absolute paths and scp-like `user@host:path` home-relative paths have distinct `path_mode` values. No generic SSH/HTTPS, host-alias, case, suffix or home-relative/absolute equivalence |
| `git_local_v1` | Enrolled `host_id` and exact canonical absolute directory locator, using approved-root platform rules; relative/file-URL ambiguity and unavailable/unapproved resolution are rejected | Exact Host plus canonical path only. A local remote path is still mutable evidence, not Checkout identity; never scan/open its contents to infer equivalence |
| `github_repository_v1` | Authority is exactly `github.com`; owner is 1–39 ASCII alphanumeric/hyphen characters with alphanumeric ends and no consecutive hyphens; repository uses 1–100 ASCII alphanumeric/underscore/dot/hyphen characters, excluding `.`/`..`. Lowercase both, remove one terminal `.git`, then validate nonempty repository again. Permit only canonical HTTPS port 443 or SSH `git` port 22 input forms for this profile; other ports/users stay generic | Explicit approved GitHub profile joins its SSH/scp/HTTPS locator forms. Optional provider-verified immutable repository ID is a canonical positive decimal string without leading zeros, an extra unique claim keyed by authority. No guessed Enterprise, GitLab or arbitrary-provider profile |

Reject empty names, non-ASCII hosts not already in punycode, trailing DNS dots and malformed locators rather
than inventing another normalization. A safe implementation may report an unsupported descriptor instead of
broadening this union. Credentials embedded in input are rejected before persistence and never echoed; generic
SSH usernames are routing fields, not stored passwords/keys. Byte limits must be bounded by the implementation
without truncation or equality-changing coercion.

Use the most specific supported approved profile for canonical input; do not keep a parallel generic claim that
lets the same GitHub locator evade uniqueness. Explicit equivalent descriptors form a Repository-owned set, not
a global fuzzy matcher. Approval claims every member transactionally. Cross-transport/provider equivalence
outside the GitHub profile requires a human-approved addition of exact descriptors and conflict preview. Two
arbitrary paths sharing a suffix are never equivalent. A native ID obtained from a qualified authenticated
provider can strengthen evidence only with recorded authority/provenance; caller-supplied IDs cannot.

Transfers, renamed provider locators and reused URLs require explicit source change. A stable provider ID does
not silently change approved locators, merge two registrations or authorize a checkout. Fork and upstream are
explicit directional Repository/source relationships, not equivalences; sharing commits or an `upstream` remote
never makes the fork the same collaboration. Multiple remotes matching different registered Repositories are an
ambiguity even when one is named `origin`.

### 4. Matching precedence and physical availability

Read-only resolution may return an existing identity, a proposal, an unavailable/changed-evidence hold or a refusal.
It creates no links. Existing-link priority is not permission to ignore contradictory fresh evidence. Automatic
resolution below means read-only identity resolution at the already confirmed physical location, not automatic
first linking, new Worktree enrollment, designation, execution or authority refresh.

| Evidence | Resolution and permitted next action |
|---|---|
| Existing confirmed Host/common-directory/Worktree link, exact recognized marker and unchanged relevant observations | Resolve the existing IDs automatically; derive current availability separately |
| Same confirmed common directory, known distinct Worktree administrative directory/path | Resolve its Checkout. An already linked Worktree keeps its own ID; an unseen working tree is a candidate and needs explicit link confirmation before minting its WorktreeId |
| No durable link/marker, exactly one approved normalized source match | Propose that Repository only. Human confirms the first Checkout/Worktree link; no automatic write |
| Multiple matches, fork/upstream or divergent matching remotes | Explain ambiguity; human explicitly selects the intended collaboration and exact source relationship. No priority by remote name or commit graph |
| Missing remote, no supported source, or no match | No inferred match. Authorized human selects an existing Repository or separately registers one; source-free linking still needs full path/marker confirmation |
| Existing link with changed/missing/contradictory source or missing marker | Retain identity/history, mark link held, refuse ordinary physical operations pending explicit reconciliation. Source evidence cannot silently reassign the Checkout |
| Same recognized marker at a new path after a claimed move | Propose a move of existing Checkout/Worktree identities only after old-location reconciliation. Old location unavailable is not proof of a move. Require human attestation plus fresh approved-root evidence; otherwise hold |
| Reclone, with or without the same source/HEAD | New common directory without the marker needs a new CheckoutId and new WorktreeIds after confirmation; RepositoryId may be the same. Never resurrect the old CheckoutId from source similarity |
| Copied marker at another common directory or Host | Refuse automatic resolution/writes. If both locations exist, explain the duplicate. Human may identify a move, or quarantine the copy and explicitly reset its marker before confirming a new Checkout; never overwrite it in a first-link command |
| Unknown marker version, malformed/duplicate values, wrong Workspace/Repository/Host/Checkout, unknown operation/generation, or source/DB disagreement | Refuse. Retain evidence; exact-value operator reconciliation is required, not marker adoption or replacement |
| Bare repository, unsupported shared object-store layout, symlink escape, ambiguous aliases, unavailable Host/root or inaccessible config | No physical link. Registration metadata may remain viewable; do not claim containment or write capability |
| Directory, display-name, slug or branch similarity only | No match and no identity proposal based on that similarity |

A file copy and a move may preserve identical marker bytes. Git cannot prove physical continuity, especially
when the old location is offline or a copy replaces it at the same path. Observed device/file IDs, canonical
paths and Git administrative-directory facts help detect changes but are not durable identities or anti-cloning
secrets. Document this trust limit: ordinary operations assume the approved local filesystem/operator boundary;
known drift holds the link, and an ambiguous move needs human judgment, not a claimed cryptographic proof.

Containment applies to the working tree, Git administrative directory, common directory and config/lock targets,
not just `--show-toplevel`. Each must be a regular supported local location under approved roots on the enrolled
Host, with verified ownership/access. Linked worktrees outside approval can be listed as unavailable without
opening them. Do not follow a `.git` file or common-directory pointer into an unapproved parent. Recheck path and
object identity before each write; symlink substitution, root revision/mount drift or inability to pin the target
fails closed. HTTP Actions only map input; the approved-root Adapter and Application link use case own this work.

### 5. Preview, confirmation and original results

Preview is read-only and secret-safe. Persist a bounded expiring confirmation proposal only as an explicit
application preparation operation, never as a query side effect. The server binds its opaque confirmation
reference and immutable canonical digest to all of:

- verified human actor/type, Workspace, Repository, enrolled Host and intended command;
- internally reserved Checkout/Worktree IDs (if new), intended link generation and operation ID;
- exact canonical approved working-tree path, common directory, Git administrative directory, observed object
  identities, root IDs/revisions and actual mount/read/write/ownership capability;
- raw common-config marker cardinality/bytes, recognized marker fields, relevant config shape and worktree facts;
- the complete sorted remote-name/raw-locator observations and normalized supported source set (secret-bearing
  observations cause rejection rather than being persisted), plus source relationship/claim decisions;
- relevant Repository, Host, Checkout, Worktree, source, lifecycle and designation expected revisions;
- safe reason/selected ambiguity resolution, idempotency key and server-issued expiry: ten minutes from preview.

Material drift invalidates the proposal, including actor/authority changes, source edits, path movement, marker
changes or revision changes. Expiry is not extended by retry. Branch/HEAD are informational, not identity evidence;
a command that also intends a branch operation is outside this contract. Confirmation freshly checks current
server-side permission/access, actor and evidence before admitting intent. It never trusts a client boolean or
cached Permission result. No secret or full config blob appears in a browser or audit record.

Confirmation and operational evidence are private records with bounded size/count and authorized scoped reads.
Never persist full Git config, credentials, credential-bearing URLs, arbitrary driver/provider messages or copied
instruction contents in proposals, results, audit or ordinary logs. Public responses use typed IDs and safe
reason codes; exact private paths/source details require the current view authority. Bound preparation/recovery
admission and attempts so an unauthorized or repeated request cannot create unbounded pending work. Unused
confirmation payloads may expire; pending intent, original results, generations and audit remain durable until a
separately approved retention policy proves that removing them cannot enable replay or orphan a marker.

A matching idempotency key/request digest returns the original committed result or current pending/held operation
status after current view authorization; it does not consume another confirmation or mint new IDs. A different
actor cannot replay the confirmation. Lost-response recovery reads the original result; it does not reapply the
command against the latest revisions. No matching result means inspect durable state, not assume rollback.

### 6. Marker format and exact-key ownership

Use the non-reserved owned key **`fightagentos.checkoutlink`** in the raw common-directory `config`, not a tracked
file, global/system config or per-worktree config. Its value is one compact UTF-8 JSON object, in this fixed field
order: `version` (integer 1), `workspace_id`, `repository_id`, `host_id`, `checkout_id` (canonical lowercase typed
non-nil UUID strings), `link_generation` (positive integer), `operation_id` (canonical internally generated non-nil
COMB operation UUID).
Reject unknown fields, duplicate JSON fields, unsupported versions, noncanonical encoding and multiple key values.
The marker is a public locator/correlation hint, never a credential, signature or independent registration authority.
The included CheckoutId satisfies WF-008; the other fields make accidental cross-installation/cross-generation
reuse explicit. No WorktreeId is stored in shared config.

Read the exact raw file with include expansion disabled and trusted Git invocation/environment. Ignore ambient
system/global marker values for identity. Inspect worktree-local config for a conflicting marker; refuse any such
marker rather than choose the effective last value. For this first profile, local/common or worktree `include`/
`includeIf` directives are unsupported for link mutation: report the configuration hold without following them
outside approval. Do not execute repository hooks, helpers, aliases, configured commands or URL rewrites while
inspecting Git. Never turn an unknown value into an owned value simply because it parses as a UUID.

The later Git Adapter must provide an exact-key conditional mutation, not a shell sequence of read-then-unchecked
`git config set`. Under Git's exclusive common `config.lock` protocol it rechecks target identity and raw
cardinality/value, then publishes a replacement preserving all unrelated config bytes/semantics, permissions and
ownership. First write requires absence; retry accepts only the exact expected owned bytes; an approved relink
requires the exact recognized predecessor. Multiple, unknown or changed values are a refusal. Config lock busy
is retryable without stealing/deleting it. Worktree-specific marker writes are forbidden.

Qualify lock, no-follow/pinned-target access, atomic replacement and crash durability on the supported local Linux
and macOS filesystems before enabling the writer. If Git/library primitives cannot satisfy conditional exact-key
publication, block that implementation; do not claim that CLI write success proves the protocol. A completed write
is re-read, and config plus parent-directory durability is established where the platform supports it. After
restart the database always reconciles the observed marker, even if an earlier write reported success.

Never commit the marker, edit branches/remotes/index/working files, change ownership/mode to gain access, add a
repository trust exception or rewrite other keys. An owned leftover `config.lock` may require explicit operator
cleanup only after proving no live writer and checking its ownership; unknown locks are never removed automatically.

### 7. Database-first link intent, fencing and recovery

Choose **durable database intent, conditional marker publication, then database finalization**. Registration by
itself has no marker phase. Link/relink is a recoverable multi-phase operation, not one atomic command spanning
PostgreSQL and Git. Safe public outcomes are complete, pending, held/conflict or refused; never return linked
success until finalization commits.

1. **Observe and confirm.** Resolve approved paths/Git evidence, prepare the exact proposal and obtain human
   confirmation. Re-observe before admission; stale evidence/permission/expiry refuses without link mutation.
2. **Admit intent (transaction A).** Under the database fences, recheck authority, revisions, uniqueness and
   confirmation; atomically consume confirmation, reserve exact paths/IDs/generation, store expected predecessor
   and intended marker plus evidence, append intent audit and persist pending original-result state. New links
   remain unavailable; relinks hold the existing link/designation. Commit before any Git mutation.
3. **Publish marker (outside a business transaction).** The enrolled Host executor takes a per-common-directory
   exclusive local coordination lock, checks current pending operation/generation against the database, and
   revalidates root/path/source/config evidence. The exact-key Git lock protocol then publishes the intended marker
   or observes it already present. No database availability means no marker mutation. Keep the Host coordination
   lock through observation and finalization. A crash releases the process lock, not the durable reservation.
4. **Finalize (transaction B).** Recheck current authority, expected operation/generation, aggregate revisions and
   held reservations, and the fresh marker/path/source observation under the Host lock. Atomically establish the
   linked Checkout/Worktree state, locator history, exact original result and required audit; advance revisions
   and mark the operation complete. Emit owned link facts only after commit. Release the Host lock.
5. **Respond.** Return the durable original result, or a safe pending/held status and operation reference. Clients
   may explicitly retry/status-check. They never drive an unchecked second marker write.

The pending operation is the durable fence: related source change, archive/restore, conflicting link, relink and
designation mutations refuse while unresolved. Reservations and generations do **not** expire merely because a
worker lease, preview or timeout expires. A token checked before filesystem I/O alone cannot fence a paused writer.
Therefore this design has no automatic lease takeover/cancellation that releases a reservation beneath one.
Retries resume the same operation under the exclusive Host lock. Operator cancellation, marker reset or generation
succession must prove the old Host writer is stopped/excluded and hold that same coordination lock before any
transaction releases or replaces intent. If the Host is unavailable or exclusion cannot be proved, remain held.
Across Hosts, no worker writes another Host's directory; cross-Host migration uses explicit held reconciliation.

Git's lock protects cooperating Git writers; the Host lock protects all cooperating Agent OS link/recovery paths.
Neither is a security lock against an arbitrary local filesystem owner. Uncoordinated edits detected before/after
publication hold the operation. There is no enforceable promise of atomicity with hostile external mutation.

| Interruption or competing action | Durable/observed state | Retry, reconciliation or refusal |
|---|---|---|
| Before preview/confirmation, after preview persistence, or confirmation lost/expired before A | No link intent or marker change | Fresh preview if needed; unused proposals expire. Never infer consent |
| Competing registration, source replacement or restore | Constraints/fences choose one committed claim set | Loser gets safe conflict and fresh preview; old state is intact on rollback |
| Stale confirmation before/during A | Revisions/claims/evidence no longer match | Refuse with no admitted intent; do not refresh silently |
| A rolls back | No durable intent; no marker phase may start | Same idempotency retry revalidates; absent result is not permission for a Git write |
| A commit acknowledgement lost | Intent may or may not exist | Query by actor/key; if database unavailable report unknown/pending and do not write |
| A committed; process stops before marker observation/lock/write | Pending reservation, marker absent or predecessor | Discover pending intent after restart; revalidate and resume exactly that operation |
| Marker evidence changed, root gone, permission revoked, or config locked after A | Pending reservation remains; no authorized new effect | Hold/refuse drift; lock contention may retry same operation. No silent overwrite or reservation release |
| Crash after config.lock creation, before publication | Old config or leftover lock; DB pending | Inspect raw config; an owned abandoned lock requires proved exclusion/operator cleanup. Unknown lock escalates |
| Crash or I/O error during atomic config publication/durability | Marker may be absent, predecessor, intended or damaged; DB pending | Re-read. Absent/predecessor may resume only with unchanged evidence/authority; exact intended can finalize. Damaged/other bytes hold for operator; never assume rollback |
| Marker write succeeds, process stops before readback or B | Intended marker may exist; DB still pending | Same-operation recovery re-observes. Exact intended + unchanged evidence permits B without another write |
| B fails/rolls back, or current permission is revoked after write | Marker may be intended, DB pending/held | Never report linked. Keep reservation; recover only with current authorization or perform explicit authorized cleanup under writer exclusion |
| B commit acknowledgement lost | Either complete or still pending, marker intended | Read original operation result; if pending, reconcile same generation. No new IDs or duplicate event fact |
| B committed, process stops before dispatch/response | Linked state, audit and original result exist | Retry returns original result; missing in-process event is not rollback or durable-delivery proof |
| Completed link later loses/changes its marker or filesystem evidence | Historical IDs remain, availability becomes held | Reconcile explicitly; never auto-recreate a marker or relink from remotes |
| Paused old writer versus cancellation/relink/new generation | Pending old reservation cannot be released under live writer | Prove writer exclusion under Host lock first; otherwise hold indefinitely rather than allow a stale write |
| Database restored behind an already written marker | Marker's operation/generation may have no matching intent | Refuse orphan adoption. Operator reconciles database history/backups and exact marker under exclusion |
| Response lost during cancellation/reset | Original cancellation operation may have committed | Inspect operation + raw marker; repeat only the same exact conditional cleanup, never clear an arbitrary key |

A bounded trusted recovery operation discovers pending/held link intents from PostgreSQL independently of event
or browser delivery. It runs on the enrolled Host with explicit internal executor authority, uses the same fences
and exact-key Adapter, and records attempts/reason codes. Ordinary retries cannot bypass original actor permission
revocation. When completing an effect needs new human authorization, issue a new bound reconciliation proposal;
never silently impersonate the initiating actor. No general scheduler/outbox is required by ADR 0005.

Cleanup is explicit, audited compensation, **not rollback**. For an abandoned first link, retain provisional
identities/history. After proving writer exclusion, an authorized reconciliation may conditionally remove only
the exact intended owned marker, verify absence, and commit cancelled intent/released reservations in a later
transaction. If removal succeeds and the database commit fails, keep intent recoverable and repeat inspection;
if the database is unavailable, do not start cleanup. An unknown/mismatched marker is never removed automatically.
For relink, only an explicitly confirmed compensation may conditionally restore the exact recognized predecessor;
neither unknown values nor a missing predecessor may be invented. No reconciliation deletes source or history.

### 8. Lifecycle, relink and downstream authority

Archive changes Repository lifecycle/revision and audit atomically, releases its active slug/source claims, disables
new Planning/Execution and designation-based physical work, and retains IDs, prior claims, links, history and local
files. Existing nonterminal Planning/Execution references cause archive refusal until their owning contexts
explicitly quiesce them; ADR 0005 does not cancel workflows or rewrite Planning. Historical references do not
prevent archive. Pending physical intents must be reconciled first. Checkout marker/history is retained on archive;
physical-link claims are not silently reassigned to a newly registered Repository using the released source.

Restore claims the archived Repository's exact slug/source set transactionally before reactivating it. Any active
claim conflict refuses the entire restore. An authorized human can explicitly revise archived metadata/source
with expected revisions and audit, then preview restore again; there is no suffixing, merging or permanent deletion.
Source changes, archive and restore never change RepositoryId or edit Git remotes. Archived source matches may be
shown only to authorized viewers as history, not as an active automatic candidate.

A confirmed physical move preserves IDs only under the reconciliation rules above; an additional independent
clone gets new Checkout/Worktree IDs. An explicit reassociation of a Checkout to another Repository holds both
sides, requires permissions/access on both and no active dependent operations, advances generation, preserves
old association history, and uses the same marker protocol. It never transfers Planning/Execution history or
Repository access. A Worktree designation changes only its Repository's reference/revision/audit; it neither
moves files nor creates a link. Missing/unavailable designation is an honest unavailable state, not permission
to fall back to the current directory or another clone.

| Operation | Independently checked server-side authority |
|---|---|
| View/list/preview/status | Registration-view Permission plus current Workspace/Repository access; safe unavailable/conflict details only |
| Register | Registration Permission plus current Workspace authority; does not create a User/Agent Repository access grant |
| Change metadata | Separate metadata-change Permission and Repository access |
| Change sources/relationships | Separate source-change Permission and Repository access, explicit expected state |
| Link/relink/reconcile | Separate link/relink Permission and access to every affected Repository; first/ambiguous physical links require a verified human; Host/root write capability is additionally required |
| Designate | Separate designation Permission and Repository access; target relationship/availability checked |
| Archive/restore | Separate lifecycle Permissions and Repository access; retained downstream references checked |
| Recovery/cleanup | Explicit narrow internal Host executor capability plus the current authorization/reconciliation rules above; never authority from a marker or caller-supplied actor |

Exact new Permission identifiers/default assignments belong to implementation, not ADR 0005. Do not grant by role
name or UI reachability. [TASK-00498](../../planning/tasks/00498-TASK.md) separately owns explicit User/Agent Repository access
writers and its accepted access Permissions; registration/linking/configuration are not alternative grant paths.

Registration/linking grants no Planning import/cutover, execution, Runner mount/sandbox, Agent, Git commit/push/PR/
merge or publication authority. Planning stays Markdown-authoritative until its independently approved cutover.
Context consumers receive immutable typed identity/revisions/provenance and current availability, not permission
to refresh authority silently. The Dashboard consumes commands/queries and safe match reasons; it owns no matcher,
normalizer, containment, transaction, marker or permission policy.

## Alternatives and consequences

- **Path/remote/provider/marker as primary identity:** rejected; moves, forks, aliases, clones and copies break it.
- **Automatic first link from unique remote:** rejected; it proposes collaboration but does not prove physical intent.
- **Marker-first then insert:** rejected; a failed database insert leaves an untracked durable claim. Database-first
  intent makes recovery discoverable, at the cost of explicit pending state and held reservations.
- **One database transaction around Git:** rejected; locks cannot make PostgreSQL roll back a filesystem effect.
- **Lease expiry alone fences filesystem writes:** rejected; a paused writer can resume after takeover. Safety here
  deliberately trades availability for proved writer exclusion before cancellation/succession.
- **One shared package audit/event store or generic job engine:** rejected; package subjects do not express this
  owned domain, and current-state plus a bounded link intent/result is enough.
- **Broad URL heuristics and include-expanded effective marker reads:** rejected; they hide distinct sources and
  ambiguous ownership. The initial supported descriptor/config profile is deliberately narrow.

Costs include conservative human confirmations, unsupported include-bearing link mutation, explicit operator
recovery when a Host is unavailable, and host/filesystem capability qualification before the writer can ship.
The marker is not tamper-proof. Unmanaged local writers and complete offline copies cannot be cryptographically
excluded; stronger isolation would need separately accepted requirements, not an inflated claim for this protocol.

## Evidence and implementation handoff

Inspected at base `799643cffc586d1b0daf36a94a46e777b3d0441a` on 2026-10-19:

- WF-008, ADR 0001/0002, TICKET-00023/00024 and TASK-00073's planned approved-root contract. None is treated as
  proof that repository registration or approved-root runtime access exists today.
- Selected-lock/installed Fight Common `v1.3.0`, source `7de6cad6e8a9752973ad9f8e27e285b0c1510582`:
  `UniqueId::generate()` calls `Uuid::comb()`; `TransactionalUnitOfWork` and `DoctrineTransactionalUnitOfWork`
  use the EntityManager transaction and reject nesting. The initially inspected canonical-provider Common files
  were subsequently byte-compared against this checkout's explicit installation and match.
- Current `config/common/persistence.php` shares one DBAL Connection/EntityManager and canonical transaction
  binding. The inspected audit Adapter has User/Agent subjects and closed action contexts, not Repository audit
  support. Selected Fight Access Control is `v0.6.0` at `dc4621d13851dd08cdf85ed9715ead24b1051e80`; the canonical
  provider's initially installed `v0.5.0` is not substituted as selected-runtime evidence.
- Selected-lock DBAL `4.4.4` (`fb9e0ffe15e1590e24dc61c0c0a23f9a33ee42ce`), ORM `3.6.8`
  (`a4d13ed5b11e7f7b4d654b1adf95432031ae3ffc`), Migrations `3.9.7`
  (`96cb2a89b56c9efb0bac38e606dc0b0f13e650ec`). Isolated runtime reports PHP `8.5.10`, Xdebug `3.5.0`,
  PostgreSQL `17.11` on Linux/aarch64. These are observations, not future compatibility promises.
- Disposable macOS/Darwin `25.6.0 arm64`, Apple Git `2.50.1` observations used `git init`, fixture commit,
  `remote add`, `worktree add`, `rev-parse --git-common-dir`, `config --local`, explicit raw-config reads,
  `clone --no-hardlinks`, owned directory move/copy, changed fixture remote, worktree-local override and duplicate
  marker values. Shared common config was visible in the linked Worktree; ordinary clone omitted it; move and
  copy retained identical marker bytes; changing the copied remote left the marker unchanged; worktree override
  added a second effective value; conflicting duplicate values were observable. Every disposable marker was
  removed; retained fixture HEAD/content/status observations stayed unchanged by marker operations.

Checkout-local, ignored evidence is `.runs/tasks/TASK-00077/runs/initial-work/` relative to the selected checkout
root (not this note's directory): `git-observations.py`,
`git-commands.json`, `git-observations.json`, setup logs and engineer handoff. Fixture IDs were generated with
Python's UUID library; the selected runtime also exercised Common's COMB factory. These Git observations are not
product tests, production registration, PostgreSQL race tests or Linux filesystem-writer qualification. The latter
belongs to implementation. Durable verification outcomes/limits are recorded in TASK-00077.

With human acceptance recorded and subject to completed TASK prerequisites, TASK-00078 owns registration/current-state/source uniqueness and bootstrap;
TASK-00079 owns approved-root-qualified preview/confirmation/first linking/designation; TASK-00080 owns lifecycle
and reconciliation. Their acceptance evidence must include real PostgreSQL competing writes, expected-state and
rollback contracts, disposable normal/failed/restarted link operations, stale-writer exclusion, exact-key config
preservation and supported-platform qualification. A missing filesystem capability blocks physical linking,
not permission to silently weaken ADR 0005 and this supporting contract. UI/context/Planning consumers cannot bypass these suppliers.

## Acceptance belongs to ADR 0005

John's exact **“approved”** reply on 2026-10-19 accepted this note together with
[ADR 0005](../../planning/adr/0005-repository-identity-and-link-consistency.md#human-acceptance), without amendments.
The ADR records both pre-recording SHA-256 subjects and the coordinator's 23:02:10 UTC observation;
TASK-00077 records the remaining verification hold. This note has no independent acceptance path.
Design acceptance does not waive the full gate, independent Review/QA, downstream TASK prerequisites or
supported-platform qualification. It neither completes TASK-00077 nor authorizes production linking.
