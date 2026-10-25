# WF-044: Per-user OpenRouter and honest allowance

## Question

Can first-release browser Planning/help use each logged-in user's OpenRouter key, what can that key report about
usage/allowance, and which existing protected owners need amendment rather than duplication?

## Short answer

**Yes as a documented integration design, not as a qualified operating integration.** OpenRouter explicitly
supports applications using a user's own account-funded API key. A normal inference key can inspect itself at
`GET /api/v1/key`: key spending cap/remaining amount, usage, applicable budget layers, expiry and the account's
reported free-model daily quota. **Its remaining key cap is not the account's credit balance.** Account-wide
credit totals at `/api/v1/credits` require an unscoped account-level management key; do not request that power
just to display a balance. [S1–S6]

Recommend a dedicated ordinary key per user/environment, server-held encrypted credentials, exact user/connection
binding and explicit bounded snapshot refresh. Keep account balance visibly unavailable in the initial design.
Use existing admission/accounting/allowance owners; propose TASK-00346 as protected credential/key-info adapter
supplier and TASK-00399 as refresh/observation/query owner. TASK-00343 explicitly excludes credentials and must
remain the nonsecret catalog, not a vault. These are **proposed amendments**, not accepted supplier mappings.
TASK-00399 and TASK-00346 retain `needs-info`, and TASK-00350's operational host/broker/storage gap remains. [R1–R7]

Research observed official documentation on **2026-10-23 UTC**. No credential, account, model, allowance or
management API was accessed. Only unauthenticated public documentation was fetched. No model, SDK or runtime is
qualified or selected by this note. [WF-044](../tickets/WF-044-qualify-per-user-openrouter-and-allowance-ownership.md)
owns reconciliation; [the map](../private-production-rollout-map.md) retains the subsequent frontier.

## Findings

### 1. Per-user authentication is supported; it is not a shared household budget

**Documented facts.** OpenRouter uses Bearer API keys. Keys can have spending caps and reset intervals; exhausted
caps reject requests. Expiration and external key/account restrictions can invalidate a previously usable key.
OpenRouter's PKCE guide explicitly describes users authorizing an app, receiving a user-controlled API key and
paying inference from their own OpenRouter credits. This establishes a supported user-funded application pattern,
not account-specific availability or proof that an arbitrary pasted key belongs legally to the logged-in person.
[S1, S5, S13]

**Recommended first pass.** Follow John's requested key-entry path: the user creates a dedicated capped ordinary
inference key and submits it over authenticated same-origin HTTPS to the protected server. The server binds it
to the current Agent OS user, not a caller-selected user/account ID. Do not enroll keys, purchase credits or
configure auto-top-ups on the user's behalf. Separate family keys do not establish separate funding accounts;
keys under one account share account resources and applicable aggregate budgets. Parallel agents do not multiply
allowance. Keep production/staging keys and application budgets separate. [S1–S4]

OpenRouter's terminology **BYOK** means supplying an upstream model-provider key to OpenRouter, not merely
supplying an OpenRouter key to Fight. Do not accidentally promise upstream BYOK setup/support. If actual requests
report `is_byok` or external BYOK spend, retain the distinction and qualify its cost/limit coverage before use;
never treat that spend as ordinary OpenRouter credit usage automatically. [S2, S3, S8]

**Optional later alternative.** OpenRouter also documents PKCE (`S256` and `state`) that returns an API key,
not a ChatGPT subscription token or an assumed refresh-token lifecycle. If later selected, perform the code
exchange server-side, bind state/verifier/callback to the authenticated user and exact connection operation,
exclude codes from logs/referrers and store only the resulting protected key. The docs' browser-storage examples
are not permission to weaken Fight's server-only boundary. This note does not add OAuth onboarding to first
release or initiate any authorization flow. ChatGPT remains WF-051. [S5]

### 2. Authoritative scope matters more than a single remaining number

