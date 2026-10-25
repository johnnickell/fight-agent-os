# ChatGPT plan usage for a private hosted Fight installation

## Question

For [WF-051](../tickets/WF-051-assess-optional-household-chatgpt-oauth.md), can John and household users bring their
own ChatGPT subscriptions into browser Planning at `fight-agent.dev`, with protected per-user credentials, real
tool support and honest usage reporting, without replacing the selected OpenRouter first-release path?

Observed **2026-10-24 UTC** from unauthenticated official public documentation. This is documentary research, not
account eligibility, partner approval, a working integration or authorization to enroll, transfer tokens or call
inference. The checkout's [LICENSE](../../../LICENSE) is MIT; that fact alone does not establish hosted-service
eligibility. Scratch snapshots, URL/time/status/hash receipts and an aggregate index live under
`.runs/notes/wf-051-chatgpt/`, never as application runtime dependencies.

## Short answer

**There is a documented, supported route—not a reason to reuse personal Codex credentials or undocumented APIs.**
Sign in with ChatGPT (SIWC) can authorize eligible Plus/Pro users' Responses API requests. OpenAI explicitly
documents open-source apps on self-hosted VMs. However, its open-source bootstrap is local loopback OAuth followed
by protected credential transfer; its hosted website/partner path is separately registered and restricted. The
exact eligibility of this shared, private household web installation and a browser-only onboarding path are not
established by the public documents. [S1–S4, S12]

**Recommend deferring this optional integration from the first production milestone**, retaining it as a named
follow-up rather than declaring it impossible. A human-approved alternative is bounded eligibility/onboarding
qualification first. Do not silently accept local credential-copy ceremonies, submit a partner request or add
implementation TASKs. **Subsequent decision:** John explicitly accepted deferral until after first production;
WF-051 is Closed. OpenRouter launch is unaffected. Preserve this dated research for a later explicitly selected
follow-up, not automatic enrollment/implementation when production starts.

## Findings

### 1. Eligibility and prices: distinguish the three surfaces

| Surface | Public documentation | Consequence for Fight |
|---|---|---|
| Open-source/local SIWC plan usage | Quickstart says available to all open-source partners and selected private clients; overview covers OSS/locally hosted apps | MIT is relevant but not an account/installation approval receipt |
| Open-source app on self-hosted VM | Explicit guide: OAuth locally with the same tool/client/user/workspace, transfer its protected session, VM owns subsequent refresh | A real documented remote-runtime option; not evidence that household multi-user website onboarding has the same approval boundary |
| Website/hosted service | Overview directs paid or remotely hosted apps to an interest form; website/client-ID docs describe selected partners, exact registered callback URLs and a limited trial | Personal VM guidance and broad hosted-service wording must be reconciled for the exact topology; do not assert either universal eligibility or a blanket VM prohibition |

SIWC plan-usage docs explicitly name **eligible Plus and Pro** users. General Codex/ChatGPT product availability on
Free/Go/Business/Edu/Enterprise is not proof those plans qualify for this SIWC flow. The separate pricing page
currently advertises Plus **$20/month** and Pro **$100/$200/$500 USD/month** variants. These are dated public plan
prices, not tax-inclusive quotes, selected subscriptions or a guarantee of family account eligibility. John's
intended higher-tier plan and relatives' plans need their actual account-specific checks only if pursued. [S1, S13]

The Codex app-server reference explicitly says its own authentication has not been permitted for commercial or
hosted services and points those integrations toward SIWC/partner access. Existing local Pi/Codex support therefore
cannot be repurposed as proof that Fight may reuse that login on a shared server. No Pi/Codex credential file was
inspected; no device-login or personal auth-cache copying is proposed. [S14]

### 2. Supported OAuth bootstrap and the browser-only mismatch

The OSS route uses `client_id=dynamic_agent_client` only for first registration, the real tool name, fresh state,
nonce and S256 PKCE, and a stable opaque `ext_agent_host_id`. Save the **issued** client ID, not the bootstrap ID.
Each issued client is bound to the authenticated user and selected workspace; a host ID identifies an environment,
not a user or authentication proof. Reuse a host's ID across restarts, and do not overwrite a VM ID with a copied
laptop ID. Generate identifiers with the owning UUID/key library if ever implemented, not improvised literals. [S2, S3]

