# Local password-reset email

TASK-00053 maps Access Control's existing direct reset handler to the shared Twig/`TemplateEngine` and
`MailDelivery` capability. The sole durable work remains the package reset generation. There is no consumer
queue, completion HTTP/UI, production mail vendor or scheduler enrollment. TASK-00054 now adds the
[generic request journey](PASSWORD_RESET_REQUEST.md); its reset delivery is asynchronous through the existing
runner, not immediate SMTP in the public request.

## Configure and operate

Follow [local invitation email](INVITATION_EMAIL.md) for independent keys, isolated Compose project/loopback
ports, locked dependency setup, catcher startup, private inspection and mailbox cleanup. In the selected
checkout's private ignored `.env`, catcher mode now requires **both** activation and reset canonical settings:

```dotenv
APP_PASSWORD_RESET_ORIGIN=https://localhost:18443
APP_PASSWORD_RESET_PATH=/reset-password
```

Use the actual trusted HTTPS listener if one is separately configured; these values do not provision a listener
or completion page. No request Host/forwarded header, destination or persisted value chooses origin/path.
`CanonicalCredentialLink` shares the existing narrow HTTPS grammar with invitation links: no userinfo, query,
fragment, IP/ambiguous host, encoded/dot path segments or insecure local exception. Invalid reset configuration
fails catcher-enabled HTTP boot and delivery preflight before work mutation. Production-like startup additionally
refuses every currently available provider; catcher/memory/null never substitute for a real enrolled vendor.

Apply changed `.env` with `./bin/up`, then use the existing bounded internal operations:

```sh
./bin/credential-delivery run --internal --page-size=100 --capacity=10
./bin/credential-delivery status password_reset DELIVERY_ID --internal
./bin/credential-delivery expire --internal
```

Replace `DELIVERY_ID` with the known package-generated generation ID, not a UserId. The runner recovers committed
work even when immediate event handling was lost. Handler return/runner dispatch counts do not prove SMTP success;
inspect safe status and the private scoped catcher. Status and expiry remain key/provider-free.
[Recovery bounds and exit codes](CREDENTIAL_RECOVERY.md) are unchanged. This task supplies no public request
command/UI, test-only production endpoint or reinvitation workaround for creating reset work.

After private inspection, `./bin/mail-catcher clear` destroys only this project's messages; it does not revoke
credentials or resend delivered work. Never retain bodies, fragments, MIME, credential URLs, private mailbox
screenshots or SMTP debug output. Preserve the external delivery key while outstanding encrypted work exists.

## Qualified routing and completion contract

TASK-00053 originally qualified Access Control **v0.6.0**, source/lock reference
`dc4621d13851dd08cdf85ed9715ead24b1051e80`. The current lock and TASK-00054 request integration use
Access Control **v0.8.0**, `319738255e682d587642b67856039f882b9588d6`, and Common **v1.3.0**,
`7de6cad6e8a9752973ad9f8e27e285b0c1510582`. The invocation exposes purpose, generation ID, destination and
credential, but no UserId, expiry or claim token. Under the owner's [approved bounded contract](../../planning/tasks/00053-TASK.md#approved-invocation-routing-and-fragment-contract),
`PasswordResetMailProvider` dispatches `FindCredentialDeliveryStatus` through the configured query bus using
**exact invocation purpose + generation ID**. Its package-owned secret-free View provides that generation's
immutable UserId. The PostgreSQL repository resolves the unique delivery ID including historical generations,
never destination email or newest-user inference. Missing, wrong-type, mismatched or failed resolution sends
nothing and returns generic retryable failure. Provider destination, credential and stable identity stay unchanged.
No credential/digest/ciphertext/claim token is exposed by this query projection.

The exact fragment has only `user_id` then `credential`, each once. UserId is the canonical 36-character package
UUID; credential is 1–4096 decoded bytes, preserved untrimmed and RFC3986-encoded entirely inside the fragment.
No query carries authority. [TASK-00055](../../planning/tasks/00055-TASK.md) owns bounded one-time capture,
history removal, no-referrer behavior and completion; it is not implemented by sending this email. UserId alone
supplies no reset authority. There is no email, delivery ID, advisory status or claim token in the fragment.

## Presentation, lifecycle and limits

The fixed `password-reset-v1` contract uses `templates/mail/password-reset.txt.twig` and
`templates/mail/password-reset.html.twig`. Only the transient reset URL enters render context; recipient and
invocation do not. Text preserves the URL encoding; HTML escapes it, including the fragment separator. Either
render failure prevents mail. The deterministic alternatives state **one hour after request**, not after receipt,
with request-new-link, ignore-if-unrequested and non-forwarding guidance. No exact expiry is invented from arrival.
No remote resources, mutable content, tracking or unnecessary identity data are included. The existing catcher
MIME/referrer headers, fixed local sender and cleanup apply unchanged. Mail clients need not enforce those headers.

Claim commit precedes provider invocation; delivered/retryable/permanent outcomes remain package-owned.
The routing read is **not** a claim fence: its status/revision/expiry are advisory and are not consumed as send
authority. Succession, revocation, expiry or lease reclamation can race before/after lookup and SMTP submission.
Already-invoked mail can therefore arrive stale; historical routing stays stable, package outcome fencing prevents
state overwrite, and completion must reject obsolete grants. Expiry prevents a new package invocation, not an
atomic cancellation of an in-flight external effect. No additional lifecycle promise is introduced.

A matching delivered/permanent/expired outcome destroys live-row recoverable ciphertext. Success followed by
lost outcome commit can duplicate mail after lease recovery with the **same generation Message-ID**. Message-ID
is not provider deduplication: at-least-once, never exactly-once. No production privileges, scheduler availability,
real-inbox delivery or physical WAL/backup erasure are certified. PHP/Twig/Symfony can make temporary string
copies; reference minimization and redacted/nonserializable invocation/message objects are not reliable zeroization.
Reset-provider `print_r`/`var_dump` output exposes only a fixed mapping label, not the injected query-bus/container
or transport graph. This does not authorize arbitrary reflection, environment dumps or diagnostic serialization.

Focused behavior checks: `./bin/phpunit --filter 'PasswordResetMailTest|InvitationMailTest'` and guarded
`DirectCredentialDeliveryTest` cover mapping, immutable routing/history, no-send failures, retry/expiry/stale
outcomes and controlled crash windows. Use `./bin/database test-reset` before PostgreSQL iteration after coverage
cleanup. Full `./bin/build`, independent Review and applicable QA remain separate required stages.