| Facility / source | Credential | Documented meaning | Honest first-release interpretation |
|---|---|---|---|
| `GET /api/v1/key` → `limit`, `limit_remaining`, `limit_reset` | The selected ordinary inference key | That key's own spending cap and remaining amount, in USD; nullable | Label **key spending cap**, not wallet/account balance. Null is documented as uncapped at this layer, not unlimited service or zero usage |
| Same → `usage`, `usage_daily`, `usage_weekly`, `usage_monthly` | Same | OpenRouter credit usage in USD; UTC day/week/month, week starts Monday | Provider-reported key activity, potentially including other applications using this key; not Agent OS's recorded total |
| Same → `byok_usage*`, `include_byok_in_limit` | Same | External upstream-BYOK usage and whether it counts against the key cap | Separate category and inclusion flag; avoid double counting or comparing unlike coverage |
| Same → `budgets[]`, `effective_budget` | Same | Enforced guardrail/member/workspace/key budget layers, USD limit/spend/remaining, reset interval/time, BYOK coverage | Label each scope. `effective_budget` is the first already-blocking layer or least remaining layer, not account wallet or a guarantee of request admission |
| Same → `free_model_daily_requests` | Same | Account-level free-model daily request counter/ceiling/remaining; UTC midnight reset | Requests, not tokens/USD. Exceptions/BYOK can be outside enforcement; display scope/coverage, not a universal allowance |
| Same → `expires_at`, `is_management_key` | Same | Expiry and credential type | Useful protected readiness facts. Do not accept a management key as a substitute inference credential |
| `GET /api/v1/credits` → `total_credits`, `total_usage` | **Management key required** | Account totals purchased/used | Initial ordinary-key design leaves account balance unavailable. Do not collect a management key to compute it |
| Rate-limit error headers | Request-dependent | Limit/reset for the platform limit actually hit; successful inference does not supply these headers | Do not invent a full quota dashboard or reuse the deprecated `rate_limit` object |

The primary `/key` schema describes `limit`/`limit_remaining` as only the key cap; `budgets` describes other enforced
layers. A positive cap is compatible with an empty account, insufficient in-flight capacity, exhausted broader
budget or model/provider unavailability. Account-wide `/credits` is explicitly management-only in both its
endpoint reference and management-key guide; regular-key access is not assumed from older examples. Management
keys are account-wide/unscoped administrative credentials and cannot perform inference. [S2–S4, S6]

Budget windows are UTC; daily resets at midnight, weekly on Monday, monthly on the first. Lifetime layers have
no reset. Preserve provider-returned `resets_at` where present; a cached reset time is not proof of new available
capacity after that time. Do not turn null or absent values into zero. Budget remaining can be zero or negative;
retain the reported state rather than conceal overspend with clamping. [S2, S3]

The documentation's deprecated rate-limit example and free-tier prose are not a stable universal quota contract.
Use supported returned fields and actual qualified error metadata rather than hardcoding free-tier thresholds.
Some rendered limits prose has missing status-code text and an unresolved threshold template; prefer the
structured error examples and schema, and recheck at implementation. None of those examples is a live account
receipt. [S2, S3]

**Conclusion for TASK-00399:** `/key` is a precise **documented candidate source** for credential-key and labelled
broader budget/free-quota observations. It is enough to specify a useful finite source-integration proposal;
it is not proof of safe source qualification, an accepted supplier or full account-balance support. Keep the
existing hold until those accepted mappings and actual qualification exist. Unsupported account/Codex/ChatGPT
allowance remains independently unavailable, not a reason to block ordinary usage. [R5]

### 3. Protected connection and refresh contract — recommendation, not implementation

Keep provider authentication under TICKET-00089's protected provider owner, separate from Fight Access Control's
Agent HMAC/credential-delivery lifecycle and from nonsecret connection profiles/catalogs. [R1–R3]

- **Ownership:** current authenticated human owns the browser provider connection. Agent/work requests also need
  actual acting-Agent/delegation, Repository/environment, disclosure, model and budget authority. A user-visible
  provider ID, role name, admin status or repository read is not provider spending/account inspection permission.
  Binding a submitted key proves possession/use authority supplied by the user, not independent OpenRouter-person
  identity verification. Do not merge household identities based on provider account metadata.
- **Storage:** authenticated bounded secret submission, no secret readback, protected authenticated encryption at
  rest, narrowly available decryption and an external installation-controlled key/key identifier. Do not reuse
  JWT, CSRF, credential-email or Agent signing secrets. Choose the exact storage/mount/rotation/backup participant
  under existing runtime owners before implementation; inspected planning records do not establish a qualified
  provider service.
