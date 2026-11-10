# Local project domains

The local Fight installation owns `var/local-ingress/hosts.json` in its canonical
Git checkout. Worktrees share that file. It records explicitly enrolled project
roots, each project's `local.{domain}` namespace and native Checkout UUID bindings.
It is ignored local configuration, not a resource inventory or hosted database.
Git and Docker remain authoritative for source and running resources.

The [registration flow and source traces](local-domains/README.md) describe the implemented local boundary.

## Install and enroll

Use a maintained Fight checkout as the local supplier. `bin/local-domain install`
writes a user-local pointer at `${XDG_CONFIG_HOME:-$HOME/.config}/fight/local-ingress.json`.
It never installs trust, changes DNS or starts services. `FIGHT_INGRESS_SUPPLIER`
can explicitly select a supplier for one invocation without writing that pointer.
Copied harness installations discover this machine-local provider rather than
assuming the author's directory or a path inside the installed npm package.

Preview the exact namespace and checkout, then apply the returned plan path:

```sh
./bin/local-domain plan --namespace local.fight-agent.dev --primary
./bin/local-domain apply /absolute/path/printed/by/plan.json
```

For another application add `--project-root /absolute/canonical/app`. Each app
chooses its own domain; framework starters ship no invented public domain. To bind
an isolated checkout explicitly, replace `--primary` with `--worktree /exact/path
--task TASK-NNNNN`. The actual TASK record must exist in that checkout. Only one
checkout may own the base name. Preview/remove retires one binding, preserving
project enrollment, trust and application data. Empty project enrollment still
reserves its namespace; edit it only while no consumers or resolver enrollment remain.

Plans bind registry digest, native Git identity and HEAD. Apply refuses stale plans,
collisions, overlapping project zones, aliases and changes under a running gateway.
Checkout UUIDs live in native Git metadata; moving a worktree retains its name.
TASK labels include ten SHA-256 hexadecimal characters of that canonical UUID.
Removing/recreating source cannot inherit another checkout's identity.

## Repository startup and worktree environments

After project enrollment, `bin/up --worktree /exact/path --task TASK-NNNNN` ensures
its local binding before preparing the environment, including build-only setup.
Existing bindings are idempotent. A new checkout may infer its TASK only from an
unambiguous `feature/task-NNNNN-*` branch and an existing TASK record. Other branch
names require `--task`. Shared workflow environment creation uses this same wrapper.
Main enrollment opts primary startup into shared HTTPS. Unenrolled framework apps
retain ordinary local startup; no configured provider is required for that path.
Configured-but-unavailable providers fail explicitly rather than silently changing origin.

Registration does not enroll trust, start the shared supplier or issue certificates.
Prepare the project base/wildcard leaf explicitly through `bin/https` (Fight) or the
project's `bin/ingress certificate` adapter. `setup` reuses a valid project leaf;
`renew` stages and validates a new pair, and `restore` restores the previous pair.
Only leaves/public CA are mounted, never CA signing material. Refresh the supplier
and affected gateways explicitly after rotation. Removal refuses attached consumers.

## DNS and front doors

Fight's scoped DNS service and shared HTTPS supplier work without DockerProxy.
Choose the supplier's independent listener mode for direct loopback 80/443, or its
external mode when a separately managed front door owns those ports. See
[local ingress](LOCAL_INGRESS.md) for ownership and lifecycle commands. DockerProxy
is specific to John's machine and is not installed or required by this project.
`bin/local-dns plan` previews zones from the registry; `apply` qualifies and starts
only the separate `fight-local-dns` Compose project. `check` exercises a disposable
UDP/TCP resolver without changing the installed service. `status`, `up` and `down`
inspect or operate that service independently. `enrollment` prints narrow macOS
resolver commands for human execution. Re-plan/apply after namespace enrollment;
adding a checkout beneath an existing wildcard zone needs no DNS refresh.
Scoped resolver enrollment is a separate explicit workstation operation. Never
intercept a public top-level domain, change global DNS or install CA trust silently.
The base and one-level names resolve locally; routes exist only for exact running
gateways. Unknown or stopped targets must fail locally without a fallback app.

Diagnose DNS, certificate/trust, supplier and app readiness separately. DNS success
alone proves no HTTPS route. Default-trust curl/browser checks through ordinary DNS
and 443 establish workstation readiness; high-port/connection-override checks prove
only isolated routing. Container clients need an explicit supplier address and the
public CA; their loopback is not the workstation. Maintenance stops persist until
explicit startup. Restart policies do not establish observed reboot or sleep/wake proof.

## Future Project creation

A local browser installation should invoke the same local registration operation
with an authorized project root, namespace and selected checkout, then show the
remaining DNS/trust prerequisites. Do not create a second browser-owned registry.
A hosted installation stores project intent but cannot write this workstation file.
Its future UI may offer a copy-paste local enrollment command using the installed
harness, with a preview of project, namespace, checkout and effects before apply.
Do not put credentials in that command or make a browser request execute an arbitrary
local shell command. Authentication, browser authorization and hosted enrollment
transport belong to the future Project-creation work; this task supplies the local seam.
