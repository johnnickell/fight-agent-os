# Browser foundation

The production React shell is available at `/app` and `/app/`. It is deliberately not a dashboard,
login, Planning workspace, or final visual design. `/` retains the PHP smoke response; `/api/v1` retains
its independent API contract. Unknown `/app/...` paths render the shell's not-found page (HTTP 200),
not an API response. Paths outside `/app` never fall back to React.

## Toolchain and commands

Use Docker and the repository wrapper; no host Node installation or global frontend tools are required:

```sh
./bin/client setup        # Explicit pinned image pull and clean npm ci; requires registry access
./bin/client storybook-setup # Explicit pinned browser image build, once per Docker host
./bin/client versions
./bin/client check        # Complete focused frontend checks; no network or dependency changes
./bin/client build        # Publish inspected assets for the local /app (check uses scratch output)
./bin/client coverage     # Scoped client unit/component coverage under .runs/client/coverage/
./bin/client audit        # Optional live registry vulnerability report
./bin/up
# Open http://localhost:18087/app, or use the existing opt-in HTTPS setup below
./bin/build              # Complete canonical gate, including this frontend sequence
```

Individual read-only acceptance commands: `typecheck`, `lint`, `format-check`, `test`, `coverage`,
`build-check`, `storybook-build`, `storybook-test`, `storybook-capture`. `build` additionally publishes assets
under ignored `public/build/`, preserving old hashes.
`format` explicitly rewrites formatting; `lint-fix` explicitly applies semantic/import autofixes. `lock` deliberately regenerates the lock after an authorized dependency change; routine checks
never install or update packages. Commit `package.json` and `package-lock.json` together. Installation disables
package lifecycle scripts; ESBuild uses its locked platform binary dependency. Caches/reports live in ignored
`.runs/client/`, dependencies in ignored `client/node_modules/`, and assets in ignored `public/build/`.
Setup records hashes of the manifest, committed lock and npm's installed lock in
`node_modules/.fight-install.json`. Every tooling invocation verifies this receipt and exact Node/npm versions;
a missing/stale receipt fails with an explicit setup instruction, never an automatic install. The receipt
is local provenance/staleness evidence, not a tamper-proof integrity check of every installed byte.
Acceptance containers mount source and dependencies read-only, with only ignored Vite/Storybook cache mounts
writable beneath `node_modules`. Fix/install/lock commands are the explicit exceptions.

Selected from registry engine/peer contracts and qualified by clean installation and checks:

- Node **24.21.0**, npm **11.19.0**, official Bookworm-slim image pinned by digest in `bin/client`.
  Node 24 is the [LTS line](https://github.com/nodejs/Release#release-schedule); npm is its bundled manager,
  avoiding a second bootstrap dependency. The manifest enforces exact engines and manager version.
- React/React DOM **19.3.0**, TypeScript **6.0.3**, ESBuild **0.28.2**, Bootstrap **5.3.8**,
  React-Bootstrap **2.10.10** (see [control qualification](#standard-controls)) and Formik **2.4.9**.
  Formik declares React >=16.8; the exact locked combination was clean-installed and checked without peer overrides.
- Vitest/coverage-v8 **4.1.11**, RTL **16.3.3**, jest-dom **6.9.1**, jsdom **30.1.1**.
- Storybook/react-vite/a11y/Vitest addon **10.4.6**, browser-playwright **4.1.11**,
  Playwright **1.61.0**, axe-core **4.13.0**. See [catalog qualification](#component-catalog).
- ESLint **9.39.5**, typescript-eslint **8.71.0**, accessibility/hooks plugins, Prettier **3.9.9**.
- eslint-plugin-simple-import-sort **14.0.0** (ESLint >=5 peer), Dart Sass **1.105.1** (Node >=20.19).
  Existing dependency versions are unchanged. Sass uses ESBuild's native `onLoad` API in production and
  Vite's built-in Sass support in the catalog; no additional resolver or Sass-loader package is needed.

TypeScript 7 is outside typescript-eslint's current peer range; Vitest 5 conflicts with jest-dom's assertion
type declarations. Neither is forced through with ignored peer dependencies or `skipLibCheck`. ESLint 9 is
compatible with the accessibility plugin's declared peer range but emits an unsupported-version deprecation
at installation. Retain this disclosed limitation until a compatible lint-stack upgrade; a clean audit does
not establish ongoing upstream support. Exact remaining direct pins and transitive integrity hashes are in
the manifest/lock. The [canonical repository gate](../docs/engineering/BUILD.md) runs these checks directly
from its PHP orchestrator in the prepared web service, using the same Node/browser binary pins and installed lock.
It does not call `bin/client check` or rerun its phases; the wrapper remains a focused iteration entrypoint.

### Focused frontend quality gate

`./bin/client check` runs versions/installed dependency validation, both strict TypeScript projects, semantic
and accessibility lint, non-mutating formatting, V8-instrumented Vitest/RTL, a clean ESBuild production build
with artifact inspection, static Storybook build/inspection, Chromium story interactions/a11y, and static
catalog captures/resource checks. Each stage fails fast. Only explicit setup/maintenance/dev commands have
external networking; checks do not install, update or audit. `npm run check` is intentionally absent: the
focused repository wrapper owns orchestration across the two pinned container environments; PHP owns the
canonical gate. Both use Node 24.21.0
and npm 11.19.0. There is no percentage-only frontend gate or seeded-failure/product test of these tools.

Production check output is `.runs/client/production/`, rebuilt clean without pruning live `public/build/`.
`production-metafile.json` and `production-artifacts.json` record the actual ESBuild/Sass input graph and
output sizes/SHA-256 hashes. `build` applies the same inspection before publishing assets/manifest and writes
`build-*` reports. The two-field manifest is published last; no extra private configuration is serialized.
The current allowlist admits production `src` TypeScript, `styles` SCSS and locked non-dev runtime packages;
it rejects test/story/fixture/prototype/cache inputs, development modules, external module imports and
unexpected output types. Only hashed JS/CSS and external license notices are emitted. Source maps and
source-map/sourceURL references are forbidden. The stylesheet may embed self-contained Bootstrap SVG controls,
not external fonts/images/imports. New asset types or third-party resource contracts require a deliberate policy change.

Artifact content inspection rejects known private environment names, process/import-meta environment access,
private-key/JWT/AWS/GitHub credential signatures, literal credential properties, embedded UUID identities and
unapproved remote JavaScript URL literals. XML namespace identifiers and React's diagnostic URL are narrowly
allowed; they are not runtime resource loads. These are bounded graph/content checks, **not a general secret
scanner or a proof against obfuscated/dynamically constructed values**. Human source review must still establish
that credentials/private config/authority snapshots are not bundled. Production never receives host application
secrets through the wrapper. Catalog tooling legitimately contains help URLs and example authority fixtures;
it is isolated from production, has no source maps, and its static captures fail on external requests, render
errors, accessibility violations or horizontal overflow. `storybook-artifacts.json` inventories its files/hashes.

Coverage includes **all** `src/**/*.{ts,tsx}`, including unimported source. Exact exclusions are
`src/**/*.d.ts` (declarations) and `src/main.tsx` (browser root/CSS/boot composition). Type-only modules have no
executable denominator. Tests, stories, configuration, scripts, SCSS and dependencies are outside this owned
TypeScript behavior measure, not secretly counted as covered. Reports (`text`, JSON summary, HTML) are under
`.runs/client/coverage/`; read uncovered branches alongside their contracts. Unit/component coverage is not
browser-story, backend or end-to-end coverage. Narrow checks remain available for iteration; the full repository
`./bin/build` remains mandatory and already includes this sequence. Screenshot evidence is not visual approval or universal accessibility certification.

## Frontend conventions

- `src/` contains production TypeScript, `tests/` contains all behavior tests and test-only support, and
  `stories/` contains production-backed catalog examples. Mirror each source-relative path under `tests/`
  using `.test.ts`/`.test.tsx`; shared setup and fixtures live directly under `tests/`. Do not colocate tests
  or fixtures with production modules, create test barrels, or rename the production tree.
- Use `@/` for application imports/re-exports, lazy imports and mock targets in production, tests and
  stories: `import { Button } from '@/components/Button'`. `tsconfig.json` maps `@/*` to `src/*` for
  TypeScript and ESBuild; `vite.resolve.ts` supplies the same single root to Vitest and Storybook dev,
  static and browser-test builds. Relative paths remain for test-only support, tooling and assets/styles.
  Tooling imports use explicit `.ts` extensions where native ESM requires them; no-emit TypeScript allows
  these without relaxing type strictness.
- ESLint enforces deterministic alphabetized groups: React-family (`react`, `react-*`) runtime imports, other built-in and
  external imports, internal `@/` imports, permitted relative imports, then type-only imports. Side-effect
  imports form a separate first group **in original order**, never alphabetized; keep production styles
  before catalog-only styles. Existing inline `type` specifiers remain type-only. ESLint also enforces
  application-root paths for static imports/re-exports and literal dynamic/mock targets; TypeScript and
  builds validate actual resolution. Do not construct application module paths dynamically.
- Supported TypeScript type-aware recommendations run against the production/test and separate catalog
  projects, including `.storybook/` configuration. React Hooks, JSX accessibility, strict optional/index
  checks and zero-warning lint remain enabled. JavaScript tooling retains ordinary semantic/import lint.
- Prettier owns formatting: 100 columns, single quotes, semicolons, two spaces, no tabs or trailing commas.
  `./bin/client lint` and `format-check` do not fix or install anything; use `lint-fix` and `format` explicitly,
  review their diff, then run `check`. Dependency changes use explicit `lock`/`setup`, never acceptance checks.

### Standard controls

Prefer React-Bootstrap directly for standard buttons/forms, alerts/spinners, accordions/collapse,
modals/offcanvas, dropdowns, tabs/navigation and tooltips/popovers. Import the supported component at its
point of use, e.g. `import Accordion from 'react-bootstrap/Accordion'`; do not create a local library barrel,
registry or a wrapper that merely renames/forwards its API. Use supported React props and controlled/uncontrolled
state contracts, never Bootstrap's imperative JavaScript plugins or `data-bs-toggle` wiring. Bootstrap SCSS owns
appearance; React-Bootstrap owns standard React UI mechanics. Add catalog examples and relevant keyboard,
focus and state checks when an actual scoped use case adopts a control; do not build unused galleries.

Keep an owned component when it adds application meaning, a repeated composition or additional behavior.
The [catalog responsibility table](#component-catalog) describes the five retained contracts:
`Button` adds focus-preserving busy labels and activation/submission suppression to the library button;
`TextField` connects labels/help/errors and caller refs using FormLabel/FormControl/FormText/Feedback;
`Notice` uses Alert without its transition so explicit status/alert/no-role and atomicity reach the native div;
`ContentState` composes Notice/Button and a decorative library Spinner with caller-owned states/retry.
`ContentPanel` retains its named section/header semantics unchanged: a Card/Container wrapper would add no
useful mechanics. Shell recovery remains a native navigation link, not a library Button: the latter's anchor
mode supplies button role/Space-key behavior, which would change the accepted link contract. Decorative spinners
are hidden from accessibility APIs; adjacent text owns the meaning.
Button and Notice select an empty library variant so the existing semantic custom-property styling remains
in control, rather than layering an unrelated default primary variant. No new theme is selected.

Qualified exact **2.10.10** against the locked stack above and Storybook **10.4.6**, using strict TypeScript
and actual owned unit/browser interactions. Its [published peer contract](https://registry.npmjs.org/react-bootstrap/2.10.10)
accepts React/React DOM >=16.14.0 and @types/react >=16.14.8; its
[release-source compatibility table](https://github.com/react-bootstrap/react-bootstrap/blob/8b1cb721001cb1a47b068002d1bed12d12b8d88b/README.md#bootstrap-compatibility)
pairs 2.x with Bootstrap 5.x. TypeScript and Storybook have no declared peer constraint in this package; strict
compilation and production/catalog builds qualify our usage, not every library export. FormControl's optional
props/value declarations are narrower than native input props: the adapter omits absent values, copies readonly
array values and maps native numeric `size` to `htmlSize` without widening or casting the public contract.
No forced peers, `skipLibCheck`, unrelated upgrades or new form/state libraries are used. Future control adoption
still needs its own interaction qualification, especially overlay focus and transition behavior on React 19.

### Styles and customization boundary

`styles/app.scss` is the shared public source entrypoint for production and Storybook. Its thin ordered
`@use` composition loads Bootstrap, semantic tokens, document/layout styles, component styles, then focus
and reduced-motion rules. `settings/_bootstrap.scss` is the **only** legacy Sass-import boundary; Bootstrap
5.3.8 requires it. Deliberate future static Bootstrap variable overrides belong before that import. This
migration retains upstream defaults and does not load precompiled Bootstrap CSS or its JavaScript plugins.

Owned dependencies use namespaced Sass modules. Keep styles in small responsibility-named partials under
`styles/base/`, `styles/layouts/` and `styles/components/`; add page styles only for a real page. Shared spinner
sizing/spacing has one owner because both busy buttons and loading states use the library Spinner; Bootstrap
owns its border shape and animation through supported `--bs-spinner-*` custom properties. Reusable styling uses shallow
class selectors; the stable singleton `#app` belongs to the shell layout. Never couple styling to React-generated
accessibility IDs or add broad ID-descendant overrides. Reduced-motion `!important` rules are deliberate
accessibility overrides. `.storybook/preview.scss` contains catalog-only layout and is loaded **after** the
production foundation; it never enters the production bundle. No CSS Modules, mixin framework or empty taxonomy
is required. Production emits one hashed default CSS asset; the catalog emits its own locally compiled preview.

Sass 1.105.1 compiles Bootstrap 5.3.8 but reports `import`, `if-function`, `global-builtin` and `color-functions`
deprecations (20 displayed plus 311 repetitive warnings summarized per foundation compilation). No warnings are
silenced. Bootstrap's legacy Sass is not compatible indefinitely: qualify a separately authorized upgrade before
adopting a compiler that removes these features. Keep the exact installed versions rather than forcing peers.

Runtime semantic CSS custom properties remain the customization surface; Sass settings are build-time only.
`base/_tokens.scss` defines colors at both `:root` and explicit `[data-bs-theme='light']`/`'dark'` regions, and
font/spacing/radius/motion plus Bootstrap mappings at `:root, [data-bs-theme]`. A root-only override does **not**
replace values redeclared in an explicit themed region. Theme authors should target the intended root and/or
mode scopes and may override component classes directly, not just a palette. Preserve low specificity and the
cascade: default foundation first, a deliberate theme stylesheet later, without modifying React components or
rebuilding application Sass. Plain CSS is a valid future theme distribution format even though defaults use SCSS.

This is an extension direction, **not an implemented theme loader**. A future single CSS file after the default
in base HTML, or a deliberate theme style block, needs its own delivery design. Current `style-src 'self'` forbids
inline style blocks; do not add `unsafe-inline`. Font-family overrides do not authorize remote fonts: assets need
provenance, serving and font/CSP support. No loader, HTML hook, upload, selector, storage or policy change is added.

Database-driven theme management remains a future Wayfinder topic: authoring/import, trusted versus untrusted
CSS, assets/fonts, installation/user selection, preview, versioning/rollback, fallback, caching, pre-paint delivery
and accessibility. Decide whether to store tokens, source CSS, asset references or a combination then; no schema
or map is introduced here. Custom theme identity remains separate from TASK-00050's system/light/dark preference.

## Serving and deployment boundary

`build` emits minified, content-hashed ESM/CSS and external license notices, plus a two-field asset manifest.
ESBuild, not Vite, builds production; Vite serves tests and the development/static catalog. No source maps, dev server,
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
  Reusable guarded outlets and a volatile intended-route owner are available for future protected routes.
  Navigation uses native same-origin links and browser history, not a second client router or state store.
- `src/layouts/`: named navigation, skip link, focusable main outlet and footer.
- `src/pages/`: home, not-found, pending page import, and generic boot/render/module failure.
- `src/components/`: reusable presentation, native controls/forms and the shared render-error boundary. Recovery reloads the
  fixed `/app` path; it never reconstructs a destination from caller input.

Loading/errors are actual boot/import states, not pretend product routes or a permanent debug gallery.
Malformed config never mounts routes; failed page imports show generic recovery. Initial entry-script/network
failure cannot execute React recovery; the base document retains its loading/noscript fallback. The shell makes
no API calls and mounts no principal owner, authentication restoration or protected routes. The transport,
CSRF service and authority foundation below are available for later feature composition; no browser persistence
or synthetic production principal is introduced.

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
  status/message pairs and malformed values become `protocol`. Validation field/message lists are bounded and
  retained only for explicit feature-level mapping against approved public schema messages; generic failures
  still expose no server prose. Correlation is taken only from a single canonical 32-hex response header; absent or
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
  state. The authority owner's scope below fences future protected-data results; transport interceptors do not
  restore sessions, authorize operations or replay mutations.

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

## Shared form validation boundary

`features/validation/PublicSchemaService` reads only the anonymous named validation metadata through the
existing GET transport. It strictly decodes schema version 1, the 64-hex whole-catalog revision, explicit
snake_case → camelCase field mappings, and the currently published `Required`, `Type[string]`,
`MinLength`, `MaxLength`, `Same` rules. Unknown or malformed rules fail closed; missing/unavailable schemas
must be shown and retried explicitly by the owning form, never treated as valid. The service caches at most
eight safe schemas in memory, invalidates its cache on a changed revision and can be cleared at teardown or
deployment change. Later-started reads that establish a different revision fence earlier outstanding responses;
`clear()` also invalidates pending results. Callers abort pending reads on unmount/supersession. No form values or credentials are cached.

`validatePublicSchema` reproduces the published PHP rule subset on source-compatible primitive values:
`Required` checks presence (not nonempty); `Type[string]` checks string type; length counts Unicode codepoints
(`mb_strlen` equivalent for the published subset), with PHP-compatible primitive casting; and `Same` compares
two present values strictly. This is usability feedback, never server validation or credential policy.
`usePublicForm` is a narrow Formik adapter: form components bind registered field props, present multiple
plain-text messages with the production `TextField`, and submit through a feature-owned function. Typed/pasted
and adapter-mediated programmatic changes increment field and dependency generations; local/server errors for
affected fields are invalidated without erasing unrelated errors. Reset, schema replacement and unmount fence
late failures. A current successful submission resets owned values when unchanged; an edit after dispatch is
left intact. A real server success still reaches the owning callback even after an edit/reset, since a client
cancel cannot undo a mutation. Independent unchanged fields may receive current errors from a late response;
unknown server field/dependency provenance is discarded after any intervening edit. A rejection containing only
superseded field feedback does not become a generic form error; current unclassified failures retain generic
feedback. Locally invalid resubmission preserves current server feedback on unchanged, unrelated fields. Only
published safe messages on explicit `body.<wire_field>` paths are shown; mixed published/private failures show approved field messages and generic
form-level feedback without rendering private text. No arbitrary server path
is used as a Formik setter. The owning journey controls loading/retry, form-level status, completion/cleanup and
server success; it must not persist or log credential values. Real activation/login/reset/change forms and their
nonempty PHP registrations are still owned by their journey TASKs. The catalog examples are synthetic local
interactions, not endpoints or authentication E2E evidence.

## Client authority foundation

TASK-00028 implements [ADR 0004](../planning/adr/0004-client-authority-and-runtime-state.md) as reusable
in-process capabilities. The public shell deliberately does not construct them: **`/api/v1/me` is not
implemented**, and there is no fake production authentication. Tests and the Authority catalog inject bounded
example loaders. TASK-00117/TASK-00049 still own the server projection and real HTTP feature service.

- `features/authority/CurrentPrincipal.ts` accepts exactly `userId`, canonical lowercase `email`, `roles` and
  `permissions`; it atomically rejects malformed/incomplete/extra fields, duplicate names and sparse arrays.
  UUID identity, bounded display email and bounded exact name lists are copied and frozen. The injected model
  is camelCase, not a transport View; the future feature service must qualify its snake_case codec. No token,
  session identifier, lifecycle state or credential belongs in this model.
- `AuthorityCache` owns one immutable snapshot and subscriptions per tab context. `load()` coalesces demand;
  decoded success alone authenticates. Network/protocol errors are distinct non-authoritative outcomes.
  Neither cache reads nor errors extend the 60-second monotonic request-start budget. A one-shot foreground
  timer removes mounted authority; every render/action read also checks freshness in case that timer was delayed.
  `observeAuthorityResume` attaches once per owner and returns listener cleanup. Hidden-tab return/focus removes
  authority; resumption/expiry does not start a polling loop. Navigation/feature demand or an explicit retry calls
  `load()`; the guard itself does not initiate authentication or automatically retry a denied route.
- The future credential coordinator calls `signal('refresh-started')` before refresh, `credentials-changed`
  after accepting current credentials, and `refresh-failed` on recoverable failure. `authority-changed` retires
  and refetches when loading is permitted. Logout/terminal signals clear authority immediately and establish a
  context barrier. `beginAuthentication()` returns a single-use, context-fenced attempt; only its
  `acceptCredentials()` can cross that barrier. Its `fail()` reports recoverable failure. These notifications
  carry no credentials or principal; the credential owner must also clear its memory, fence its own login/refresh
  results and implement ADR 0003's bounded restoration. Those integrations are not supplied here.
- Request and context generations reject old successes **and** failures even if the injected loader ignores
  abort. `captureScope()` supplies an abort signal and `isCurrent()` fence for protected data/actions. A future
  private-data owner must clear retained data on abort and check this fence before adopting either success or
  error, including after identity replacement. This is a tested seam, not an implemented private-data cache.
  Dispose owners and detach lifecycle listeners at context teardown; no cache or snapshot is persisted.
- `evaluateAccess` is the only permission evaluator. Route metadata is an explicit complete ordered list of
  ancestor requirements followed by the leaf: `public`, `authenticated`, or nonempty `permissions.all`.
  Every requirement must pass; even a public leaf cannot weaken its protected parent. Missing/malformed metadata
  is unavailable, never implicitly public. Exact names grant; displayed role names never do. Forbidden is derived
  presentation for a valid principal, not another stored principal state or a login/refresh loop.
- `GuardedRoute` mounts a component outlet only after evaluation succeeds, so a denied lazy outlet is not imported
  and its loaders do not run. Put protected loaders inside that outlet and honor its scope fence; do not prefetch
  above the guard. `PermissionButton` has explicit hide/disable policy and re-evaluates at activation, suppressing
  handlers if authority expired after render. `AuthorityStatus` renders safe pending/recovery/denial outcomes.
  **Server authorization remains authoritative.** A server 403 remains the operation's forbidden result even after
  a fresh projection; signal authority invalidation when appropriate, but never replay the operation automatically.
- `routes/IntendedRoute` owns at most one in-memory intent, for 10 minutes and 2,048 input characters. Only exact
  registered protected static paths under `/app/` can opt in via `intendedRoute` query metadata. Initially only
  `page`, `sort` and `view` keys with enumerated non-sensitive 1–64-character alphanumeric/underscore/hyphen values
  are supported; even those keys are rejected unless explicitly listed by that route. Duplicate/unknown keys,
  credentials, auth/grant paths, external/scheme/relative targets, controls, backslashes, ambiguous paths/encoding
  and oversized input are rejected. Fragments are discarded. Parameterized paths need a later classified contract.
  The guard captures eligible confirmed-anonymous navigation when given this owner and the current location.
  Logout clears intent through `AuthorityCache.subscribeLogout`, independently of snapshot subscriptions.
  Cleanup recipients are captured at logout intake and notified after authority retirement even if an abort or
  earlier subscriber replaces the logout snapshot with terminal; involuntary terminal expiry alone retains intent.
  Dispose the navigation owner to unsubscribe. Repeated redirects do not replace intent or renew its deadline.
  After fresh authentication, `consume()` re-resolves current route requirements and consumes once. A null result
  leaves landing selection to the future navigation owner: choose an authorized landing route or forbidden,
  never invent a Dashboard grant.
  No login return URL, history-state payload, form data, browser storage or cross-tab transfer is used.

Authority tests cover controlled/out-of-order promises, context barriers, invalidation, freshness, loader
rejection, safe navigation, exact nested permissions, protected mount/import and action suppression. Catalog
examples exercise the actual components with injected fixtures, including session-end removal. They are not
server integration, live authentication, cross-tab consistency, final visual design or independent QA evidence.

## Presentation foundation

A static light `data-bs-theme` marker and isolated stylesheet preserve the theme-bootstrap integration point.
TASK-00050 owns the shared preference rules, pre-paint preference bootstrap, OS/storage listeners and selector;
this shell does not read or write preferences. `styles/base/_tokens.scss` maps neutral semantic colors, typography,
spacing and motion to Bootstrap variables. Semantic landmarks, visible keyboard focus, reduced-motion overrides
and wrapping establish only a foundation baseline, not accessibility certification.

Tests prove observable route/landmark/title/keyboard behavior, controlled pending/failed imports, render failures
and strict config decoding. V8 coverage measures client unit/component behavior, excludes declarations and the
browser-only root composition, and is not backend or end-to-end coverage. Verify build/install/manifest/static
wiring directly rather than testing tooling in the product suite. Builder browser captures live under ignored
`.runs/notes/TASK-00026/`; independent technical review and interactive QA remain separate acceptance steps.

## Component catalog

The catalog's presentation core imports five reusable production components, rather than embedding
implementations or promoting prototype code. Authority examples additionally compose the production cache,
guard, status and permission-action components above. None adds product routes to `/app`.

| Component         | Responsibility                                                                                                                                                                                                                                |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Button`          | Native keyboard/pointer control; defaults to `type="button"`. `disabled` uses native unavailable semantics. `busy` preserves focus/tab order, changes the visible label, sets `aria-busy`/`aria-disabled`, and cancels activation/submission. |
| `forms/TextField` | Native labelled text-like input with required text, unique IDs, help/error association and caller focus ref. The feature owns validation, value, submission and focus movement.                                                               |
| `Notice`          | Visible title/content plus polite status (default), explicitly urgent alert, or static presentation. Select urgency deliberately; mount/update a status in response to a meaningful change, not every render.                                 |
| `ContentState`    | Loading/empty/error/success presentation. Retry is explicit and only offered when supplied; no fetching, automatic retries or retained data.                                                                                                  |
| `ContentPanel`    | Named section with wrapping header/actions and unbounded content reflow.                                                                                                                                                                      |

`stories/` holds **37** named examples across six story files: 4 Button, 9 TextField, 4 Notice, 4 ContentState,
5 Composition states and 11 Authority states/interactions. The form composition demonstrates rejection,
focus correction and local success; the shared Formik examples use synthetic local responses to exercise
multiple messages, correction, unavailable metadata and late-response suppression;
it never sends or saves data. Fields and callbacks use invented local text only, with no assets, credentials,
provider records or mutable external content. React `useId` associates fields/regions. Authority fixtures
use a factory-generated UUID and deterministic injected outcomes; only the real cache freshness clock runs.
Stories confer no runtime authority, real authentication or server-side permission semantics.

`styles/base/_tokens.scss` owns background/surface/text/muted/border/action/on-action/focus and info/success/warning/danger
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
./bin/client storybook-setup    # Explicit pinned Playwright + Node image build, once per Docker host
./bin/client storybook-dev      # Interactive catalog at http://localhost:16006; Ctrl-C stops it
./bin/client storybook-build    # Offline static output: .runs/client/storybook/
./bin/client storybook-test     # Chromium story interactions + Storybook a11y addon; violations fail
./bin/client storybook-capture  # Built artifact: all 37 stories at 320x900 and 1280x900, axe + PNG evidence
./bin/client check
./bin/client coverage
./bin/build                    # Sole canonical gate; includes frontend and backend phases
```

Build/test/capture containers have no external network. The development server alone publishes a loopback port;
its middleware-server HMR client uses the wrapper's host port 16006, not container port 6006. Browser story tests
keep their own server/port. Check port ownership before starting the development server and stop only your instance.
Serve it only for local engineering, never as a public product endpoint. The static build requires no CDN or
runtime network. Tooling ships documentation/help/license URL strings; their presence is not a runtime fetch.
Telemetry/crash reporting are disabled. Storybook emits `project.json.generatedAt` despite that setting;
`finalize-catalog-build.mjs` removes only that non-runtime timestamp. Compare repeated output hashes directly,
not through product-suite tests. Static artifacts are ignored and are not deployed with `/app`.

`storybook-capture` starts/closes its own loopback static server and Chromium inside the network-disabled
container. It renders every story, blocks/records external requests, runs axe-core on the production preview,
and sets the addon to manual only in each capture URL to prevent overlapping axe scans (browser story tests
retain automatic addon checks),
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

The browser base is Playwright **1.61.0 Noble**, pinned by digest in `bin/client`, with Chromium **149.0.7827.0**.
`storybook-setup` builds `etc/docker/client-browser/Dockerfile`, copying `/usr/local/` from the same pinned Node
image used for installation/build: **24.21.0** / npm **11.19.0**, replacing the base's older Node runtime.
The Dockerfile defaults retain the same digest pins for direct builds; update both locations deliberately.
It runs only explicitly installed tooling. Browser binaries and system libraries come from the pinned
Playwright base, never an install script or on-demand download.
Builds disclose Storybook's >500 kB tool-chunk warnings (not production `/app` chunks), React-Bootstrap
`use client` directive-preservation warnings in the browser-only catalog, and Rolldown plugin-timing warnings
when emitted. None is suppressed. The inherited ESLint 9 installation deprecation remains. Caches, static output, coverage, browser failure screenshots and captures are
ignored under `.runs/client/`. The otherwise read-only install's `node_modules/.cache/` and `.vite-temp/`
mount `.runs/client/dependency-cache/` and `.runs/client/vite-temp/` respectively during checks.