- **Views:** safe user-chosen connection label, configured/unchecked/usable/expired/revoked/unavailable state and
  exact observation times. `/key.label` can include key fragments; raw labels, creator IDs, organization/workspace
  IDs, headers and source bodies are not automatically safe display/log fields. Project only required scope and
  policy facts. No key in browser persistence, prompts, transcripts, model files, child environments or receipts.
- **Lifecycle:** replace with a new credential generation; disconnect/revoke Agent OS access immediately for new
  dispatch; retire secret references and wipe material under the accepted retention/recovery contract. Preserve
  minimal safe original-attempt accounting, not decrypted credentials. Do not silently swap a new key into an
  already admitted or uncertain attempt, even if the user reselects the same connection.
- **Provider revocation differs:** removing a stored key from Fight does not revoke it at OpenRouter. Direct users
  to OpenRouter key settings for provider disable/archive/delete/rotation. Standard inference-key possession does
  not grant the management API. In-flight requests may still incur charges; report that limit explicitly. [S1, S4, S13]
- **Explicit refresh:** TASK-00399 admits one bounded current-authorized original refresh intent; the protected
  adapter resolves only that connection generation and issues `GET https://openrouter.ai/api/v1/key`. No caller
  URL, arbitrary redirects/account endpoint, login renewal or generation. Network work follows committed intent
  outside business transactions. Numeric/rate/time/byte limits and declared side effects must be finite.
- **Snapshot:** retain source endpoint/schema revision, internal connection/generation and scope, reported units,
  key/budget/free-quota fields actually supported, service `observed_at`, last-success/failure and freshness policy.
  `/key` does not supply a universal provider-as-of timestamp: mark it unavailable, not equal to server observation
  time. Show observation age and provider-reported reset separately. Any local harness contribution adds producer,
  authentication and transport provenance, never replaces provider meaning.
- **Passive reads:** current-authorized recorded snapshots only. Dashboard polling/reconnect does not refresh the
  provider, contact a disconnected harness, generate a response or renew authentication. Stale observations remain
  stale; missing observations remain unavailable, not zero. Outage cannot erase recorded usage.
- **Fences:** scope queries and late writes to human/connection/credential generation and current authority;
  changed-away-and-back, cross-user IDs, revoked users, duplicate refresh receipts and browser context changes
  cannot resurrect private account data. A failed/uncertain refresh can be reconciled explicitly; a later fresh
  snapshot is a new observation, not evidence the old one succeeded or that zero spend occurred in between.

An ordinary static inference key has no documented automatic renewal flow in the inspected key/PKCE contracts.
Expiration/authentication failure should ask for an explicitly supplied replacement; do not invent token refresh
from the word refresh in **allowance refresh**. Exact freshness thresholds, numeric budgets and user-facing
connection controls remain acceptance/configuration decisions for the owning delivery work and WF-049/050,
not automatic policy changes from this research. [S1, S5, S13]

### 4. Generation accounting, tools and routing need their own exact contracts

**Recorded usage.** Chat Completions documents prompt/completion/total tokens, optional cache/reasoning details,
nullable cost and BYOK fields. Metadata lookup for an exact generation documents cost in USD, native token counts,
model/provider, upstream ID, finish/cancel state and request correlation. Treat missing optional values as unknown;
count reasoning/cache quantities as reported, not automatically additional tokens beyond totals. Metadata lookup
is not transcript recovery and must never enable private input/output logging just to recover an answer. [S7–S9]

**Streaming.** The current streaming guide documents an extra final accounting chunk before `[DONE]`, including a
repeated terminal `finish_reason`. It is one usage observation, not a second completion/bill. Responses and Messages
have different usage-event locations. Deduplicate original attempt/provider-generation observations; don't add
final stream usage, generation lookup and key cumulative usage as separate charges. Reconnect reads durable local
results without replaying paid calls. Header `X-Generation-Id` can help correlation when received, but a lost header
or response leaves uncertainty. The inspected docs establish no exactly-once replay guarantee. [S7–S9]

**Cancellation/failures.** Aborting a stream stops processing/billing only for supported upstreams; unsupported
upstreams and nonstreaming calls may complete and charge. A committed HTTP 200 can still contain a first-event or
midstream error. Do not treat user Stop, dropped socket, timeout, HTTP success or missing metadata as unsent/free
work. Bound original-generation metadata reconciliation; if it cannot establish the result/cost, retain uncertainty
and liability. Do not follow provider retry suggestions as automatic product authority. [S3, S7, R4]

