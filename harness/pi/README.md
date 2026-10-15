# Fight terminal for Pi

Version 0.1.0 supplies the approved Fight Agent OS welcome mark and compact working header (qualified with Pi
0.87.1), plus operator-only Jev setup/synthetic readiness and a trusted-consumer typed-judgment capability (Pi 1.2.0 on
macOS). Mixed typed forms have controlled Pi integration evidence, not live qualification; see
[bounded typed judgments](JUDGMENTS.md). One authorized live Decisions
check succeeded on October 14, 2026; independent acceptance remains pending. A synthetic result does not establish
model accuracy or another installation's account access. This is not the
managed Runner or an execution sandbox. TASK-00160 adds an explicitly selected
[deterministic tool policy](TOOL_POLICY.md) for qualified Pi 1.2.0 macOS sessions: final-argument file admission,
digest-bound script/argv admission and denial of unsupported helper/nested/child tools. It says **deterministic
protection only**, not Jev screening or process isolation; installation alone never activates it.
TASK-00162 adds opt-in, instruction-based single-file
screening; TASK-00163 extends it with bounded lists/globs and session-local metadata caching; see [operator policy, session activation and qualification limits](FILE_SCREENING.md). No global policy
is activated by installing/updating this source. TASK-00309's [enrolled-profile implementation candidate](FILE_SCREENING_PROFILE.md)
adds a stable `/file-screening` diagnostic, explicit enrollment preview, shared monthly accounting and local
receipt admission, with bounded native/build dispatch and conservative legacy reconciliation. For an enrolled
ordinary Build use `/screened-build TASK-NNNNN`: one native Build preview supplies protected host preparation,
pinned finite roles and exact bounded role-report handoffs without per-child approvals or Agent shell access.
See the profile's [launcher contract](FILE_SCREENING_PROFILE.md#coverage-and-launchers). Limited live synthetic
boundary evidence exists; representative investigations, visual proof and independent acceptance remain pending.
Local checks do not establish production readiness.

## Install

From the repository root, install for every Pi session:

```sh
pi install ./harness/pi
```

Pi stores its package declaration in personal settings. Restart Pi to load the package; an already running
session is unchanged. A local install resolves to this directory, so moving/removing the checkout requires
removing the old declaration and installing from the new location. Source updates take effect in later sessions.
No npm registry publication is required. The manifest is private to prevent accidental publication.

For repository-only use, use `pi install --local ./harness/pi` instead. To remove the personal installation:

```sh
pi remove /absolute/path/to/fight-agent-os/harness/pi
```

An install changes only Pi's package declaration. It does not select models, alter credentials, install skills,
authorize tools, or overwrite another theme. Pi's normal package/project trust rules still apply.

## Appearance

The default `auto` mode shows the refined F welcome and switches to a one-line identity at the first Agent run.
Resumed conversations start compact. Choose another mode for the current session with `/fight-header`, or use
`/fight-header port`, `wordmark`, `compact`, `ascii`, `auto`, or `off`. `off` restores Pi's own header.

Use `pi --fight-header compact` for a quiet startup, or set `FIGHT_PI_HEADER=compact` in the launching environment.
The CLI flag takes precedence over the environment. Preferences are not silently written to personal files.
Unknown options produce a warning; an invalid startup option falls back to `auto`.

Narrow terminals use a short ASCII identity. `TERM=dumb` or `NO_COLOR` also uses unstyled ASCII. Pi's built-in
light/dark themes use the approved orange accent; custom themes keep their own accent. Other colors follow the
active theme. JSON/print/RPC output receives no header. The editor, footer, status indicators and error messages
remain owned by Pi.

The mark is an approved terminal adaptation of this repository's `docs/assets/fight-agent-os-dark.svg`, with
symmetric light squares above/below the orange entry point. It uses terminal block characters, not an image
protocol. The separate prototype is not a runtime dependency.

## Verification and packaging

`./bin/harness-check` (from the repository root) runs owned presentation, Jev and deterministic-policy behavior tests in the pinned Node container. The
full `./bin/build` includes this check. If the check image is missing, explicitly fetch it first:

