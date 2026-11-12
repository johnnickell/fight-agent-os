# Critical browser smoke

`./bin/browser-smoke [all|fragment|reload|tabs|revocation|theme]` executes five deliberately narrow
platform contracts. `./bin/build` runs the same PHP-owned phase once, after the existing catalog phases.
There is no test retry, quarantine, screenshot baseline or general E2E expansion.

## Explicit prerequisites and isolation

Prepare the selected checkout normally using [local tooling](LOCAL_TOOLING.md). The CLI image includes
OpenSSL and NSS `certutil`; rebuild/start it explicitly after this change. Locked client dependencies,
live production assets and validation exports must already exist. No acceptance command installs tools,
updates locks, publishes assets, writes planning or starts Docker services.

The existing Playwright **1.61.0** lock and digest-pinned browser image supply Chromium **149.0.7827.0**,
revision **1228**. No additional runner dependency or product-test download is introduced. The runner checks
the existing Node **24.22.1** / npm **11.19.1** install receipt and exact browser version. Local execution is
containerized in the selected CLI image; native CI retains its existing explicit locked browser provisioning.
CI explicitly starts the same digest-pinned Mailpit before acceptance; the gate never operates Docker.

The phase holds the backend coverage database lock, verifies the configured AND connected guarded test
database, resets/migrates it before **each** scenario, and resets it again in `finally`. It never reads the
normal application's database. Synthetic Super Admin invitation setup uses the registered existing console
command and its normal catcher provider. An initially nonempty catcher holds the phase without deleting mail.
Only an initially empty, task-owned disposable catcher is cleared; cleanup verifies zero messages.

Each scenario launches the actual PHP application and live built assets on a private loopback fixture.
The test-only router supplies fixed TLS server metadata, not request-selected forwarded authority. A Node TLS
terminator listens on `127.0.0.1:19443` and proxies to PHP on `127.0.0.1:19087`. A one-day ephemeral CA/localhost
leaf is generated inside private scratch and trusted ONLY in that browser's private NSS database. Certificate
validation remains enabled: no `ignoreHTTPSErrors`, insecure flags or global trust changes. This tests real TLS,
Secure/HttpOnly cookies and secure-context browser APIs, **not the shared Nginx proxy chain or workstation DNS**.
TASK-00063 owns that envelope; Work's separate exact-origin shared-ingress browser probe is distinct evidence.
No claim is made that a loopback fixture qualifies production TLS, external mail or deployment enrollment.

Application keys and signing secrets are generated per invocation outside source. Node/browser receive a
minimal credential-free environment, fixed `en-US`, UTC, 1280×800 and dark OS preference. Fresh profiles,
test schema and synthetic invitation eliminate order dependence. Production clock behavior is not accelerated:
refresh is triggered by cold documents, not artificial expiry or wall-clock waits.

## Why these five scenarios need a real browser

| Scenario | Browser-exclusive observation | Synchronization |
|---|---|---|
| `fragment` | Real activation link capture replaces visible/history URL before form input/navigation; TLS cookies are Secure/HttpOnly/SameSite Strict, and script cannot read refresh at its matching cookie path; request target/referrer omit possession material | Form readiness, actual activation response/state and ordinary back navigation |
| `reload` | A new document has no memory access authority; cookie-backed refresh is unauthenticated by Bearer and `/me` cannot precede its completion | Explicit held refresh request, release, authoritative principal response and Dashboard |
| `tabs` | Two cold real pages queue on an actual held Web Lock; exactly one refresh serves both, then native BroadcastChannel logout retires both without another login/refresh loop | Native lock inventory, explicit release, both rendered principals and logout states |
| `revocation` | Another cookie jar is denied by the server immediately after revocation; same-origin owning-session lists converge; native Cancel-first dialog focus is visible and in viewport after keyboard movement | Real mutation/read outcomes, list state, immediate Bearer 401, denied cookie restoration and native focus |
| `theme` | Blocking React's main bundle still leaves the OS-dark prepaint applied to the loading shell; a live OS-light change reaches the rendered root | Resource barrier and DOM theme mutation |

Only `fragment` activates through UI. Other scenarios use deterministic API setup; revocation uses UI only for
the critical confirmation/focus/invalidation interaction. Unit/component/security/catalog suites remain the
owners of validation, permission matrices, accessibility breadth and failure permutations. Another cookie jar
has no cross-context/device push guarantee: the server denies its next request. No cross-device UI convergence,
universal browser/assistive-technology support or accessibility/security certification is claimed.

## Bounds, artifacts and evidence

Readiness/state polling has a 10-second deadline and 25 ms sampling interval; it is not an arbitrary startup
sleep. Playwright actions have 10-second bounds, catcher operations 5 seconds, crypto/tool commands 15 seconds,
and each scenario process 120 seconds. No scenario is replayed on failure. Any nonzero result fails the named
canonical phase. Explicit repeatability commands are separate evidence, not hidden retries.

The browser rejects product requests outside the exact fixture origin, including the second cookie jar.
No trace/video/screenshot/HAR/console dump/HTML snapshot or rich test reporter is enabled. Failures print only
scenario, fixed step, allowlisted safe UI states and numeric HTTP status. Credentials and URLs never become
assertion operands in output. Password/grant/cookie/access-token markers remain in memory; after browser close,
profile bytes are scanned for those actual markers before deletion. Browser local/session storage, IndexedDB,
Cache Storage, service workers, visible URL and history state are checked independently. Browser-owned history
retention remains subject to TASK-00064's narrow S64-HISTORY-01 amendment, not a general browser-internals-erasure
promise. Passing byte scans describe only the observed disposable profile, not forensic certification.

Normal success/failure closes profiles/server, empties the owned mailbox, resets the guarded schema and removes
test keys, NSS stores and scratch. Abrupt engine loss/SIGKILL cannot guarantee cleanup: preserve the failed result,
inspect native processes and exact task state before retry. No prior success substitutes for interrupted evidence.

`.runs/build/reports/browser-smoke/summary.json` retains only scenario/assertion counts, durations, browser version,
exit, source stability and cleanup booleans. Focused checks compare actual tracked/unignored bytes, modes, index
and status; canonical checks additionally cover dependencies/artifacts through the existing full-gate fingerprint.
No application runtime reads `.runs/`. Reports are verification evidence, never independent Review or QA acceptance.