**Models/tools.** The public catalog has capabilities and pricing metadata; `/models/user` filters by the selected
key's provider preferences/privacy/guardrails. Neither proves quality, actual service readiness or permission to
use a model. Intersect selected-user options with installation policy, exact role/tool requirements and finite
cost bounds. Model pricing has per-token/request/unit categories and conditional overrides: a displayed base
price is not a guaranteed request upper bound. Cheap models are candidates, not assumed suitable agents. [S10, S11]

Function/client tools are application-executed suggestions. Fight must authorize and validate each tool/MCP call;
OpenRouter compatibility does not enroll an MCP server or transfer authority to the model/browser. Do not enable
OpenRouter server shell/subagent/search/plugins or hidden extra model work because SDK features exist. Such tools
can alter disclosure/cost and require their own accepted scope. [S12, R3]

**Important default:** OpenRouter load-balances upstream providers and enables upstream fallback by default;
`require_parameters` defaults false and unsupported parameters can be ignored. This is distinct from switching
between OpenRouter and ChatGPT, but still affects destination, price, cancellation and tool/disclosure guarantees.
Recommend a qualified explicit upstream allowlist/endpoint policy, `allow_fallbacks: false`,
`require_parameters: true`, exact model identity and no automatic router/model-fallback list for first launch.
WF-049 may choose a differently bounded explicit policy; do not silently accept the defaults. Price/retention
filters cannot replace a total budget or proof of actual endpoint behavior. [S11]

**Privacy/licensing limits.** OpenRouter documents opt-in prompt/completion logging and separate upstream
policies. Provider selection documents data-collection/ZDR controls, while the general logging page uses broader
wording about retention: do not promise universal zero retention from one setting. Qualify the selected endpoint
and actual account/privacy configuration. Model/provider terms and API service terms are distinct from Agent OS's
MIT license or the API schema's MIT metadata; no blanket license/eligibility conclusion follows. [S11, S14, S15]

### 5. Precise existing-owner amendment proposal

| Existing owner | Proposed change / participant contract | Hold preserved |
|---|---|---|
| TICKET-00089 / [TASK-00343](../../tasks/00343-TASK.md) | Keep installation allowlist/catalog policy and safe exact connection references; distinguish per-user configured connection from provider readiness | Explicitly excludes key material/live refresh; not the protected storage or quota supplier |
| TICKET-00089 / [TASK-00346](../../tasks/00346-TASK.md) | Amend installation-key-only wording to protect user-owned browser credentials; provide exact generation-fenced credential resolution, ordinary-key type/readiness checks and a narrow non-generative `/key` adapter for TASK-00399 | Proposed supplier only; protected service/host/storage and self-service connection lifecycle/UI coverage require accepted amendment/decomposition, not inferred delivery |
| [TASK-00344](../../tasks/00344-TASK.md) | Carry current initiating human/connection generation through disclosure, spend admission/reservation and dispatch; enforce cross-user denial | Existing actual Agent/host/attempt holds and finite bounds remain |
| [TASK-00345](../../tasks/00345-TASK.md) | Own original usage/stream normalization and explicit generation reconciliation; preserve original credential binding/late liabilities | No credential owner or duplicate quota ledger; absent original credentials/status keeps recovery uncertain |
| TICKET-00098 / [TASK-00399](../../tasks/00399-TASK.md) | Own bounded explicit refresh admission, sanitized key/budget/free-quota snapshots, safe independent queries/presentation and source-specific stale/error handling; propose TASK-00346's narrow adapter as supplier | Do not clear `needs-info`: supplier amendment/mapping and actual source qualification are not accepted or implemented |
| TICKET-00098 / [TASK-00396](../../tasks/00396-TASK.md) | Reuse normalization/projections but explicitly scope any later conversation-only/repository totals and browser presentation amendment | Currently Workflow/phase-oriented; do not fabricate a Workflow for browser Planning/help or claim this already owns every browser rollup |
| TICKET-00090 / existing snapshot, conversation and invocation owners | Retain user/connection/configuration identity across durable attempts and linked continuations; reconnect cannot choose another account/key | TASK-00350 protected operational host/broker/storage supplier remains unmapped; source documentation does not fill it |
| TICKET-00104 / [TASK-00428](../../tasks/00428-TASK.md) | Replace explicit installation-key binding with the selected initiating user's protected connection; preserve bounded advisory help and deterministic model-free recovery | Pre-registration Agent/snapshot/assignment scope hold remains; no general assistant expansion |
| TICKET-00092 / TICKET-00107 consumers | Verify inherited per-user selection, privacy, admission, original outcomes and usage; select first launch journey under WF-049 | Do not duplicate provider/conversation engines or assume all browser surfaces exist |
| TICKET-00023 / [TASK-00072](../../tasks/00072-TASK.md), execution/host owners | Supply narrowly scoped secret custody/runtime/backup primitives where accepted; qualify the actual provider service/worker separately | TASK-00072 excludes provider traffic/production deployment; its secret mounts alone do not implement per-user storage or a protected broker |

