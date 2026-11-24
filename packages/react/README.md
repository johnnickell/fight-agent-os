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
        {retired ? <p>Reload to sign in again.</p> : session && <LoginPage service={session.coordinator} schemas={session.schemas} />}
      </ShellLayout>
    </BrandProvider>
  );
}
```

The hook creates the session after mount and returns `null` before setup and after terminal retirement. Supply a factory that creates a fresh graph each time React sets up the effect, including development Strict Mode replay.

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

## Stateful tables

`DataTable` owns the controls and emits one complete query and change reason. The parent owns data fetching, authorization, and any URL or router writes. `initial` seeds a mount; a new `restore.revision` applies external state without calling `onChange`. Supply a stable policy and a stable row key. Compose `TablePagination`, `TablePageSize`, `TablePageSummary`, and (only for searchable queries) `TableSearch` inside the table.

For browser-only hosts, `useTableLocation(policy)` handles canonical URLs and Back/Forward. Link a custom router to the same contract with `readTableQuery(location.search, policy)`, `writeTableQuery`, and an explicit restoration revision. Pass the raw search string to the reader so it can distinguish malformed UTF-8 from valid Unicode. Only table-owned fields spend the decoding budget; unrelated host parameters remain in the address. Access Control reads support `ID` and `Created` ordering and no search or filters. The defaults are `page=1&per_page=25&sort=Created&order=asc`. Both Users and the Role/Permission catalog read services use server-side ordering and counts, not sorting of the displayed page.

For example, a library catalog can enable search for its own `readBooks` query. It filters and orders the authorized matching set before returning a page and its matching total. `readBooks` in this example belongs to the host, not `@fight/react`:

```tsx
import { useEffect, useState } from 'react';
import {
  DataTable, TablePagination, TablePageSize, TablePageSummary, TableSearch, useTableLocation
} from '@fight/react/controls';
import type { TableData, TablePolicy, TableQuery } from '@fight/react/controls';

type Filters = Record<string, never>;
type Query = TableQuery<'Title', Filters>;
type Book = { id: string; title: string };
const policy: TablePolicy<'Title', Filters> = {
  sorts: ['Title'], defaultSort: 'Title', emptyFilters: {}, search: true
};

function Books({ readBooks }: {
  readBooks: (query: Query, signal: AbortSignal) => Promise<TableData<Book>>;
}) {
  const location = useTableLocation(policy);
  const { query, current } = location;
  const [result, setResult] = useState<{ query: Query; data: TableData<Book> }>();
  useEffect(() => {
    const controller = new AbortController();
    const publish = (data: TableData<Book>) => {
      if (!controller.signal.aborted && current.current === query) setResult({ query, data });
    };
    void readBooks(query, controller.signal).then(publish, () => publish({
      kind: 'error', title: 'Books unavailable', message: 'Change the query to try again.'
    }));
    return () => controller.abort();
  }, [query, current, readBooks]);
  return (
    <DataTable policy={policy} initial={query} restore={location.restore} onChange={location.change}
      rowKey={book => book.id} caption="Books"
      columns={[{ key: 'title', label: 'Title', sort: 'Title', cell: book => book.title }]}
      data={result?.query === query ? result.data : { kind: 'loading' }}>
      <TableSearch />
      <TablePageSize />
      <TablePagination />
      <TablePageSummary />
    </DataTable>
  );
}
```

The example assumes one authorized context; a protected host must also fence by principal/authority identity and drop old rows on retirement. Use `location.notice` to explain invalid URL options and `restoreQuery` to replace an out-of-range page after a count change. Search commits on submission and replaces history. Filters are page-owned, typed, allowlisted scalar values or lists of scalars; use `decodeFilters` and supply a typed `filterControls` component to build a filter control. The URL uses unpadded base64url JSON for filters. It is readable, not encrypted: keep credentials and other private input out of search, filters, callbacks, and history.

## Build and qualify a candidate

From the repository root, use `./bin/client setup` for the locked dependency installation, then `./bin/client package-build` to compile JavaScript, declarations, and CSS. `./bin/client pack-check` packs the result with npm and compiles a clean consumer against the extracted artifact, not the Agent OS dependency link. The package inventory and integrity are recorded in `.runs/build/reports/client/fight-react-pack.json`. Neither command publishes. The ordinary `./bin/build` gate runs these checks with the normal frontend build.
