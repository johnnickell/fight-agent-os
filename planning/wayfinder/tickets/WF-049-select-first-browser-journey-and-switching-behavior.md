# Select the first browser journey and switching behavior

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Private production rollout](../private-production-rollout-map.md)
**Depends on:** [WF-044](WF-044-qualify-per-user-openrouter-and-allowance-ownership.md), [WF-047](WF-047-bound-remote-harness-git-and-candidate-execution.md)

## Question

Which single browser Planning/help journey makes the first private installation useful, and what explicit
provider/model, context-transfer, failure and usage behavior must that journey accept?

## Must decide

- Choose a real bounded outcome under [TICKET-00092](../../tickets/00092-TICKET.md),
  [TICKET-00104](../../tickets/00104-TICKET.md) or [TICKET-00107](../../tickets/00107-TICKET.md), reusing existing
  conversations/proposals and actual Agent identity. Generic chat is not automatically covered by bounded help;
  identify any genuinely new destination without creating it. No disconnected demo as acceptance.
- For Planning, retain intake/answers, human-reviewed proposal, explicit authoritative Apply, reconnect and
  original-result recovery. For advisory help, retain bounded evidence, no autonomous action and useful
  deterministic help when the model is unavailable. Do not silently remove accepted owner requirements.
- Select permitted models by role, tool/function needs, output quality and cost evidence; inexpensive is an
  option, not a quality guarantee. Use initiating user's protected OpenRouter connection and finite limits.
- Specify selection before a run; explicit provider/model change in an existing conversation; what context moves,
  minimization/disclosure approval and a linked immutable continuation with a concise change summary.
- While a request is active or its result is uncertain, reconcile/stop the original attempt and preserve its
  accounting before admitting another. Cancellation is not proof that a request was unsent or free. No silent
  substitution, paid fallback, user/account switch, blind regeneration or automatic budget expansion.
- Keep one qualified provider usable independently of another. Initial browser launch is OpenRouter-only;
  unavailable future ChatGPT is visible if exposed, not a second-provider requirement or local Pi fallback.
- Show recorded conversation/workflow/repository usage through existing accounting/projection owners; attribute
  tokens/cost/estimates and unknowns. Retain separate account/key allowance with source/scope/time/freshness/reset,
  or honest stale/partial/unavailable state. Disconnected harness does not imply zero or fresh balance.
- Prevent duplicate totals across cumulative streams, retries, reconnects and multiple agents; parallel agents
  do not receive independent account allowances. Do not infer provider capacity by subtracting local usage.
- Define later actual multi-user/isolation, browser accessibility, reconnect, failure and explicit-budget evidence.
  Preserve current permissions, transcript privacy, no browser-persistent business state and original outcomes.

## Resolution boundary

Choose the bounded launch journey and UX/acceptance contract inside Wayfinder. No implementation, live model
request, key enrollment, permission grant or existing requirement amendment. Reference exact owners and missing
suppliers in the map handoff; new EPIC/TICKET/TASK planning remains a later authorized operation.

## Resolution

Closed on John's journey, rollout and model-selection decisions, with the usage/owner reconciliation below.
This closes the product-policy interview, not implementation, migration or production deployment.
OpenRouter-first, per-user protected connections and independent environment budgets are already approved;
production caps will likely be higher than staging, with no amounts selected. WF-048's optional UAT and exact
artifact promotion apply to this project, not generic changes to other projects' workflows. No live provider
request, implementation or delivery-record amendment follows from this interview.

### Selected first browser journey: Wayfinder Planning

John selected **repository-scoped browser Wayfinder Planning**, under
[TICKET-00092](../../tickets/00092-TICKET.md), and identified the transition from file-based to database-backed
Planning together with usable in-browser Planning as the right milestone for first production deployment.
The representative acceptance journey is:

1. Sign in and select an already registered, authorized Repository. Submit an idea; inspect relevant existing
   maps/artifacts and explicitly choose existing work or new work, rather than silently creating a duplicate.
2. For new work, discuss the destination and done condition with the actual Planning Agent using the initiating
   user's protected OpenRouter connection. Review/edit the proposed map and explicitly Create it. For existing
   work, resume its current eligible decision rather than starting an unrelated conversation.
