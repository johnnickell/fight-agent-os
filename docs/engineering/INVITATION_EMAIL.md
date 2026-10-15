# Local invitation email

TASK-00040 maps the locked Access Control `CredentialDeliveryProvider` directly to generic
[`MailDelivery`](../../src/Application/Mail/MailDelivery.php). It sends invitations to a disposable Mailpit
catcher, **not a real inbox**. No mail vendor, scheduler, activation API or public activation UI is enrolled here.
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
APP_ACTIVATION_PATH=/activate
```

Also set `APP_CREDENTIAL_DELIVERY_KEY` to an independent random 32-byte hex key. This command fills only an
absent/empty setting without printing it; it refuses duplicates and preserves an existing key. Run it only in
the selected checkout. Losing/changing the key while work is outstanding makes existing encrypted work unreadable.

```sh
python3 - <<'PY'
from pathlib import Path
import os, secrets
path = Path('.env')
lines = path.read_text().splitlines() if path.exists() else []
name = 'APP_CREDENTIAL_DELIVERY_KEY='
positions = [i for i, line in enumerate(lines) if line.startswith(name)]
if len(positions) > 1:
    raise SystemExit('Resolve duplicate delivery-key settings privately before continuing.')
if not positions:
    lines.append(name + secrets.token_hex(32))
elif not lines[positions[0]][len(name):].strip().strip("'\""):
    lines[positions[0]] = name + secrets.token_hex(32)
else:
    raise SystemExit('Existing delivery key preserved; no update needed.')
path.write_text('\n'.join(lines) + '\n')
os.chmod(path, 0o600)
PY
```

Do not paste `.env`, `docker compose config`, messages, SMTP debug output or activation links into transcripts.
Compose consumes `APP_ENV` (default `development`); `APP_DEBUG` remains unwired. `null` is the default retryable
no-send provider; `memory` is simulation only. `catcher` supports **activation invitations only**. Other credential
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
configured `https://localhost:28443/activate` base and a `#credential=…` fragment, never a query credential. **Do not
click expecting activation:** this TASK does not implement the activation page, and port 28443 is only a canonical
link example, not an automatically provisioned HTTPS listener. Existing optional local HTTPS has separate setup
and ports; configure its actual canonical origin if using it. The catcher delivers only to this local mailbox,
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

The unchanged Composer lock selects Access Control **v0.5.0**, source/lock reference
`46ffcf75f1e14dbc5b58a5049e05a600ce5cf8a0`, and Common **v1.3.0**,
`7de6cad6e8a9752973ad9f8e27e285b0c1510582`. The public invocation provides only purpose, destination,
credential and stable delivery-generation ID: **no expiry timestamp, user ID or claim token**. The fixed
`invitation-v1` template states seven days **from issuance**, matching locked `InvitePendingUser`, rather than
inventing an exact expiry or seven days from arrival. An altered issuance lifetime requires a qualified upstream
contract/template change. The future activation consumer must accept this credential-only fragment or qualify
additional required routing data; no unknown package field is inferred here.

Canonical URL validation accepts lowercase ASCII DNS names (or `localhost`), HTTPS, an optional canonical numeric
port 1–65535, and a simple absolute path of alphanumeric/underscore/hyphen segments. It rejects userinfo, IP/alternate
numeric hosts, Unicode/uppercase/trailing-dot hosts, whitespace, query/fragment, percent escapes, dot segments,
backslashes and double/trailing path slashes (root `/` is allowed). No request headers or recipient input select
the origin/path. Credential bytes are RFC3986-encoded entirely inside the fragment. Fragment placement avoids
request-line/referrer transmission, **not** mailbox/history/extension access or arbitrary future UI leakage.

The deterministic alternatives omit the recipient from the body, escape the URL, include expiry/recovery and
non-forwarding guidance, and use no remote images/fonts/scripts or tracking. HTML declares `no-referrer` and a
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
