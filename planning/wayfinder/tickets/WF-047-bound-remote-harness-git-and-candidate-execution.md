# Bound remote harness, trusted Git and candidate execution

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Private production rollout](../private-production-rollout-map.md)
**Depends on:** [WF-043](WF-043-bound-private-release-and-environment-names.md)

## Question

Where should trusted harness/Git operations and optional candidate execution live when production owns
application/workflow state and policy, and how are their independent authorities enforced?

## Must decide

- Separate browser model calls, trusted repository administration, candidate-code checks and release-artifact
  build/deployment. Custom local harness access to production is authenticated API/MCP, not authority from
  physical location, an SSH login or a model-supplied account/Agent identity.
- Reuse [TICKET-00061](../../tickets/00061-TICKET.md)'s authenticated tool discovery/invocation, current grants,
  replay/destination controls and credential revocation. Identify exact missing transport/enrollment/participant
  contracts without replacing existing identity, protected credentials or Workflow authority.
- Decide what repository/source capabilities browser Planning actually needs and which trusted local/remote
  component supplies them. No mandatory candidate-execution worker merely to start a browser conversation.
- Evaluate dedicated scoped SSH repository credentials held by trusted Git administration outside agent-editable
  workspaces. Distinguish fetch/push authentication from server-login keys and GitHub API permissions for PRs,
  checks and administration. Host verification, scope/revocation and secret-safe status need their own contracts.
- Reuse existing Git owners/adapters where appropriate. [TICKET-00102](../../tickets/00102-TICKET.md) owns initial
  project publication, not general Git administration. Name missing suppliers rather than force-fitting that owner.
- Candidate checks may later run locally, in GitHub Actions or on a separately qualified worker. Keep candidate
  code away from production secrets, controller/Git credentials and application contention; qualify CPU/memory,
  storage/PIDs/time/egress and independent protected results before admitting execution.
- Preserve the [tool-mediated boundary](../../../docs/engineering/TOOL_MEDIATED_EXECUTION.md): worktrees are not
  isolation, managed-profile Agents receive no unrestricted shell/host administration/Docker socket, and candidate-controlled
  exit zero is not authoritative acceptance. Existing current-policy and recovery holds remain.
- Keep GitHub CI as a valid release artifact builder; candidate tests/compilation/dependency scripts were the
  execution concern, not an approved replacement of CI. Define source/artifact identity and result provenance.
- Specify enrollment/revocation, disconnection, stale local observations and original-operation reconciliation.
  MCP transports allowance observations but does not establish their authority or freshness.

## Resolution boundary

Set architecture, operation/credential ownership and qualification boundaries through one interview. No new
runtime permissions, keys, SSH connections, repository effects, candidate execution, builds or source amendments.
Do not infer remote managed readiness from the existing local harness or historical prototypes. Identify a
minimal browser path and explicitly defer execution capabilities that are not needed for it.

## Resolution

Closed on the recorded architecture/policy approvals and existing safety/recovery contracts. Actual remote
participants, Git credential mechanisms and managed candidate-check suppliers remain unselected/unqualified.
John approved the hosted-service/contributor-laptop split and optional
human TASK responsibility below. John specified Git-flow's `develop` default and contributor feature-branch/PR
flow, then approved the snapshot/refresh and shared identity/reference allocation contracts below, including
unnumbered disconnected drafts. John also approved read-only hosted Git retrieval with protected credentials,
contributor-owned feature/PR publication and deferred managed Git writes. He approved the initial check-placement
policy while explicitly retaining current local Agents' bash checks until a qualified MCP/browser replacement.
John clarified that humans sign in through the browser, the custom harness protects HMAC credentials for locally
instantiated Agents based on templates, and MCP has no interactive sign-in/session. The proposed separate
client/machine enrollment step is withdrawn. He approved protected harness-recipient binding within the existing
browser provisioning action, with protected delivery/activation behind the scenes. Existing interruption/revocation
requirements carry forward without another approval ceremony. Exact implementation/qualification gaps below
remain held. Closure is Wayfinder direction, not delivery amendments, live readiness or installed capabilities.

