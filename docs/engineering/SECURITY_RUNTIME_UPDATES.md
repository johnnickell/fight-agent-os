# October runtime security candidate (TASK-00509)

This is an **implementation candidate**, not independently accepted security maintenance or production
certification. The patched selections build and pass the initial focused runtime/browser probes. The
maintainer's amendment authorizes catalog-only `skipLibCheck` for Storybook's upstream declaration defect;
fresh focused typing and the full local gate pass with that exception. Evidence is recorded below and
in the TASK.
Do not resume an affected Storybook dev server as a workaround. Independent Review/QA and operational
adoption remain separate.

## Selections and applicability

Registry identities were resolved on 2026-10-22; executed qualification used Linux arm64 on Docker Desktop.
The digest pins are multi-platform registry identities; other architectures were not executed.

| Surface | Before (executed or locked) | Candidate | Registry digest |
|---|---|---|---|
| CLI/web PHP | Cached floating `8.5-cli-bookworm` executed 8.5.10 | 8.5.12 CLI Bookworm | `sha256:d4f6e10eb393de7e3a7a88e8b88e139072dd3ae297f6a5dada8257e6fe32c585` |
| FPM PHP | 8.5.10 | 8.5.12 FPM Bookworm | `sha256:d3db5340c7ecfb1e3a26af5d3d9de8b4e160cdd75692a830b5cb74b0b85db2cf` |
| Node in CLI, focused tooling and browser | 24.21.0 | 24.22.1 Bookworm-slim; bundled npm 11.19.1 | `sha256:55fb5f5a527ea2c5c4a95f963e48ef90aa38c7b346054fdc8ee9957a2b55d205` |
| Storybook and first-party React/Vite/a11y/Vitest packages | 10.4.6 | 10.6.3, locked with registry integrity hashes | npm lock, not an image |
| HTTPS NGINX | 1.28.3 Alpine | 1.30.6 Alpine | `sha256:47928bf67eb62a1863cb08d9ecb52d3e704304ba614eaee17884204677702951` |

