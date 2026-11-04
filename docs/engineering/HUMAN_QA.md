# Reopen a disposable TASK for human QA

`bin/qa` is TASK-00556's initial implementation, **not yet independently accepted or fully browser-qualified**.
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
stack and data. A different revision, ambiguous sessions or stopped/interrupted setup requires explicit exact
down/up; no reset or silent source switch. A native retained exact QA ref can be reopened without changing it.

## Prepare external prerequisites explicitly

Docker, Worktrunk, locked dependency access and the [shared ingress prerequisites](LOCAL_INGRESS.md) must exist.
Up never installs tools, starts/enrolls shared ingress, generates certificates, edits Herd or changes trust/DNS.
Its preallocation output identifies the exact OID, source, future checkout and project, **not a ready URL**.
Missing leaf preparation prints a copyable human `bin/https setup --ingress --worktree /absolute/future/path`
command. The supplier now accepts an absent exact future absolute path for explicit leaf setup, and an exact
retired path for leaf removal; neither creates source or a runtime locator. Existing unknown paths/aliases fail.

The human must deliberately run the printed certificate command and any required front-door configuration
and reload from the ingress runbook, then rerun QA up. In Herd mode a new exact hostname needs its own approved
include update/full test/graceful reload; successful supplier transport alone is not browser readiness. Do not
use insecure TLS or automatically enroll trust to get past this prerequisite. Human-owned leaves and loaded
front-door references remain external prerequisites, not resources created or removed by QA up/down.

## Synthetic scenario and readiness

Supported revisions must retain the operator checkout's qualified host-executed wrappers, runtime preparation,
Compose and Docker templates plus documented bootstrap/catcher setup and lockfiles. Changed host setup or
creation hooks are refused before allocation. Effective Worktrunk startup hooks must be absent in both contexts;
Git post-checkout hooks and relative hooksPath are refused. No hooks are hidden/skipped or persistently approved.
This is trusted local tooling, **not a hostile-code sandbox**: the selected application code executes in the
existing containers. Deliberately qualify a changed setup boundary before adding support; `--ref` is not approval.

Fresh setup uses the target's documented wrappers: image build, locked Composer install (scripts/plugins disabled),
`bin/client setup` (locked npm with lifecycle scripts disabled), client build, database start/migrate and full
`bin/up --worktree`. It opts only that disposable project into the existing local catcher, then invokes the
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
historical manually entered QA scenarios. Working synthetic activation/login and full browser behavior still
need direct qualification on this implementation; curl readiness alone does not prove them.

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