### Approved hosted service and contributor-laptop split

John agreed to one hosted control plane with independently authenticated contributor tooling. His later
clarification below rejects a separate laptop enrollment/sign-in workflow:

- The hosted application owns shared Planning/Workflow operations and current authorization policy as those
  capabilities are delivered. Markdown remains authoritative until the separately approved, verified cutover;
  this topology does not create a second Planning authority or approve database migration.
- John and other authorized contributors, including his son, work in their own registered repositories/checkouts
  with their own identities and bounded grants. A contributor laptop need not host a duplicate authoritative
  Planning installation. Local native tooling remains human-controlled; this proposal does not claim it is
  confined by the future managed tool profile or make its results authoritative acceptance.
- An optional trusted local harness connects outward through qualified authenticated API/MCP capabilities.
  No inbound laptop listener, production SSH access, copied administrator credential or shared family provider
  key is needed for this collaboration path. Browser JWT and Agent HMAC remain distinct; exact enrollment,
  transport and participant contracts still need reconciliation with TICKET-00061.
- Browser Planning/help remains useful while contributor laptops are asleep/offline. Live laptop-only or
  uncommitted repository context is then unavailable, never silently current; the approved snapshot contract below
  defines source selection, not a qualified supplier. No remote shell, candidate worker or automatic execution is
  required merely to converse.

Offline above means the contributor's laptop is asleep/disconnected, not an offline browser. Browser Planning
still requires connectivity to hosted Fight and its provider. It can use authorized server-available Planning/
conversation context as delivered; actual repository source suppliers remain unqualified. No offline browser/PWA,
source synchronization, automatic publication of laptop changes or complete server-held source is implied.

This approves architectural direction, not deployment topology, remote managed readiness, per-task execution,
Git writes or credential enrollment. The later sections reconcile checks versus release CI, source/result
provenance, protected credential delivery, current grants/revocation and operation recovery.

### Approved optional human TASK responsibility

John raised optional TASK assignment to human users so he can delegate bounded, nonblocking work directly to
his son while other work proceeds. He questioned whether Agent claiming alone would be sufficient as development
accelerates. John approved this as an eventual collaboration capability, not a first-browser launch gate, without
allocating tasks, altering Board priority or extending runtime authority.

The approved direction is one optional responsible human per TASK, separate from its current execution owner/attempt:

- Human assignment records durable responsibility, even before work starts or while that person's laptop is
  offline. It does not itself mark a TASK in progress, acquire an execution lease or reserve an Agent. Unassigned
  work remains available through existing authorized selection/admission policy.
- Human-assigned work is excluded from unrelated autonomous pickup. The responsible human can request authorized
  Agent help through existing grant/start operations; assigning responsibility grants no repository, provider,
  execution or delegation privileges. Scope expansion still requires its actual owner, not an assignment label.
- Start, delegation and transfer must share the existing exclusive TASK execution ownership/claim boundary across
  managed human-led and Agent-led paths. No competing executor may silently take over assigned/active work.
  Already-started work needs explicit reconciled handoff, not an assignee-field edit or expired connection.
  This coordination does not physically prevent untracked manual edits in external laptop tools.
- Dependencies, readiness and required checks/review remain unchanged. Assignment can help select parallel work,
  but cannot make blocked work executable or guarantee non-overlapping source changes. Show assignee, execution
  owner, actual progress and dependency/hold reasons separately. Later dependent work waits or receives an explicit
  authorized reassignment/handoff; never satisfy a blocker merely because its assignee is busy or unavailable.
- Keep this an eventual collaboration capability, not a new first-browser release gate. Exact assign/accept/
  decline/reassign/unassign commands, lifecycle presentation, authorization and interrupted human-work recovery
  need later owner planning; no new status, command/API or metadata schema is approved here.