No row is a delivery-record amendment, new TASK, accepted blocker edge or qualification clearance. The exact
self-service secret submission/replacement/removal UI/use-case supplier is **not yet allocated**; later TICKET-00089
amendment planning must decide whether bounded changes to existing slices suffice or approved new slices are
needed. Do not invent an ID or silently expand TASK-00343. Preserve local Pi/development paths and every unrelated
provider/Agent/runtime/source hold. [R1–R9]

### 6. Later qualification evidence — not executed

- Separately authorize finite account reads, then validate exact ordinary-key `/key` fields/types/nulls, units,
  UTC/reset semantics, applicable budget/free-quota coverage, expiry, auth failure and response/error sanitization.
  Confirm ordinary-key `/credits` denial without enrolling management credentials. No generated workload needed
  merely to establish the read source; real generation/tool/quality acceptance needs separate bounded authority.
- Use independent test users and protected fixtures to challenge key submission/type rejection, connection
  ownership, encryption/external key access/backup recovery, replacement/revocation, cross-user/current-generation
  races and late original outcomes. A saved encrypted record is not proof of a protected operating service.
- Exercise exact admitted useful browser turns, supported tool schema/cancellation/routing, header/stream usage,
  missing final usage, generation metadata access scope/availability and uncertain-result recovery. No transparent
  retry/fallback or arbitrary paid benchmark workload. Doubles prove fault policy, not live provider contracts.
- Verify refresh is explicit/bounded and passive reads/reconnects remain provider-free; stale harness/provider,
  unavailable account balance, reported key/budget/free-quota scope, no fake zero and no duplicate accounting.
- Run owned application tests, actual browser accessibility/recovery checks and canonical `./bin/build` during
  implementation. Validate third-party tooling/configuration directly, not through product tests of SDKs/docs.

## Sources

Public primary documents retrieved on 2026-10-23; illustrative values/identities in their examples are not live
account evidence and were not imported as application fixtures. Public snapshots are retained only as ignored
research scratch under `.runs/notes/wf-044-openrouter/` in the selected checkout; runtime never depends on them.

