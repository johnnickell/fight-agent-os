# Native-session origin hardening feasibility

## Decision

**Needs-upstream; no-go within the currently qualified public API and installation boundary.** Neither of the
**two** approaches below supplies the independent anchor required by [TASK-00507](../../planning/tasks/00507-TASK.md).
**Zero disposable proofs** were attempted: a mock verifier or another self-issued signer would not qualify the
missing guarantee. This is a supported negative feasibility assessment, not production hardening or a universal
impossibility result. Independent Review and applicable documentation/security QA remain separate.

Keep TASK-00502's [explicitly accepted trusted-local-process boundary](../../planning/tasks/00502-TASK.md#owner-approved-trusted-local-process-amendment--2026-10-21)
unchanged. This note neither silently chooses weaker protection nor authorizes installation/upstream work.
John can retain that boundary with no added operation, or separately authorize investigation of a protected native
origin supplier. There is no implementation/rollout recommendation until that supplier and its UX are qualified.

**Concrete benefit if the missing primitive becomes available:** prevent an unrelated same-user Node caller from
minting a new accepted custody root for another actual Pi session/file. Current signatures protect continuity of
a selected custodian; they do not establish who was entitled to create it. Hardening would strengthen the
AGENTS/worktree-log association, not grant tools, isolate execution, or make Docker access safe against host compromise.
The immediate delivery benefit is avoiding an ineffective extra signer/helper and its installation burden.

## Subject, evidence and limits

Fresh assessment: **2026-11-08**, source baseline `78a93cb1f3eb85a5097a0248524a8d22031e3659`, branch
`feature/task-00507-native-origin`. Local qualification is read-only documentation/declaration/source inspection and
native metadata, **not execution of an origin protocol**. Observed macOS **26.6.2 / 25G83**, **arm64**, Node
**22.23.1**, installed Pi **1.5.0**. These are evidence identities, never Pi equality gates or future compatibility
promises. No Linux, Windows, alternate Node/Bun distribution or remote-host origin path is qualified.

Private canonical run, abbreviated **E** below:
`/Users/john/Projects/Fight/fight-agent-os/.runs/tasks/TASK-00507/runs/work-20261108T162730Z/`.
`E/sources.json` binds inspected installed files, current observation source and historical reports by SHA-256;
`E/supplement.json` binds additional Apple primary responses; `E/headers.json` binds installed Apple public headers.
The evidence is deliberately outside the disposable checkout. The installed package root is
`/Users/john/.pi/agent/install/releases/1.5.0/node_modules/@earendil-works/pi-coding-agent/` (**P**).
Public URLs below are navigation to publisher material; mutable upstream pages are not claims of byte identity
with P. P's exact local bytes, package manifest and declarations are the installed-API qualification.

### Historical R1, not a fresh reproduction

Read TASK-00502's completion/amendment notes and the preserved canonical
`/Users/john/Projects/Fight/fight-agent-os/.runs/reviews/TASK-00502/review.md`. Its ACCEPT explicitly retains R1 as
owner-accepted, **not fixed**. The preserved original finding is in sibling
`history-pre-amended-737cf9b.md`, R1, reviewed head `2fa6d60c388d93f7827cec2bbf2392c2675b21d2`.
That original Review used an isolated session-file stand-in; it must not be recast as a genuine-session probe.

The preserved Engineer handoff at canonical
`.runs/build/TASK-00502/run-gdci1s/work-repair3-ZyjNJK/handoff.md` separately records the stronger repair3 case:
an ordinary Node parent forked the genuine fixed custodian, supplied its own real process identity and an existing
genuine native session/file, and production `challenge()` accepted. It records zero launched children and an
unchanged genuine file digest. These are **inherited report claims**, not newly executed or raw-receipt-verified
observations in this TASK.

The requested canonical `.runs/tasks/TASK-00502/runs/repair3/r1-capability-decision.md` is **absent**. Bounded
subject-only discovery found no `*r1*` alternative under canonical TASK-00502 task scratch. The original selected
checkout named in that handoff is no longer registered. The retained repair3 handoff and historical R1 Review
recover the reasoning/provenance, **not the missing assessment or its raw probe artifacts**. No historical report
was rewritten, fabricated or rehashed into a fresh success. The negative decision below also rests on fresh
installed contract/source inspection, not an assumed historical capability inventory.