The documented callback is HTTP **`127.0.0.1`**, with the original scheme/host/path retained and only the port allowed
to vary between attempts. A callback opened in a household user's browser reaches that user's computer—not the
Droplet. Changing it to `https://fight-agent.dev/...` is not a documented substitution for this dynamic OSS flow.
The self-hosted guide solves this by local OAuth and secure transfer of the selected tool's own registration/token
record, followed by VM-owned refresh. This transfers a session; host-specific usage attribution and revocation of
transferred sessions are **not yet available**. Do not promise separate production/staging or laptop/VM quota and
revocation merely from different host IDs. [S3, S4]

The website guide supports registered callbacks and backend exchanges but says its guide covers identity scopes;
identity sign-in alone does not grant inference. Fight would need a confirmed suitable client/plan-usage grant,
not just a familiar login button. Preserve Fight's existing human authentication and authorize provider-account
linking inside the selected user session; no silent account merge or replacement of Fight login. [S1, S12]

This is a concrete conflict with the accepted browser-centered experience: an existing optional local harness does
not make every browser user willing or able to run a local bootstrap. If John wants the OSS/VM route, explicitly
resolve that onboarding tradeoff and protected transfer supplier. Do not silently turn it into terminal sign-in,
manual token pasting, device enrollment or a new prerequisite for ordinary browser Planning.

### 3. Tokens, isolation and recovery

Required plan-use scopes include `offline_access resource.invoke chatgpt.tokens.use.direct` in addition to identity
scopes, with the documented API resource. Validate callback state and the ID token's signature, issuer, audience,
expiry and nonce; bind credentials to the verified subject and issued client ID, not email alone. Check granted
scopes; a valid identity token without plan-use permission does not authorize inference. [S3, S5]

The token reference describes one-hour access tokens and rotating refresh tokens valid for 30 days, with each
successful refresh issuing a replacement with a fresh 30-day lifetime. Serialize refresh per session, atomically
replace the token set and use actual returned expiry/refresh metadata. A resumed service must not race a laptop
still refreshing the same copied session. Longer idle periods, revocation or terminal refresh failures can require
reauthorization; network failures alone are not grounds to erase a usable connection. [S4–S6, S10]

Sign-out stops requests, attempts revocation of the renewable session and clears the selected local token set.
Unconfirmed remote revocation must remain visible; disconnect in ChatGPT Settings remains a recovery path. OpenAI
currently does not notify the tool of disconnection, so failure/refresh reconciliation matters. Preserve original
attempt liabilities and admitted work; provider reconnection, account switching or restored backups do not reset
Fight accounting or authorize duplicate replay. [S5, S10; R1]

The examples' owner-only local files and app-server child-process environment are **not** proof of Fight's stronger
protected runtime boundary. Do not put credentials in browser storage, prompts, command arguments, ordinary
process environments, logs or tool-visible files. Plan reuse of the existing protected connection/host participant,
with exact recipient-bound delivery and server-side encrypted custody—not a second identity or token service.
No token transfer or account/secret inspection occurred here. [S3, S5, S11; R1]

### 4. Model discovery and invocation

Use the **selected account's** OAuth token at the documented `GET https://api.openai.com/v1/models`; this flow's
response contains `models`, with displayable entries marked `visibility: "list"`. Preserve returned ordering,
show `display_name`, and use `slug` for inference. Refresh on account changes and use the existing light-cache,
explicit-refresh policy scoped to connection generation/environment. Never copy a catalog between household
users or use a bundled app-server catalog as an entitlement assertion. No exact model name is selected by this
research; documentary example IDs are not account access evidence. [S7, S11; R1]

Inference is **`POST https://api.openai.com/v1/responses`**, not ChatGPT `backend-api`. Each HTTP request requires
`store: false`, `stream: true`, and the necessary context as an input array. Only `response.completed` establishes
successful completion; partial text can precede a usage-limit failure. Preserve failed/incomplete/interrupted
states and original usage independently. No inference or authenticated model discovery was performed. [S7, S10]

### 5. Tools, MCP, context and output limits

The current preview supports function/custom tools grouped in namespaces or supplied with `additional_tools`.
**Hosted MCP/connectors are unsupported**, but a client may execute its own authenticated MCP calls behind
supported function/custom tool dispatch. Those are different mechanisms. Fight still needs permission-checked,
idempotent tools and its own result/context loop; model tool calls grant neither MCP authentication nor shell
access. Codex app-server can run shell/MCP/child agents, but that does not authorize those capabilities on Fight's
application hosts. [S8, S11; R1]

