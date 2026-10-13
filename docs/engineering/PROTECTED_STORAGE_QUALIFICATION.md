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

## PostgreSQL ordering and replay primitives — 2026-10-13 UTC

This **R1 / AC-1** continuation measures the selected PostgreSQL mechanism with independent client/backend
sessions. It does not implement a package sink, enroll an Agent or qualify encrypted durable storage. The disposable
SQL and Python instruments stay under `.runs/tasks/TASK-00138/ordered-storage/` in the authorized worktree; no
production schema, migration, adapter or product test is added. The prior custody experiment remains separate.

Run `564fe132-f5fc-44d8-b81d-cc43399fb46e`, 21:24:33–21:24:41 UTC, completed **63 scoped observations** on
PostgreSQL **17.10**, aarch64 Alpine, image
`sha256:742f40ea20b9ff2ff31db5458d127452988a2164df9e17441e191f3b72252193`.
The retained `instrument.py`, `fixture.sql`, `commands.json`, per-session SQL/stdout/stderr, `profile.json` and
`result.json` record exact inputs, independent observations and cleanup. This is a first successful attempt, not
a corrected failure. Observation count includes identity/client/cleanup checks, not 63 independent guarantees.

### Measured transaction shape

The fixture separates immutable delivery metadata/receipt/fingerprint from removable material and persistent slot
high-water. A single `SELECT ... FOR UPDATE` capacity row serializes stage and cleanup, including the same delivery
ID across different slots. Delivery-ID and slot/order uniqueness provide additional constraints. New entries,
material, high-water and an eight-entry lifetime reservation commit together. Existing exact retries are resolved
before checking new-entry capacity; cleanup preserves the entry and reservation rather than forgetting history.
This deliberately conservative global lock is a qualification mechanism, not a selected production schema or
throughput claim. Narrower locks require their own consistent lock order and concurrency proof.

Public inert fixture bytes stand in for encrypted material. Metadata equality uses all twelve field names from
`AgentIssuance::toArray()` at the retained package commit `614c34b189fd1ab73a10ace173e206ce010bb2e3`; the two delivery
contract documents and that class were freshly matched to the pinned Git blobs. This is not adoption or a claim
about current releases. The fixture does not call package constructors/services, validate canonical issuance,
perform actual rotation or authenticate associated data. A SHA-256 fingerprint detects changed fixture bytes;
it is not a production ciphertext/key design. Opaque receipts are independently generated with PostgreSQL's UUID
factory, not derived from material, and verification reads binding metadata plus material presence, not bytes.

| Direct probe | Observed outcome |
|---|---|
| Two concurrent identical stages | Session B had recorded `pg_blocking_pids` while A held the lock. An independent session saw no uncommitted entry. After A committed, both clients returned the same receipt, with one entry/reservation. |
| Exact binding and bytes | Changing each of twelve metadata fields denied receipt verification. With the same delivery ID, each of the other eleven field changes and changed bytes returned conflict. Fabricated/swapped receipts also failed. Changing delivery ID denotes another entry, not a same-ID retry. |
| Arrival order and reassignment | Order 20 arrived after order 30 without lowering high-water. An order-30 different binding was denied; a distinct Agent/caller binding at order 40 advanced the same slot. An older retry retained its original receipt. These are synthetic bindings, not package rotations or authority checks. |
| Before-commit backend loss | `pg_terminate_backend` killed a held transaction. A new session observed neither its entry nor its reservation; the client exited 2. |
| Committed result unavailable to caller | `psql` suppressed the receipt to `/dev/null`; an independent session observed the committed entry before its backend was terminated during a subsequent sleep. A fresh retry recovered the exact stored receipt without another reservation. This is controlled result withholding plus real connection loss, **not** packet-level loss during COMMIT or unknown-outcome fault injection. |
| Last reservation contention | With seven entries retained, A held the eighth while B demonstrably blocked. After commit B returned capacity; eight entries/reservations remained. Existing exact retries still worked at capacity. |
| Cleanup versus replay | Replay demonstrably blocked behind cleanup, then returned retired. Material was absent, receipt verification failed, and changed-byte replay remained conflict. Full binding, receipt/fingerprint, high-water and all eight reservations survived; cleanup did not reopen capacity. |
| Material loss and oversize input | Separate material deletion made verification fail and retry unavailable, without reconstructing material. A 257-byte payload exceeded the 256-byte fixture limit before writing. |

At READ COMMITTED, these sampled blocked/unblocked sequences and fresh-session reads establish transaction
visibility and ordering for this fixture. Three competing sessions were observed waiting on PostgreSQL locks;
barriers use backend state, not an assumed delay. They do not establish every interleaving, deadlock recovery,
timeout classification, authority-writer participation or cross-system exactly-once behavior. An old receipt still
verified before cleanup despite newer slot entries: receipt validity is deliberately **not** current selection,
activation or signing authority. The real consumer must enforce that separate boundary.

### Isolation, retention and remaining limits

One disposable database container used UID/GID 70, read-only root, dropped capabilities, no-new-privileges,
network none, no TCP listener/ports, no host binds/socket, disabled Docker logging, 0.5 CPU, 512 MiB memory/no
extra swap, 64 PIDs, 256 file descriptors and zero core limit. Data used a **256 MiB tmpfs**, with separate
16 MiB `/tmp`, 1 MiB Unix-socket tmpfs and 16 MiB shared memory. Twenty database connections and 20-second
statement/idle-transaction timeouts bounded the fixture. Trusted `docker exec` clients used its private Unix socket;
no Agent/check container received database or Engine authority. Profile limits were inspected, not stress-tested.

