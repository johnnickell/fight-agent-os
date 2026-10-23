# Local invitation email

TASK-00040 maps the locked Access Control `CredentialDeliveryProvider` directly to generic
[`MailDelivery`](../../src/Application/Mail/MailDelivery.php). It sends invitations to a disposable Mailpit
catcher, **not a real inbox**. No production mail vendor or scheduler is enrolled here. [TASK-00043 activation](ACTIVATION.md) now supplies
the public UI/API; this runbook retains TASK-00040's delivery-specific evidence separately.
Do not expose this development stack or its mailbox to untrusted users.

## Configure the selected checkout

Use a private ignored `.env` in the intended checkout, never another worktree's secrets or database. Install
locked dependencies and prepare browser/CSRF/access-token configuration using [README](../../README.md) and
[access-token configuration](ACCESS_TOKENS.md). Keep independent cryptographically generated keys for each purpose.
For an isolated TASK-00040 checkout, set these exact non-secret values (choose other unused ports/project names
for another concurrent checkout):

```dotenv
COMPOSE_PROJECT_NAME=task-00040-invitation-email
FIGHT_AGENT_OS_PORT=28040
FIGHT_AGENT_OS_MAIL_PORT=28041
APP_ENV=development
APP_CREDENTIAL_DELIVERY_ADAPTER=catcher
APP_ACTIVATION_ORIGIN=https://localhost:28443
APP_ACTIVATION_PATH=/app/activate
```

Also set `APP_CREDENTIAL_DELIVERY_KEY` to an independent random 32-byte hex key. This command fills only an
absent or literally empty setting (unquoted, `''` or `""`) without printing it. It recognizes single-line whitespace and
`export` assignments, preserves nonempty values and all unrelated bytes, and refuses duplicates before writing.
It deliberately refuses multiline, escaped, interpolated or otherwise unsupported syntax anywhere in `.env`;
resolve such input privately, preserving any existing effective key, rather than deleting it to bypass refusal.
Preservation does not validate an existing key's strength. Run only in the selected checkout, with no concurrent
`.env` edits. Losing/changing the key while work is outstanding makes existing encrypted work unreadable.

```sh
python3 - <<'PY'
from pathlib import Path
import os, re, secrets
path = Path('.env')
if path.is_symlink():
    raise SystemExit('Refusing a symlink; .env unchanged.')
data = path.read_bytes() if path.exists() else b''
if b'\r' in data.replace(b'\r\n', b''):
    raise SystemExit('Unsupported line endings; .env unchanged.')
name = b'APP_CREDENTIAL_DELIVERY_KEY'
# A deliberately limited single-line Compose subset; never guess about quoting/interpolation.
assignment = re.compile(
    rb'''[ \t]*(?:export[ \t]+)?([A-Za-z_][A-Za-z0-9_]*)[ \t]*=[ \t]*?'''
    rb'''([^ \t\r\n'"\\$#]*|'[^'\\$\r\n]*'|"[^"\\$\r\n]*")(?:[ \t]+\#.*)?[ \t]*'''
)
positions = []
offset = 0
for line in data.splitlines(keepends=True):
    body = line.removesuffix(b'\n').removesuffix(b'\r')
    if body.strip(b' \t') and not body.lstrip(b' \t').startswith(b'#'):
        match = assignment.fullmatch(body)
        if not match:
            raise SystemExit('Unsupported .env syntax; file unchanged. Resolve privately.')
        if match[1] == name:
            positions.append((offset + match.start(2), offset + match.end(2), match[2]))
    offset += len(line)
if len(positions) > 1:
    raise SystemExit('Duplicate delivery-key settings; file unchanged. Resolve privately.')
if positions and positions[0][2] not in (b'', b"''", b'""'):
    raise SystemExit('Existing delivery key preserved; no update needed.')
key = secrets.token_hex(32).encode('ascii')
if positions:
    start, end, _ = positions[0]
    updated = data[:start] + key + data[end:]
else:
    newline = b'\r\n' if b'\r\n' in data else b'\n'
    separator = newline if data and not data.endswith(b'\n') else b''
    updated = data + separator + name + b'=' + key + newline
os.umask(0o077)
path.write_bytes(updated)
os.chmod(path, 0o600)
PY
```

Do not paste `.env`, `docker compose config`, messages, SMTP debug output or activation links into transcripts.
Compose consumes `APP_ENV` (default `local`) and strictly parsed independent `APP_DEBUG`; see the
[documentation/debug contract](SWAGGER.md). `null` is the default retryable
no-send provider; `memory` is simulation only. `catcher` supports activation invitations and
[password-reset mail](PASSWORD_RESET_EMAIL.md). Catcher mode now also requires `APP_PASSWORD_RESET_ORIGIN` and
`APP_PASSWORD_RESET_PATH`; configure them before boot even when sending invitations only. Other credential
purposes remain retryable and unsent until their own mapping is implemented.

## Start, send, run, inspect, clean

From that same checkout, after the ordinary explicit dependency setup:

```sh
export FIGHT_AGENT_OS_USER="$(id -u):$(id -g)"
./bin/mail-catcher up
./bin/up                         # Applies changed .env to this project's web service
./bin/database migrate          # Only the selected isolated development database
./bin/mail-catcher status
```

The catcher UI/API is **http://127.0.0.1:28041** for the settings above. SMTP is fixed to private Compose
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

Open the catcher privately and inspect the invitation's text and HTML alternatives. The link should have the
configured `https://localhost:28443/app/activate` base and fragment-only credential and user routing fields,
never a query credential. Port 28443 remains only an isolated configuration example, not an automatically
provisioned HTTPS listener. Configure the actual trusted browser origin and `/app/activate` before enabling
catcher delivery; composition rejects a different origin/path. See the activation runbook for current capture,
history, request and session behavior. Existing optional local HTTPS has separate setup and ports. The catcher delivers only to this local mailbox,
regardless of the recipient address; it cannot deliver to Gmail or another real inbox.

The mailbox intentionally holds activation material. Treat every body, source, screenshot and API response as
sensitive. Inspect transiently; retain only redacted/boolean verification, not full links, fragments or MIME.
Do not enable SMTP message logging, provider event subscribers, analytics or captures containing credentials.

```sh
./bin/mail-catcher clear          # Destroys/recreates only this project's mailbox
./bin/mail-catcher down           # Removes only this project's catcher and its activation material
./bin/down                       # Stops this project's application/DB services; preserves its named dev DB
```

Clearing the mailbox does not resend delivered work, revoke grants or clear PostgreSQL. Do not reinvite an existing
address as a resend workaround. Preserve the delivery key while any retained work is live. Deleting a project DB
is a separate destructive decision, never part of mailbox cleanup. Other Compose projects are untouched.

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