Other limitations matter to an adapter:

- Use developer messages or `instructions`; explicit `role: system` message items are rejected.
- No HTTP `previous_response_id`, hosted `conversation` or persistent provider history; send needed history and
  retain Fight's existing transcript/explicit continuation semantics. WebSocket references are connection-local.
- Unsupported fields include `max_output_tokens`, `max_tool_calls`, `background`, `metadata`, `temperature`, `top_p`,
  `truncation` and the other fields enumerated by the preview page. Do not silently send a generic API request body.
- Unsupported tools include file search, Code Interpreter, native computer use, image generation, hosted MCP and
  Responses `tool_search`; local execution does not make `tool_search` supported. Web search is model/policy dependent.
- Text/images/files are model-dependent inputs; audio/video, Files upload and transcription APIs are not supported.

In particular, an unsupported output-token cap is a real admission/accounting integration issue. Do not describe
stream cancellation as a guaranteed provider spending cap or weaken TASK-00344/00345 to fit this route. Later
qualification must establish a truthful finite admission policy, or leave affected requests unavailable. [S8; R1]

Codex app-server's documented SIWC configuration uses stdio to the app and HTTP/SSE to Responses, with the app
refreshing OAuth and restarting/resuming app-server after token renewal. Its `env_key` recipe is a documentary
example, **not selected Fight deployment wiring**. A direct protected Responses adapter may avoid that process
pattern, but exact ownership/feasibility is later authorized work, not a new runtime selected here. [S11]

### 6. Usage and allowance: no invented percentage

Keep three different concepts visible:

1. **Fight's recorded original-attempt usage and environment limits**, governed by existing admission/accounting.
   Subscription-backed inference is not automatically an observed $0 provider charge; unavailable cost stays
   unavailable, while reported token observations can still be shown.
2. **SIWC app-specific sharing and ChatGPT plan limits.** OpenAI directs users to ChatGPT Settings → Usage for app
   limits/access and credits choices. Plus has a five-hour limit shared across apps, including OSS/private clients;
   Pro has no such five-hour limit, which does **not** establish unlimited weekly/app usage. Clients/hosts/parallel
   agents do not each get a fresh subscription. Do not infer a complete reset time from a limit error. [S5, S9, S10]
3. **Codex service rate-limit/usage displays.** The general app-server API documents `account/rateLimits/read`,
   `rateLimitsByLimitId`, percentages/windows/resets and `account/usage/read`. These have their own metered buckets
   and authentication context; the examples are not a SIWC app-limit schema. Its usage endpoint explicitly needs
   Codex-services-backed authentication. Nothing inspected establishes that the SIWC custom Responses-provider
   configuration supplies authoritative app-specific remaining allowance through those RPCs. Do not copy a
   `codex` percentage into Fight as ChatGPT plan-sharing allowance. [S11, S14]

The inspected SIWC docs do **not** publish a general app-allowance read endpoint/schema. That is a bounded research
finding, not proof none can ever exist. Initially show recorded usage plus **provider allowance unavailable** and
a Manage usage link unless a supported scoped observation source is subsequently qualified. No UI scraping,
undocumented endpoint, browser-session reuse, provider polling loop or synthetic balance calculation. Missing
allowance alone need not disable useful Planning; actual admission/eligibility restrictions still govern. [R1]

Errors distinguish ineligible users/workspaces, exhausted app/plan limits, temporarily unavailable usage and
unsupported capability. `subscription_sharing_usage_limit_exceeded` can arrive after streaming begins; it does not
say every plan allowance is empty. Preserve terminal and uncertain states. No implicit paid-credit opt-in, automatic
OpenRouter fallback, account cycling or limit expansion. A different provider requires explicit user selection
and the existing authorized continuation boundary. [S7, S10; R1]

### 7. Proposed follow-up, not new delivery scope

If pursued, reuse the existing per-user connection/catalog/continuation and original-accounting owners described
in WF-044/049, coordinating TICKET-00089/00090/00092/00098 and TASK-00342/00343/00344/00345/00346/00350/00367/00399.
A new provider's adapter/credential lifecycle must be explicitly allocated later; no task currently gains OAuth
custody by implication. Preserve source/runtime and continuation holds. MCP/harness credentials remain separate
from provider OAuth, and no Workflow-only usage owner becomes a conversation ledger. [R1]

Concrete later qualification, only under separate authority:

