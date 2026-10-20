# Public invitation activation

TASK-00043 implements `/app/activate` and `POST /api/v1/auth/activate`. This is invitation possession,
not registration, login, refresh, logout, a `/me` client or a dashboard. Independent acceptance and deployment
remain separate from implementation. Use the existing [invitation recovery API](INVITATION_API.md) to resend
an invitation; do not reinvite an existing identity or choose its password as a recovery shortcut.

## Link and transient browser ownership

Configure `APP_ACTIVATION_ORIGIN` to the exact trusted HTTPS browser origin and `APP_ACTIVATION_PATH=/app/activate`.
The mail adapter now resolves the invocation's stable delivery ID through the installed package's
`ActivationGrantRepository::getByDeliveryId()` and adds the owning user ID **inside the fragment** alongside
`credential`. It does not infer identity from credential bytes or reproduce grant validation. Both fields are
required; the builder no longer offers credential-only links. All configured invitation credentials are independent
256-bit lowercase hex values. Query parameters, remote resources and analytics are not part of the journey.

Previously delivered credential-only links cannot supply the identity required by the package activation API.
They receive the same safe recovery page as other unusable links; an administrator must explicitly resend to
obtain the qualified link shape. There was no prior activation UI. No public credential-to-account search,
account enumeration endpoint, plaintext lookup index or consumer activation policy is added.

The blocking same-origin head script captures only the two bounded expected fragment fields and immediately
replaces the **whole** URL and history state with `/app/activate` and `null`, before theme storage access,
React boot, schema fetch or normal rendering. A symbol-keyed, single-use closure hands material to a private
in-memory owner; the root deletes that handoff. Page hide synchronously retires/unmounts the password form as
well as clearing owners, before a browser can freeze the document in back/forward cache. Teardown also clears
owners. Later same-document fragment navigations are stripped and retire the form; they are never adopted as
replacement authority. Open such an invitation in a new tab/document. The owned skip link remains usable without
retiring the invitation. Reload, direct navigation and history traversal cannot restore a removed grant. Failure to replace history denies possession intake.
The existing HTML/no-referrer/CSP/no-store boundary remains in place. There are no remote fonts or services.

Passwords support Unicode/spaces, paste and password managers, without HTML code-unit length or composition
filters. The real `invitation_activation` PHP schema is anonymously read and decoded before the shared Formik
adapter enables the form. Its explicit public field map contains only password and confirmation constraints;
identity, credential, grant matching and compromised-password internals are not exported. PHP rule arguments
are **lists of strings**, not the synthetic string representation formerly expected by the client. The decoder
now verifies list arity/types and translates only the supported rule subset. Password guidance comes from the
shared PHP policy's deliberately published safe message. All password-policy rejections use that same approved
`body.password` message list; no private enum or arbitrary server text is displayed. The server independently
runs `PasswordSetting` before package activation.

Current field and dependency generations invalidate errors after edits, A→B→A, schema replacement/reset and
unmount. Unchanged neighboring errors survive. Obsolete non-field rejections are also discarded after edits.
An observed mutation success is never discarded merely because the user edited or reset a form. Success and
terminal invalid-link outcomes remove the password form and clear transient grant ownership. Retryable policy,
throttle, metadata and system failures retain only volatile state; there is no automatic mutation retry.

## Request, admission and response contract

The JSON object requires exactly `user_id`, `credential`, `password`, `password_confirmation`; unknown fields,
remember options, duplicate keys, malformed UTF-8/JSON, wrong types/sources and oversized bodies are refused.
The existing 65,536-byte JSON-source limit applies. Credentials are bounded to 1–512 characters, passwords and
confirmation to 8–128 Unicode code points, with exact confirmation equality. Package IDs use the owning factory.

Activation is an explicit JWT-exempt Action, not a blanket public path exemption. Before activation it requires:

- exact configured HTTPS URI authority and `Origin`; no absent/null/foreign/ambiguous Origin;
- `Sec-Fetch-Site: same-origin` when supplied; absent Fetch Metadata still needs Origin and CSRF;
- JSON content type, no query/body CSRF alternative, no Authorization or method override;
- exactly one bounded CSRF nonce cookie and `X-CSRF-Proof`, verified by the existing transport-free MAC verifier.