Proposed reuse: [TICKET-00026](../../tickets/00026-TICKET.md) owns semantic TASK responsibility/history;
[TICKET-00027](../../tickets/00027-TICKET.md) owns canonical queue eligibility and explanations;
[TICKET-00032](../../tickets/00032-TICKET.md)/TASK-00139 retain exclusive claim/start/recovery;
[TICKET-00064](../../tickets/00064-TICKET.md) remains Agent/Team selection, not a human-assignee registry.
Existing inspected contracts do not supply this human TASK-assignment use case explicitly. Treat the mappings
as proposed amendments/integration gaps, not already accepted delivery scope or a second scheduler. Preserve
current standing-policy authorization and all qualification holds; this proposal installs none of them.

Human-assignment approval does not authorize editing the proposed delivery owners, adding an assignee field to
current Markdown, creating an EPIC or assigning any actual TASK. No EPIC, TICKET, TASK, Board order, credential,
runtime or external project was changed. Authorized optional relevance
screening covered three explicit owner candidates: queue/Agent-selection records were positive; canonical
Planning lifecycle was not positive and was read directly for its concrete ownership need. Coverage was complete;
three evaluator requests reported $0.000315546, with no unknown cost. These were discovery calls, not application
provider qualification or inference for browser Planning.

### Approved Git-flow default and repository snapshot contract

John specified that this project uses Git-flow: Planning normally looks at the latest `develop` commit;
contributors push feature branches and open PRs. Treat `develop` as the configured default Planning/source branch,
not an instruction to change GitHub's default branch, production's release reference or another project's settings.
No push, PR, merge or remote setting change is authorized by recording this workflow.

John approved resolving the latest available remote `develop` head on new Planning context or explicit refresh and
binding its exact commit to the session. A cached observation is not a claim of the latest remote head. Existing
conversations retain their snapshot; show drift and explicitly revalidate before controlled operations. A selected
feature branch/PR can supply review or task-specific context when explicitly requested and authorized, without
silently changing the normal Planning default. Local terminal Planning records whether it is using this shared
baseline or an explicitly selected local worktree/diff; neither silently impersonates the other.

The approved direction uses authorized read-only source snapshots of explicitly selected published revisions
from registered repositories, supplied by a trusted retrieval component separately from deployed application files. Published
here means pushed to the registered remote, not publicly visible; private repositories remain private:

- Bind each source view to its Repository identity, exact commit and context/configuration revisions. Show which
  revision is being discussed and retrieval time; an observed branch head is not an assurance of current freshness.
  A conversation never silently switches source because a branch advances. Apply TICKET-00025's drift/authority
  rules before controlled operations; historical source or a cached snapshot cannot grant stale permissions.
- Use server-available authorized Planning/conversation context plus this pinned source without needing a laptop
  online. In Markdown-authoritative mode, published repository Planning may lag a contributor's unpublished edits;
  label that limit rather than treating the published copy as a new or globally latest Planning authority.
- Laptop-only changes are excluded unless a contributor explicitly supplies a bounded identified snapshot through
  a later qualified path. Do not require such dirty-worktree upload for the initial browser path or silently sync
  home directories, local instructions, credentials or unrelated checkouts.
- Source is data, not executable runtime configuration. Reading a repository does not run its hooks, dependency
  scripts, tests, builds, plugins or instructions as authority. Bound file/link/size access, keep Git credentials
  outside model/app-readable source, and recheck current human/Agent repository authority for every protected read.
- Retrieval needs only qualified repository-read capability; push, PR creation, repository administration and
  server login remain separate later questions. Missing authentication, inaccessible revision, drift or lost
  authority is explicit unavailability, never an implicit laptop connection or alternate repository fallback.

Reuse [TICKET-00025](../../tickets/00025-TICKET.md)'s versioned context and TICKET-00024's registered identities;
source materialization/refresh and actual remote Git-read participation remain missing/unqualified suppliers, not
capabilities supplied by the context query itself. Mirror versus bounded source export, storage/retention, exact
Git credential type and host placement still need owner qualification; this question approves none of those
mechanisms. Read-only repository context does not publish Planning edits, authorize Git writes or replace the
existing controlled Planning-publication/cutover owners.

### Approved centralized identities and human references

