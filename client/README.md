# Browser foundation

The production React shell is available at `/app` and `/app/`. It is deliberately not a dashboard,
login, Planning workspace, or final visual design. `/` retains the PHP smoke response; `/api/v1` retains
its independent API contract. Unknown `/app/...` paths render the shell's not-found page (HTTP 200),
not an API response. Paths outside `/app` never fall back to React.

## Toolchain and commands

Use Docker and the repository wrapper; no host Node installation or global frontend tools are required:

```sh
./bin/client setup        # Explicit pinned image pull and clean npm ci; requires registry access
./bin/client versions
./bin/client check        # Typecheck, lint, format check, Vitest, ESBuild; no network or dependency changes
./bin/client coverage     # Scoped client unit/component coverage under .runs/client/coverage/
./bin/client audit        # Optional live registry vulnerability report
./bin/up
# Open http://localhost:18087/app, or use the existing opt-in HTTPS setup below
./bin/build              # Current complete repository gate, additionally required
```

Individual commands: `typecheck`, `lint`, `format-check`, `test`, `build`. Only `format` rewrites source
formatting. `lock` deliberately regenerates the lock after an authorized dependency change; routine checks
never install or update packages. Commit `package.json` and `package-lock.json` together. Installation disables
package lifecycle scripts; ESBuild uses its locked platform binary dependency. Caches/reports live in ignored
`.runs/client/`, dependencies in ignored `client/node_modules/`, and assets in ignored `public/build/`.

Selected from registry engine/peer contracts and qualified by clean installation and checks:

