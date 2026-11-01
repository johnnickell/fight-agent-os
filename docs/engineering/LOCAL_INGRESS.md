# Shared local HTTPS ingress

TASK-00553 supplies a separately operated `fight-local-ingress` Compose project: pinned Nginx 1.30.6 Alpine
and pinned nginxproxy/docker-gen 0.15.0, both by registry digest. It uses the upstream
[separate-container topology](https://github.com/nginx-proxy/docker-gen/tree/0.15.0#separate-container-install)
with a bounded repository-owned template, not the general nginx-proxy template's HTTP-upstream defaults.
This is local development infrastructure, not production deployment or a hostile-code sandbox.

**Implementation checkpoint: qualification is incomplete.** Native Compose, empty-route Nginx validation,
Docker API/template rendering, missing-prerequisite refusal and no-listener lifecycle have been exercised.
Actual-name leaf generation, listener startup, main plus two full worktrees, encrypted live upstreams,
authentication, browser trust, route removal, occupied ports and partial-start recovery still require the
explicit human operations below and subsequent direct qualification. The final TASK records gate results.
Neither service health nor this document establishes browser readiness or independent acceptance.

## Ownership and boundaries

The canonical Git checkout owns `var/local-ingress/compose.json`, copied runtime configuration, generated routes
and ignored leaves. Application runtime never reads `.runs/`. One external bridge is named `fight-local-ingress`;
only app TLS gateways join it. PHP, PostgreSQL, Mailpit and tooling remain on their own private project network.
Proxy-connected stacks publish no inherited web, TLS or Mailpit host ports. No application gets a Docker socket.

Only nonpublic docker-gen mounts the selected local Unix Docker socket read-only. It has no container network
and no published ports. Read-only mounting does **not** make the Docker API read-only: this process can inspect
container environments and signal containers, and a compromise can exercise daemon authority. Only trusted local
operators may run it. Public Nginx has no socket and mounts generated routes and leaves read-only. Both services
have bounded CPU/memory/PIDs, read-only roots, temporary writable paths and bounded failure restarts. Nginx retains
only CHOWN/SETUID/SETGID capabilities for its native worker/temp-directory setup; it binds loopback 80/443 only.
Remote/TCP Docker endpoints and foreign reserved network/project ownership are refused, never adopted or stopped.

## Explicit human preparation

Run these commands from the selected implementation checkout. No command installs packages or changes DNS/hosts.
Do not supply privilege passwords or private keys to an agent. Standard port binding may require a separately
configured Docker Desktop/host privilege setting; the tool reports failure, never configures it or invokes sudo.

```sh
./bin/ingress setup          # Inspect native ownership; write config and create only the external network
mkcert -install             # HUMAN: explicit machine-wide trust decision, in your own terminal
./bin/https setup --ingress  # HUMAN: main's fight-agent-os.localhost leaf; no implicit trust enrollment
./bin/https setup --ingress --worktree /absolute/registered/worktree
# Repeat the preceding command for the second registered worktree.
./bin/ingress up --bind-ports # HUMAN: explicitly authorize loopback 80/443 listeners
```

The ingress extension requires OpenSSL `x509 -checkhost` and `verify -verify_hostname` capabilities and checks
them before any certificate setup writes. macOS's system LibreSSL 3.3.6 lacks these capabilities. Explicitly
select an already installed capable executable in the invoking shell, for example
`export FIGHT_INGRESS_OPENSSL=/opt/homebrew/opt/openssl@3/bin/openssl` on the qualified macOS host; use the actual
path on your host. Preserve that selection for ingress certificate status, application startup and ingress up.
No automatic installation, PATH rewrite or equality-version gate is performed. OpenSSL 3.6.3 was capability-checked;
actual-name certificate operations remain unexecuted in this checkpoint.

The existing [TASK-00145 direct TLS flow](LOCAL_HTTPS.md) remains unchanged. The ingress extension reuses
mkcert/OpenSSL, but does not invoke `mkcert -install`, rewrite `.env`, copy main keys or generate wildcards.
It copies only mkcert's **public** root certificate into `var/local-ingress/certs/rootCA.pem`; the shared CA
private key stays outside the repository and containers. Each exact-name leaf/key is owner-readable under
`var/local-ingress/certs/<name>.localhost/`. An existing different CA is refused; coordinate consumers before
CA rotation. Missing, mismatched, untrusted-to-the-shared-CA, invalid or less-than-one-day-valid leaves fail
preflight. Leaf generation and enrollment are explicit human operations, not startup side effects.

`<project>.localhost` uses the stable checkout project returned by `./bin/runtime inspect` inside that worktree
(or `./bin/runtime --worktree SELECTOR inspect` from main). Git registration resolves selectors; branch changes
never rename a checkout project. On clients without `.localhost` resolution, stop and diagnose; do not edit hosts
or silently switch origins. curl probes use exact `--resolve` mappings to loopback without changing DNS.

## Start applications, not infrastructure

Explicitly prepare locked dependencies, assets, independent keys and schemas using the owning wrappers first.
Do not import main `.env`, ignored state or databases into a worktree. Full app startup never migrates schemas,
installs dependencies, starts ingress, generates leaves or enrolls trust.

```sh
./bin/up --shared-ingress                    # Explicit MAIN opt-in: https://fight-agent-os.localhost
./bin/up --worktree /absolute/registered/worktree
./bin/ingress status
./bin/https status --ingress --worktree /absolute/registered/worktree
```

Ordinary `./bin/up` still means main's direct **https://localhost:18443**, with only `compose.yaml` and `.env`.
It never loads the shared override, even if one exists. Returning from shared to direct startup deliberately
reconfigures main's gateway and removes its shared route; it is not an additional main project. Main down
retains database storage. There is one native project per registered checkout, reused across roles.

Main's explicit override is `var/local-ingress/main/ingress.yaml`; task overrides, settings and outputs remain
in `var/worktrees/<project>/`. Only the gateway receives opt-in metadata `FIGHT_LOCAL_INGRESS=1`, one exact
`VIRTUAL_HOST`, `VIRTUAL_PROTO=https` and `VIRTUAL_PORT=8443`. docker-gen renders only running matching gateways
on the named ingress network with existing leaves. Invalid names, duplicate opted-in names and missing leaves
produce no route, never wildcard/default routing. Container start/stop/die events regenerate routes and signal
only the shared Nginx. Automatic discovery is not application orchestration.

The proxy verifies its HTTPS upstream against the public root CA with exact-name SNI verification enabled.
Both public SNI and the Host authority must match the canonical name exactly (no explicit `:443` alias).
The app gateway independently checks Host and owns canonical HTTPS/443 FastCGI metadata. Forwarded headers
are stripped at both hops, never used as authority. Known-name HTTP only redirects to the exact HTTPS origin;
it does not serve authentication. Unknown HTTP authorities return 421; unknown TLS names reject the handshake.
No persistent localhost HSTS is added. Request/access logs are disabled; proxy error logs that can contain
credential-bearing URLs are disabled. Use native service/config validation and bounded status instead.

Application commands print the origin only after verified curl root **and** CSRF bootstrap requests. Failures
can leave partial services: inspect the selected project's native `ps`/logs privately, correct the reported
prerequisite and rerun its explicit up. Never use `curl -k`. A successful curl default-store check is not
browser trust or a completed login/activation/logout journey. `bin/ingress status` reports service readiness and
only the generated origins whose live root/CSRF probes verify; an empty origin list is not browser readiness.
After setup/source/template changes or leaf renewal, explicitly refresh ingress with `up --bind-ports` and the
selected application with its appropriate up command. Ingress refresh recreates only its own two services,
validates/reloads Nginx and leaves app/foreign projects alone. Unknown/changed ingress configuration fails closed;
inspect it privately and deliberately rerun setup, rather than using ambient Compose overrides.

## Stop, remove and recover

```sh
./bin/down --worktree /absolute/registered/worktree # Disposable app storage; works without ingress
./bin/down                                        # MAIN down, retains persistent database data
./bin/ingress down                                # Only proxy services; retains network/config/leaves
./bin/https remove --ingress --worktree /absolute/registered/worktree
./bin/https remove --ingress                      # Main exact-name leaf/key only, after stopping main
./bin/ingress remove                              # Proxy down, then remove the external network
```

Application teardown uses the [shared native resource procedure](RESOURCE_LIFECYCLE.md), including guarded
Worktrunk removal for owned qualification worktrees. While discovery runs, removing one gateway removes its
route without stopping other projects. Repeated teardown is harmless. Ingress down intentionally makes shared
URLs unavailable without stopping apps; direct main remains independently operable. Removal refuses network
attachments through Docker's native non-force network removal, preserving all app/foreign consumers. Stop those
consumers explicitly if removal is desired. Leaf removal refuses a matching attached app gateway; it preserves
the shared public root and machine trust. Network removal retains configuration and leaves for explicit recovery
with setup/up; it does not delete private TLS material implicitly.

No prune, foreign teardown, global installation/reset, privileged port configuration, CA deletion or trust
unenrollment is performed. Explicit `mkcert -uninstall` is a separate **global** human operation affecting all
consumers; review them first. Keep required evidence only while consumed by the active workflow. Actual cleanup
outcomes and finite holds belong in the TASK and its existing ignored handoff, not another resource ledger.