John proposed UUID identity plus centralized TASK/TICKET/EPIC number reservation so terminal and hosted Planning
cannot allocate colliding references from their own checkouts. This aligns with existing
[TICKET-00026](../../tickets/00026-TICKET.md): COMB-backed internal identities, immutable repository-local human
references, atomic per-Repository/per-artifact-kind allocation, idempotent creation and no reference reuse.
Do not create a parallel numbering registry or infer that source branch names can arbitrate identity.

John approved the following integrated creation contract:

- Terminal and browser use the same authenticated authoritative creation capability. Assign the human reference
  atomically with the created artifact and identity, not by scanning a local `develop` checkout or Git branch.
  Reference sequences remain per Repository and kind under the existing contract, not one global cross-project
  integer. UUIDs use the owning factory/library and existing supported identity contract, not invented literals
  or an independently selected UUID variant.
- Prefer allocation on authoritative creation over a separate reserve-now/create-later number pool. Idempotent
  retried requests recover the original artifact/reference; conflicting payloads fail rather than silently mint
  another artifact. An uncertain response is reconciled with the original command, not retried as a new creation.
  Retired/abandoned allocated references are not reused; legitimate gaps are acceptable.
- When disconnected from the authority, allow explicitly local drafts with stable draft/command identity but no
  new official TASK/TICKET/EPIC reference. On reconnect, perform authorized validated creation and obtain its
  canonical reference; do not auto-create, infer permission from the draft or merge stale edits. Existing artifact
  edits retain their identities and need the normal expected-revision conflict checks.
- Preserve historical human references at migration, account for live/archived records and seed allocator state
  from the approved complete baseline. No renumbering of current records or deletion of their Markdown occurs.

This is future integrated Planning behavior, not an immediate change to the current authorized Markdown numbering
process. Reuse TICKET-00026/TASK-00088/00089 creation and idempotency owners, TICKET-00028 migration/authority
switching and existing authenticated API/MCP consumers. Markdown remains authoritative now; browser/terminal
integration and actual allocator participation require qualification. A pre-cutover independent reservation
service, bulk leases/ranges or dual Markdown/database writers are not selected. Exact draft-to-persisted-identity
mapping and transport/recovery contracts remain owner work, not a new UUID protocol imposed by this map.

### Approved protected Git capabilities and publication

John agreed to keep hosted source retrieval read-only and contributor publication separately authorized:

- The trusted hosted Git/source participant receives only registered-repository read capability for source
  snapshots. Evaluate a dedicated read-only repository SSH key as the minimal candidate; do not reuse a personal
  all-repository key, production-server login key or another contributor's credentials. Exact credential mechanism,
  actual host and protected supplier remain subject to qualification, not provisioned by this approval.
- Trusted Git administration owns credentials, host verification, remote allowlisting and safe credential status
  outside Agent-readable/writable source and candidate execution. Models request bounded source operations, not
  key material, arbitrary remotes or unrestricted Git/shell flags. Repository instructions cannot change host
  trust, install hooks or expand fetch authority; missing/conflicting host evidence fails closed.
- Contributors publish their own authorized feature branches and open PRs using their own GitHub access. A
  human TASK assignee is not thereby granted push, PR, merge or administrative permission. Existing native local
  tooling is not claimed to be confined by a managed profile merely because this workflow is recorded.
- Hosted browser Planning initially receives no repository write capability. Managed branch push/PR creation is
  a separately qualified later capability, not a first-browser gate. SSH repository authentication covers Git
  transport, not GitHub API permissions for PRs/checks/administration; server-login SSH is a third distinct scope.
  Do not introduce a broad token to make all three appear available.
- Recheck actor/repository authority on each protected operation. Revocation/disconnection makes new operations
  unavailable; a cached source snapshot does not retain revoked read authority. Already-admitted or uncertain
  effects keep original-operation identity and require reconciliation, not a blind retry or success claim.

Reuse existing TICKET-00024/00025 identity/context and TICKET-00061 current-use boundaries. TICKET-00032's trusted
Git administration may supply appropriate local primitives, but its current local qualification is not remote
fetch readiness. TICKET-00102 remains initial project publication, not a general remote Git service. Actual
hosted retrieval/credential custody and any later GitHub API/write participant need explicit supplier allocation
through authorized owner planning; no new owner or runtime capability is declared delivered here.