- Node **24.21.0**, npm **11.19.0**, official Bookworm-slim image pinned by digest in `bin/client`.
  Node 24 is the [LTS line](https://github.com/nodejs/Release#release-schedule); npm is its bundled manager,
  avoiding a second bootstrap dependency. The manifest enforces exact engines and manager version.
- React/React DOM **19.3.0**, TypeScript **6.0.3**, ESBuild **0.28.2**, Bootstrap **5.3.8**.
- Vitest/coverage-v8 **4.1.11**, RTL **16.3.3**, jest-dom **7.0.1**, jsdom **30.1.1**.
- ESLint **9.39.5**, typescript-eslint **8.71.0**, accessibility/hooks plugins, Prettier **3.9.9**.

TypeScript 7 is outside typescript-eslint's current peer range; Vitest 5 conflicts with jest-dom's assertion
type declarations. Neither is forced through with ignored peer dependencies or `skipLibCheck`. ESLint 9 is
compatible with the accessibility plugin's declared peer range but emits an unsupported-version deprecation
at installation. Retain this disclosed limitation until a compatible lint-stack upgrade; a clean audit does
not establish ongoing upstream support. Exact remaining direct pins and transitive integrity hashes are in
the manifest/lock. The current repository gate does **not** call `bin/client`; TICKET-00013 owns combined gate
integration and final quality thresholds. Run both gates for this shell.

## Serving and deployment boundary

`build` emits minified, content-hashed ESM/CSS and external license notices, plus a two-field asset manifest.
ESBuild, not Vite, builds production; Vite is only Vitest's transform dependency. No source maps, dev server,
environment serialization, CDN, or service worker is enabled. The target is modern ES2022 browsers with ESM.
Bootstrap CSS is included; no Bootstrap JavaScript plugins or product theme are introduced.

Slim's `ClientShellAction` serves base HTML through `ClientShellResponder`; `ClientAssetManifest` accepts only
existing hashed entry assets. Absent/invalid manifest or entry files produce generic no-store HTTP 503 without
broken script references. The manifest is an internal build artifact, not a public API. Rebuild to repair an
incomplete local deployment. HTTP development routing and the HTTPS gateway allow only hashed JS/CSS/license
files under `/build/`. API and unrelated routing misses remain server-owned, including dotted paths.

For production-like local HTTPS, follow [local HTTPS setup](../docs/engineering/LOCAL_HTTPS.md), then run
`./bin/https up` after building assets to refresh the gateway configuration. Open **https://localhost:18443/app**.
The gateway mounts only `public/build/` for static delivery, with immutable caching of hashed assets. HTML is
no-store with a restrictive self-only script/style CSP, no inline executable code/eval, no-referrer and nosniff.
The inert config JSON is not executable. This shell policy is not the integrated production security certification.

Deploy the PHP release together with its complete `public/build/` output. Assets are **not committed**.
The build publishes the manifest last by rename and retains old hashes for already-open documents. A real
deployment should atomically switch complete release directories and retain old assets for its supported
client lifetime; deployment/pruning automation is not implemented here. Do not delete live chunks during a
build. Local static output and PHP must belong to the same release.

## Runtime responsibilities

- `src/main.tsx`: one production React root, CSS and inert boot-config intake, safe last-resort failure.
- `src/runtimeConfiguration.ts`: strictly decode `{"schema_version":1,"api_base_path":"/api/v1"}` to immutable
  camelCase properties. Reject absent/malformed/extra fields or any other API location, with no raw-value echo.
- `src/routes/`: match only the public foundation and unknown state; lazy-load the home Page with Suspense.
  Navigation uses native same-origin links and browser history, not a second client router or state store.
- `src/layouts/`: named navigation, skip link, focusable main outlet and footer.
- `src/pages/`: home, not-found, pending page import, and generic boot/render/module failure.
- `src/components/`: title/heading presentation and the shared render-error boundary. Recovery reloads the
  fixed `/app` path; it never reconstructs a destination from caller input.

Loading/errors are actual boot/import states, not pretend product routes or a permanent debug gallery.
Malformed config never mounts routes; failed page imports show generic recovery. Initial entry-script/network
failure cannot execute React recovery; the base document retains its loading/noscript fallback. The shell makes
no API calls and has no principal context, auth restoration, permissions or browser persistence. The typed
transport and CSRF service below are available for later feature composition; authority guards belong to TASK-00028.

## Typed API and CSRF boundary

- `src/api/ApiClient.ts` owns shared GET transport. Construct it with validated runtime configuration; inject
  `fetch` for deterministic tests and an `AccessTokenProvider` from the future memory-only authentication owner
  when protected operations exist. A provider is read on every explicitly `access: 'required'` call, never on
  public bootstrap. Missing/unsafe credentials fail before dispatch; this is transport validation, not JWT authority.
- Feature services own fixed paths and `ResponseDecoder<T>` codecs. Paths are root-relative **below** `/api/v1`,
  restricted to nonempty alphanumeric/underscore/hyphen segments and 2,048 characters. Query, fragment, encoded,
  dot, backslash and external-origin forms are unsupported. No arbitrary header/body pass-through is exposed.
- Requests send `Accept: application/json` and a fresh 32-hex correlation ID, no GET body or `Content-Type`.
  Fetch uses `same-origin` mode/credentials, `no-store`, `no-referrer` and redirect rejection. Browser-managed
  cookies are neither read nor constructed. Production-like bootstrap requires the existing trusted HTTPS origin.
- Shared decoding requires the documented exact `application/json` and `no-store` response headers, HTTP 200
  with an exact success envelope, or recognized HTTP/JSend fail/error combinations. Unknown fields, inconsistent
  status/message pairs and malformed values become `protocol`. Validation messages are checked for shape then
  discarded, not displayed. Correlation is taken only from a single canonical 32-hex response header; absent or
  invalid values become null. No raw Response, URL, headers, body, exception, cause or server message is returned
  as diagnostics. `ApiResult` failures expose only their category and safe correlation.
- `src/features/auth/CsrfProofService.ts` calls the actual `/auth/csrf` operation. Its exact codec maps `expires_at`
  to UTC-second `expiresAt`, checks the proof format and matching expiry, and rejects extra fields including cookie
  material. One instance per tab's future authentication coordinator owns the proof in private volatile state.
  `bootstrap(signal?)` replaces pending work and clears the old proof; `current()` removes expired proof;
  `clear()` invalidates pending work and memory on teardown or nonce changes. Local expiry is conservative and
  depends on the browser clock; it never verifies the MAC or grants authority. The server still validates every
  future mutation. No expiry timer, automatic bootstrap retry or refresh is introduced.
- Abort resolves promptly as `cancelled`, including while reading a body or when fetch ignores its signal.
  The CSRF service additionally fences generations so old success **and** failure cannot overwrite newer state.
  Consumers must ignore cancelled outcomes rather than render them as errors; render only current feature-owned
  state. Future principal/protected-data context fences belong to TASK-00028, not a transport response interceptor.

The shared normalization matrix distinguishes validation (400/422 fail), authentication (401), authorization
(403), conflict (409), not-found (404), bad-request (400 error), method-not-allowed (405), gone (410), rate-limited
(429), generic system (5xx with the canonical message), protocol, network and cancellation. Non-bootstrap cases
are transport tests against documented mapper contracts, **not implemented protected endpoints**. The service is
not mounted into the non-product shell: no visible journey, `/me`, login, refresh, mutation API, retry loop, cache
library, cross-tab channel or browser persistence is added. Future methods/codecs need their own accepted contract.

Client tests use deterministic fetch/controlled-promise doubles, synthetic non-live values and the published
CSRF shape. They prove strict decoding, safe outcomes, memory-token policy, abort and independent generation
fences, expiry and absence of cookie/storage/history/console interactions; they do not prove browser HttpOnly
behavior or a live client/server journey. Existing PHP functional contract tests separately exercise issuance,
cookie attributes/reuse, server guards and OpenAPI response validation. No test-only endpoint is needed.

## Presentation foundation

A static light `data-bs-theme` marker and isolated stylesheet preserve the theme-bootstrap integration point.
TASK-00050 owns the shared preference rules, pre-paint preference bootstrap, OS/storage listeners and selector;
this shell does not read or write preferences. Neutral Bootstrap styling, semantic landmarks, visible keyboard
focus, reduced-motion overrides and wrapping establish only a foundation baseline, not accessibility certification.

Tests prove observable route/landmark/title/keyboard behavior, controlled pending/failed imports, render failures
and strict config decoding. V8 coverage measures client unit/component behavior, excludes declarations and the
browser-only root composition, and is not backend or end-to-end coverage. Verify build/install/manifest/static
wiring directly rather than testing tooling in the product suite. Builder browser captures live under ignored
`.runs/notes/TASK-00026/`; independent technical review and interactive QA remain separate acceptance steps.