3. Work one HITL decision, review the proposed result and explicitly apply the authorized Planning changes.
   Agent discussion is not accepted state. Further grill/decomposition/execution remains a distinct operation;
   completing this journey does not automatically produce TICKETs/TASKs or launch code work.
4. Leave and return to the same durable conversation/question/proposal with truthful progress, original-result
   recovery and attributed usage. Another authorized planner does not automatically gain private transcripts/drafts.

This directly serves the approved hosted Planning destination while leaving independent laptop coding/checks
intact. It is the selected first acceptance scenario, not a reduction of TICKET-00092's accepted scope, a new demo
TASK, claimed availability or Board reprioritization. Its complete required privacy, current authority, reconnect,
Stop/Abandon, conflicts and explicit model/context-change contracts remain; the resolution below carries those
launch interactions through their existing owners.

### Existing-owner comparison and dependencies

- **Browser Planning — selected:** TICKET-00092's existing TASK-00361/00362/00363/00365 and related privacy/
  continuation slices consume TICKET-00090 durable conversations and TICKET-00091 proposals/explicit Apply.
  Database authority and the browser journey are the intended production rollout milestone, not a requirement
  to possess a previously qualified production installation before developing them. Prepare and check the real
  behavior locally/in staging; establish target-specific operation during the authorized deployment. This does
  **not authorize cutover now**, create a Markdown/database dual writer or substitute a chat transcript for
  authoritative Planning. Markdown remains authoritative until the approved, verified migration. Actual protected
  runtime/provider/Agent suppliers still belong to their existing owners, not a new readiness project.
- **Creation-failure support — alternative:** [TICKET-00104](../../tickets/00104-TICKET.md) explains an actual
  creation attempt from bounded evidence/playbooks. It is not general product help and depends on the creation
  flow plus TASK-00428's unresolved pre-registration Agent/assignment scope. Its installation-key wording still
  needs WF-044's later authorized per-user amendment. Model-free guide/export/recovery remains independent.
- **Instruction suggestions — alternative:** [TICKET-00107](../../tickets/00107-TICKET.md) proposes a complete
  diff for one selected instruction file, with separate human Save through the existing form. It is not general
  coding chat or Planning Apply; actual file-custody/read/write and provider/session qualification remain required.
  Choosing it would prioritize assisted instruction work rather than the central Planning outcome.

These owners were read directly for their concrete journey/scope and prerequisite contracts. No application
provider calls, research fetches or new relevance-screening requests were needed for this opening comparison.

### Rollout intent: deploy the useful milestone, then fix observed defects

John explicitly rejects turning these planning answers into additional TASKs, tests or rules that block progress
until a production environment already exists. Target the database transition plus useful browser Planning,
deploy when that milestone is ready through the ordinary authorized operator path, and let John judge actual
usefulness in production. Defects found there become concrete bugfix work when he requests it, not speculative
pre-deployment test/governance projects.

Do not create a circular “production must already work before we can implement or first deploy it” prerequisite.
Separate real feature dependencies from target-specific deployment steps: a missing production target prevents
observing that target, not local implementation, product tests or staging work that do not need it. Keep necessary
packaging, migration, configuration and smoke/recovery checks within the actual delivery work, using owning tools;
do not create a readiness framework, permanent guard, synthetic refusal test or TASK solely to enforce this map.
Missing capabilities must be described concretely, without making every future managed supplier a first-release gate.

This is a practical first rollout, not a demand for a defect-free service or complete advance production proof.
Normal repository checks and independent acceptance still apply, as do the already-agreed privacy, explicit
migration/promotion, budget and backup/recovery precautions. They do not become a second certification process.
Observe actual target health after deployment and report failures honestly; do not label an untested deployment
successful or proceed through a known destructive/security problem merely to collect a future bug. No additional
human approval question is needed to restate this rollout posture.

This milestone selection is not immediate provisioning, spending, migration, deployment or delivery-record change
authority. It does not create an automatic bugfix TASK or start implementation. WF-050 should compose the practical
operator/recovery handoff around this milestone, not add a new production-readiness backlog. Existing owner records
are unchanged; any genuinely necessary later amendment must identify the actual product behavior or delivery blocker.

The subsequent model-selection agreement below resolves selection/continuation behavior. Exact model IDs, numeric
provider caps/reset windows and cache tuning remain configuration for ordinary implementation/use, not a new
planning or certification project. Reuse established interruption/accounting requirements rather than inventing
more approval gates; missing numeric limits are not implied spending authority.