```sh
docker pull node@sha256:dd9d21971ec4395903fa6143c2b9267d048ae01ca6d3ea96f16cb30df6187d94
```

For direct development with Node 22 or later, `npm test` in this directory runs the same tests. Pi supplies the
declared peer dependencies when loading extensions. There are no third-party runtime dependencies to install.
Use `npm pack --ignore-scripts --pack-destination <output-directory>` to create a distributable tarball; package
contents are limited to the manifest, source, README and license. Keep local build artifacts under `.runs/`.

Controlled behavior tests are separate from Pi integration qualification. Real terminal/font
rendering still varies; a browser design mockup must not be presented as a native terminal screenshot.

## Jev setup and readiness (operator only)

Load the trusted package through the installation above, or explicitly for one invocation with
`pi --extension ./harness/pi`. Nothing resolves Jev credentials or sends a Decisions request on load/reload.
Setup commands are TUI-only; no generic Agent-callable judgment or credential tool is registered.
`jev_read_file` is exposed only with its shared rule and a separately configured operator/session file policy. Never pass a key in a command,
prompt, environment, argv, transcript or receipt. Keep the main Agent provider/model unchanged.

1. If OpenRouter is already configured in Pi, reuse it; no separate Jev key or Keychain helper is needed.
   Otherwise the human operator can use Pi's `/login openrouter` privately. Account enrollment, key creation,
   billing capacity and source access are the operator's responsibility, not automatic Harness setup.