### Approved candidate-check placement, retaining current local Agent checks

John agreed to no candidate-code runner on production/staging application hosts for the initial private browser
path, with an explicit transition condition: current local Agents retain their existing bash-based checks until
MCP tooling can replace that path, including a future way to request CI from MCP/browser. Do not interpret
human-controlled local development as human-only command execution:

- Contributors and their currently authorized native/local Agents retain local development/checks and
  feature-branch PR workflows, with applicable GitHub CI. No bash removal or native tool-policy change is authorized here.
  Preserve the repository's required `./bin/build` gate and independent Review/QA; hosted CI is not an implicit
  replacement or acceptance waiver. Native laptop checks are not thereby a qualified managed sandbox.
- This preserves the existing human-controlled native workflow; it does not add shell access to the future
  restricted managed Engineer profile in TOOL_MEDIATED_EXECUTION.md/TERMINAL_EXECUTION.md. That profile still
  requests fixed, qualified check capabilities through the trusted application. Bash possession itself grants
  no hosted identity, execution claim or broader operation authority.
- Hosted Planning reads approved context and coordinates delivered capabilities; it never runs candidate code,
  dependency/package scripts, tests, builds or arbitrary model commands as a side effect of a conversation or
  source read. A candidate worker is not required merely to plan. Report unavailable managed checks honestly;
  missing required checks cannot become a green result or the managed Awaiting review transition.
- A dedicated managed candidate-check worker remains an optional later topology, not a first-browser gate or
  approved hosting spend. Qualification must exclude production secrets/data, controller/provider/Git credentials,
  privileged host authority and Docker sockets; bound CPU/memory, storage/PIDs, time/output/egress, concurrency and
  retained resources, with frozen source and independently protected policy/results. Containers and worktrees
  alone do not prove that boundary. Preserve TICKET-00032/TASK-00138–00142's actual holds and required acceptance.
- GitHub CI remains a valid release-artifact builder. Candidate checks and privileged release publication need
  distinct trust/credential boundaries even if both use GitHub Actions; PR-controlled code/configuration cannot
  authorize its own check policy, receive production credentials or turn an exit-zero claim into acceptance.
  WF-048 still owns the actual staging/promotion/artifact contract and WF-050 launch/recovery acceptance.
- Bind a check result to exact Repository/source identity, required-check policy revision, invocation/profile and
  protected provenance. Committed source names an exact commit; dirty local source requires an immutable manifest,
  not just its base commit or branch label. Later edits invalidate the earlier candidate result. Local logs,
  candidate-authored reports and a passing PR check do not independently establish managed acceptance or deployment.

Before deliberately retiring the current native bash check path, qualify a usable tool-mediated replacement,
including the intended MCP/browser CI request/status/result path. Reuse TICKET-00032's check/Workflow operations
and TICKET-00061's per-use authorization; do not adopt arbitrary shell over MCP, a second Runner or broad GitHub
credentials. The exact CI-trigger supplier, supported fixed checks, protected policy/results, capacity/budgets,
source publication and uncertain-dispatch/cancel recovery are deferred owner work. CI needs an exact available
source revision: unpublished laptop changes cannot be claimed checked by remote CI. A future CI-trigger grant is
distinct from both read-only source retrieval and permission to push/merge/deploy; do not silently widen the
approved initial hosted Git-read scope. This retirement condition is not a new first-browser launch gate, an
installed CI capability or authority to change the existing managed-profile requirements.

No new checker, hosted build, provider call, candidate execution, runtime enrollment, bash restriction or CI
configuration is performed. This approves initial-rollout policy, not a replacement for the existing managed
execution implementation/qualification path or a reduction of its acceptance criteria.

### Human clarification: browser sign-in, harness credential custody and stateless MCP

John rejected an additional client/machine enrollment step. Humans sign in through the browser. The custom
harness must securely store HMAC credentials for locally instantiated Agents based on Agent templates. MCP
requires no interactive sign-in or persistent authentication session; trusted harness infrastructure authenticates
each request on behalf of the actual Agent. This is authenticated stateless invocation, not anonymous tools.
The earlier description of signing a laptop into a human account was misleading and is withdrawn.

