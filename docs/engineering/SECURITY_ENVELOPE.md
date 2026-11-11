# Browser security envelope

TASK-00063 integrates the existing [local HTTPS](LOCAL_HTTPS.md), [shared ingress](LOCAL_INGRESS.md),
[browser request controls](BROWSER_AUTH_REQUESTS.md) and journey-owned admission. It does not enroll a
production mail provider, deploy a production proxy or certify a production artifact. Independent acceptance
and outstanding host/browser evidence are recorded in the TASK, not implied by this policy.

The [source-verified runtime flow](security-envelope/README.md) shows startup and request ordering.

## Startup and transport

`bootstrap/app.php` resolves the shared `HttpSecurityConfiguration`, authentication admission, activation
admission and invitation rate policy before registering HTTP routes. Existing signing-keyring, CSRF,
password-platform/corpus and public-debug validation remain authoritative. The new cross-control validation
rejects missing/weak/malformed required keys and reuse among signing, CSRF, auth admission, activation admission
and delivery encryption (including case-insensitive hex equivalence). Local/test null delivery can omit its
unused cipher key; production-like HTTP cannot. HTTP boot contains all failures as a generic no-store 503,
without dumping/logging the exception, DSN or private input. Operator qualification must inspect configuration
privately; never enable server error dumps to diagnose startup.

Only exact `local`, `development` and `test` are development compositions. Every other environment requires
explicit `APP_AUTH_TRANSPORT=direct`, `APP_AUTH_TRUSTED_PROXIES=[]`, every configurable rate input, independent
delivery encryption and exact activation/reset origins and paths. Both mail origins must equal
`APP_BROWSER_ORIGIN`, with `/activate` and `/reset-password/complete`. `HmacCsrfProofs` validates and
normalizes bounded HTTPS origins (no userinfo, query, fragment or path). No runtime permission can select or
relax these settings. Cookies have compiled names, host-only scope, `/api/v1/auth`, Secure, HttpOnly and
SameSite=Strict; there are no environment overrides for unsafe cookie variants.

Production-like request dispatch additionally requires canonical direct HTTPS URI and original Host authority.
Forwarded, X-Forwarded and X-Original assertions never establish trust. Invalid authority/insecure traffic
receives 421 without redirects, cookies or Action dispatch. The production edge must terminate TLS and supply
canonical direct FastCGI authority, not promote arbitrary client forwarding headers. This is the already
qualified local gateway model, not permission to enroll a public proxy. The shared local edge redirects only
known-name HTTP navigation to its fixed HTTPS origin; application HTTP mutations remain rejected. Ordinary
local main retains its documented HTTP development root, which cannot authenticate or bootstrap cookies.

Production-like boot still **refuses all currently available delivery adapters**: there is no qualified real
provider. A valid-controls probe reaching that denial proves fail-closed composition, not a successful production
startup or production Swagger qualification. Do not substitute catcher/memory/null or bypass delivery preflight.

## Response and CORS policy

The outer `SecurityHeaders` middleware wraps successful responses, routing errors and contained failures.
The same CSP applies to every HTML/API route, including an explicitly eligible Swagger viewer:

```text
default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self';
img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'none';
form-action 'none'; frame-ancestors 'none'
```

Production assets are self-hosted hashed scripts/styles/fonts, including the prepaint script and bundled
Swagger. No nonce, mutable remote dependency, unsafe-inline or unsafe-eval exception is introduced. JSON public
boot configuration remains the narrow ADR-0004 contract, never raw environment hydration. A deployment that
needs additional resources must qualify that change rather than broaden this policy silently.

All application/API responses are `Cache-Control: no-store`, `Referrer-Policy: no-referrer`,
`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, and use the explicit camera/microphone/geolocation/
payment/USB deny Permissions Policy. COOP and CORP are `same-origin`. COEP is deliberately not enabled: no
approved cross-origin-isolation feature requires its additional resource/embedding contract. HSTS is one year,
without includeSubDomains/preload, only for production-like HTTPS and never localhost or its subdomains.
The local gateway extends isolation/referrer/framing headers to immutable assets and edge failures, suppresses
URL-bearing error logs and PHP's version header; content-hashed public assets retain immutable caching.

`SameOriginApi`, inside the sole API safe-error boundary, refuses foreign/null/malformed/multiple Origin,
cross-site or same-site Fetch Metadata and preflight negotiation headers with a safe 403. Same-site is not
same-origin. Absent Origin permits independently authenticated nonbrowser reads, never ambient-cookie mutation
authority; existing mutation/CSRF guards still require their exact Origin/JSON/proof contracts. Unsupported
OPTIONS without negotiation headers retains the existing 405 route behavior. No Access-Control response header
can escape the outer policy, including wildcard/reflected origins or credential grants. API authorization and
current permission checks remain independent.

## Tools and limits

Storybook is a separate explicit engineering container command, never an application route or runtime resource.
Catcher is private to the task Compose network (main's explicit UI is loopback); its provider is local-only and
cannot qualify production. No test route is added. Swagger retains its separately parsed exact environment set,
initially `["local"]`, current READ_SWAGGER on bootstrap/document and real endpoint authority for Try it out.
Debug neither enables Swagger nor weakens this envelope; exact prod/production plus true refuses startup.
No production artifact has been introduced by this TASK; TASK-00522 owns that delivery boundary.

| Admission owner | Typed bounded policy |
|---|---|
| `AuthAdmission` | Configured network 1–20 and endpoint 1–300 per 900s for login, refresh, logout, reset completion, password change; fixed five login attempts per canonical email / 900s |
| Reset request | Same bounded endpoint/network limits plus fixed three canonical-email attempts / 900s, consumed atomically |
| `ActivationAdmission` | Compiled 900s windows, network 10, endpoint 300, retained-key capacity 10000; independent MAC key |
| `InvitationRatePolicy` | Window 1–86400s, issue/retry/resend/correction 1–1000, status 1–10000, capacity 1–100000 |

HTTP startup resolves every policy, including recovery mutations, not only the first requested journey.
Production-like configuration cannot omit configurable limits; local defaults retain existing thresholds.
Compiled policies cannot be absent or supplied through untyped environment overrides. Journey owners retain
behavioral ownership and database transaction/admission semantics.

## Verify and operate

Use existing `bin/https setup/status/remove` and `bin/up` wrappers, with their explicit trust scope and platform
limitations. Never copy main keys/data into an isolated checkout. Reuse one task project across all roles.
Qualify startup/configuration and Nginx directly with their owning tools; product tests cover response,
transport, CORS and journey behavior, not wrapper/configuration text. Run focused tests then normal `bin/build`.
Use default-store curl and actual browser TLS without `--insecure`; a diagnostic `--connect-to` retaining
canonical Host/SNI can qualify supplier transport but **cannot** establish canonical port-443 browser readiness.
With an independently operated router, use the supplier's explicit [external front door](LOCAL_INGRESS.md#external-proxy-front-door):
no published supplier ports, unchanged TLS/SNI passthrough, and ordinary canonical port-443 readiness.
Refresh only the explicitly authorized two supplier services to install changed edge templates; full-test
installed Nginx and qualify its actual redirects/denials. Before retiring the installed-template source,
reconcile through supported setup/up from a surviving checkout with the integrated security templates.
Retain necessary source rather than silently reverting security policy; task runtime retirement is independent.
If actual host ownership differs from a supplied runbook/context, preserve the existing service and report the
specific missing front door. A sudo denial is not permission to restart it or invoke an unauthorized OS dialog.
Do not remove any leaf referenced by a live/pending include; Docker/source cleanup has its independent lifecycle.