### Approved direction: configurable default, current model choices and explicit switching

John liked the configurable-default and switching proposal, adding a requirement to update the default from
OpenRouter's current available models, fetched on load or through a light cache. Retain the behavior below with
the provider-backed catalog refinement that follows; no fixed model name or permanent static shortlist is selected:

- Use the existing Agent/profile selection and permitted-model catalog to supply a sensible **Planning default**.
  Show the selected OpenRouter model before sending and let the user choose another allowed, compatible model.
  Do not create a new model registry, require separate models for every role, or automatically route to a cheaper/
  stronger model. Distinct Project Manager/Explorer identities can use the same model where suitable.
- Select the initial concrete default during implementation from current OpenRouter offerings, considering
  tool/structured-output support, Planning usefulness and cost. Thereafter expose refreshed provider-backed model
  choices and an authorized **Set as default** action rather than requiring a code change to follow model releases.
  Filter choices by applicable policy and supported capabilities, without freezing the provider list at implementation
  time or adding a model-benchmark/certification TASK. Normal bounded product use establishes suitability; no
  production environment is needed to develop this behavior. Unknown prices/limits remain explicit.
- Keep the chosen model for the current conversation until the human changes it. Default/catalog changes apply to
  future selection, not silent replacement of an active model. Outage, removal or budget exhaustion shows the real
  problem and offers explicit allowed choices; it never authorizes automatic paid fallback or budget expansion.
- **Switch between turns**, not halfway through a response. For an active turn, finish it or use the existing Stop
  and original-operation recovery path first. A dropdown change cannot replay an uncertain request or release its
  outstanding liability. Reuse those existing semantics rather than add another stop/recovery gate or engine.
- Keep a coherent conversation in the browser: show a concise model/context change summary and one explicit
  confirmation. Internally create the existing linked immutable continuation, retaining the prior history rather
  than rewriting it. Reconnecting without a change needs no confirmation, new chat or repeated interview.
- Carry forward authorized accepted answers, current question and proposal references, plus the minimized summary/
  relevant messages and approved repository context needed to continue. Show the scope of context being supplied;
  model selection is not permission to disclose unrelated files, another user's history or hidden reasoning.
  If context must be reduced for the new model, disclose that rather than promising the complete transcript fits.
  Revalidate retained proposals; no regenerated answer, transferred approval or automatic Apply follows a switch.
- Initial browser switching stays **between OpenRouter models** on the user's selected environment connection.
  Changing the connection/disclosure boundary needs its actual authorization; another vendor through OpenRouter
  is not automatically an identical data-handling policy. ChatGPT/OAuth and local Pi credentials remain outside
  this first browser journey. Same-provider switching does not claim cross-provider support.

Existing owners re-read for this direction: [TASK-00343](../../tasks/00343-TASK.md) owns safe allowed-model catalog
facts, [TASK-00342](../../tasks/00342-TASK.md) owns immutable linked continuations, and
[TASK-00367](../../tasks/00367-TASK.md) owns browser selection/change confirmation and proposal applicability.
TASK-00344/00345 retain budget admission and original accounting. No new supplier TASK, owner amendment, live model
request, fresh public-source research or comparison benchmark is created by this planning update. Exact numeric budgets remain
separate environment configuration, not an assumed unlimited allowance or a new pricing decision made here.

### Current model discovery and updating the default

Implement the requested refresh within the existing provider/catalog and browser selection owners, not a new
service or certification effort. Suggested engineering default: a **15-minute server-side cache**, tunable without
changing this policy decision. John approved a light cache/on-load approach, not a particular TTL.

- On opening the model picker/settings, use a fresh cached catalog or request a bounded refresh when absent/stale.
  Expose **Refresh models** for an explicit refresh and **Set as default** for a selected eligible model. Coalesce
  concurrent loads and rate-limit refreshes; do not poll on every render, token, message or browser reconnect.
  Display last-checked time, model identity, relevant capabilities and reported pricing with honest unknowns.
- Discovery and changing a default are distinct: refreshing choices never changes the selected model or default.
  Apply a default update only to its displayed authorized scope through the existing Agent/profile/configuration
  owner; ordinary users cannot overwrite another user's or an installation-wide default without that authority.
  Future conversations use the new default. Existing conversations retain their model unless the human explicitly
  switches through the already-agreed continuation flow. No migration of old history or paid retry follows.
