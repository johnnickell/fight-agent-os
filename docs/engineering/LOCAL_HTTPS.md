# Local HTTPS

Use this opt-in development path for **https://localhost:18443**. It serves the existing root and
`GET /api/v1/auth/csrf`; it does not implement login or complete the production security envelope.
[TASK-00145](../../planning/tasks/00145-TASK.md) owns this transport slice;
[TASK-00063](../../planning/tasks/00063-TASK.md) retains integrated security and production runtime policy.

## Enroll and start

Requirements: Docker Compose, host Python 3/OpenSSL/curl, and **mkcert v1.4.4**. The local gateway pins
Nginx 1.28.3 Alpine and the PHP runtime pins PHP 8.5.10 FPM Bookworm. These are development images, not a
production deployment certification. Run commands from the repository root.

1. Install Composer dependencies using `./bin/composer install --no-interaction --prefer-dist --no-progress`.
2. Create an ignored `.env` with your existing database settings and a separately generated
   `APP_CSRF_MAC_KEY` (at least 32 random bytes as lowercase hex). `openssl rand -hex 32` generates a key;
   never commit or include it in diagnostics. If `.env` is not ignored, add `/.env` to your personal
   `.git/info/exclude` before proceeding. Do not rotate an existing key just to enable HTTPS.
3. Explicitly enroll local trust and configure the origin:

   ```sh
   ./bin/https setup
   ./bin/https up
   ./bin/https status
   ```

`setup` invokes `mkcert -install`, which may ask for your system password in your terminal. Do not give that
password to an agent or paste it in chat. If a noninteractive agent cannot complete enrollment, run setup
yourself, then ask it to continue. Enrollment adds/reuses mkcert's **CA** in supported trust stores; the CA can
sign certificates for other names, even though this setup issues a leaf for **localhost only**. CA trust is a
machine-level decision, not trust restricted to this application. The CA and its private key remain in mkcert's
user-controlled directory outside Git and are never mounted into containers.

Leaf certificate/key files live under ignored `.runs/https/certs/`, with directory mode 700 and file mode 600.
Setup regenerates those leaves, sets `.env` mode 600 and updates only `APP_BROWSER_ORIGIN` to
`https://localhost:18443`; other settings, including the CSRF key, remain unchanged. A conflicting shell origin
override is rejected rather than silently ignored. Compose shell variables take precedence over `.env`.

Leave `FIGHT_AGENT_OS_PORT=18087`: it controls the inherited HTTP listener, not TLS. `./bin/https up` starts
both the ordinary stack and optional TLS services, refreshing their environment. After future `.env` changes,
use this wrapper again to update both PHP runtimes. Ordinary `./bin/up` does not enable the HTTPS profile.
Dependencies and database data are shared with the existing local stack; no reset or migration is implicit.

## Transport and verification

The Nginx listener binds to `127.0.0.1:18443`. Use `localhost`, not `127.0.0.1`, in the browser URL: only
`localhost:18443` is an accepted HTTP authority and only localhost is on the leaf certificate. FastCGI has
**no host-published port**. The TLS server invokes only `/app/public/index.php` and supplies the HTTPS flag and
canonical authority itself; it does not convert client forwarding headers into trusted scheme/host information.
Hidden-file paths are rejected at Nginx; other paths go through the front controller. Static frontend asset
serving is deferred to the actual UI slice.
The repository is mounted read-only in the FPM service, and Nginx has no source-tree mount. This is a trusted
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
Use setup/up again to re-enroll leaves and restart. `./bin/down` remains the full-stack stop; it does not remove
database volumes or leaf files. Do not use `down -v` for TLS cleanup.

For **explicit global trust removal**, `mkcert -uninstall` removes the shared CA from supported trust stores
and may require a system password. It affects other projects using the same CA and is intentionally not run by
repository removal commands. Review those consumers first. Do not delete the shared CA private key as project
cleanup. After global trust removal, setup must enroll trust again before browser operation.

Missing keys, a wrong effective origin, missing/expiring certificates or invalid configuration should stop
startup with a bounded error. Inspect your private `.env` locally if parsing fails; do not print full resolved
`docker compose config`, which includes secrets. A certificate/key mismatch is rejected by Nginx. Setup/up
renews leaves and reloads the gateway. Independent review and production qualification remain separate.