2. Review the [Decisions contract](https://openrouter.ai/docs/api/api-reference/alphadecisions/submit-a-decisions-request)
   and [Jev endpoint/prices](https://openrouter.ai/api/v1/models/typesafe/jev-1.13/endpoints).
   The qualified public contract is `POST https://openrouter.ai/api/alpha/decisions`, model
   `typesafe/jev-1.13`, provider TypeSafe, expected response build `typesafe/jev-1.13-20260917`.
   A different returned build is rejected, never silently adopted. The fixed synthetic path returned that build
   in the October 14 live check; other question types and live refusal behavior are not qualified here.
3. Use `/jev setup`. Confirm that exact connection, enter explicit positive integer request/session ceilings in
   micro-USD (1 USD = 1,000,000), choose `fixed-synthetic-only` for readiness alone (or explicitly enable
   `authorized-supplied-state` for [trusted consumers](JUDGMENTS.md)), then confirm disclosure and current price.
   No credential is read and no provider request is sent by setup. No monetary ceiling is selected for you.
   Price confirmation expires after 24 hours; run setup again after expiry or any connection/price change.
4. Use `/jev inspect` for redacted effective settings and configuration-bound readiness. Inspection does not
   resolve authentication. Use `/jev check` only when you authorize one billed synthetic request and Pi's
   credential resolution, whose configured source may itself prompt for access. Cancel the confirmation to send nothing.

The October 14 public endpoint lists $0.042 per million input tokens, free output, 64,000 context tokens and a
32,000 prompt-token maximum. We conservatively reserve the entire 64,000 context: **$0.002688 (2,688 micro-USD)**
per attempt, not an estimated kettle-token count. Both explicit ceilings must accommodate the reservation;
request must not exceed session. Reconfirm pricing before use. Provider routing preferences request no fallback,
TypeSafe only and the published token-price maxima; they are not proof of server-side billing enforcement.
An unknown or uncertain billed outcome retains the reservation; reported actual cost settles it once, rounded
up to micro-USD. Missing cost is unknown, never zero. There are no automatic retries. A price-bound breach is a
failure that halts further admission in the persisted session ledger: reconcile and requalify pricing, rather than retrying. Do not delete a ledger to regain budget.

Settings and session charges live outside the repository under
`~/Library/Application Support/Fight Agent OS/jev/` (directory `0700`, files `0600`). Settings contain **no key**
or duplicate credential-store reference. Session-ID-bound reservations survive reload/process resume; a stale
lock, corrupted ledger or unresolved charge fails closed and needs human reconciliation, not automatic reset.
Changing settings invalidates readiness and cancels pending work. Shutdown/reload rejects stale completions.
Two concurrent operations, a 15-second deadline, 64 KiB input and response limits, eight questions and sixteen
choices are shared ceilings across readiness and typed consumers. `/jev check` still submits exactly one fixed
yes/no question and no choices or private source. Trusted consumers separately require current disclosure
permission and explicit request/session allocations; see [their contract and qualification limits](JUDGMENTS.md).
A complete finite Yes probability of at least 0.70 passes this synthetic check, not a measured accuracy claim.

### Authentication boundary and failures

The injected adapter calls only `ctx.modelRegistry.getApiKeyForProvider('openrouter')`. Pi chooses its configured
source (runtime override, stored authentication, provider configuration or its own environment/ambient rules);
Jev does not inspect/copy auth files, choose an independent fallback or resolve another provider. A key reusable
by Pi is **not** proof of Decisions access. Regional/proxy-specific keys may be rejected at the fixed official
Decisions endpoint; there is no automatic redirect or endpoint substitution.

Pi 1.2.0 returns undefined both for absent authentication and resolution errors. Jev reports the honest opaque
`credential-unavailable`, not fabricated missing/locked/denied categories. `provider-denied` (HTTP 401/403),
`billing` (402), `provider-rejected` (400/413/422), `timeout`, `provider-unavailable`, `refusal`, `invalid-result`,
`synthetic-negative`, budget and setup failures remain distinct. HTTP 422 alone is not proof of refusal.
The controlled explicit refusal fixture is not a qualified live refusal schema. Raw upstream bodies/errors
are discarded; never use their content to construct operator diagnostics.

Credential resolution has no supported abort parameter. A timed-out resolution cannot dispatch later, and holds
its concurrency slot until it settles; an unresolved Pi source may require human intervention/restart.
The transient buffer is cleared after transfer, but Pi's immutable/cached key string and fetch's header string
cannot be zeroized here. The key exists in the trusted Pi process; same-user tools/extensions can access that
process's resources. Nonsecret settings permissions do not isolate them from same-user Agents. Only macOS local
settings/runtime are directly qualified in this slice; other hosts report unsupported. There is no Keychain-only
isolation claim. Managed infrastructure-owned credentials and TASK-00138 isolation remain separate future work.

### Qualify your installation

The [TASK-00184 qualification record](../../planning/tasks/00184-TASK.md#authorized-live-evidence-recovery--2026-10-14)
reports one real Pi-managed OpenRouter check: Yes probability 0.99, 438 ms, 290 input/23 output tokens and
provider-reported cost $0.00001218. The conservative ledger settled 13 micro-USD, with no pending reservation.
Its $0.002688 request / $0.005376 session ceilings authorized **one request only**, not a repeat. Reload returned
to unchecked without another dispatch; the main Agent remained openai-codex/gpt-6.1-sol/high. Exact-key scans of
actual captured presentation/evidence found no occurrences. Native terminal images, a persisted JSONL transcript
(no model messages were sent), real pending-network cancellation and independent invoice verification were not
obtained; pending cancellation/stale completion use separately labeled controlled tests. Independent Review/QA
remain separate. This receipt neither enrolls another account nor authorizes another billed check.

For your own qualification, explicitly authorize ceilings, credential resolution and one paid fixed synthetic
check. With the trusted package loaded, use `/jev setup`, `/jev inspect`, then `/jev check` and confirm once.
Retain only redacted configuration/evaluator/schema, elapsed time, outcome and available measured usage/cost.
Inspect actual terminal/session/log presentation for disclosure without printing credentials; record absent files
rather than claiming they were scanned. Exercise reload/shutdown and verify an unchanged main model. Do not invoke
Pi credential-print commands or inspect private auth files. Keep controlled injected credentials/transport separate
from live evidence. Preserve uncertain charges and obtain new permission before any additional live request.