- Keep all authenticated OpenRouter discovery server-side with the selected user's protected environment
  connection. Cache filtered results by the relevant connection/environment/configuration generation, not in a
  global account-visible list. Current access and connection replacement invalidate access/applicability; do not
  reuse another user's results or leak keys. Public nonsecret catalog metadata may be shared where appropriate.
- Apply current application policy/capability constraints to provider results. A provider listing is a discovery
  fact, not a new permission, a change to budgets, a guarantee of capacity or proof the model works with every
  tool/runtime. Exact IDs/pricing observations should be retained rather than guessing what “latest” resolves to.
  Filtered metadata reflects provider preferences as observed at refresh, not instantaneous knowledge of every
  external policy change. Normal request handling still owns actual unavailable/denied outcomes.
- On refresh failure, keep the last known list with its timestamp/stale warning and a retry option; do not silently
  replace the default, erase history or claim a cached entry is currently available. With no cached result, show
  catalog unavailability. A removed/ineligible default is visible and needs explicit replacement; no automatic
  cheaper/stronger fallback or budget expansion. Catalog trouble does not disable unrelated Planning reads.
- This is a **model-catalog metadata read only**. It sends no conversation/source content and starts no generation,
  allowance refresh, credit lookup or credential enrollment. Existing allowance/status reads retain WF-044's
  explicit-refresh/no-implicit-polling contract; the requested catalog-on-load behavior does not amend that boundary.

### Source support and later owner mapping

