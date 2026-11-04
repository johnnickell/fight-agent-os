# Reopen a disposable TASK for human QA

`bin/qa` is TASK-00556's candidate implementation, **awaiting independent Review/QA acceptance**.
Work qualified default published and explicit revisions, isolated stacks, host Chrome activation/login and scoped
cleanup on the existing macOS/Herd supplier. This is bounded host evidence, not other-platform certification.
It is an operator convenience over Worktrunk, the existing isolated Compose project and shared HTTPS ingress.
It never accepts a TASK, publishes, merges, delays Land cleanup or keeps an original engineering checkout alive.

## Select a fixed revision

Run from a surviving operator checkout containing these tools, not from the disposable QA checkout:

```sh
./bin/qa up TASK-00556 --ref FULL_COMMIT_OID
./bin/qa up TASK-00060                       # Only with unambiguous TASK-owned PR metadata
./bin/qa list
./bin/qa down /absolute/qa/checkout          # Copy the exact command printed by up/list
```

The examples identify real TASKs, not accepted browser qualification subjects. `--ref` accepts a locally
resolvable revision or an unambiguous retrievable origin ref. Both paths resolve once to a full commit OID
**before allocation**. Default resolution requires the TASK's exact origin-owned PR URL, matching TASK title,
feature branch and develop base, and an open or merged PR. Missing/ambiguous publication requires `--ref`;
there is no fallback to develop, a similarly named branch, or the original task checkout. A remote advance
cannot change the allocated revision. An unsupported revision fails rather than running changed host setup code.

The QA-purpose native branch is `qa/task-NNNNN/FULL_OID`; its registered checkout is a sibling under
`.worktrees/qa-task-NNNNN-FULL_OID`. It never adopts an engineering/main checkout. The existing path-derived
Compose project/hostname and isolated settings, database and output are reused without a second inventory.
An OS advisory lock serializes QA up/down, without saved PIDs or a daemon. A healthy repeat reuses the SAME
stack and data. A different live/partial revision, ambiguous sessions or stopped/interrupted setup requires
explicit exact down/up; no reset or silent source switch. After completed down, unchanged retained refs are
annotations, not conflicting sessions: another revision can start, or the same OID can reopen, even with several
retired refs. Changed/re-registered refs, remaining source/configuration or native project resources still block
allocation; do not force-delete refs or repeat completed cleanup merely to switch revisions.

## Prepare external prerequisites explicitly

Docker, Worktrunk, locked dependency access and the [shared ingress prerequisites](LOCAL_INGRESS.md) must exist.
Up never installs tools, starts/enrolls shared ingress, generates certificates, edits Herd or changes trust/DNS.
Its preallocation output identifies the exact OID, source, future checkout and project, **not a ready URL**.
Missing leaf preparation prints a copyable explicit `bin/https setup --ingress --worktree /absolute/future/path`
command. The supplier now accepts an absent exact future absolute path for explicit leaf setup, and an exact
retired path for leaf removal; neither creates source or a runtime locator. Existing unknown paths/aliases fail.