## Required independent primitive

A trusted issuer, whose admission/key cannot be created or exercised merely by running another Node script,
must bind all of:

1. The actual permitted native Pi execution/context and its lifetime, not a claimed CLI pathname, TUI flag,
   environment variable, PID string or lifecycle-event-shaped object.
2. The actual session ID **and file lifetime** to the permitted bootstrap and custodian public key/generation.
   A readable header/inode is a useful identity witness, not proof of who initiated the association.
3. Rediscovery after same-file resume to that same authority, with explicit replacement/revocation rules.
   A resumed reader must not accept whichever new signer happens to assert the same file.

The issuer must distinguish the task's ordinary-Node/fixed-custodian counterexample without trusting its asserted
session fields. A nonce can stop replay of an old reply; it cannot stop a freshly bootstrapped unauthorized signer
from signing a new nonce. Key rotation cannot repair an untrusted initial enrollment.

Fresh source trace: [NativeObservation.start()](../../harness/pi/src/observation/native-observation.mjs) forks the
fixed daemon and sends session/file/process/entry/TUI assertions. [startCustodian()](../../harness/pi/src/observation/custodian.mjs)
checks its immediate parent's process lifetime, installed-entry path, context clock bounds and file witness, then
generates Ed25519 keys and publishes the descriptor. [Custody verification](../../harness/pi/src/observation/custody.mjs)
checks signed challenge and witnesses. Those checks have useful normal-lifetime purposes; none supplies a separate
issuer for the asserted native/session origin. This trace is current passive observation source, not a claim that
the historical launch-capable daemon still exists unchanged.

## Two approaches, no viable current proof

| Approach | Proposed independent anchor | Fresh qualification | Decision |
|---|---|---|---|
| **A. Public Pi extension/session API with a session key** | Pi would need to issue a protected, externally verifiable native-context capability, bound to the file and custodian key | P exposes mode, readonly session data, startup/shutdown notifications and caller-usable SDK session creation/opening. No such protected issuer is in the inspected contracts | **No-go now**. Extension-generated keys, custom entries and inherited IPC alone remain caller-bootstrapped |
| **B. macOS code-identity broker for a protected native Pi origin** | OS-enforced peer code identity plus an origin host that owns session/bootstrap/key admission, not merely a signed helper that trusts a Node request | Apple supplies public XPC code-requirement and message-sender code-reference APIs. Installed Pi is a JS CLI on general-purpose Node; no qualified protected Pi session issuer/guest identity bridges that gap | **Needs-upstream / new installation scope**. Stronger potential anchor, but not viable for a proof under current contracts/permissions |

### A — Why ordinary supported Pi APIs are insufficient

[Pi extension documentation][pi-extensions] describes `ctx.mode`, `ctx.sessionManager`, `session_start` and
`session_shutdown`. P's `dist/core/extensions/types.d.ts:232–272,594–600,656–661` freshly confirms that contract.
The complete `ReadonlySessionManager` selection at `dist/core/session-manager.d.ts:178` supplies session/tree
queries, not an attestation or protected bootstrap operation. Readonly is the extension's interface, not an
OS-enforced denial of filesystem or SDK access.

[Pi SDK][pi-sdk] and its installed `examples/sdk/11-sessions.ts` explicitly support `SessionManager.create()`,
`open()` and `inMemory()` in an ordinary host process. `session-manager.d.ts:5–16,378–401` permits caller-supplied
IDs and opening a selected file. [Session format][pi-format] defines JSONL metadata, custom entries and normal
branching, not an authenticated native-origin certificate. The declarations also document deferred persistence:
a new file need not exist until a user/assistant message. An origin design must handle that pending state rather
than forcing a provider turn to make discovery work.

These contracts are useful for normal native launch/resume. They do **not** let an external custodian distinguish
Pi's true notification from a caller constructing equivalent values. `isProjectTrusted()` is project-resource
loading policy, not process/session attestation. A per-session secret readable by the same local caller or a
self-issued public key persisted beside the session moves the assertion; it does not add independent authority.
No new runtime behavior, model request, extension loading experiment or impersonation success/refusal is claimed.

### B — Real OS primitive, missing Pi-specific subject

