# Generated runtime files and ignored execution scratch

Markdown Planning and committed source remain authoritative. Both `var/` and `.runs/` are ignored and absent
from a fresh clone, but they have different owners. **Application runtime must never depend on `.runs/`.**

## Application-generated files: `var/`

Keep generated application files and persistent local runtime state under `var/`, not `.runs/` or a top-level
`generated/` directory:

- `var/validations/catalog.json`: the exported private catalog read by the application.
- `var/openapi/openapi.json`: the private generated API contract and its freshness-check input.
- `var/client/build/`: compiled browser assets and private manifest; only allowlisted hashes are served at `/build/`.
- `var/client/check/production/`: isolated production-check output, never pruned over live browser assets.
- `var/https/certs/`: private local TLS leaf certificate/key, not execution evidence. Preserve identity and modes
  during an explicitly authorized relocation; do not regenerate keys to tidy a directory. The mkcert CA remains
  outside the checkout.

Export and package runtime artifacts explicitly with their matching source. Moving an artifact requires changing
its producer, consumer and any mounts together. Never use a compatibility symlink to hide a runtime dependency
on `.runs/`; runtime export directories reject symlinks.

## Execution categories: `.runs/`

| Directory | Owner and contents |
|---|---|
| `archive/<batch>/` | Retired execution evidence with reversible old/new manifest and file digests |
| `build/` | Gate runs, task build coordination, `reports/backend/`, `reports/client/` and temporary test fixtures under `tmp/` |
| `cache/<tool>/` | Disposable Composer/npm/Vite/Storybook caches and local tool state; not application runtime input |
| `handoffs/<scope>/` | Cross-task/package handoffs plus necessary attachments; bulk retained build output belongs with its run/archive |
| `notes/<scope>/` | Non-executable working notes; task-owned run evidence belongs under `tasks/` |
| `prototypes/<TASK-ID>/<experiment>/` | Disposable design, architecture and tool-qualification experiments |
| `qa/<TASK-ID>/` | Canonical independent QA reports and their runs/captures |
| `reviews/<TASK-ID>/` | Canonical independent technical reports and their attachments |
| `tasks/<TASK-ID>/runs/<run-id>/` | Task-owned execution logs, probes, manifests, coordination and delivery/landing evidence |
| `worktrees/` | Approved registered Git worktrees, operated through Git rather than ordinary file moves |

Allocate unique run IDs rather than overwriting another run. Do not create loose logs, root-level task/date
folders, a separate `land/` category, or a new tool-named top-level directory. A directory need not exist until
it has a real consumer. The base worktree owns canonical Review/QA locations; Engineer may read its TASK's
reports but only their independent owners publish them.

Retain active build runs, locks and evidence still required by current consumers. Explicitly archive completed,
inactive runs, including failures, rather than treating a passing receipt or terminal TASK status alone as
proof that a process/resource is retired. Preserve registered worktrees, running mounts, credentials and
independent report bytes. A local archive is neither a portable backup nor publication.

For an authorized Land cleanup, apply the [owning inventory/preservation and cleanup procedure](LANDING.md#cleanup-and-handoff).
Retain its receipt and verified required evidence outside the disposable checkout before removing it. Record
resource IDs, old/new locators/digests, results and exact holds in existing TASK handoffs, not a new registry.
Ignored evidence, private runtime/data and active session mounts require inspection even when Git is clean;
terminal TASK status alone never permits disposal. Later cleanup results stay in ignored evidence, not another
tracked commit merely to certify successful deletion.

## Relocation and verification

Keep the manifest outside the retired source with checkout identity, old/new paths, SHA-256 digests and
exclusions. Review the dry run; check actual ownership, processes, mounts, symlinks, collisions and source
identity before applying a move. Compare digests afterward. Unknown live ownership stops the affected move.
Never force-unmount, prune, overwrite or delete another owner's data to complete cleanup.

Repair present-tense navigation in Planning, docs, instructions and scripts. Preserve historical commands,
original signed receipt paths and source identities; add a current locator/relocation record rather than
rewriting history. Audit Markdown links/anchors and code-span/plain-text/absolute paths, including archived
Planning and Wayfinder records. Classify templates, remote URLs and missing evidence honestly. Review and QA
independently check intended targets against the manifest; a passing Planning validator is not sufficient.

TASK-00157's original inventory remains under `.runs/tasks/TASK-00157/`. The October 9 archive batch remains
`.runs/archive/2026-10-09-task-00157/`. October 14 consolidation and runtime-path repair are separately retained
under `.runs/archive/20261014T220249Z-root-consolidation/` and
`.runs/archive/20261014T223441Z-layout-repair/`. Their local manifests resolve relocated historical citations;
original independent reports and historical results were not rewritten. Earlier archive/retention claims in
Planning remain historical, not a declaration that those original paths still exist. All this evidence is
checkout-local; unavailable historical receipts are not reconstructed or counted as present.
