# Critical browser smoke

`./bin/browser-smoke [all|fragment|reload|tabs|revocation|theme]` executes five deliberately narrow
platform contracts. `./bin/build` runs the same PHP-owned phase once, after the existing catalog phases.
There is no test retry, quarantine, screenshot baseline or general E2E expansion.

## Explicit prerequisites and isolation

Prepare the selected checkout normally using [local tooling](LOCAL_TOOLING.md). The CLI image includes
the matching digest-pinned PHP FPM binary, OpenSSL and NSS `certutil`; rebuild/start it explicitly after this
change. Locked client dependencies,
live production assets and validation exports must already exist. No acceptance command installs tools,
updates locks, publishes assets, writes planning or starts Docker services.

The existing Playwright **1.61.0** lock and digest-pinned browser image supply Chromium **149.0.7827.0**,
revision **1228**. No additional runner dependency or product-test download is introduced. The runner checks
the existing Node **24.22.1** / npm **11.19.1** install receipt and exact browser version. Local execution is
containerized in the selected CLI image; native CI retains its existing explicit locked browser provisioning.
CI explicitly prepares native FPM, the production direct Nginx configuration, a disposable localhost TLS leaf/CA
and the same digest-pinned Nginx/Mailpit before acceptance; the gate never operates Docker. Native CI is a direct
Nginx/FPM envelope, not a claim of the workstation's shared ingress or default trust.

The phase holds the backend coverage database lock, verifies the configured AND connected guarded test
database, resets/migrates it before **each** scenario, and resets it again in `finally`. It never reads the
normal application's database. Synthetic Super Admin invitation setup uses the registered existing console
command and its normal catcher provider. An initially nonempty catcher holds the phase without deleting mail.
Only an initially empty, task-owned disposable catcher is cleared; cleanup verifies zero messages.

Explicitly select the existing task gateway's test supplier after ordinary setup, using ONLY the existing
public CA (never its private key):

```sh
./bin/browser-smoke setup --ca /absolute/canonical/var/local-ingress/certs/rootCA.pem
./bin/browser-smoke all
```

Setup changes only `fastcgi_pass https_php:9000` to `web:19087` in this selected gateway, validates and gracefully
reloads that Nginx, and records its configuration/public-CA digests under ignored `var/browser-smoke/`. It never
changes shared supplier/router configuration, TLS leaves, trust, DNS or peer routes. This is an explicit exclusive
test mode for the selected task origin: ordinary development requests are unavailable when no fixture runs.
`./bin/up --worktree /absolute/selected/checkout` restores the ordinary development upstream afterward; rerun
browser setup before further smoke acceptance. Do not refresh startup underneath a gate.

Each scenario starts actual FPM on the task-private `web:19087`, executing **public/index.php**, using only the
guarded test database and fresh external keys. The production gateway still owns TLS, canonical FastCGI metadata,
headers, static assets and disabled request/error logs. No custom Node terminator, PHP router or production test
route remains. Local journeys use the actual chain:

`Chromium → existing external front door → shared verified-TLS ingress → task Nginx → isolated FPM/application`.

The browser trusts ONLY the existing public CA in its private NSS store. An exact per-browser host mapping to
the existing front door is recorded, not installed as workstation DNS. Certificate validation remains enabled:
no `ignoreHTTPSErrors`, insecure flags or global trust changes. Readiness uses trusted root navigation with the
main application bundle blocked and a per-invocation FPM readiness header checked **before** same-origin CSRF
fetch or any browser database operation. A disposable FPM prepend adds only that random correlation header;
it does not route requests or set HTTPS metadata. A restored/wrong development gateway cannot pass this barrier.
Successful activation then uses the uniquely seeded grant and fixture keys. Unknown existing FastCGI listeners are preserved/refused, and the owned FPM process must
remain alive throughout each scenario. The task-private upstream has no fallback to the development FPM/database.
No claim is made about production TLS, external mail or deployment enrollment.

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
and each scenario process 120 seconds. FPM shutdown is bounded at two seconds. No scenario is replayed on
failure. Any nonzero result fails the named canonical phase. Explicit repeatability commands are separate evidence, not hidden retries.

The browser rejects product requests outside the exact fixture origin, including the second cookie jar.
No trace/video/screenshot/HAR/console dump/HTML snapshot or rich test reporter is enabled. Failures print only
scenario, fixed step, allowlisted safe UI states, numeric HTTP status and bounded browser network error codes.
Credentials and URLs never become assertion operands in output. Password/grant/cookie/access-token markers remain in memory; after browser close,
profile bytes are scanned for those actual markers before deletion. Browser local/session storage, IndexedDB,
Cache Storage, service workers, visible URL and history state are checked independently. Browser-owned history
retention remains subject to TASK-00064's narrow S64-HISTORY-01 amendment, not a general browser-internals-erasure
promise. Passing byte scans describe only the observed disposable profile, not forensic certification.

Normal success/failure closes profiles/FPM, empties the owned mailbox, resets the guarded schema and removes
per-run keys, NSS stores and runtime scratch under `var/browser-smoke/run-*`. The prepared public CA and gateway
metadata are not secret test keys; they remain for subsequent invocations until owned task runtime teardown.
Native CI additionally removes its entire prepared envelope/CA/leaf/catcher in an always-run setup cleanup step.
Abrupt engine loss/SIGKILL cannot guarantee cleanup: preserve the failed result,
inspect native processes and exact task state before retry. No prior success substitutes for interrupted evidence.

`.runs/build/reports/browser-smoke/summary.json` retains only scenario/assertion counts, durations, browser version,
exit, source stability, topology/origin, setup gateway/public-CA digests and cleanup booleans. Focused checks
compare actual tracked/unignored bytes, modes, index and status; canonical checks additionally cover dependencies/artifacts through the existing full-gate fingerprint.
No application runtime reads `.runs/`. Reports are verification evidence, never independent Review or QA acceptance.