Keep template configuration separate from actual Agent identity and credential custody: a template is not a
shared secret or authority grant. The model receives bounded tools, not HMAC material; trusted signing happens
outside model-visible context and candidate-controlled code. Exact template-to-Agent instantiation, credential
reuse/rotation and secure local storage still need qualification with existing owners, not a guessed protocol.

[TICKET-00060](../../tickets/00060-TICKET.md) already requires protected holder/destination registration and a
fixed sink for credential delivery; TASK-00234 calls that internal provisioning boundary enrollment. Preserve
those security contracts without turning them into a second user sign-in, MCP session or mandatory independent
device-registration ceremony. TICKET-00059 retains durable credential-operation fencing; TICKET-00061 owns actual
request authentication/activation/current-use checks. Stateless MCP does not eliminate server-side credential,
revocation, replay/nonce, authorization or original-operation records. Browser credential UI submits intent and
safe status, not raw HMAC keys. The actual secure delivery path into local harness custody remains unqualified;
no copy/paste-secret, OS-keychain product or transport mechanism is selected here.

### Credential-delivery reconciliation with existing owners

On John's request, inspected the concrete creation, holder, crypto, sink, delivery, activation and signer contracts.
They support the separation below without MCP sign-in or a new credential registry. This is documented contract
reconciliation, not live qualification or approval of an invented remote transport.

| Stage | Existing owner | Local harness integration and boundary |
|---|---|---|
| Authorize creation | TICKET-00063 / TASK-00257–00259 | Browser-authenticated authorized administrator selects the reviewed template, distinct named Agent, Team/Repository scope and desired eligible Permissions; submits retained provisioning intent. Starting a local template session is not authority to mint an identity/key or create another Agent on every restart |
| Bind protected recipient | TICKET-00060 / TASK-00234 | Resolve the intended trusted holder and fixed server-registered destination through protected setup. Integrate this binding into the intended provisioning experience, not a second human login or MCP session; never accept a model-supplied path/URL as the destination |
| Generate and protect | TASK-00235 with TICKET-00059 | Package issuance generates a random per-holder secret and independently protected authentication/delivery copies. Credentials are not embedded in templates or shared across contributors/Agents; the local harness does not invent a server-accepted HMAC secret |
| Deliver into custody | TASK-00236 / TASK-00238 / TASK-00239 | A protected harness credential store is the proposed local endpoint of the existing fixed sink adapter. Preserve immutable exact-tuple/byte binding, stable-slot order, durable deduplication/tombstones and independently verifiable receipts. The actual remote transport must satisfy these guarantees, not merely report a successful download |
| Activate exact material | TICKET-00061 / TASK-00243 | After confirmed delivery and independently verified evidence, explicitly activate the exact current Agent/holder/credential/destination binding through the protected operation. This is a machine lifecycle operation, not another user sign-in. Stored bytes or a sink receipt alone do not enable signing |
| Use MCP tools | TASK-00242 / TASK-00244 / TASK-00245 | Trusted holder selects the active credential, admits/signs only the exact bounded request and dispatches it. MCP validates request authentication, replay/destination and current authority per use; no interactive login or persistent MCP authentication session. No general-purpose signing oracle or raw secret reaches a Pi child |
| Recover and retire | TASK-00238–00241 / TASK-00243 and existing lifecycle owners | Reconcile original delivery/activation identities after interruption. Rotation/revocation changes current eligibility; late or obsolete deliveries stay inert. Restart never generates a fresh Agent/key just because local status is uncertain |

#### Approved user experience, not a new sign-in

John approved integrating protected harness-recipient binding into the existing browser-authenticated Agent
provisioning action, authorizing the recipient as well as the named Agent. Trusted infrastructure then performs
delivery and activation without another sign-in; the browser
shows safe issued/delivered/activated/usable or held status. The harness references the Agent/template and a
protected credential handle, not an exported key in project configuration. Routine MCP calls require no browser
login repetition. Local instance/session identity must still bind to the actual authorized named Agent; template
selection does not confer that identity or copy its authority.

