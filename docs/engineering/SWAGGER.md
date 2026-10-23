# Private API documentation

TASK-00154 implements `/app/swagger` within the existing application session and frame. Engineer verification
is not independent Review/QA acceptance or deployment certification. The generated contract remains private.

## Enable deliberately

Use the selected checkout's ordinary dependency, database, [HTTPS](LOCAL_HTTPS.md),
[access-token](ACCESS_TOKENS.md) and [session](SESSION_CONTINUITY.md) setup. Export the complete contract with
`./bin/openapi export`, check it with `./bin/openapi check`, and publish inspected browser assets with
`./bin/client build`. Generated JSON stays at `var/openapi/openapi.json`, outside public/static roots.

The checked-in example starts with:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_SWAGGER_ALLOWED_ENVIRONMENTS='["local"]'
```

The operator edits their own ignored `.env`. No migration/updater rewrites it. Existing `development` is not an
alias for `local`: deliberately change the environment and refresh both HTTP/TLS services after reviewing
all existing environment-specific controls. Compose defaults APP_ENV to `local`, diagnostics to false and the
allowlist to absent/disabled. Copying the example is not implicit permission or a deployed security assessment.
`APP_ENV=test` retains its independent destructive-test database guards.

APP_DEBUG accepts only exact `true` or `false`; absence means false. Other spellings fail composition with a
fixed non-sensitive error. True in exact `prod` or `production` refuses startup. The boolean is independent of
Swagger: even deliberately enabled non-local diagnostics emit only three closed documentation event names,
not exceptions, responses, submitted values, identities or credentials. Use false for staging/production.
Changing a public flag in browser tools cannot change server authorization.

The allowlist is a JSON list of at most 32 unique exact environment names, each matching
`[a-z][a-z0-9_-]{0,31}`. `[]` disables documentation everywhere. Missing/malformed lists, duplicate names,
wildcards and invalid/unset/unlisted environments disable both reads. A deliberate `test`, staging, `prod` or
`production` entry changes eligibility only; it does not weaken authentication, endpoint policy, CSRF, TLS,
CORS, delivery preflight or any other developer tool's restrictions. Unavailable responses are bounded; no
rejected operator values are echoed.

**Acceptance split approved by the maintainer:** full local HTTP/HTTPS/browser qualification uses an isolated
disposable email catcher. Controlled integration composition separately qualifies environment/current-permission
policy, including opted-in staging/production. Complete production-like HTTP startup/deployment qualification
is deferred until the independent real-mail supplier exists. The shipped production mail preflight still refuses
all available adapters; no real email is enabled or authorized. Policy tests are not full production startup proof.

## Entry, direct reads and deployment

Sign in normally, then open API documentation in Administration or navigate to `/app/swagger`. Navigation/reload
obtains credentials through the same cookie-backed bounded restoration and fresh `/me`, never a token URL or
HTML workaround. The public shell contains neither document nor protected viewer initialization.

Both internal reads traverse the existing matched-Action Bearer/current-authority middleware, separately require
`READ_SWAGGER`, then check configured eligibility:

| Resource | Representation |
|---|---|
| `GET /api/v1/documentation/bootstrap` | No-store JSend success with viewer protocol version and fixed private document path |
| `GET /api/v1/documentation/openapi` | Complete raw OpenAPI 3.0.3 JSON, not a JSend success wrapper |

Both reject anonymous, expired/stale/revoked and unavailable authority. A valid principal without the exact
permission receives 403, not a role-name bypass. Unlisted eligibility returns 404 after authentication. These
are internal viewer resources, not public validation metadata. The generic shell may still serve HTTP 200;
that shell response is not the protected viewer response.

Direct browser navigation does not supply a Bearer token and cannot retrieve either protected response.
The spec read has no arbitrary path/ref parameter, public alias, CORS, cache or download exception. Missing,
malformed, oversized, linked or externally referencing artifacts fail safely; unavailable document data returns
503 with a fixed JSend error. The reader does not generate/reflect attributes or install development tools at
request time. Export/validation/freshness and packaging remain the [OpenAPI tools'](../api/README.md) responsibility.

Publish server and browser schema **version 2 atomically**. The escaped inert boot block contains exactly
`schema_version`, fixed `api_base_path`, `app_debug` and `swagger_available`; the latter is eligibility, not a
grant. Every producer/decoder/fixture uses version 2. Version 1, missing/extra fields and unsafe API locations
fail boot rather than falling back permissively. Keep complete releases/old hashed assets as documented in
[client delivery](../../client/README.md#serving-and-deployment-boundary). No source map, spec or environment dump
is published under `/build/`.

## Explicit execution matrix

The complete private document is viewable. Live execution is deliberately narrower:

| Operation | Live support | Boundary |
|---|---|---|
| `GET /api/v1/me` | Yes | Existing strict safe principal codec; latest memory Bearer |
| Role/Permission list and detail GETs | Yes | Existing catalog codecs, exact IDs and closed bounded numeric pagination |
| `POST /api/v1/roles` | Yes | Explicit JSON containing only a valid Role `name`; real CREATE_ROLES server policy |
| Login, activation, CSRF, refresh, logout | Documentation only | Credential/proof/cookie outputs and lifecycle remain with existing auth owners |
| Invitation issuance/status/recovery | Documentation only | Delivery/confirmation/target and response-state handling not qualified here |
| Role rename/removal | Documentation only | Displayed revision/confirmation intent not qualified by a generic JSON editor |
| Repository/Workspace/reference operations | Documentation only | Target authority, complex descriptors and operation-specific state not qualified here |
| Every other method/path | No | Denied before credential selection or transport |

READ_SWAGGER grants documentation access, never another operation's authority. Execute is a real operation, not
a harmless preview. Creation sends once, then retires the viewer and refetches authority even on uncertainty;
check the catalog before explicitly retrying. Cancellation/sign-out cannot undo a server commit. No business
mutation is automatically replayed after 401/403/refresh/network failure or remount.

Swagger's `fn.fetch` selects these existing fixed-path feature transports. It ignores library headers and rejects
foreign/alternate origins, userinfo, encoded/ambiguous paths, unsupported queries, remote refs and redirects
before credentials can be added. The shared ApiClient alone selects the newest token immediately before fetch,
with same-origin credentials/mode, no-store, no-referrer and redirect rejection. Bearer Role creation is not a
cookie-authorized mutation. Cookie/CSRF operations remain unsupported here; the viewer introduces no proof
store, refresh loop, token form, token issuer or lifetime change. A current 401 enters only the existing bounded
coordinator and never replays the operation; 403 invalidates/refetches current authority once.

Do not attach credentials through `requestInterceptor`: the qualified upstream library retains its returned
request in Redux. Tokens stay below that boundary. Request-state snapshots contain no URL/header/body payload;
Swagger authorization actions/stores, curl/request snippets, clipboard and download controls are disabled.
Responses are reconstructed solely from existing safe codecs; raw headers, Response/error objects and credential
responses never reach the renderer. User-entered Role names are volatile form state, not persisted drafts.

Authority/credential/context invalidation aborts and fences loads/operations. Late success and failure cannot
restore the view or affect a newer context. On teardown the host is hidden immediately, child subscribers are
unmounted through a supported React wrapper, then the entire library store/config document is erased and late
dispatches ignored. Erasing Redux before unmounting children caused upstream selector TypeErrors in earlier
unaccepted probes; that ordering is not used. This is application memory retirement, not a claim to erase a
browser debugger's external snapshots or a server effect already committed.

## Presentation and dependency qualification

Swagger UI distribution **5.34.0** is a locked runtime dependency (Apache-2.0). It is self-contained, so its
internal React renderer is not mixed with the application's React 19 renderer. No vendor source is patched.
The supported plugin surface supplies a small embedded layout using existing operation/model/filter components,
a named JSON editor, plain-text descriptions, transport/state hooks and lifecycle wrapper. It avoids a nested
`main`, duplicate h1, unlabelled alternate-server picker, raw error pane and unsafe export controls.

Groups and operations start collapsed (`docExpansion: none`) on each mount. Search, expansion and theme
changes execute nothing. The current generated contract has no authored tags, so upstream groups its operations
under `default`; this is not evidence of multiple authored tag groups. The production-backed catalog separately
checks named groups. No expansion state or second theme preference is stored. Scoped styles consume the shared
semantic tokens for controls, status, code, responses, focus and narrow layout; they do not affect ordinary forms.

Qualification is for exact installed npm bytes, not every upstream build or future patch:

- Bundle SHA-256: `3f6abf70480db7e07914df4c98a77385f22ea585eaf457a3e6415ba0eae2d167`.
- Stylesheet SHA-256: `c3cfe8248c4b0b89673600fb829924eafbac6de4bc4b2b06f5f5243483074d0a`.
- npm publication metadata identifies gitHead `a3d333d7e99611288312d597c4023ee10df721e0`; the bundle's own
  version receipt reports `gfa20f4dc`, **gitDirty true**. Do not equate publisher metadata with a pristine source
  checkout. The committed npm integrity and inspected installed-byte hashes identify the qualified artifact.

Artifact inspection requires these exact vendor bytes before admitting their built-in grammar/schema/diagnostic
URLs, disabled validator/social-link defaults and standard upstream UUID example. These are not application
identities or permission to request those destinations. Authored source is inspected separately without vendor
literal exceptions. Secret signatures, private environment access, source maps, external imports and runtime
input-graph restrictions remain enforced. CSS permits only self-contained screened SVG and four exact embedded
raster hashes, not arbitrary images/URLs. Existing install ignores lifecycle scripts, including the transitive
Scarf install telemetry hook; it is not part of the browser graph. CSP remains self-only, with no new unsafe-inline,
eval, remote resource or frame exception.

## Reproduce and assess

Run focused PHP environment/HTTP/OpenAPI/security and client tests, `./bin/client check`, owning Compose/TLS tools,
planning refresh/read-only/whitespace and the full `./bin/build`. Inspect real light/dark, narrow/wide,
collapsed/expanded/input/response/focus, denied/direct reads and authority expiry/late-response behavior. Test only
disposable isolated data; never capture filled passwords, credential responses, mailbox bodies or token-bearing
requests. The isolated catcher is not a production provider.

TASK-00154's Engineer evidence is retained under `.runs/tasks/TASK-00154/runs/xdebug-swagger/` in the selected
checkout. Its task-owned `run.sh` supplies the isolated project, ports and private environment file; runtime
fixtures, leaves, browser profile/trust and generated assets stay under ignored `var/`. Reuse the retained private
fixture file only locally; do not paste it into reports. The handoff records exact receipts, warnings, host-browser
trust provenance, visual inspection limits and service cleanup. Native browser evidence and synthetic catalog
checks are distinct. Independent Review/QA must assess the committed candidate; none of these checks certifies
all browsers, physical assistive technology, production ingress or universal accessibility.
