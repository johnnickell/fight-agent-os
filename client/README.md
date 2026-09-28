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
failure cannot execute React recovery; the base document retains its loading/noscript fallback. No API calls,
principal context, auth restoration, permissions, credential handling or browser persistence exist yet.
Transport services and authority guards belong to TASK-00027/00028.

A static light `data-bs-theme` marker and isolated stylesheet preserve the theme-bootstrap integration point.
TASK-00050 owns the shared preference rules, pre-paint preference bootstrap, OS/storage listeners and selector;
this shell does not read or write preferences. Neutral Bootstrap styling, semantic landmarks, visible keyboard
focus, reduced-motion overrides and wrapping establish only a foundation baseline, not accessibility certification.

Tests prove observable route/landmark/title/keyboard behavior, controlled pending/failed imports, render failures
and strict config decoding. V8 coverage measures client unit/component behavior, excludes declarations and the
browser-only root composition, and is not backend or end-to-end coverage. Verify build/install/manifest/static
wiring directly rather than testing tooling in the product suite. Builder browser captures live under ignored
`.runs/notes/TASK-00026/`; independent technical review and interactive QA remain separate acceptance steps.