Configure an independent external random 32-byte hex `APP_ACTIVATION_RATE_KEY`. Invalid encoding and fewer
than 16 distinct decoded bytes are refused; this rejects obvious weak patterns, not all low-entropy choices.
The HTTP entrypoint also requires `zend.exception_ignore_args=1` before application boot, so credential-bearing
request objects and transactional closures cannot enter exception arguments. This is a process-local setting;
unavailable capability returns a generic no-store 503 before request construction, not a weaker fallback.
Production admission uses the
shared PostgreSQL connection in its **own committed transaction before activation**, and refuses an enclosing
transaction. One fixed 900-second window admits at most 10 attempts per trusted `REMOTE_ADDR` and 300 endpoint
attempts globally. Address bytes and endpoint purpose are HMACed with distinct prefixes; raw addresses, emails,
passwords and grants are never storage keys or diagnostics. A transaction-scoped advisory lock atomically checks
both budgets and a 10,000-row capacity. Expired rows are removed; saturation returns bounded `Retry-After` and
storage failure denies. Counters survive subsequent activation rollback. Untrusted forwarding headers are never
network identity. A real ingress must enroll its trusted network boundary separately; the local Nginx profile
supplies peer address and discards forwarding metadata. These limits are an initial bounded policy, not a DDoS
or distributed-ingress qualification.

`ActivateInvitation` owns only password acceptance. The installed `AuthenticationService::activate()` owns latest
purpose-bound grant matching/consumption, pending-user activation, Argon2id hashing, authority revision, ordinary
refresh session/token creation and post-commit `UserActivated`. No repository mutation shortcut is used.
Malformed/unknown/used/expired/revoked/replaced/raced authority maps to generic `409 Conflict.` without account
state. Unexpected infrastructure failure discards its cause and returns a sanitized `500`; its outcome must not
be assumed rolled back if post-commit event delivery failed. Retry/inspection is explicit, not an automatic replay.

Success returns no-store JSend data containing only `access_token`, `issued_at`, `expires_at` (UTC seconds,
900-second difference, no later expiry than the JWT). The typed feature service places access only in the
shared `AccessTokenProvider` memory seam; it never puts a token in React page state, browser storage or an intent.
The ordinary token seam expires conservatively from monotonic request-start time. No principal is fabricated.

Refresh appears **only** as `__Secure-agent_os_refresh`, host-only, `Path=/api/v1/auth`, `Secure`, `HttpOnly`,
`SameSite=Strict`, with **no** `Expires` or `Max-Age`. Server lifetime is one-day idle/two-day absolute. Activation
has no remembered option. The CSRF nonce rotates on success, so a future mutation must bootstrap a new proof.
The server response never includes refresh material in JSON. `SessionAccessData` does not retain the refresh
TokenSet, and wraps its temporary access string for redacted PHP diagnostics.

## Qualified package and future consumers

The unchanged Composer lock selects Access Control **0.6.0**,
`dc4621d13851dd08cdf85ed9715ead24b1051e80`, and Common **1.3.0**,
`7de6cad6e8a9752973ad9f8e27e285b0c1510582`. The available tagged release and actual installed APIs were inspected;
version numbers alone are not acceptance. Shared repositories and Doctrine transaction composition are retained.
Controlled real PostgreSQL duplicate activation, activation/resend/correction winner orders, rollback at grant,
user and session writes, and independently committed admission are exercised with bounded barriers, not sleeps.

TICKET-00017 should reuse `BrowserMutationGuard`, the configured package `AuthenticationService`/token policy,
`SessionResponder`, `SessionAccessData`, `ApiClient.post()` and `MemoryAccessToken`. The registered login throttle
now uses TASK-00045's independently committed rolling email admission; see
[browser request controls](BROWSER_AUTH_REQUESTS.md). Shared cookie/session responders support remembered
responses and matching expiry, and [canonical login](LOGIN.md) now consumes them; refresh/logout remain unregistered. Cross-tab coordination,
restoration, authority projection and scheduling still need their owning implementations and tests. TICKET-00018
must load real `/me` authority before any protected landing; activation currently presents a bounded truthful
success page with a link to the application foundation, not an invented dashboard.

## Local reproduction and evidence safety

Use [explicit setup](BUILD.md), [local mail](INVITATION_EMAIL.md), and [local TLS](LOCAL_HTTPS.md) in an isolated
Compose project with unused loopback ports, private development/test data and independent disposable keys.
Generate the migration with the owning Doctrine tool and migrate only the selected project. Export both private
OpenAPI and validation artifacts after source changes, then build the browser assets before opening the link.
The Engineer's ignored task handoff records its actual override, ports, certificate verification and runtime
ownership; it is execution evidence, never an application dependency. Do not run the fixed-port TLS setup over
another project's stack or change global trust to reproduce task-local checks.

Never copy an invitation URL, message body, cookie, password, token, error object, browser trace or storage dump
into evidence. Inspect catcher messages transiently. Capture only blank forms, empty validation and cleared
terminal states after history removal; real passwords are never captured. Catalog examples use synthetic public
dictionary text, not installation passwords or live credentials. Keep traces/videos/DOM snapshots off for live
activation. The Engineer's controlled traversal used reload rather than an observed back/forward-cache restoration;
synchronous pagehide retirement is separately regression-tested. Do not label that observation a frozen-document
resume qualification. Automated axe, screenshots and scripted keyboard/reflow checks are bounded evidence, not universal
WCAG, physical-device, assistive-technology, password-manager or production-trust certification.
