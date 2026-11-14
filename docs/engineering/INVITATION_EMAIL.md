# Local invitation email

TASK-00040 maps the locked Access Control `CredentialDeliveryProvider` directly to generic
[`MailDelivery`](../../src/Application/Mail/MailDelivery.php). It sends invitations to a disposable Mailpit
catcher, **not a real inbox**. No production mail vendor or scheduler is enrolled here. [TASK-00043 activation](ACTIVATION.md) now supplies
the public UI/API; this runbook retains TASK-00040's delivery-specific evidence separately.
Do not expose this development stack or its mailbox to untrusted users.

## Configure the selected checkout

This procedure is for one **registered linked checkout**, not main. Run from that checkout and keep the
exact selector for its entire lifecycle:

```sh
CHECKOUT="$(pwd -P)"
export FIGHT_AGENT_OS_USER="$(id -u):$(id -g)"
./bin/runtime --worktree "$CHECKOUT" inspect
```

Confirm the reported checkout and deterministic `fight-wt-…` project before continuing. Stop if the path is
not the intended registered worktree. Unselected `bin/up` and `bin/down` target **main**, even from here.
Follow [the runtime guide](PLANNING_RUNTIME.md) for explicit initial `./bin/runtime setup`, image/dependency
and browser asset preparation. Reuse an already prepared installation; do not generate a second stack.
Reuse its enrolled HTTPS name and existing supplier/certificate; hostname, trust and DNS changes are separate
operator operations. Mailpit has no browser/host port.

Before editing configuration or applying migrations on an already running installation, stop only its gateway:

```sh
./bin/runtime --worktree "$CHECKOUT" docker compose stop https
```

Worktree configuration comes from the canonical checkout's ignored `var/worktrees/<project>/task.env`,
not the linked checkout's `.env`. Privately edit only the intended installation's settings. For TASK-00072,
the enrolled origin is `https://task-00072-b46c674612.local.fight-agent.dev`; use these non-secret settings:

```dotenv
APP_ENV=development
APP_CREDENTIAL_DELIVERY_ADAPTER=catcher
APP_ACTIVATION_ORIGIN=https://task-00072-b46c674612.local.fight-agent.dev
APP_ACTIVATION_PATH=/activate
APP_PASSWORD_RESET_ORIGIN=https://task-00072-b46c674612.local.fight-agent.dev
APP_PASSWORD_RESET_PATH=/reset-password/complete
```

For another selected worktree, use its own verified enrolled origin consistently, including `APP_BROWSER_ORIGIN`.
Explicit runtime setup generates independent worktree keys, including `APP_CREDENTIAL_DELIVERY_KEY`, in
service-specific mounted secret files. Preserve existing keys; `.env` edits do not rotate those files.
Follow the runtime guide for deliberate rotation, and [access-token configuration](ACCESS_TOKENS.md) for the
keyring contract. Losing/changing the delivery key while work is outstanding makes encrypted work unreadable.

Do not paste `.env`, `task.env`, secret files, `docker compose config`, messages, SMTP debug output or activation links into transcripts.
Compose consumes `APP_ENV` (default `local`) and strictly parsed independent `APP_DEBUG`; see the
[documentation/debug contract](SWAGGER.md). `null` is the default retryable
no-send provider; `memory` is simulation only. `catcher` supports activation invitations and
[password-reset mail](PASSWORD_RESET_EMAIL.md). Catcher mode now also requires `APP_PASSWORD_RESET_ORIGIN` and
`APP_PASSWORD_RESET_PATH`; configure them before boot even when sending invitations only. Other credential
purposes remain retryable and unsent until their own mapping is implemented.

The owning TASK records historical delivery qualification. The procedure below uses the current selected
runtime, schema-before-traffic order and private Mailpit API.

## Start, send, run, inspect, clean

From that same checkout, after explicit runtime/dependency preparation and configuration above:

```sh
# Keep the selected gateway stopped until reviewed migrations have completed.
./bin/database start
./bin/database status
./bin/database migrate          # Apply only reviewed migrations to this selected worktree
./bin/up --worktree "$CHECKOUT"  # Checks schema currency before admitting HTTPS traffic
./bin/mail-catcher status
```