`fsync` and `synchronous_commit` were on, but **tmpfs is not power-loss durability**. Backend termination leaves
the same database server alive; this tmpfs run qualifies no server/engine restart, disk failure, backup, restore or
independently witnessed incarnation. The one container was removed after matching full ID/owner, its owner inventory
was empty, and main Agent OS service start times were unchanged. No named volume/network was created; retained
non-secret scratch is not forensic erasure or quota-qualified host retention.

The eight-entry admission bound and 256-byte payload/2,048-byte binding limits apply only to this synthetic SQL
path. They do not bound WAL, indexes, maintenance, service/controller metadata or retained host evidence. Permanent
history cannot grow forever in a finite store: production must deny **new** enrollment/staging at its reserved
capacity without deleting tombstones or disabling existing recovery. Physical quotas, lifetime policy, storage
headroom and restart reconciliation remain unqualified; this is not R2 aggregate-accounting closure.

### Persistent-volume restart follow-up

A second profile replaces only the data tmpfs with one owned local named volume and adds controlled database
container restart/recreation. Run `7c9baac9-fd7a-408d-8a35-4b4812a0ec8b`, 21:29:56–21:30:06 UTC, passed **80 scoped
observations**, including the preceding ordering/replay matrix and seven continuity observations after each
restart/recreation. The replacement had a new full container ID and reattached only that same volume. Fresh
sessions observed the same eight entries, six remaining materials, eight reservations and slot high-waters 50/3;
original receipts still matched, cleaned delivery stayed retired, missing material stayed unavailable and new
admission stayed at capacity. Replaying the retained lower-order entry did not lose its original receipt.

The volume initializer alone ran as container root with CHOWN/FOWNER, then retired before PostgreSQL startup.
It had only the owned data volume, no host bind/socket/network, read-only root, 0.25 CPU, 64 MiB memory/no extra
swap, 16 PIDs and disabled logging. Database/replacement profiles retain the preceding non-root/resource/network
controls except that **the named data volume has no hard byte quota**. Tiny fixed input and eight entries bound
this experiment's requests, not PostgreSQL's physical allocation or future repeated operation. Do not combine the
first run's 256 MiB tmpfs bound with this run's persistence into a claim that one qualified profile provides both.

The first persistent attempt, `a0e7a185-6440-4c80-ace3-deb720c73518`, failed before schema creation: `pg_isready`
accepted the image entrypoint's temporary initialization server, then the SQL connection met its shutdown. Its
exact instrument, error and cleanup remain. The corrected readiness check additionally requires PID 1 to be the
final PostgreSQL executable before the matrix begins. This is an image-specific startup observation, not general
production readiness or package/schema availability. Both intentionally terminated clients in the successful
matrix again exited 2; no unexpected SQL failure occurred in that repeat.

The failed attempt's two containers/one volume and successful repeat's three containers/one volume were removed
after exact ID/name and owner checks. Final owner inventories were empty; main Agent OS starts were unchanged.
These are ordinary logical removals, not secure erasure. No daemon/host restart or shared-service mutation occurred.
Controlled restart and replacement establish retained-volume continuity, **not power-loss/server-crash durability,
backup independence, restore freshness, encrypted recovery or supported unattended service lifecycle**.

Next R1 proof must compose real encrypted storage, purpose-separated keys, original-byte recovery and protected
receipts with an independently controlled restore witness and qualified physical storage. Actual package conformance,
database roles, installer/path/ACL and service lifecycle remain downstream prerequisites. R2 uncertain effects/lifecycle/
aggregate retention and R3 required-check policy are unchanged. No production readiness or full-TASK acceptance
follows from these primitives.

## Disposition and remaining proof

Continue the protected-file candidate without Keychain enrollment. Minimum qualification still needs:

1. Effective file/ACL/parent-path protection and installation-owned mount exclusion with the actual service identity;
   supported automatic service/runtime lifecycle, unsafe-state cache invalidation and recoverable provisioning.
2. Every issuance-field crypto binding, key-version selection, original-byte-preserving rewrapping and independently
   protected backup/restore. Never decrypt authentication envelopes as delivery recovery or auto-reissue after loss.
3. Complete encrypted immutable staging and exact durable receipts: the PostgreSQL follow-up measures concurrent
   retries, binding/order rejection, backend loss and retained-volume restart continuity, not actual package
   conformance, crash-loss durability, encrypted original-byte recovery or unknown commit outcomes.
4. Complete cleanup/retention and capacity: the follow-up measures tombstone/high-water preservation and contended
   entry admission; physical storage quotas, restart accounting and production retention remain unqualified.
5. Independent incarnation-bound restoration admission and lossless reconciliation. Unknown commits, missing
   keys/receipts or partial reconciliation stay unavailable, not locally asserted readiness.

TASK-00138 owns minimum selection/qualification. TASK-00139–00141 own actual authority, storage and role/broker
composition before activation; TASK-00142 owns integrated parallel/recovery proof. Qualifying tagged adoption and
real consumer conformance remain prerequisites. No production service, Agent session, managed Workflow or launch
permission was introduced. Independent review must assess this replacement and its scoped evidence.