- [PHP's announcement](https://www.php.net/archive/2026.php) fixes CVE-2026-109618 and
  CVE-2026-109619 at 8.5.12. Both web-serving paths are in scope even though the built-in server's
  dependencies previously used a floating tag. Framework changes do not fix these runtime defects.
- [Node's October advisory](https://nodejs.org/en/blog/vulnerability/october-2026-security-releases)
  identifies 24.22.1 as the fixed 24.x release, including Brotli CVE-2026-111497, DNS/TLS defects and
  the CA update. Its per-line list, not the roundup's total count, controls applicability.
  [Release source](https://github.com/nodejs/node/blob/v24.22.1/doc/changelogs/CHANGELOG_V24.md)
  records the bundled npm 11.19.1 update. Playwright 1.61.0/Chromium 149.0.7827.0 remain unchanged.
- [Storybook GHSA-f2ph-cmg5-wxc8](https://github.com/storybookjs/storybook/security/advisories/GHSA-f2ph-cmg5-wxc8)
  affects interactive 10.x dev servers below 10.6.2 (CVE-2026-114125). Static catalogs and headless-only
  use are excluded upstream; loopback/read-only mounts reduce effects but are not a substitute for patching.
  10.6.2 and 10.6.3 both fail internal React CSF declaration checks. The human-authorized catalog-only
  `skipLibCheck` exception below retains 10.6.3 without insecure iframe embedding/messaging flags,
  peer overrides, vendor declaration edits or reduced application/source strictness.
- [NGINX advisories](https://nginx.org/en/security_advisories.html) place 1.28.3 in the ranges for
  alias/rewrite CVE-2026-15176, HTTP/3 CVE-2026-95101, session tickets CVE-2026-97629 and AIO
  CVE-2026-97205. The unchanged local config enables neither HTTP/3 nor AIO nor alias/rewrite;
  this is affected-version evidence, not a demonstrated reachable exploit. Script-evaluation
  CVE-2026-93479 excludes 1.28.3. 1.30.6 is outside all those listed vulnerable ranges. TLS tickets
  retain the existing default; no claim of ticket forgery reproduction is made.

## Trust-store provenance

The Node-slim image has no installed OS CA bundle; Node uses its compiled Mozilla store. The rebuilt
CLI and browser carry that same Node binary, alongside **separate** OS bundles used by PHP/OpenSSL
and browser system libraries. No custom CA, TLS-verification bypass or host trust change is installed.

| Store | Candidate provenance | Observed remediation |
|---|---|---|
| PHP CLI/FPM Debian 12 | `ca-certificates 20250419~deb12u2`, OpenSSL `3.0.22-1~deb12u1` | Vendor package changelog blacklists emSign ECC Root CA G3; absent from installed bundle |
| Browser Ubuntu Noble | Explicit setup upgrades `20240203` to `20260816ubuntu0.24.04.1` | Vendor changelog, LP #2171507, blacklists G3 after intermediate compromise |
| NGINX Alpine 3.24.3 | CA packages `20261020-r0`; runtime OpenSSL 3.5.9 | Bundle contains only emSign G1, not G3/C1/C3 |
| Node 24.22.1 | NSS 3.131.1 compiled roots | G3/C1/C3 removed; G1 remains |

Debian's changelog was recovered from its apt-selected package archive: the online changelog endpoint
returned 404. Ubuntu's installed package changelog provides its vendor statement. Alpine's
[versioned packaging source](https://gitlab.alpinelinux.org/alpine/aports/-/blob/3.24-stable/main/ca-certificates/APKBUILD)
selects `20261020`; its referenced source archive SHA-512 was verified against APKBUILD and its certdata
contains only emSign G1. Node's
[CA-removal commit](https://github.com/nodejs/node/commit/2a73ee5a892e2ff7fea7c7f18d3b01780ed2e4a5)
names G3/C1/C3 explicitly. Debian/Ubuntu retain C1/C3 under their vendor policy; do not describe their
bundles as identical to Node/Alpine or as removal of every eMudhra certificate.

The original public G3 certificate, exported from the old Node store, fails trust verification against
both rebuilt PHP bundles, the rebuilt browser bundle, and exported NGINX/Node bundles. NGINX has no
OpenSSL executable, so its **exported bundle** was checked by the PHP image's OpenSSL; the initial
in-image command-not-found is not counted as a negative-certificate pass. PHP and Node also successfully
verified a normal public HTTPS registry connection. These bounded checks do not certify every chain,
browser revocation policy, host trust store or external service.

## Verification and declaration-check tradeoff

Direct pre-repair inventory failed all six version checks; the same post-repair check passes. Both PHP
images execute 8.5.12 with pdo_pgsql, mbstring, sodium, OpenSSL and Xdebug 3.5.0. The PHP 512 MiB limit,
web's 2 GiB ceiling, FPM's two on-demand workers/500 requests/60-second deadline and diagnostic settings
are unchanged. `php-fpm -tt` and `nginx -t` pass. Twelve isolated TLS/FastCGI probes pass, including
CSRF cookie security, foreign Origin/Fetch Metadata denial, wrong-authority 421, private-file denial and
refusal to promote forwarded HTTP to trusted TLS. This is not a full authenticated browser journey.

The 10.6.3 candidate passes app typing, lint, formatting, 924 unit/component tests, production build,
93 Chromium story interactions/a11y checks and 186 narrow/wide story captures without page errors or
external requests. A live dev preview renders with upstream `frame-ancestors` localhost restrictions
and no insecure flags. These are initial implementation receipts, not fresh repair evidence. Initial
catalog typing exhausted Node's default heap; a 1536 MiB diagnostic exposed TS2578/TS2344 in upstream
`@storybook/react` declarations with `exactOptionalPropertyTypes`. The initial mandatory gate failed
with exit 134; its failed receipt remains preserved in the TASK.

After that checkpoint, the maintainer instructed: "I think we should try that, and if that doesn't work,
let's remove storybook", referring to `skipLibCheck`. This explicitly supersedes the initial prohibition.
The repair sets `skipLibCheck: true` **only in `client/tsconfig.catalog.json`**; fresh focused typecheck,
lint and formatting pass without a heap override. TypeScript skips internal validation of **all catalog
`.d.ts` files**, not just the faulty Storybook declarations, so their internal consistency is no longer
verified by that project. Source usage is still checked against imported declarations; application,
story and configuration source retain strict, exact-optional and unchecked-index checks. The separate
application/test project keeps declaration validation enabled. No vendor files, dependency pins, resource
limits or upstream origin protections change. Storybook removal is conditional on this option failing
to resolve the demonstrated blocker; focused typing succeeds, so no catalog capabilities or tests are
removed. The full repair gate passes all 24 phases in 840.459 seconds, including 1690 backend tests,
456 Pi tests, 924 client tests, 93 browser-story tests and 186 captures. Receipt:
`.runs/build/20261023T002034Z-893/receipt.json` (`result=pass`, `source_unchanged=true`). The TASK records
coverage, warnings and the final committed-head handoff; independent acceptance remains pending.

Live npm audit additionally reports seven affected dependency entries (five moderate, two high) in
Vitest/mocker, Vite/launch-editor and source-map-js. These are escalated for a separate scope decision,
not silently upgraded here. The fresh repair audit reproduces the same count and exits 1; the mandatory
gate passes, but this is not a vulnerability-free claim. Neither audit reports the removed Storybook
advisory. Existing Sass,
catalog chunk/directive warnings and unsupported ESLint 9 installation warnings remain visible.

Initial evidence is checkout-local under `.runs/tasks/TASK-00509/runs/20261022-work-initial/`;
fresh repair evidence is under `.runs/tasks/TASK-00509/runs/20261023-work-repair1/`. See the
[TASK](../../planning/tasks/00509-TASK.md) for the current gate disposition and continuation.

## Explicit adoption and safe rollback

Only after acceptance, operate in the intended checkout with its existing private environment and explicitly
owned Compose project. Check ports/service ownership first. Do not copy this task's disposable keys or TLS
CA, enroll host trust, or restart shared/live services merely to validate the patch.

```sh
# Explicit registry/build setup; acceptance commands never perform this work.
docker compose build --pull --no-cache web
./bin/composer install --no-interaction --prefer-dist --no-progress
# Stop only this project's web before replacing its mounted frontend installation.
docker compose stop web
./bin/client setup
./bin/client storybook-setup
# Explicit HTTPS image preparation; TLS enrollment must already exist:
docker compose --profile https build --pull --no-cache https_php
docker compose --profile https pull https
# Refresh both PHP runtimes and validate/reload the gateway after FPM replacement.
./bin/up
# Check the actual processes, not only the Dockerfile/tag.
docker compose exec -T web php --version
docker compose exec -T web node --version
./bin/client versions
docker compose --profile https exec -T https_php php-fpm -tt
docker compose --profile https exec -T https nginx -t
./bin/https status
./bin/build
```

Initial setup still requires the repository's explicit database/export/TLS procedures. Refresh browser
assets with `./bin/client build` for the selected installed lock. A tag/digest edit does not update an
already running container; record container image IDs and runtime versions after replacement. Rebuild
browser images as well as PHP: their Ubuntu CA store is independently installed during image setup.

If a patched candidate is unusable, stop/isolate the affected owned service and dev server. Retain evidence
and existing data. Do **not** silently roll back to PHP 8.5.10, Node 24.21.0, Storybook 10.4.6 or NGINX
1.28.3; resume only with a separately qualified patched candidate. No production rollback/deployment,
compromise assessment or secret rotation is authorized by this maintenance task.