`database`, `mail-catcher`, `exec`, `console` and `credential-delivery` infer this linked checkout's project;
keep running them from here. `up/down` require the explicit selector. Startup never applies migrations or
rotates secrets. If migration or readiness fails, diagnose it before sending; do not substitute main or bypass
TLS verification. SMTP is fixed to private Compose
`mailcatcher:1025` with a five-second socket timeout; no host SMTP port, arbitrary DSN, relay or production fallback
is configured. Mailpit v1.27.10 is digest-pinned in Compose. Its database is a bounded tmpfs mailbox with at most
100 messages; it disappears when the container is removed and is not a delivery/audit store.

Generate an operator ID through the owning package factory. This is trusted local operator attribution, not
user authentication. Bootstrap is manual and single-operator: use it **only if no Super Admin exists**, following
the [bootstrap runbook](SUPER_ADMIN_BOOTSTRAP.md).

```sh
OPERATOR="$(./bin/exec php -r 'require "vendor/autoload.php"; echo Fight\AccessControl\Domain\AccessControl\User\UserId::generate()->toString();')"
./bin/console user:bootstrap-super-admin --internal --confirm-bootstrap --operator="$OPERATOR" --email=first@example.test
```

For an installation already reconciled by bootstrap, invite a **new** ordinary address instead:

```sh
./bin/console user:invite --internal --operator="$OPERATOR" --email=another@example.test
./bin/credential-delivery run --internal --page-size=100 --capacity=10
```

Immediate postcommit delivery normally precedes role assignment; the runner can therefore find no work after
successful issuance. If the catcher was offline, start it, wait for the package's due time (first retry: one minute),
then run the same bounded runner again. A fresh process discovers the retained generation without reinviting.
Use `./bin/credential-delivery status activation DELIVERY_ID --internal` with a known safe generation ID; see
[recovery status/exit codes](CREDENTIAL_RECOVERY.md). Console success does not prove mail arrival or activation.
There is no scheduler: later due retries require another explicit runner invocation.

Inspect Mailpit through trusted tooling on the selected private network. Run these only in a private operator
terminal with recording/capture disabled; responses contain credentials and must not enter transcripts or logs:

```sh
./bin/runtime --worktree "$CHECKOUT" docker compose exec -T web curl --fail --silent --show-error http://mailcatcher:8025/api/v1/messages
# Substitute the selected message ID from that private response:
./bin/runtime --worktree "$CHECKOUT" docker compose exec -T web curl --fail --silent --show-error http://mailcatcher:8025/api/v1/message/MESSAGE_ID
```

Inspect the returned text and HTML alternatives transiently. For TASK-00072 the link base must be
`https://task-00072-b46c674612.local.fight-agent.dev/activate`, with fragment-only credential and user routing
fields, never a query credential. Another installation must match its own configured canonical origin/path;
composition rejects a different origin/path. See the activation runbook for capture, history, request and
session behavior. The catcher delivers only to this private mailbox regardless of recipient address; it cannot
deliver to Gmail or another real inbox. Do not publish a mailbox port or add an ingress route to inspect it.

The mailbox intentionally holds activation material. Treat every body, source, screenshot and API response as
sensitive. Inspect transiently; retain only redacted/boolean verification, not full links, fragments or MIME.
Do not enable SMTP message logging, provider event subscribers, analytics or captures containing credentials.

```sh
./bin/mail-catcher clear          # Destroys/recreates only this selected project's mailbox
./bin/mail-catcher down           # Removes only this selected project's catcher and activation material
# Only after all assessors/other consumers have finished with this disposable installation:
./bin/down --worktree "$CHECKOUT" # Destroys task services AND disposable PostgreSQL/SeaweedFS volumes
```

Clearing the mailbox does not resend delivered work, revoke grants or clear PostgreSQL. Do not reinvite an existing
address as a resend workaround. Preserve the delivery key while any retained work is live. Mailbox cleanup alone
does not authorize task teardown: selected worktree down deletes its disposable database and storage, not merely
stops them. To retain state for subsequent roles, omit down and use the runtime guide's selected service stop
commands if needed. Never use unselected down as a substitute. Other Compose projects must remain untouched.

## Contracts and limits