Apple's [XPC peer requirement API][apple-peer] checks received messages against a peer code-signing requirement;
the installed public `xpc/connection.h:771–804` documents refusal and macOS availability from 12.0 (embedded-platform
limits are explicit). [SecCodeCreateWithXPCMessage][apple-xpc] identifies the process sending an XPC message using
its associated audit token, also documented in installed `Security.framework/Headers/SecCode.h:192–211`.
These are genuine platform identities, not caller-provided PID fields. **Header/documentation availability is not
a compiled or live broker qualification**, and macOS 12+ annotation alone is not a supported-platform guarantee.

Fresh `/usr/bin/codesign -dvv /Users/john/.local/bin/node` exits **0** and resolves to the Node executable with
identifier `node`, Node.js Foundation Developer ID and **no sealed resources** (`E/command-6.log`). P's manifest
maps `pi` to `dist/bundle/cli.js`. [Node's fork contract][node-fork] says `fork()` starts a new Node process, normally
using the parent's `process.execPath`, with an IPC channel. Thus checking that the peer is signed Node would admit
the shared interpreter class, not establish which Pi JavaScript/session initialized it. Checking only the genuine
custodian's code identity also fails: the counterexample intentionally invokes that same custodian.

Apple's [code-signing limitations][apple-signing] explicitly do not guarantee safe loaded plug-ins. Its
[requirement language][apple-requirements] tests signed identifier/certificates/entitlements or code-directory hash;
none is a Pi session-file lifetime. [Code-host guest lookup][apple-guest] requires the host to actually support
identifying/separating its guests; it does not automatically turn JavaScript into independently authenticated Pi
sessions. No such Node/Pi guest/session contract was found in the inspected installed interfaces. A protected
native host/issuer would need deliberate supplier work, trusted loading and bootstrap/resume ownership. Signing
an otherwise unchanged permissive launcher or installing an elevated helper alone is **not** the missing proof.

B is the strongest potential direction, but the necessary trusted origin integration, distribution, keys and
session admission are not installed/qualified. Implementing them would exceed this TASK's no-upstream-change,
no-global-install/trust-change and no-managed-redesign boundaries. No helper, XPC service, code signing, Keychain
item, trust root, elevation, security setting or upstream patch was applied. Linux/Windows equivalents are outside
this two-approach assessment, not silently supported alternatives.

## Lifetime and failure obligations still unproved

Neither candidate earns **go** on any of the following. These are proposed supplier requirements, not measured
proof results or instructions to implement here:

| Boundary | Needed evidence before any future go |
|---|---|
| Native launch / impersonation | Genuine native admission and refusal of ordinary Node forking the actual fixed custodian while asserting another actual session/file; refusal by the independent anchor |
| Pending file / first persistence | Bind the real first file lifetime once it exists without a forced prompt or caller substitution; refuse another file/inode |
| Same-session resume | Reauthenticate origin and recover the authorized binding/key lineage without per-child flags, replay or enrolling an arbitrary replacement signer |
| Replay / rotation | Fresh challenge bound to session/file/key/generation; old challenges and retired keys refused; rotation authorized by the existing independent issuer |
| Pi/broker restart / crash | Explicit generation and recovery semantics, no resurrection from an editable descriptor; absent issuer/binding means unavailable observation, not weaker fallback |
| New/fork/reload/replaced file | Revoke old authority at actual replacement/shutdown; do not revoke merely on a cancelled transition proposal; fork receives distinct authorized identity |
| Key/session removal | Finite authority lifetime, explicit revocation and deletion rules even if a daemon or descriptor survives; no other owner's session or child cancellation |

No positive genuine-launch/resume or adversarial rejection proof was collected. Historical normal-use evidence
from TASK-00502 is not a fresh test of B. No replay, rotation, restart or recovery success is inferred from a nonce,
a code signature, this worker's trusted Build binding or gate success.

## Counted operator burden

Counts below are **incremental to ordinary Pi use**, unless stated. Proposal counts are logical actions/lower
bounds, **not measured clicks, implementation estimates or promises**. A and B are not installed flows.

| Lifecycle | A: public extension key proposal (insufficient protection) | B: protected-origin/broker proposal (unqualified) |
|---|---|---|
| Initial setup | **2 actions**: update the user Harness package, restart at a safe boundary; proposed **0 admin / 0 new security dialogs** | At least **3 actions**: adopt trusted native origin distribution, install/register broker, verify origin/session admission. Privileged system-wide variant needs explicit admin approval; actual number of admin/OS dialogs **unknown**, not zero |
| Daily / each session | Target **0 extra actions/prompts**; one ordinary Pi launch remains | Target **0 extra actions/prompts**, but only through the qualified protected origin. Ordinary unrestricted `node …` must not silently receive authority |
| Each child | Target **0** user actions or sockets/flags | Target **0** user actions; automatic origin-bound admission still needs proof |
| Same-session resume | **1 ordinary resume selection/command**, **0 extra** proposed; no stronger origin | **1 ordinary resume selection/command**, target **0 extra**. Secure automatic rebinding after restart is unproved; no recurring approval flow selected |
| Updates / rotation | **2 actions**: package update and safe restart; no independent enrollment authority | At least **2 component updates** (origin and broker), possibly one bundled installer; safe restart and compatibility/revocation handling required. Dialog count and unattended rotation **unknown** |
| Failure / recovery | At least **1 restart/retry**; not an origin repair | At least **2 recovery steps**: restore qualified issuer, reconcile original session/binding. Lost authority stays unavailable; additional re-enrollment/dialog burden **unknown** |
| Multiple projects/worktrees | Target **1 user-level setup**, **0 per-project actions**, separate session/file bindings | Target **1 installation**, **0 per-project approvals**; project/session separation and broker multi-client behavior unproved |
| Removal | **2 actions**: remove/disable package feature, restart; remove proposed key state explicitly if created | At least **3 actions**: disable origin integration, revoke/remove broker binding/key state, uninstall/unregister broker; privileged variant may require admin. Never delete transcripts as implicit removal |

**Measured assessment burden:** **0** installs, **0** privilege elevations/admin dialogs, **0** operational native
session launches/resumes, **0** child launches, **0** origin keys and **0** prototypes for the investigation.
Separate mandatory gate preparation generated isolated application settings (including application keys), overrides,
locked Composer/client/Harness installations and build/export output through owning wrappers. These are verification
prerequisites, not an origin issuer, global installation or measured hardening setup.
Source fetches and read-only metadata commands do not measure either operational UX. Retaining today's accepted boundary adds **0** setup,
per-session, per-child or removal actions, but deliberately retains malicious-local impersonation risk. The
low-recurring-friction targets above cannot be traded for silently weaker origin admission.

Residual trust for any future B includes macOS/kernel, signing/distribution and update authority, the native
issuer/broker, permitted Pi/extension code and the human selecting the trusted distribution. Signed code can have
bugs or load hostile code; a legitimate permitted process may act as a confused deputy. Arbitrary same-user
filesystem tampering, root/kernel compromise, injection and delegated tools are not solved by this note. No
OS-sandbox, universal hostile-local-process protection or provider attestation is claimed.

## Direct validation and gate provenance

- `python3 E/collect.py` and `python3 E/extract.py`: exit **0**; bounded read-only collection and text extraction.
  Collection made **8** publisher HTTP requests, **6 HTTP 200 / 2 HTTP 404**. The two guessed audit-token documentation
  locators failed; they are retained in `sources.json`, not cited as proof. The correctly located public XPC
  requirement/message APIs and installed headers support the narrower claims above. No retries, credentials,
  provider calls or upstream mutation. Collector success is not a claim that all HTTP requests succeeded.
- Seven captured metadata commands exit **0**, including codesign and exact worktree `./bin/runtime inspect`.
  Native project **`fight-wt-4116abea7eff`** has **0 containers / 0 volumes / 0 networks** at intake.
- Installed SDK/extension/session-format documents and selected SDK examples were read directly, including the
  complete extension document. No product-suite tests, tooling tests or seeded quality failures were added.
- `python3 E/check-doc.py`: exit **0**, **18 passed / 0 failed**: five local links/anchor, ten exact-source
  claim checks and three Pi publisher-navigation HEAD requests (HTTP 200). These direct checks are not product
  tests or a proof of secure origin. Installed-source reading supplies the interpretation, not string counts.
- `./bin/planning-check --write` and read-only `./bin/planning-check`: exit **0**, **749 records / 513 active**;
  generated views current. `git diff --check`: exit **0**.
- **Historical initial gate:** normal `./bin/build` exits **1** because Compose web was absent, before its own
  planning/application pipeline; no application phase or receipt reuse. `E/gates.json` and logs preserve that
  failure. Evidence-only recovery subsequently prepared the isolated tooling project and passed **16 phases**,
  then failed unchanged vendored engine import ordering (**193 files / 1 error**, exit **1**). Its retained failed
  receipt is canonical `.runs/compose/fight-wt-4116abea7eff/build/20261108T164322Z-19/receipt.json`; it is not success.
  The recovery runtime/dependencies/output were retired, preserving source, private settings and needed evidence.
- **Owner-approved repair, 2026-11-08:** John explicitly authorized only the small existing import-order fix,
  rerun verification and independent Review/QA. In `harness/pi/vendor/pi-subagents/src/index.ts`, `node:url` now
  follows `node:fs` and `node:path`; bindings, runtime behavior/API, vendored version and locks are unchanged.
  Direct owning Biome lint reproduced **193 files / 1 error**, exit **1**, before the edit and passes **193 files /
  0 errors**, exit **0**, afterwards; no formatter or product test of tooling was added. New evidence is canonical
  `.runs/tasks/TASK-00507/runs/repair-1-20261108T1844Z/`. Gate-only prerequisites were restored in the **same**
  project `fight-wt-4116abea7eff`, starting only web and its postgres/postgres_test dependencies. No full app,
  TLS/FPM/mail/ingress/trust, installed-host update or main data import. This does not expand the two approaches
  or supply origin proof. The final normal gate result, exact tested snapshot, counts/warnings and commit bridge
  belong to [TASK-00507's completion notes](../../planning/tasks/00507-TASK.md#completion-notes) and canonical
  `.runs/tasks/TASK-00507/runs/work-handoff.md`; earlier failures remain historical, never relabeled passes.
  Config/teardown locator is canonical `var/worktrees/fight-wt-4116abea7eff/locator.json`; do not expose `task.env`.
  Teardown uses canonical `bin/down --worktree` with the exact selected checkout path, not main or foreign resources.
- **Evidence-only cache recovery, 2026-11-08:** the approved repair's first gate failed PHPStan's generated
  DI-cache rename/create operation with ENOENT; its failed receipt remains unchanged. Fresh mount, ownership,
  alias, process and gate-lock checks qualified only this project's disposable `output/cache/phpstan` subtree.
  Owning `clear-result-cache` succeeded but retained compiled DI entries, so that exact generated subtree was
  retired under the gate lock, without source/configuration/dependency changes or weakened parallel analysis.
  Cache ownership/readiness is established, not the original failure's causal mechanism. Canonical evidence is
  `.runs/tasks/TASK-00507/runs/cache-recovery-20261108T1859Z/`; the subsequent one normal gate result and final
  committed-subject bridge belong to the TASK and Work handoff above. No new approach or origin proof is implied.

A future authorized scope must qualify the issuer rather than merely add signatures, and supply the real
counterexample/lifetime/UX evidence before implementation adoption. This bounded assessment stops here; it does
not open a PR, publish, alter installed sessions or execute a third approach.

[pi-extensions]: https://github.com/earendil-works/pi/blob/main/packages/coding-agent/docs/extensions.md
[pi-sdk]: https://github.com/earendil-works/pi/blob/main/packages/coding-agent/docs/sdk.md
[pi-format]: https://github.com/earendil-works/pi/blob/main/packages/coding-agent/docs/session-format.md
[apple-peer]: https://developer.apple.com/documentation/xpc/xpc_connection_set_peer_code_signing_requirement(_:_:)
[apple-xpc]: https://developer.apple.com/documentation/security/seccodecreatewithxpcmessage(_:_:_:)
[apple-signing]: https://developer.apple.com/library/archive/documentation/Security/Conceptual/CodeSigningGuide/Introduction/Introduction.html
[apple-requirements]: https://developer.apple.com/library/archive/documentation/Security/Conceptual/CodeSigningGuide/RequirementLang/RequirementLang.html
[apple-guest]: https://developer.apple.com/documentation/security/seccodecopyguestwithattributes(_:_:_:_:)
[node-fork]: https://nodejs.org/docs/latest-v22.x/api/child_process.html#child_processforkmodulepath-args-options
