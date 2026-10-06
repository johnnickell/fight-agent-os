# Protected credential storage qualification

## Approved candidate and scope

John approved **encrypted PostgreSQL-backed ordered delivery storage with protected local key files and
broker-held signing** for qualification on October 6. This replaces the earlier Keychain/user-session candidate
below, after considering installation burden, always-on laptop/desktop service and future cloud deployment.
The [execution catalog](TERMINAL_AUTHORITY.md#permission-catalog-and-operator-confirmation) is unchanged.
[TASK-00138](../../planning/tasks/00138-TASK.md) remains in progress. No real credential enrollment, host service
installation or production launch is authorized by this qualification.

PostgreSQL remains the candidate for encrypted immutable delivery entries, exact binding metadata, opaque receipts,
permanent delivery tombstones and nondecreasing slot order. Protected files supply versioned wrapping keys, not an
ordered sink. Authentication-envelope and delivery keys remain separate. Neither key files nor plaintext credentials
are Agent-readable or mounted into candidate checks. Redis, a new general-purpose secrets platform and per-user
Apple signing/provisioning are not prerequisites for this candidate.

### Accepted trust boundary and file contract

The installation host, its administrators, container engine and trusted credential component are trusted. This
profile protects against Agents exceeding their file/tool grants and candidate code obtaining credentials; it does
not resist root/sudo, Docker administrators, offline access to an unencrypted disk or a compromised broker/controller.
Permissions alone do not isolate processes sharing the credential service's OS identity. The Agent profile still
forbids shells and untrusted runtime loading; independent candidate mounts remain mandatory.

- Provision cryptographically random, purpose-separated keys only through explicit installation enrollment. Place
  them outside every repository, worktree, project workspace and Agent-readable root. A dedicated service identity
  owns a private `0700` directory and `0400` regular key files; parent paths must not be writable by untrusted users.
  Deny unexpected ownership, permissive mode/ACL, links, invalid length/version and unsafe paths. The fixture's UID
  is not a production identity assignment. Qualify effective access on the selected filesystem, not mode bits alone.
- Runtime access is read-only. Only the installation administrator can provision, replace or recover material through
  a separate controlled path. For container deployment, use installation-owned storage mounted only into trusted
  consumers, never project/Agent/check containers; no Docker socket or mount-selection capability is Agent-accessible.
- Keep keys out of command arguments, environment variables, images, logs, tool results and routine database backups.
  Credentials remain encrypted at rest. Read success is custody availability, not authority to use a credential.
- Missing, unreadable, unsafe, unknown-version or corrupt material makes custody unavailable. Never silently recreate
  a lost key, choose a fallback key or reissue credentials to conceal loss. Cache invalidation and stop/recovery
  fencing must preserve this contract; a previously loaded secret cannot supply current permission by itself.
- Keep an independently access-controlled recovery copy, separate from ordinary database backup/export. Production
  backup protection, version retention and recovery authorization need qualification. Preserve original bytes and
  required key versions through rotation; replacing a lost key with random bytes cannot recover existing ciphertext.
  Restore keeps admission closed until package/consumer reconciliation proves freshness independently of restored data.

### Broker-held signing and availability

The trusted broker retains each package Agent's HMAC secret. An authenticated runtime channel binds the actual
Workflow, role and attempt; a caller-supplied Agent ID does not authenticate that channel. The broker accepts only
registered typed operations and fixed destinations, checks current authority, and constructs/signs/sends the request.
There is no arbitrary payload-signing, decrypt or secret-read tool, and no credential delivery to the model or its
harness. A signature authenticates a request; the receiving service still checks package credential validity,
replay protection, live Permissions and current resource/Workflow authority. Channel authentication, fencing and
actual package-backed signing/MCP composition remain TASK-00139–00141 work, not this disposable fixture.

The desired service starts automatically once the computer, unlocked storage and runtime are available, without a
separate interactive credential unlock. Sleeping laptops cannot execute work; offline operation cannot assume
provider or remote-service availability. Protected files remove the Keychain login-session dependency, but do not
establish boot/logout, full-disk-unlock or engine availability.
A production service manager, installation permissions and supported runtime lifecycle still need qualification;
this experiment neither changes Docker Desktop startup nor reboots/logs out the human's machine.

Staged entries stay inert. Exact current package/destination identity, confirmed delivery and fresh fenced
enrollment authority govern activation; every subsequent use repeats current authority checks. A high-water pointer,
receipt, successful key read or completed SQL transaction cannot independently authorize a launch.

## Package contract inspected

The earlier storage checkpoint's source inspection is pinned to the retained, **unreleased** Access Control
candidate `614c34b189fd1ab73a10ace173e206ce010bb2e3`. Its three archived documents were compared byte-for-byte
with that Git commit, excluding upstream working changes; this inspection was not repeated for file custody:

- [Protected delivery](https://github.com/johnnickell/fight-access-control/blob/614c34b189fd1ab73a10ace173e206ce010bb2e3/docs/agent-credential-delivery.md):
  delivery-ID-only idempotency, exact original tuple/bytes, immutable staging and independent receipt verification;
  sink calls occur outside package transactions and after confirmed admission.
- [Delivery conformance](https://github.com/johnnickell/fight-access-control/blob/614c34b189fd1ab73a10ace173e206ce010bb2e3/docs/agent-delivery-conformance.md):
  real concurrent adapter qualification, all twelve issuance fields, cleanup replay, original-byte recovery and
  separately qualified consumer activation/use. Package reference tests are not consumer proof.
- [Restoration safety](https://github.com/johnnickell/fight-access-control/blob/614c34b189fd1ab73a10ace173e206ce010bb2e3/docs/agent-restoration-safety.md):
  independent incarnation-bound admission evidence, lossless reconciliation and writer exclusion. The database
  being restored cannot certify its own freshness; key availability alone is not that witness either.

This is contract tracing, not execution of package services or conformance suites. No remote tag inventory, release
qualification, dependency update, source-project edit or upstream gate was performed in this checkpoint.

## Noninteractive Keychain preflight — 2026-10-06 UTC

The owning native APIs were probed on macOS 26.6.2 / build 25G83, arm64, using a compiled Swift helper with
Security.framework and LocalAuthentication. `codesign` reports a linker/ad-hoc signature, no TeamIdentifier and no
entitlements. The helper uses `SecItem` with `kSecUseDataProtectionKeychain=true`, synchronization disabled and
`LAContext.interactionNotAllowed=true`. It does not fall back to the legacy login keychain, change search lists,
create signing identities, set access groups, lock an existing keychain or request interactive authorization.

A UUID-library-generated service/account pair identifies one disposable synthetic item. A 32-byte random value
was generated in process memory only; no real credential, raw value, ciphertext or secret digest was logged,
passed in command arguments or written to an evidence file.

| Operation | Observation |
|---|---|
| Look up the exact new fixture selector | `errSecItemNotFound` (`-25300`) |
| Add the synthetic key with `kSecAttrAccessibleWhenUnlockedThisDeviceOnly` | **`errSecMissingEntitlement` (`-34018`)**; no successful creation |
| Roundtrip, duplicate-add and deletion probes | Not reached; the instrument stopped the lifecycle path after failed add |
| Fresh process, exact-selector lookup without credential-byte return | `errSecItemNotFound`; no mutation attempted |
| Compile and invocation | Exit 0; no compiler diagnostics. Process exit records completion of the probe, **not successful key custody** |

The SDK's `SecBase.h` defines those status meanings. Its `SecItem.h` identifies the data-protection selector and
noninteractive authentication controls. The first signature-inspection command used deprecated `--entitlements :-`
syntax and emitted one warning; the corrected `--entitlements -` invocation is retained separately. That tool
warning did not cause the Security.framework failure. No framework fallback or fabricated entitlement was used.

Evidence: isolated-worktree `.runs/tasks/TASK-00138/storage-qualification/`, including exact Swift/Python instruments,
input digests, signature/entitlement records, `keychain-result.json` and `keychain-observation.json`. The second
lookup corroborates absence visible to that observer, not a global privileged inventory of every keychain. No
successful key insertion or owned keychain was recorded, so no keychain deletion was attempted. Scratch executable,
compiler cache and non-secret receipts are retained. The main checkout and canonical review are unchanged.

## Historical Keychain disposition

**The ad-hoc helper did not qualify custody.** The result neither rules out every Keychain mechanism nor qualifies
a correctly signed helper. Subsequent read-only inspection found no code-signing identity in the default search
and no provisioning profiles in the two standard locations. Apple's
[Keychain guidance](https://developer.apple.com/documentation/technotes/tn3137-on-mac-keychains) and
[provisioning guidance](https://developer.apple.com/documentation/technotes/tn3125-inside-code-signing-provisioning-profiles)
identify the app-bundle/profile and user-login-context requirements for data-protection Keychain access. Public
source captures and inspection receipts are under `.runs/tasks/TASK-00138/custody-caller/`. No signing identity was
created, credential exported or helper signed. The approved protected-file replacement removes those prerequisites
from the selected path; it does not convert failed Keychain evidence into a pass.

## Protected-file primitive qualification — 2026-10-06 UTC

Direct Docker/PHP/libsodium probes completed **34 passing observations** in
`.runs/tasks/TASK-00138/file-custody/run-71e26de1-3c7d-4919-866a-cb8b390623d5/` in the isolated worktree.
The locally retained image is pinned to `sha256:a4193582e6675ff918eb0fd95f450586b9910cf199cab60da1825c5279874ecf`
(PHP 8.5.10, libsodium 1.0.18). This qualifies sampled **Linux container filesystem** behavior on this Mac's Docker
engine, not native macOS ACLs or a deployed cloud service. Three owned local named volumes held key files, encrypted
synthetic material and an operator-only recovery copy; none was a host bind into a repository/workspace. The sole
host bind was the read-only synthetic instrument. No real credentials, package Agents or network endpoints existed.

The initializer generated separate 32-byte delivery/authentication keys and one synthetic HMAC credential inside
owned runtime storage. XChaCha20-Poly1305 encrypted that credential under the delivery key with a fixed fixture
context. An enrollment-time HMAC over one fixed synthetic message supplied a continuity check after decryption;
only booleans/statuses left the fixture, not plaintext, keys, ciphertext or signatures. This is not a package
`SignedAgentRequest`, arbitrary signing API, twelve-field issuance binding or MCP authentication result.

| Direct observation | Result and boundary |
|---|---|
| Dedicated broker identity | UID/GID 10001 recovered the original credential using its `0700` directory/`0400` key and read-only custody/data mounts |
| Different identity | UID/GID 10002 could not open the key even with a deliberately attached custody volume |
| Same-identity countercheck | UID/GID 10001 **could** open the key when deliberately given the mount; OS permissions alone are not Agent isolation |
| Candidate exclusion | A same-UID candidate fixture without the custody/data mounts saw neither store nor a Docker socket; no live Agent/tool session was run |
| Runtime immutability | Non-truncating write-open failed on the broker's read-only key mount |
| Restart/recreation | Controlled container restart and replacement with a new container ID both recovered the original credential without a prompt or new issuance |
| Unsafe file state | Permissive key/directory modes, wrong owner, symlink, hardlink and truncated key all returned unavailable |
| Cryptographic rejection | Wrong same-length key, substitution of the separate authentication key, changed ciphertext and a different authenticated context all returned unavailable |
| Missing key | A subsequent call and a fresh container failed; a separate open confirmed no replacement key was generated |
| Explicit recovery | Operator restoration from the fixture-only backup restored the original credential after every mutation and the missing-key outage; the runtime never mounted the backup |

All containers were network-none, read-only root, 128 MiB/no extra swap, 0.25 CPU, 16 PIDs, core dumps disabled and
logging-driver none. Runtime/probe containers dropped every capability and used no-new-privileges. Only the bounded
initializer/fault/restore processes ran as container root, with CHOWN/FOWNER/DAC_OVERRIDE and writable **owned**
volumes. They are operator actions, not broker or Agent grants. The initializer fsynced files; no parent-directory
fsync, power-loss durability, atomic rotation or interrupted provisioning claim is made.

The first attempt failed at PHP startup because the instrument declared a function named `key`, conflicting with
PHP's built-in function. No keys were generated. Its one container and three empty volumes were retired, and exact
failed source/receipts preserved in `run-a8bd522c-d2f7-496f-8a85-b73b2c71ad49/`. Renaming the function allowed the full
repeat. The successful attempt retired all **33 containers and three volumes** by exact identity/owner; both final
owner inventories were empty and existing Agent OS service start times were unchanged. Volume removal is ordinary
logical cleanup, not forensic secure erasure. No host account, service enrollment, global cleanup or engine restart
was performed. Evidence scripts and non-secret receipts remain under `.runs/`.

### Limits and downstream handoff

The fixture uses trusted fixed paths with lstat/fstat checks and no concurrent writer. It does not qualify race-free
path opening, inherited ACLs, hostile mount replacement or native host permissions. Production must qualify those
boundaries under its actual service identity and installer. The positive same-UID countercheck requires keeping
untrusted execution outside the broker identity/mounts and enforcing real Agent file-tool exclusions.

Each measured signing call was a new PHP process that reread the key; the holding process does not implement a
credential cache. Missing-key denial therefore does not prove cache invalidation in a long-lived production signer. Stop/current-authority checks, runtime-channel authentication, concurrent requests, replay,
permission removal and cross-role/attempt denial remain unimplemented. No new Python broker or production Runner
was introduced; the Python driver only orchestrates direct tools and PHP only exercises fixture primitives.

Controlled restart/recreation and backup restoration are not automatic service restart, logout/boot, suspend/resume,
engine crash, offline recovery or restoration freshness proof. The recovery copy is a temporary same-engine volume,
not a qualified separately protected/off-host backup. The encrypted fixture is a file, **not PostgreSQL ordered
storage**. R1 is advanced, not closed; R2/R3 supervision, accounting and required-check obligations remain open.

## Disposition and remaining proof

Continue the protected-file candidate without Keychain enrollment. Minimum qualification still needs:

1. Effective file/ACL/parent-path protection and installation-owned mount exclusion with the actual service identity;
   supported automatic service/runtime lifecycle, unsafe-state cache invalidation and recoverable provisioning.
2. Every issuance-field crypto binding, key-version selection, original-byte-preserving rewrapping and independently
   protected backup/restore. Never decrypt authentication envelopes as delivery recovery or auto-reissue after loss.
3. Atomic immutable staging and exact durable receipts on independent PostgreSQL sessions: identical retry and lost
   response, changed tuple/bytes rejection, same-order collision, delayed predecessor and reassignment.
4. Cleanup retaining permanent tombstones and slot high-water; no replay resurrection. Capacity denies new staging
   without discarding existing recovery/deduplication history.
5. Independent incarnation-bound restoration admission and lossless reconciliation. Unknown commits, missing
   keys/receipts or partial reconciliation stay unavailable, not locally asserted readiness.

TASK-00138 owns minimum selection/qualification. TASK-00139–00141 own actual authority, storage and role/broker
composition before activation; TASK-00142 owns integrated parallel/recovery proof. Qualifying tagged adoption and
real consumer conformance remain prerequisites. No production service, Agent session, managed Workflow or launch
permission was introduced. Independent review must assess this replacement and its scoped evidence.