TASK-00040 originally qualified Access Control **v0.5.0** (`46ffcf75f1e14dbc5b58a5049e05a600ce5cf8a0`).
The current unchanged Composer lock selects Access Control **v0.6.0**, source/lock reference
`dc4621d13851dd08cdf85ed9715ead24b1051e80`, and Common **v1.3.0**,
`7de6cad6e8a9752973ad9f8e27e285b0c1510582`. The public invocation provides only purpose, destination,
credential and stable delivery-generation ID: **no expiry timestamp, user ID or claim token**. The fixed
`invitation-v1` template states seven days **from issuance**, matching locked `InvitePendingUser`, rather than
inventing an exact expiry or seven days from arrival. An altered issuance lifetime requires a qualified upstream
contract/template change. TASK-00043 has now qualified the current release's supported delivery-ID lookup to obtain the owning user ID
and include it in new fragment links. No unknown invocation field or credential encoding is inferred. Older
credential-only messages require an explicit administrative resend; the activation runbook records this boundary.

Shared `CanonicalCredentialLink` URL validation accepts lowercase ASCII DNS names (or `localhost`), HTTPS, an optional canonical numeric
port 1–65535, and a simple absolute path of alphanumeric/underscore/hyphen segments. It rejects userinfo, IP/alternate
numeric hosts, Unicode/uppercase/trailing-dot hosts, whitespace, query/fragment, percent escapes, dot segments,
backslashes and double/trailing path slashes (root `/` is allowed). No request headers or recipient input select
the origin/path. Credential bytes and the qualified user routing ID are RFC3986-encoded entirely inside the fragment. Fragment placement avoids
request-line/referrer transmission, **not** mailbox/history/extension access or arbitrary future UI leakage.

The fixed `templates/mail/invitation.txt.twig` and `templates/mail/invitation.html.twig` alternatives use the
existing shared Fight Common `TemplateEngine`/Twig registration. The provider builds the canonical URL and
assembles `MailMessage` in PHP; only the transient activation URL enters render context, never the invocation or
recipient. Plain text preserves the already URL-encoded link; HTML applies HTML escaping. Render failures send
nothing and return a safe retryable outcome without logging or retaining the exception/context. Templates are
fixed by composition, never selected from persisted work; Twig debug and template caching remain disabled.

The deterministic alternatives omit the recipient from the body, include expiry/recovery and non-forwarding
guidance, and use no remote images/fonts/scripts or tracking. HTML declares `no-referrer` and a
restrictive CSP; MIME adds automatic-response suppression and `Referrer-Policy`. Mail clients need not enforce
these headers. The fixed sender is `invitations@example.test`, not a verified production sender.

The package alone owns grant material, claims, retry/backoff, leases, outcome/audit writes and stale-worker fencing.
Origin and claim commits precede SMTP; the outcome commits afterward. No schema, intent, outbox, command alias or
consumer retry policy is added. The mail capability returns only delivered/retryable/permanent results. This local
SMTP adapter conservatively treats failures/rejections as retryable and suppresses arbitrary exception diagnostics.
Delivery means SMTP acceptance by the catcher, not inbox placement or account activation.

Each attempt preserves the generation ID as the MIME Message-ID. **Message-ID is not provider deduplication.**
Mailpit can accept duplicates; a success followed by lost outcome commit can send again after lease recovery.
At-least-once attempts, not exactly-once mail, are the guarantee. Templates/messages exist only within invocation;
message serialization is refused and ordinary dumps redact content. SMTP sessions/debug buffers are cleared on
success/failure, with no logger or message-event subscriber. PHP/Symfony may make temporary string copies:
zeroization, swap/core-dump protection and physical erasure of backups/WAL are not guaranteed.

Production-like HTTP startup resolves secure delivery composition and refuses missing/weak/malformed delivery
keys, invalid canonical URLs, and the absence of a real provider. Every currently offered adapter is refused there;
setting `catcher` or `memory` never bypasses that guard. The CLI runner validates before cleanup/mutation;
key-free `status`, `expire`, unrelated Console list/help remain available without resolving the provider. This is
fail-closed composition, not production enrollment or deployment certification. `./bin/build` and independent
Review/QA remain separate. PHPUnit forces the default adapter to `null`, so developer catcher configuration cannot
cause deterministic suites to send synthetic mail; explicit test doubles exercise MIME and mapping without sockets.
