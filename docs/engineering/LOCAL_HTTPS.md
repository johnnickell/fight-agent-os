# Local HTTPS

The ordinary `./bin/up` development stack includes HTTPS at **https://localhost:18443**. It serves the existing root and
`GET /api/v1/auth/csrf`, plus the [non-product browser shell](../../client/README.md) at `/app` after
`./bin/client setup` and `./bin/client build`; it does not implement login or complete the production security envelope.
[TASK-00145](../../planning/tasks/00145-TASK.md) owns this transport slice;
[TASK-00063](../../planning/tasks/00063-TASK.md) retains integrated security and production runtime policy.

Ordinary main `bin/up` remains this direct localhost stack, independent of shared ingress. It explicitly selects
`compose.yaml` and `.env`, ignores ambient `COMPOSE_FILE` and stale generated main/task proxy overrides, and never
auto-selects a proxy. TASK-00543's earlier failure without ingress was a regression, now repaired.
`bin/up --worktree SELECTOR` is different: it requires [TASK-00553](../../planning/tasks/00553-TASK.md)'s separately
operated shared ingress and actual-name certificates, and fails clearly until those exist. See
[shared ingress operations](LOCAL_INGRESS.md) for `bin/ingress`, explicit `bin/up --shared-ingress` main opt-in
and human `bin/https setup --ingress [--worktree SELECTOR]` actual-name leaves. That extension never enrolls
trust or changes `.env`; the direct flow below is unchanged. Live shared-topology qualification is incomplete,
not a main-startup dependency. Task down remains available without ingress.

## Enroll and start

Requirements: Docker Compose, host Python 3/OpenSSL/curl, and **mkcert v1.4.4**. The local gateway pins
Nginx 1.30.6 Alpine and the PHP runtime pins PHP 8.5.12 FPM Bookworm, both by digest. These are development
images, not a production deployment certification. The October security candidate resolves the catalog-typing
blocker with the authorized catalog-only `skipLibCheck` exception and passes the local gate; independent
Review/QA acceptance and operational adoption remain separate. See
[qualification, rebuild and safe rollback](SECURITY_RUNTIME_UPDATES.md).
Run commands from the repository root.

1. Install Composer dependencies using `./bin/composer install --no-interaction --prefer-dist --no-progress`.
   Prepare the root/browser shell with `./bin/client setup` and `./bin/client build` before startup;
   missing live assets fail the HTTPS readiness probe rather than returning startup success.
2. Create an ignored `.env` with your existing database settings and a separately generated
   `APP_CSRF_MAC_KEY` (at least 32 random bytes as lowercase hex). `openssl rand -hex 32` generates a key;
   never commit or include it in diagnostics. If `.env` is not ignored, add `/.env` to your personal
   `.git/info/exclude` before proceeding. Do not rotate an existing key just to enable HTTPS.
3. Explicitly enroll local trust and configure the origin:

   ```sh
   ./bin/https setup
   ./bin/up
   ./bin/https status
   ```

`setup` invokes `mkcert -install`, which may ask for your system password in your terminal. Do not give that
password to an agent or paste it in chat. If a noninteractive agent cannot complete enrollment, run setup
yourself, then ask it to continue. Enrollment adds/reuses mkcert's **CA** in supported trust stores; the CA can
sign certificates for other names, even though this setup issues a leaf for **localhost only**. CA trust is a
machine-level decision, not trust restricted to this application. The CA and its private key remain in mkcert's
user-controlled directory outside Git and are never mounted into containers.

Leaf certificate/key files live under ignored `var/https/certs/`, with directory mode 700 and file mode 600.
Setup regenerates those leaves, sets `.env` mode 600 and updates only `APP_BROWSER_ORIGIN` to
`https://localhost:18443`; other settings, including the CSRF key, remain unchanged. A conflicting shell origin
override is rejected rather than silently ignored. Compose shell variables take precedence over `.env`.

The setup editor supports single-line `NAME=value` assignments (including quoted values, comments and optional
`export`). It rejects multiline values and other unsupported dotenv syntax during preflight, **before** CA
trust enrollment, leaf generation or `.env` changes. This is intentionally narrower than Compose's parser:
setup must not mistake text inside an unrelated value for an origin assignment. If rejected, leave the file
unchanged and inspect it privately; only convert a value to a supported representation if its meaning is
preserved. `up`/`status` still use Compose's parser and do not rewrite `.env`.