TICKET-00063 currently restricts Agent-management intents to authorized Super Admin management rights. Do not
infer that an ordinary contributor can self-provision merely because he can run a template or owns a TASK. John
can authorize an Agent for his son's supported local holder through the existing management boundary; delegated
self-service would need a separate owner amendment. No such amendment is made here.

#### Concrete missing integration, not another credential subsystem

- **First-recipient binding:** the server must distinguish the intended protected harness from an impostor before
  sending its first secret. It cannot authenticate bootstrap with the not-yet-delivered Agent HMAC. TASK-00234
  owns this protected holder/destination association. John approved obtaining the necessary consent within
  browser provisioning rather than adding a second account login. A qualified recipient proof/transport identity
  still needs selection. No device-code, pairing token, client certificate, self-registration or public-key envelope
  protocol is approved by the user-experience decision.
  Existing preconfigured protected operator setup may supply the binding; if absent, delivery stays unavailable.
- **Outbound-only delivery:** preserve the approved no-inbound-laptop-listener direction. Qualify an outbound
  harness connection through a fixed protected delivery adapter, with end-to-end exact recipient/tuple binding
  and receipt evidence. A relay acceptance is not confirmed local storage. The current sink interfaces do not
  themselves implement this remote/offline path. No browser/MCP secret-download API, arbitrary sink URL, new
  delivery queue or extra credential copy is adopted. Actual transport and any required owner amendment remain
  explicit TASK-00234/00236/00238 integration gaps.
- **Storage plus signer protection:** qualify OS-supported protected custody/access on each supported platform,
  including restart, locked store, rotation, loss and independent receipt history. A generic keychain write alone
  does not meet the sink's ordering/tombstone/receipt contract. Secrets stay outside repositories, templates,
  prompts, ordinary logs, child environments and command arguments. A locked/unavailable store reports a hold;
  no plaintext fallback or claim that unlocking the OS store is MCP sign-in.
- **Retained bash:** current native local Agents keep their approved bash/check path. An in-process extension,
  same-user file permissions or encryption at rest alone does not prove that arbitrary same-user commands cannot
  read process memory, invoke a signer or access an unlocked keychain. Qualify the actual protected process/OS
  boundary and bounded caller authentication; do not grant a Pi child the raw key or a reusable unrestricted
  signing handle. This protection must be delivered before claiming that native-bash execution safely coexists
  with managed credentials. If unsupported, remote authenticated Agent tools remain unavailable on that profile;
  ordinary local work continues without claiming managed authority. No bash removal is authorized as a shortcut.

These are concrete qualification inputs for existing delivery/current-use/Harness owners, not reasons to invent
new TASKs or declare the records delivered. TICKET-00063 retains creation; TICKET-00060 retains protected delivery;
TICKET-00061 retains activation and signing. TASK-00241 owns integrated readiness. Secure local recipient/storage
and outbound adapter suppliers still need actual allocation within later authorized planning. No separate user
sign-in is needed by this composition, but no secure bootstrap or hardware isolation is proven by that UX choice.

The existing contracts require original-operation recovery regardless of MCP sessionlessness. John responded
that the answers to the interruption/revocation question were obvious. Carry the existing safeguards below as
requirements, not another product choice requiring repeated confirmation. This does not grant implementation,
runtime effects or approval of unrelated future tradeoffs. No credential, local store, keychain, endpoint,
native runtime or package source was inspected or changed.

### Existing interruption and revocation requirements

- Current permissions, delegation, repository scope, context revisions and required capability readiness are
  checked for every protected discovery/invocation. A valid HMAC proves request authentication, not blanket
  permission. Missing/unqualified transport or protected participant means unavailable. Revoke/disable denies
  subsequent protected operations; cached snapshots and MCP availability do not preserve revoked authority.
- Disconnected laptops may continue their existing locally authorized native work and keep explicit drafts,
  but cannot mint official central references, start new registered managed work or claim fresh server authority
  from cache. Hosted Planning remains available with its authorized server context and visibly unavailable/stale
  laptop observations. Human responsibility persists independently of connectivity or execution-lease lifetime.