- Confirm the permitted personal/self-hosted-versus-household-service classification and eligible client grant.
- Choose an approved hosted browser callback or explicitly accepted local-bootstrap/protected-transfer experience.
- Check each selected account/workspace's actual scopes/catalog/eligibility, secure refresh/revocation and VM
  custody; no account should share another user's registration or quota observations.
- Observe one authorized bounded Planning/tool interaction through completion/failure and original accounting,
  including supported prompt/tool mapping and finite admission behavior despite preview parameter limits.
- Establish the actual allowance source, or retain explicit unavailable status plus the provider settings link.

These affect only optional ChatGPT integration. No new readiness framework, benchmark project, implementation
TASK, spending grant, application-host shell capability or first-release dependency is proposed.

## Sources

Official pages fetched 2026-10-24; paths below are the source of the individual claims, not inherited receipts.

- S1: [SIWC quickstart](https://developers.openai.com/siwc/quickstart.md).
- S2: [OSS plan-usage overview](https://developers.openai.com/siwc/token-sharing-open-source.md).
- S3: [Registration and sign-in](https://developers.openai.com/siwc/token-sharing-open-source/sign-in.md).
- S4: [Self-hosted VMs](https://developers.openai.com/siwc/token-sharing-open-source/self-hosted-vms.md).
- S5: [Accounts, sessions and usage](https://developers.openai.com/siwc/token-sharing-open-source/profiles-and-sessions.md).
- S6: [Token reference](https://developers.openai.com/siwc/token-sharing-open-source/token-reference.md).
- S7: [Models and inference](https://developers.openai.com/siwc/token-sharing-open-source/models-and-inference.md).
- S8: [Preview limitations](https://developers.openai.com/siwc/token-sharing-open-source/preview-limitations.md).
- S9: [Usage UI guidelines](https://developers.openai.com/siwc/ui-ux-guidelines.md).
- S10: [Errors and recovery](https://developers.openai.com/siwc/token-sharing-open-source/errors-and-recovery.md).
- S11: [SIWC through Codex app-server](https://developers.openai.com/siwc/token-sharing-open-source/codex-app-server.md).
- S12: [Website sign-in](https://developers.openai.com/siwc/website.md) and
  [requesting a client ID](https://developers.openai.com/siwc/request-client-id.md).
- S13: [ChatGPT/Codex pricing](https://learn.chatgpt.com/docs/pricing.md), plan cards and usage FAQ sections.
- S14: [Codex app-server](https://learn.chatgpt.com/docs/app-server.md), auth endpoints and rate-limit/usage sections.
- R1: [WF-044 provider ownership](WF-044-per-user-openrouter-and-allowance.md),
  [WF-049 model/usage handoff](../tickets/WF-049-select-first-browser-journey-and-switching-behavior.md#existing-owner-handoff-and-concrete-gaps),
  [WF-047 host/credential boundary](../tickets/WF-047-bound-remote-harness-git-and-candidate-execution.md).
  These remain prior planning/owner evidence, not freshly qualified implementations.

Discovery used the official [developer index](https://developers.openai.com/llms.txt),
[SIWC index](https://developers.openai.com/siwc/llms.txt) and
[redirected product index](https://learn.chatgpt.com/docs/llms.txt). The scratch receipt index contains **24 requests:
19 successful snapshots and five HTTP-308 failures/redirect observations**. The initial old Codex auth/pricing/SDK
paths and index did not return source through the fetch helper; an explicit index redirect inspection identified
the current product documentation. The SIWC documents and relevant new-location sections—not failed paths—support
these findings. The successful general auth snapshot was not needed for the conclusions. No content was fetched
from a logged-in account, settings page, interest form submission or provider API.

## Open questions

- The product choice is settled: defer optional ChatGPT integration until after first production. Revisit only
  through later explicit selection, rechecking then-current documentation and account/runtime evidence.
- Exact shared-household hosted eligibility, browser-only grant and per-account limits remain unqualified; no claim
  of universal rejection, free/unlimited use or a successful private installation is made.
- Actual protected OAuth/runtime supplier, finite admission mapping and allowance observations remain later owner
  work if pursued. No account inspection, OAuth, token transfer, inference, full application build or runtime probe
  occurred. No paid Jev discovery was used; all changes are uncommitted planning work.

Next: `/skill:wayfinder work WF-052` — the next optional map frontier, not automatically started. This research
remains the named ChatGPT follow-up after first production; no implementation/decomposition is authorized.
