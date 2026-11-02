# Shared local HTTPS ingress

TASK-00553 supplies a separately operated `fight-local-ingress` Compose project: pinned Nginx 1.30.6 Alpine
and pinned nginxproxy/docker-gen 0.15.0, both by registry digest. It uses the upstream
[separate-container topology](https://github.com/nginx-proxy/docker-gen/tree/0.15.0#separate-container-install)
with a bounded repository-owned template, not the general nginx-proxy template's HTTP-upstream defaults.
This is local development infrastructure, not production deployment or a hostile-code sandbox.

**Accepted candidate checkpoint:** independent Review ACCEPT and QA PASS bind TASK-00553's recorded candidate;
publication and cleanup remain separate. John completed the full test/graceful reload
for the approved three-name Herd topology on 2026-11-01. Canonical default-trust curl and host Chrome qualified
two full registered task stacks, available auth flows and both verified proxy hops, including untrusted-upstream
rejection/recovery. Main root/CSRF/UI transport and temporary integration/direct restoration were exercised without
migrating its inherited unprepared database; no main auth success is claimed. Default independent low-port runtime
cannot be exercised while protected Herd owns 80/443; its native model is qualified, not its live listeners.
The TASK and canonical Work handoff own exact subjects, warnings and platform/browser limits. Neither service
health nor this document supplies independent acceptance.

**Current handoff:** TASK-00543's integrated consumer candidate has independent Review ACCEPT and QA PASS;
see its [acceptance checkpoint](../../planning/tasks/00543-TASK.md#accepted-implementation-checkpoint--2026-11-02).
Publication and final delivery checks are separate. The supplier's later cleanup detached its Herd include;
that does not prove live unload of retained configuration or leaves. Its Land handoff owns those separate holds.

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
only CHOWN/SETUID/SETGID capabilities for its native worker/temp-directory setup. Default independent mode
publishes loopback 80/443. Optional Herd mode publishes only one explicitly selected high loopback HTTPS port.
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
actual-name leaves were subsequently generated with the existing trusted CA on the qualified host.

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

## Optional existing-Herd coexistence

Use this ONLY when an already operated Herd owns 80/443 and the operator explicitly selects coexistence.
Independent ingress above remains the default; Herd is not a global platform dependency. Canonical browser
origins do not change or gain a high port:

`browser https://<exact Fight name>.localhost:443 → Herd → verified HTTPS 127.0.0.1:18444 → pinned supplier → verified HTTPS app gateway:8443`

Do not stop/move Herd, change its global TLD/loopback/stubs, add aliases/DNS/hosts rules or use stock
`herd proxy --secure`: its configured TLD and unverified TLS/normalized Host template do not satisfy this contract.
Do not repeat alternate low-loopback-IP publication after a Docker wildcard-bind conflict. Existing `.test`
sites, default servers, trust and other projects stay owned by their operators.

1. Inspect native port/project ownership and effective site includes/server names. Refuse target-name or file
   conflicts. Select a free high port, then explicitly prepare and start the SAME shared supplier:

   ```sh
   ./bin/ingress setup --front-door herd --https-port 18444
   # Explicitly generate the actual main/registered-worktree leaves as above, reusing existing trust.
   ./bin/ingress up --bind-ports
   ./bin/ingress herd-config
   ```

   Only HTTPS is published by the supplier in this mode. Herd owns exact-name HTTP redirects; a second supplier
   host HTTP listener is unnecessary. The chosen mode/port live in the native Compose configuration, not another
   resource registry. To return to independent mode, stop the supplier explicitly, retire its Herd adapter, then
   `./bin/ingress setup` and `./bin/ingress up --bind-ports` after freeing the standard listeners legitimately.

2. `herd-config` only renders canonical `var/local-ingress/herd.conf`. It enumerates Git-registered checkout
   names with valid installed exact-only DNS-SAN leaves (no wildcard/alias SANs). It never reads application
   environments or installs/reloads Herd. Inspect the result, then exclusively create ONE owned site file
   `~/Library/Application Support/Herd/config/valet/Nginx/fight-local-ingress.conf` in Herd's existing HTTP-scope
   include directory. Never overwrite an unrelated file or edit global config. `cp -n` followed by `cmp` can
   install/verify the new file; inspect ownership first. Record its path, owner marker and digest in the handoff.
   The adapter references only supplier-owned leaf/key/public-CA and header configuration paths under `var/`.

3. Validate the FULL existing Herd configuration using the installed Herd Nginx binary and actual `-c` path.
   If root-owned files or privileged test binds require sudo, the human runs it in their own terminal. Never
   request a password in an agent. Only after a successful full test, gracefully signal the verified existing
   master with HUP (or native Nginx reload using its verified prefix/pid path); never restart/stop the service.
   On the qualified macOS installation the binary is `/Applications/Herd.app/Contents/Resources/nginx-arm64`
   and config is `$HOME/Library/Application Support/Herd/config/nginx/nginx.conf`. Run the test from canonical
   `var/local-ingress/` so any native diagnostic pid file stays out of source. A scoped high-listener syntax
   diagnostic is NOT a successful full service test or reload. Record full-test/reload state, not just file creation.

4. Verify safe read-only existing `.test` requests before/after, then canonical Fight root/CSRF, exact original
   Host/SNI (including `:443` aliases), Origin, cookies/auth and default browser trust. Startup/status probe BOTH
   the verified high-port supplier transport (connect-to retains canonical SNI/Host) and standard browser entry.
   A failed Herd entry produces no ready URL even when the supplier/app are healthy. Use no insecure TLS fallback.
   The scoped include has no wildcard/default server: unknown names cannot reach Fight; unrelated Herd name
   handling stays unchanged. Every Fight server rejects mismatched SNI/raw Host before proxying. Proxy access
   and URL-bearing error logs are disabled. A closed browser/API header list strips all forwarding/original
   authority/client-verification assertions while retaining auth, cookie, Origin/CSRF and application denial inputs.

New exact names or leaf renewal require explicit `herd-config`, ownership/digest-checked replacement of this
SAME owned include, full test and graceful reload. There is no sync daemon/host manager. Gateway addition/removal
still updates the supplier automatically; a retired gateway cannot be reached through a stale host include.
Before deleting a referenced leaf, remove its owned host block/include and gracefully reload, then use certificate
removal. If privilege blocks reload, retain the referenced valid leaves and report the finite hold. To remove the
adapter completely, verify its recorded owner/content, unlink ONLY that exact owned include, full-test and
HUP the same verified master. Do not delete shared trust, other site configs or the running Herd service.
Supplier/application down never operates Herd. Keep leaves/header paths while a live or pending include needs them.

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
in `var/worktrees/<project>/`. Each shared gateway mounts only its dedicated `gateway/` configuration directory,
exact-name certificates and compiled assets read-only. Directory mounting keeps atomic config refreshes visible;
the parent state directory (including private task settings) is never mounted into the gateway. Only the gateway receives opt-in metadata `FIGHT_LOCAL_INGRESS=1`, one exact
`VIRTUAL_HOST`, `VIRTUAL_PROTO=https` and `VIRTUAL_PORT=8443`. docker-gen renders only running matching gateways
on the named ingress network with existing leaves. Invalid names, duplicate opted-in names and missing leaves
produce no route, never wildcard/default routing. Container start/stop/die events regenerate routes and signal
only the shared Nginx. Automatic discovery is not application orchestration.

The proxy verifies its HTTPS upstream against the public root CA with exact-name SNI verification enabled.
Both public SNI and the original Host authority must match the canonical name exactly (no explicit `:443`
alias). Only origin-form request targets are admitted; absolute-form authorities are rejected before Nginx
normalization can erase their port/case spelling.
The app gateway independently checks Host and owns canonical HTTPS/443 FastCGI metadata. Forwarding/original-authority and client-verification assertions
are stripped at proxy boundaries, never used as authority. Browser/API headers are explicitly allowlisted;
unsupported extension headers are not forwarded. The gateway also strips forwarding metadata. Known-name HTTP only redirects to the exact HTTPS origin;
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

After `bin/ingress down`, recover immediately with `bin/ingress up --bind-ports`; no socket-expiry wait is
required. When the supplier is absent, preflight checks for live loopback TCP listeners rather than treating
teardown TIME_WAIT sockets as occupied ports. This opens/closes a TCP connection without sending application
data. A listener or uncertain result refuses startup without foreign teardown; Docker remains authoritative for
the actual bind and reports any subsequent publication failure. No port reservation or race-free admission is
claimed. Partial-service recovery uses the same explicit up command.

No prune, foreign teardown, global installation/reset, privileged port configuration, CA deletion or trust
unenrollment is performed. Explicit `mkcert -uninstall` is a separate **global** human operation affecting all
consumers; review them first. Keep required evidence only while consumed by the active workflow. Actual cleanup
outcomes and finite holds belong in the TASK and its existing ignored handoff, not another resource ledger.