[WF-044's existing research](../research/WF-044-per-user-openrouter-and-allowance.md) and its official documentation
snapshots observed on 2026-10-23 establish the relevant API shapes:

- [General models catalog](https://openrouter.ai/docs/guides/overview/models.md): `GET /api/v1/models` exposes model
  IDs, capabilities/context and pricing metadata. It is not an account-specific capacity promise.
- [Models filtered by user preferences/privacy/guardrails](https://openrouter.ai/docs/api/api-reference/models/list-models-filtered-by-user-provider-preferences-privacy-settings-and-guardrails.md):
  authenticated `GET /api/v1/models/user` supports a list reflecting the selected key's provider preferences and
  relevant restrictions. Use the protected owner rather than exposing that key in browser JavaScript.

This update reuses dated public documentation, not a fresh endpoint/account call or live-path qualification.
Actual supported authentication/filtering/cache behavior is ordinary integration work in the consuming feature;
it does not require production to exist or create a separate qualification TASK.

TASK-00343 owns nonsecret catalog/policy observations and TASK-00346 is the proposed protected OpenRouter metadata
adapter, consistent with WF-044's credential boundary. TASK-00343 currently excludes live requests: do not silently
put key handling/network effects there or amend its scope in this session. Existing profile/default writers and
TASK-00367's browser selection consume the safe result; exact default-settings UI and refresh composition need
later authorized owner allocation/amendment within the product work, not a second model registry or new test of
planning rules. TASK-00399 remains allowance-specific; do not turn it into model discovery.

### Reconciled usage presentation

Carry the existing honest-accounting requirements into the selected browser Planning journey. This is a concrete
presentation handoff, not a new analytics product, ledger, billing service or further approval ceremony. Suggested
placement is a compact conversation summary with expandable details; exact visual layout belongs to the existing
browser owner.

| Surface | Display | Meaning and limits |
|---|---|---|
| Conversation summary | Environment, selected model and recorded conversation cost/token summary, with partial/stale status where relevant | Conversation activity, not an OpenRouter wallet balance or complete Repository bill. Preserve original-attempt attribution across linked model changes |
| Turn details | Requested model, actually reported model where available, input/output tokens, other supported usage categories, reported cost/currency or clearly marked estimate, source/time | Unknown is not zero. Do not infer reported identity from an alias or expose hidden reasoning, prompts or credentials as usage metadata |
| Applicable budget | Authorized view of the environment cap/window, recorded consumption and reserved/uncertain capacity, plus relevant per-user/connection restrictions | Application admission policy, not provider funds. Separate staging/production; no borrowing/reset on switch, reconnect or key replacement. If the viewer cannot inspect an environment-wide total, show the applicable limit/reason without other users' private usage |
| Provider allowance | Independently loaded key/account-scoped reported limits/usage, source, report time, freshness and reset/window where supplied; explicit Refresh allowance | Keep separate from conversation totals and application caps. No safe source means unavailable, not zero balance, unlimited capacity or completed allowance integration |
| Model picker | Current or last-known provider catalog, capabilities and reported pricing with last-check/stale status | Catalog discovery only; per-token pricing is not an actual charge, wallet balance or guaranteed upper bound for the next request |

Recorded conversation totals must cover the original attributable attempts, including known charges for stopped,
failed or superseded turns; changing the displayed model does not hide earlier costs. Reuse TASK-00345's normalized
outcomes instead of summing cumulative stream events again. Include retries/new authorized attempts only once,
and do not add a parent total to the same included nested/compaction activity. Mark incomplete coverage and show
known subtotals separately from unknown/estimated amounts; do not silently combine different currencies or units.
A linked continuation preserves history and attribution without charging the same prior usage to two totals.

Update UI from committed owning observations/reloads, not fresh provider generation or implicit allowance polling.
Loading, empty, denied, stale, partial, unavailable and pending reconciliation remain distinguishable. Secondary
usage/allowance loading failure must not erase the conversation or prevent reading existing Planning. Actual paid
admission still uses its authoritative budget checks; a missing display is neither a budget reset nor proof that
new work is forbidden. Handle the specific operation's real state rather than blocking unrelated product work.

WF-044's ordinary-key `/api/v1/key` source supplies only its documented scope. Do not collect management keys for
wallet totals, subtract locally recorded usage from a provider snapshot, duplicate a shared account allowance for
every Agent or treat a null limit as unlimited service. The application connection routes already selected in
WF-044 return sanitized stored observations; allowance refresh remains an explicit bounded action. The approved
on-load/light-cache **model catalog** refresh is a different operation and grants no background credit inspection.

Show recorded facts already available from the chosen provider. If a category or source is unsupported, disclose
that and keep the useful journey; do not make every possible billing field a new launch requirement. Exact model
IDs, finite environment/connection limits, period/reset definitions and cache freshness values are configuration
within the real feature work. Set actual limits under the appropriate authority before paid use; this map selects
no amounts, free tier, guaranteed capacity or new spending. Hosting's sub-$100/month preference remains separate.

### Existing-owner handoff and concrete gaps

References below preserve existing ownership. Proposed extensions require later authorized owner planning; they
are not delivery-record edits, newly executable prerequisites or a request for proof-only TASKs.

| Product behavior | Existing owners to reuse | Concrete reconciliation |
|---|---|---|
| Database-backed Planning and first rollout | [TICKET-00028](../../tickets/00028-TICKET.md) migration/cutover, TICKET-00026/00027 authoritative semantics/order; [TICKET-00092](../../tickets/00092-TICKET.md) browser journey | Keep the accepted source manifest/rehearsal/atomic authority switch and frozen-source retention, within the real migration work. Pair usable browser Planning with first rollout; do not require production to already exist before implementation or turn this into another migration/production-certification TASK |
| Conversation, human answers and explicit Apply | TICKET-00090, [TASK-00352](../../tasks/00352-TASK.md) same-conversation inspection, TICKET-00091 proposals/Apply; TICKET-00092 browser consumers | Preserve original conversations/attempts, private drafts, accepted answers, revision checks and explicit effects. Usage is a safe derived view of original provider facts, not another transcript writer or fake execution Workflow |
| Provider admission and accounting | [TASK-00344](../../tasks/00344-TASK.md), [TASK-00345](../../tasks/00345-TASK.md), [TASK-00346](../../tasks/00346-TASK.md) | Carry per-user/environment/connection-generation binding into the existing protected path. Reuse reservations, original usage normalization and uncertain liability. Per-user key lifecycle and actual host/broker participants retain WF-044/047/048's precise gaps; a cloud host is not their implementation |
| Browser Planning usage | TICKET-00090's attempt/inspection and TICKET-00092's existing conversation UI, consuming TASK-00345; coordinate with [TICKET-00098](../../tickets/00098-TICKET.md) | Allocate the exact safe conversation-to-original-usage query/projection and UI extension within these owners. [TASK-00396](../../tasks/00396-TASK.md) is Workflow/phase attribution, and [TASK-00391](../../tasks/00391-TASK.md) is a Workflow Dashboard: neither currently supplies standalone conversation usage. Do not silently widen them, fabricate a Workflow or introduce a parallel ledger |
| Provider allowance | [TASK-00399](../../tasks/00399-TASK.md), proposed TASK-00346 key-info adapter from WF-044 | Keep source/refresh mapping hold and broader-scope disclosure. Reuse safe observations for the browser without dragging the complete Workflow Dashboard into early Planning. Missing allowance is visible/unavailable, not a blocker on useful recorded conversation usage or a claim the integration is done |
| Current models and defaults | [TASK-00343](../../tasks/00343-TASK.md), protected TASK-00346 metadata adapter, existing Agent/profile/default writers | Later owner amendment must add the requested bounded model discovery/cache and exact authorized default-setting surface. TASK-00343 stays nonsecret; it does not gain key custody. Do not put model discovery in TASK-00399 or freeze the picker to an implementation-time list. No new registry, benchmark or cache-certification TASK |
| Switching and retained proposals | [TASK-00342](../../tasks/00342-TASK.md), [TASK-00367](../../tasks/00367-TASK.md), existing proposal applicability owner | Preserve one explicit between-turn linked continuation, current disclosure/budget authority, same visible conversation and candidate revalidation. Default changes and catalog refresh do not switch active sessions or replay requests |
| Deployment and follow-up defects | WF-048's operator/artifact handoff; WF-050's practical cost/recovery decisions | Ordinary implementation checks, independent acceptance and agreed data/credential recovery remain. Real target observations occur during authorized deployment/use. Concrete observed defects become requested bugfix work; no speculative prerequisite/gate/test suite is introduced |

Exact conversation-usage projection/UI allocation, default-settings/discovery composition, per-user connection
lifecycle and actual protected runtime/source integration remain implementation/owner-amendment gaps, not reopened
product decisions or invented TASK IDs. Existing TASK-00346/00350/00399 holds remain where applicable; these do not
become a blanket requirement to complete every future managed feature before browser Planning development.

### Delivery evidence in the owning work, not a new test project

Use the selected real journey to observe usable intake, reviewed map/decision Apply, reconnect and explicit model
change. Its ordinary owned behavior/integration tests cover duplicate/late accounting, incomplete/unknown cost,
exhausted budgets, failed refresh, missing allowance, current-user/environment isolation and retained proposals.
Use current browser accessibility expectations for keyboard/focus/status, narrow/wide layouts and light/dark;
no fixed design artifact or separate UI certification is required by this map.

These are reminders of existing product requirements, not instructions to create tests for this decision, TASK
metadata, provider SDKs, configuration files, wrappers or readiness mechanisms. Run owning tools directly for
integration/setup concerns. Normal `./bin/build`, independent Review and applicable QA remain in their actual
implementation work; do not duplicate them in a Wayfinder-derived gate. Use separately authorized bounded real
provider interactions where the feature needs them, and never label fixture results as a live provider receipt.
Production itself is not a prerequisite for this development; report actual deployment defects rather than
promising advance proof of every production condition.

### Closure and next handoff

WF-049 is decision-complete: browser Wayfinder plus database transition is the first production rollout milestone;
configurable current OpenRouter model choices/default updates and explicit between-turn switching are selected;
usage, application budgets and provider allowance have distinct truthful presentation and existing-owner handoffs.
Concrete model names, numeric caps/reset periods and the suggested cache TTL remain ordinary configuration, not
new EPIC destinations, automatic grants or reasons to block this map on production existence.

No EPIC/TICKET/TASK, Board priority, source code, tests, credentials, runtime, migration or provider operation changed.
No public fetch or relevance-screening request was needed for this reconciliation. Source references are repository
contracts and the already-labelled WF-044 documentation, not fresh live integration evidence. Full application
build and independent acceptance were not run for this planning-only change.

`./bin/planning-check --write` and `./bin/planning-check` passed: **645 delivery records, 496 active**, generated
views current. Tracked `git diff --check` and whitespace/path-scope checks across **17 changed/untracked Wayfinder
paths** passed; tracked delivery/configuration files remain unchanged from HEAD. All work remains uncommitted in
the selected isolated planning checkout. These are planning receipts, not fresh application or live-provider evidence.

Next: `/skill:wayfinder work WF-050` — finish the practical deployment/cost/recovery decisions around the selected
milestone, without creating a production-readiness framework or automatically starting that interview.
