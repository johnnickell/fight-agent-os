# @fight/react

`@fight/react` is an ES module library for React and React DOM 19. It uses Bootstrap 5 and React-Bootstrap. Install both React peers in the host; the library does not bundle them. The first candidate is `0.1.0`; this name and version have not been published.

## Use the public entry points

- `@fight/react/controls`: controls, public and error layouts, theme preference, and branding.
- `@fight/react/account`: account screens, account links, and the session and current-principal owner.
- `@fight/react/styles.css`: compiled Bootstrap and shared defaults. Import it once, then import the host's override stylesheet. Consumers do not need Sass.

Use a browser bundler that supports ESM, React JSX, and CSS imports. The library does not require the Agent OS router or Storybook. Render public screens outside the authenticated tree where a credential link must remain inert until the user submits it. For routes with a continuing session, call `useAccountSession` once at the host boundary and pass the resulting services into the screens. Use `LoginJourney` only when that standalone screen is the session owner; otherwise compose `LoginPage` with the existing owner.

```tsx
import { createAccountSession, LoginPage, useAccountSession } from '@fight/react/account';
import { BrandProvider, ShellLayout } from '@fight/react/controls';
import '@fight/react/styles.css';
import './brand.css';

function SignIn() {
  const { session, retired } = useAccountSession(() =>
    createAccountSession({ schemaVersion: 2, apiBasePath: '/api/v1' }, () => ({
      clear() {},
      dispose() {}
    }))
  );
  return (
    <BrandProvider brand={{ name: 'Harbor Desk', logo: { src: '/brand/sailboat.svg', alt: 'Harbor sailboat' } }}>
      <ShellLayout>
        {retired ? <p>Reload to sign in again.</p> : <LoginPage service={session.coordinator} schemas={session.schemas} />}
      </ShellLayout>
    </BrandProvider>
  );
}
```

An intent owner can clear a host's pending destination at session retirement. Supply `AccountLinksProvider` to map semantic destinations such as `signIn`, `account`, and `invitation` to trusted same-origin paths and applicable display requirements. A destination can be omitted. The host still owns routing, outlet admission, and navigation. The credential readers follow the existing `/activate` and `/reset-password/complete` link and fragment contract.

The server still authorizes requests and supplies the current principal. The library expects the Fight browser HTTP v1 transport at same-origin `/api/v1`, including public validation schemas, CSRF proofs, cookie-backed continuity and protected JSON. It neither installs endpoints nor grants any role or permission. The session factory uses the qualified browser transport, not a consumer-provided URL, headers, or fetch override. It owns memory-only credentials, a single authority cache, and terminal retirement. Do not persist credentials or the principal projection.

## Brand and theme

Supply plain `name` text and optionally `{ src, alt }` for a same-origin logo. Use a root-relative static SVG, PNG, WebP, or AVIF path with simple path segments. Remote, protocol-relative, encoded, query, and fragment locations are unsupported. Supply meaningful alt text. The host should serve reviewed logo bytes under its normal content security policy.

Set `data-bs-theme="light"` or `"dark"` on the document or host scope. As normal CSS loaded after the defaults, override the roles you need in each theme. The library uses system fallbacks unless the host already serves the optional Ubuntu and Open Sans fonts locally. The package does not load remote fonts.

```css
:root {
  --ui-font: system-ui, sans-serif;
  --ui-heading-font: Georgia, serif;
  --ui-space: 0.28rem;
  --ui-radius: 0.65rem;
}
:root[data-bs-theme='light'] {
  --ui-action: #126657;
  --bs-primary-rgb: 18, 102, 87;
}
:root[data-bs-theme='dark'] {
  --ui-action: #a2e4d0;
  --bs-primary-rgb: 162, 228, 208;
}
```

`--ui-background`, `--ui-surface`, `--ui-rail`, `--ui-raised`, `--ui-text`, `--ui-muted`, `--ui-border`, `--ui-action`, `--ui-action-hover`, `--ui-on-action`, `--ui-focus`, and the semantic status colors are defined in [the default tokens](styles/base/_tokens.scss). Update Bootstrap RGB companions for the corresponding semantic color when using Bootstrap utilities. Verify contrast, visible focus, layout, and reduced motion after overriding tokens. Do not inject runtime CSS or arbitrary HTML.

## Build and qualify a candidate

From the repository root, use `./bin/client setup` for the locked dependency installation, then `./bin/client package-build` to compile JavaScript, declarations, and CSS. `./bin/client pack-check` packs the result with npm and compiles a clean consumer against the extracted artifact, not the Agent OS dependency link. The package inventory and integrity are recorded in `.runs/build/reports/client/fight-react-pack.json`. Neither command publishes. The ordinary `./bin/build` gate runs these checks with the normal frontend build.