An authorized operator/agent deliberately performs the printed leaf command using the existing CA and follows
[scoped ingress preparation/retirement](LOCAL_INGRESS.md#scoped-task-preparation-and-retirement), then reruns QA up.
In Herd mode, `bin/ingress herd-config --include-worktree /absolute/future/path` explicitly renders the name
before allocation; leaf setup alone does not create its front-door block. Preserve existing peer blocks, verify
owned include content/digests, full-test and gracefully reload with actual available privilege. No blanket
human-only rule applies to routine setup, but missing privilege is a genuine browser-readiness hold; never
substitute restart, insecure TLS or trust enrollment. Successful supplier transport alone is not browser readiness.
Leaves/loaded front-door references are explicitly managed outside QA up/down, not automatically removed by it.
After exact QA down, unload only its owned host blocks and verify reload before removing a referenced leaf;
retain held leaves without retaining disposable runtime/source merely for the privilege hold.

## Synthetic scenario and readiness

Supported revisions must retain the operator checkout's qualified host-executed wrappers, runtime preparation,
Compose and Docker templates plus documented bootstrap/catcher setup and lockfiles. Changed host setup or
creation hooks are refused before allocation. The fixed setup commands also require unchanged Python import
namespaces at `scripts/` and `scripts/resource_lifecycle/`: new package initializers, module/package shadows,
bytecode and extension-module names are refused before they can run on the host. Other existing scripts are
not implicitly authorized for host execution. Effective Worktrunk startup hooks must be absent in both contexts;
Git post-checkout hooks and relative hooksPath are refused. No hooks are hidden/skipped or persistently approved.
This is trusted local tooling, **not a hostile-code sandbox**: the selected application code executes in the
existing containers. Deliberately qualify a changed setup boundary before adding support; `--ref` is not approval.

Fresh setup uses the target's documented wrappers: image build, locked Composer install (scripts/plugins disabled),
`bin/client setup` (locked npm with lifecycle scripts disabled), client build, database start/migrate, full
`bin/up --worktree` and `bin/validations export/check` for the exact-source public activation/login catalog. It opts only that disposable project into the existing local catcher, then invokes the
[trusted bootstrap](SUPER_ADMIN_BOOTSTRAP.md) once with a library-generated operator UUID and synthetic
`human-qa@example.test`. It imports no main/private/production data or settings. Missing setup capability or
bootstrap/mail delivery is a failed startup, not an invented account or successful readiness claim.

A final ready result includes exact revision, checkout, verified HTTPS root/CSRF URL, a private scenario-file
path and exact down command. It does **not** claim a completed login or browser trust. Open the owner-readable
`qa-activation.html` file locally in your browser, follow its synthetic activation link, choose a password in
the real activation UI, then sign in at the printed origin's `/app/login`. The pending account is a synthetic
Super Admin; this is not evidence for ordinary-user permission scenarios. Do not paste/capture the file,
fragment, mail body, password, cookies or tokens. No password or possession link is printed by the tools.

Repeat up does not reseed accounts, renew grants, reset QA data or regenerate a consumed activation scenario.
An already activated account uses its human-chosen password. An expired/consumed grant without a usable account
needs explicit down/up or the revision's documented isolated recovery commands. Fresh data cannot reconstruct
historical manually entered QA scenarios. Work exercised synthetic activation and fresh-context login in trusted
host Chrome on two exact revisions; independent behavioral QA remains required. Readiness additionally probes
both public form schemas, so missing catalog output cannot yield a ready URL. Curl readiness alone does not
prove a browser journey.

## Inspect and finish explicitly

List is read-only. It combines native QA refs/registration with existing interrupted teardown locators and
queries Docker. Each row has TASK, full requested/actual OID, checkout, status and exact down selector; it prints
a URL only after live HTTPS verification. Missing/failed inventory is nonzero/unavailable, never an empty success.
`partial` includes interrupted allocation/setup; `stopped` includes prepared but stopped services. Worktrunk's
intentionally retained unmerged ref is labeled separately, not reported as a running stack.

Closing a terminal/browser does **not** stop QA. Run the printed `bin/qa down /absolute/qa/checkout` at the end
of the session. It calls the SAME public `bin/down --worktree` first, removing that project's disposable data,
services and route, then retires known installed dependency output and invokes the SAME guarded Worktrunk
remover as Land. QA-purpose full-OID refs use exact-revision proof instead of fabricated publication proof;
Land's publication checks and ordinary fixture ancestry checks are unchanged. Worktrunk retains non-force ref
policy: an exact unmerged ref may survive with source/registration/runtime/config gone. Do not force-delete it.

Dirty/unique source, unknown ignored private files, consumers and unqualified removal hooks retain source;
they do not block independent Docker teardown. Resolve the specific hold and rerun the exact down command.
Only known disposable dependency installations are automatically removed; do not store unique work in them.
Failed startup attempts cleanup of that attempt's allocations and prints an exact retry command for any hold.
SIGKILL/engine loss can interrupt effects: list and retry exact down. After locator retirement, only the original
absolute path is a repeatable cleanup selector. Main, another QA environment, shared ingress, certificates/trust,
shared image layers/build caches and unrelated work remain protected; no global prune or expiry service exists.

Human findings belong in the existing TASK/PR. Keep independent acceptance separate from environment readiness.