- Timeout/lost connection after submitting an operation retains its original command/attempt identity and an
  unknown/pending state until reconciled. Reconnect queries/reconciles that operation, rather than launching a
  duplicate, reassigning an active TASK or interpreting silence as completion. Claim release/takeover uses the
  existing fenced Workflow/quiescence/recovery operations, not the transport heartbeat or an assignee edit.
- Revoking hosted access cannot erase already-read files or guarantee termination of offline native bash jobs.
  Treat already-admitted streams, checks, publications and charges through their real bounded stop/reconciliation
  contracts, preserving liability and evidence; do not claim retroactive cancellation or zero cost. Distinguish
  revoke access, stop an operation, transfer ownership and remove locally held credential material.

Exact protected provision/delivery/renewal/rotation/storage, remote transport/origin and recovery participants
remain missing/unqualified under the existing owners. No separate human terminal sign-in or client-enrollment
workflow is proposed. TICKET-00060/00061's actual protected delivery/broker/Harness/MCP holds and TICKET-00032's
launch/recovery holds remain; no delivery record is amended. Retaining these requirements is not authority to
provision credentials or a claim that recovery is implemented.

The browser provisioning/recipient-binding experience is approved; exact bootstrap, outbound transport and
native-bash-safe custody remain qualification holds. This approval creates no credential or operational grant.

### Closure, owner handoff and qualification limits

The decision boundary is resolved: hosted coordination and scoped read-only source access are separate from
contributor local work, protected Git administration, candidate checks and release builds. Existing owner reuse
and concrete missing supplier seams are identified rather than creating another identity/claim/credential system.

- TICKET-00024/00025: registered identities and versioned source/context; actual hosted retrieval/materialization
  and repository-read credential host remain missing/unqualified suppliers. A read-only SSH key is a candidate,
  not an installed credential choice. TICKET-00102 is not repurposed as general Git administration.
- TICKET-00026/00027/00028: existing UUID-backed identity and central allocation/cutover; proposed human-assignee
  lifecycle and canonical queue amendments need later authorized owner work. Current Markdown remains authoritative.
- TICKET-00032 and TICKET-00064: retain exclusive execution ownership, eligibility, Team selection and recovery.
  Proposed human/Agent coordination must reuse them. Current native local bash checks remain until a qualified
  MCP/browser replacement; no managed-profile shell grant or waiver of check/Review/QA gates follows.
- TICKET-00059/00060/00061/00063: reuse existing creation, protected delivery, activation and current signing/use.
  Recipient-bootstrap proof, outbound exact-delivery/receipt transport and actual bash-safe storage/signer custody
  are explicit qualification/allocation gaps. No new login, raw browser secret or contributor self-provisioning grant.
- Existing Harness/check owners retain future fixed CI request/status/result capability design, source provenance,
  protected policy/results, dispatch/cancellation and limits before retirement of native checks. Optional managed
  worker topology and managed Git writes are deferred; no first-browser gate, spend or CI execution is authorized.
- WF-048 owns actual hosting/staging and immutable artifact promotion; WF-049 owns first browser journey;
  WF-050 retains complete operating cost/readiness/recovery. WF-052/053 retain domain UI and legacy transition.

These are reuse/amendment recommendations for later authorized planning, not accepted EPIC/TICKET/TASK edits or
new EPIC destinations. No task priority, existing hold or TASK-00510–00512 changed. Close the decision rather than
invent further policy questions solely to repeat established safety requirements; ask for genuine unresolved
scope/cost/behavior tradeoffs and preserve concrete missing evidence as qualification holds.

Verification uses `./bin/planning-check --write`, `./bin/planning-check`, tracked diff and all changed/new Wayfinder
whitespace/path-scope checks. No full application build, independent Review/QA, live delivery/activation/MCP/Git/CI
qualification or credential/runtime effect occurred. Work remains uncommitted in the isolated planning checkout.
Stop after WF-047; the next authored unblocked frontier is WF-048, not automatic implementation or decomposition.

Next: `/skill:wayfinder work WF-048`