- **S1:** [API authentication](https://openrouter.ai/docs/api_reference/authentication.md).
- **S2:** [Get current API key, including KeyBudget/FreeModelDailyRequests schema](https://openrouter.ai/docs/api/api-reference/api-keys/get-current-api-key.md).
- **S3:** [Limits, budget layers, UTC windows and in-flight/rate failures](https://openrouter.ai/docs/api_reference/limits.md).
- **S4:** [Management API keys, scope and management-only routes](https://openrouter.ai/docs/guides/overview/auth/management-api-keys.md).
- **S5:** [OpenRouter OAuth PKCE and user-funded inference](https://openrouter.ai/docs/guides/overview/auth/oauth.md).
- **S6:** [Account credits, management key required](https://openrouter.ai/docs/api/api-reference/credits/get-remaining-credits.md).
- **S7:** [Streaming usage, generation header, cancellation and errors](https://openrouter.ai/docs/api_reference/streaming.md).
- **S8:** [Chat Completions, ChatUsage schema](https://openrouter.ai/docs/api/api-reference/chat/create-a-chat-completion.md).
- **S9:** [Generation request/usage metadata](https://openrouter.ai/docs/api/api-reference/generations/get-request-&-usage-metadata-for-a-generation.md).
- **S10:** [Models/capabilities/pricing](https://openrouter.ai/docs/guides/overview/models.md) and [key-filtered models](https://openrouter.ai/docs/api/api-reference/models/list-models-filtered-by-user-provider-preferences-privacy-settings-and-guardrails.md).
- **S11:** [Provider routing, defaults and restrictions](https://openrouter.ai/docs/guides/routing/provider-selection.md).
- **S12:** [Client/function tools](https://openrouter.ai/docs/guides/features/tool-calling.md).
- **S13:** [Key security, replacement and provider disable/archive](https://openrouter.ai/docs/guides/overview/auth/security-settings.md).
- **S14:** [OpenRouter data collection/logging](https://openrouter.ai/docs/guides/privacy/data-collection.md).
- **S15:** [Upstream logging and terms boundaries](https://openrouter.ai/docs/guides/privacy/provider-logging.md).

Repository ownership sources:

- **R1:** [TICKET-00089](../../tickets/00089-TICKET.md), [TASK-00343](../../tasks/00343-TASK.md), [TASK-00346](../../tasks/00346-TASK.md).
- **R2:** [TICKET-00104](../../tickets/00104-TICKET.md), [TASK-00428](../../tasks/00428-TASK.md), [TICKET-00107](../../tickets/00107-TICKET.md).
- **R3:** [TICKET-00061](../../tickets/00061-TICKET.md), [tool-mediated boundary](../../../docs/engineering/TOOL_MEDIATED_EXECUTION.md).
- **R4:** [TASK-00344](../../tasks/00344-TASK.md), [TASK-00345](../../tasks/00345-TASK.md).
- **R5:** [TICKET-00098](../../tickets/00098-TICKET.md), [TASK-00399](../../tasks/00399-TASK.md).
- **R6:** [TASK-00350](../../tasks/00350-TASK.md), [TICKET-00090](../../tickets/00090-TICKET.md).
- **R7:** [TASK-00072](../../tasks/00072-TASK.md).
- **R8:** [TASK-00396](../../tasks/00396-TASK.md).
- **R9:** [Bounded baseline and amendment register](private-production-rollout-baseline.md).

## Approved Fight API direction

John accepted the connection-based direction after the upstream/Fight endpoint distinction was clarified:

- OpenRouter owns `GET https://openrouter.ai/api/v1/key`; Fight does not rename that upstream URL.
- Fight's route direction is `/api/v1/me/provider-connections` and
  `/api/v1/me/provider-connections/{connection_id}/allowance`. A user-owned connection identifies provider and
  environment; multiple providers or connections to the same provider do not require a provider-specific browser
  allowance interface. Current ownership/permission checks remain authoritative regardless of the `/me` path.
- The allowance resource returns safe recorded observations, not the key or an implicit live provider poll.
  Explicit refresh remains a separately authorized bounded operation; selected-provider details stay in adapters.
- This accepts design direction, not complete endpoint methods/payloads, lifecycle/refresh routes or implementation.
  Existing-owner amendments, supplier allocation and actual qualification remain pending; no delivery record changed.

## Open questions

The factual research boundary is answered. Remaining delivery decisions are explicit, not implied approvals:

- Accept and allocate per-user protected lifecycle/UI, narrow `/key` adapter and TASK-00399 supplier amendments in
  a later authorized owner handoff; qualify actual runtime/host participants. No owner amendment is authorized now.
- Select the first browser journey/models, exact upstream routing/privacy policy and finite application budgets
  under WF-049; determine configuration/freshness values with those owners. WF-050 covers deployment secret/recovery
  acceptance. Model terms, account eligibility and actual runtime support remain per selected path.
- Optional OpenRouter PKCE onboarding may reduce manual key handling later; manual per-user key entry remains the
  initial proposal. ChatGPT account/subscription research is WF-051 and has no OpenRouter-launch dependency.
- No full account balance with ordinary inference credentials; a separately justified future facility would need
  its own safe authority/eligibility analysis, not management-key collection as a prerequisite.

Closing WF-044 records documented capability and a precise proposed ownership boundary, **not** accepted owner
amendments, implemented refresh, account access or live provider/source qualification. No speculative decision
or duplicate delivery record is needed for these implementation-owned gaps.

Reconciled in WF-044; the map's next frontier is hosting research, not execution of these amendments.
`./bin/planning-check --write`, `./bin/planning-check`, tracked diff and all changed/untracked Wayfinder whitespace/
path-scope checks passed. Inspected delivery owners remain byte-identical to HEAD. The full application build,
independent acceptance and actual provider/source/host qualification were not run; all work remains uncommitted.

Next: `/skill:wayfinder work WF-045`
