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
- Vitest/coverage-v8 **4.1.11**, RTL **16.3.3**, jest-dom **6.9.1**, jsdom **30.1.1**.
- Storybook/react-vite/a11y/Vitest addon **10.4.6**, browser-playwright **4.1.11**,
  Playwright **1.61.0**, axe-core **4.13.0**. See [catalog qualification](#component-catalog).
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
Bootstrap CSS and the neutral semantic-token mappings are included; no Bootstrap JavaScript plugins or
final product theme are introduced.

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
- `src/components/`: reusable presentation, native controls/forms and the shared render-error boundary. Recovery reloads the
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
this shell does not read or write preferences. `src/tokens.css` maps neutral semantic colors, typography,
spacing and motion to Bootstrap variables. Semantic landmarks, visible keyboard focus, reduced-motion overrides
and wrapping establish only a foundation baseline, not accessibility certification.

Tests prove observable route/landmark/title/keyboard behavior, controlled pending/failed imports, render failures
and strict config decoding. V8 coverage measures client unit/component behavior, excludes declarations and the
browser-only root composition, and is not backend or end-to-end coverage. Verify build/install/manifest/static
wiring directly rather than testing tooling in the product suite. Builder browser captures live under ignored
`.runs/notes/TASK-00026/`; independent technical review and interactive QA remain separate acceptance steps.

## Component catalog

The catalog imports five reusable production components, rather than embedding implementations or promoting
prototype code. They are available to future feature compositions; they do not add product routes to `/app`.

| Component         | Responsibility                                                                                                                                                                                                                                |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Button`          | Native keyboard/pointer control; defaults to `type="button"`. `disabled` uses native unavailable semantics. `busy` preserves focus/tab order, changes the visible label, sets `aria-busy`/`aria-disabled`, and cancels activation/submission. |
| `forms/TextField` | Native labelled text-like input with required text, unique IDs, help/error association and caller focus ref. The feature owns validation, value, submission and focus movement.                                                               |
| `Notice`          | Visible title/content plus polite status (default), explicitly urgent alert, or static presentation. Select urgency deliberately; mount/update a status in response to a meaningful change, not every render.                                 |
| `ContentState`    | Loading/empty/error/success presentation. Retry is explicit and only offered when supplied; no fetching, automatic retries or retained data.                                                                                                  |
| `ContentPanel`    | Named section with wrapping header/actions and unbounded content reflow.                                                                                                                                                                      |

`stories/` holds **21** named examples across five story files: 4 Button, 4 TextField, 4 Notice, 4 ContentState
and 5 Composition states. The form composition demonstrates rejection, focus correction and local success;
it never sends or saves data. Fields and callbacks use invented local text only, with no assets, credentials,
provider records, random IDs, clocks or mutable external content. React `useId` associates fields/regions.
Stories have no runtime authority, authentication or server-side permission semantics.

`src/tokens.css` owns background/surface/text/muted/border/action/on-action/focus and info/success/warning/danger
colors, one system-font stack, a spacing unit, radius and motion duration. It maps body/link/border/form and
component-local button/alert Bootstrap variables deliberately. Components use text labels as well as borders,
not color alone. Native controls have 44 CSS-pixel minimum targets, headers wrap, long text breaks, focus uses
an offset 3px outline, and reduced motion removes animation/transitions while retaining loading/busy text.
No external fonts or imagery are used. Tokens are neutral placeholders, **not EPIC-00004 visual acceptance**.
TICKET-00014 may refine them; no runtime theme selector is authorized here.

Explicit light/dark examples use `data-bs-theme` only. The isolated SystemPreview story subscribes to the browser's
color-scheme media query and cleans up on unmount; it does not read/write storage or perform a pre-paint bootstrap.
Production remains statically light. TASK-00050 owns ADR 0004's persisted enum, unavailable-media/storage fallbacks,
OS/storage listeners and selector; the catalog is not a second production preference owner.

```sh
./bin/client setup              # Clean locked frontend install
./bin/client storybook-setup    # Explicit pinned Playwright image pull, once per Docker host
./bin/client storybook-dev      # Interactive catalog at http://localhost:16006; Ctrl-C stops it
./bin/client storybook-build    # Offline static output: .runs/client/storybook/
./bin/client storybook-test     # Chromium story interactions + Storybook a11y addon; violations fail
./bin/client storybook-capture  # Built artifact: all 21 stories at 320x900 and 1280x900, axe + PNG evidence
./bin/client check
./bin/client coverage
./bin/build                    # Still additionally required; combined gate belongs to TASK-00032/00033
```

Build/test/capture containers have no external network. The development server alone publishes a loopback port;
serve it only for local engineering, never as a public product endpoint. The static build requires no CDN or
runtime network. Tooling ships documentation/help/license URL strings; their presence is not a runtime fetch.
Telemetry/crash reporting are disabled. Storybook emits `project.json.generatedAt` despite that setting;
`finalize-catalog-build.mjs` removes only that non-runtime timestamp. Compare repeated output hashes directly,
not through product-suite tests. Static artifacts are ignored and are not deployed with `/app`.

`storybook-capture` starts/closes its own loopback static server and Chromium inside the network-disabled
container. It renders every story, blocks/records external requests, runs axe-core on the production preview,
records violations/incomplete checks, geometry, native target sizes, focus outline, motion and actual heading
font, and writes screenshots/`receipt.json` to `.runs/client/catalog-evidence/`. Captures use Linux Chromium,
DPR 1, en-US/UTC, light OS preference and reduced motion; explicit dark stories override the OS. The receipt also
observes the system preview switching light/dark with normal motion. Four additional captures record a native
Tab walk through the narrow dark composition, and one captures the static Storybook manager. These are evidence, not golden-image tests
or broad end-to-end automation. Render failures, axe violations, overflow and external requests fail this command.
Check receipt limitations and visually inspect captures; no tool certifies universal WCAG conformance or actual
screen-reader announcements. Independent interactive QA remains separate.

### Catalog dependency qualification

Storybook 10.6.1's published declarations failed strict TypeScript 6/exact-optional checks in its CSF types;
10.4.6 is pinned instead, with declared Vite 8/React 19 support. The catalog has its own strict TypeScript scope
because Storybook and Vitest expose separate assertion environments. No `skipLibCheck`, force install or ignored
peer dependencies are used. jest-dom 6.9.1 aligns with Storybook's matcher implementation. The jsdom setup manually
registers its matchers and augments only `Assertion`: its convenience Vitest adapter also augments asymmetric
matchers, which conflicts with Vitest 4 browser declarations. Existing application assertions remain exercised.

The browser image is Playwright **1.61.0 Noble**, pinned by digest in `bin/client`, with Chromium **149.0.7827.0**
and Node **24.16.0**. It only runs installed tooling; locked installation/build remains on Node **24.21.0** /
npm **11.19.0**. Browser binaries come from the pinned image, never an install script or on-demand download.
Builds disclose Storybook's >500 kB tool-chunk warnings (not production `/app` chunks). The inherited ESLint 9
installation deprecation remains. Caches, static output, coverage, browser failure screenshots and captures are
ignored under `.runs/client/` (Storybook also uses ignored `node_modules/.cache/`).