Leave `FIGHT_AGENT_OS_PORT=18087`: it controls the inherited HTTP listener, not TLS. After explicit setup,
`./bin/up` is the sole ordinary startup/refresh command for HTTP, HTTPS PHP and the gateway. Use it again after
`.env` or image/configuration changes to update both PHP runtimes. It validates effective Compose settings and
existing certificate/key files before service mutation, waits for services, validates/reloads Nginx, then probes
the root over verified HTTPS with curl's trust store. Missing prerequisites fail closed; startup never runs setup,
enrolls trust, generates leaves, rewrites `.env` or installs dependencies. `./bin/https up` is rejected with a
migration instruction and no startup effects. A later build/readiness/reload failure can leave partial services;
inspect `docker compose --profile https ps`, correct the cause and rerun `./bin/up`.
Dependencies and database data are shared with the existing local stack; no reset or migration is implicit.

## Transport and verification

The Nginx listener binds to `127.0.0.1:18443`. Use `localhost`, not `127.0.0.1`, in the browser URL: only
`localhost:18443` is an accepted HTTP authority and only localhost is on the leaf certificate. FastCGI has
**no host-published port**. The TLS server invokes only `/app/public/index.php` and supplies the HTTPS flag and
canonical authority itself; it does not convert client forwarding headers into trusted scheme/host information.
Hidden-file paths are rejected at Nginx. Only content-hashed client JS/CSS/license files under `/build/` are
served statically; manifests, maps and other build paths return 404. Other paths go through the front controller.
The repository is mounted read-only in the FPM service; Nginx mounts only the generated `var/client/build/` directory,
not the source tree. Client HTML has its own restrictive CSP and no-store policy; static hashes are immutable. This is a trusted
local development stack, not an isolation boundary against other local Docker users or untrusted application code.

Verify without `-k`/`--insecure`:

```sh
curl --fail --silent --show-error --output /dev/null https://localhost:18443/
```

The CSRF endpoint should return JSend success with a Secure, HttpOnly, SameSite=Strict nonce cookie scoped to
`/api/v1/auth`, and a no-store proof. Do not paste cookie/proof bodies into diagnostics. Foreign origins,
cross-site Fetch Metadata and insecure HTTP requests must remain denied; forwarding headers cannot promote
the inherited HTTP endpoint to HTTPS. The inherited root remains accessible over HTTP and is not automatically
redirected. Production redirect/HSTS/CSP policy is deliberately deferred; do not add persistent localhost HSTS.

`status` checks the live root and CSRF bootstrap using the selected curl client's default trust store. On macOS, browsers using
the system Keychain should recognize mkcert trust after a restart. Firefox/NSS and independently installed
curl/Python/browser runtimes may use different stores. Report which client verified the certificate; a curl
success is not evidence of a complete browser journey. If curl lacks the system CA, an explicit
`--cacert "$(mkcert -CAROOT)/rootCA.pem"` can diagnose certificate validity, but it does **not** prove system or
browser trust enrollment. Never work around a failed trust check with `--insecure`.

## Stop, remove and recover

```sh
./bin/https down     # Stop/remove only TLS gateway and FPM containers
./bin/https remove   # Also delete this checkout's leaf certificate and key
```

These commands preserve `.env`, the CSRF key, database containers/volumes, HTTP service and shared CA trust.
The retained HTTPS origin is intentional; HTTP authentication must not become permitted after stopping TLS.
Use `./bin/https setup` only when leaves/enrollment need repair, then `./bin/up` to restart. `./bin/down` remains the full-stack stop; it does not remove
database volumes or leaf files. Do not use `down -v` for TLS cleanup.

For **explicit global trust removal**, `mkcert -uninstall` removes the shared CA from supported trust stores
and may require a system password. It affects other projects using the same CA and is intentionally not run by
repository removal commands. Review those consumers first. Do not delete the shared CA private key as project
cleanup. After global trust removal, setup must enroll trust again before browser operation.

Missing keys, a wrong effective origin, missing/expiring certificates or invalid configuration should stop
startup with a bounded error. Inspect your private `.env` locally if parsing fails; do not print full resolved
`docker compose config`, which includes secrets. A certificate/key mismatch is rejected before startup and by Nginx. Only `./bin/https setup`
renews leaves; `./bin/up` reloads the gateway and verifies live TLS/application readiness. Independent review and production qualification remain separate.
