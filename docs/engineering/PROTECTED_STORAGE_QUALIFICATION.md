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

At this checkpoint, encrypted storage and original-byte recovery remained unmeasured; the follow-up below advances
those primitives. An independently controlled restore-admission witness and qualified physical storage remain open.
Actual package conformance, database roles, installer/path/ACL and service lifecycle remain prerequisites. R2 uncertain
effects/lifecycle/aggregate retention and R3 required-check policy are unchanged. No production readiness or full-TASK
acceptance follows from these primitives.

## Encrypted storage and dump/restore comparison — 2026-10-13 UTC

This **R1 / AC-1** follow-up combines PHP/libsodium encryption with actual PostgreSQL storage and `pg_dump` /
`pg_restore`. It measures original-byte recovery and comparison with separately retained fixture evidence, **not
restoration admission**, package conformance or a production sink. Instruments, bounded command transcripts,
profiles and safe observations are in `.runs/tasks/TASK-00138/encrypted-restore/` in the authorized worktree.
No production schema, package adapter, migration, service or product test was added.

The expanded run `2ffabbe4-274a-42c2-8740-d135d340cc80`, 21:43:56–21:44:07 UTC, passed **94 scoped observations**.
That count includes process/cleanup checks and repeated checks after restart, not 94 independent guarantees.
PostgreSQL 17.10 uses the preceding pinned Alpine image. PHP 8.5.10/libsodium 1.0.18 uses the preceding custody
image `sha256:a4193582e6675ff918eb0fd95f450586b9910cf199cab60da1825c5279874ecf`. No images were pulled or built
for the experiment. Exact instruments are copied into each run before invocation.

### Encryption, rotation and retained identity

The fixture generates two random synthetic 32-byte credentials and independent delivery-v1, delivery-v2,
authentication and fingerprint keys inside owned storage. PostgreSQL contains only encrypted credential material;
key files and continuity/reference evidence are separate volumes, absent from the database container. A dedicated
UID 10001 PHP process reads `0400` keys under a `0700` directory on a read-only mount. This reuses the trusted-parent
assumption; it does not newly qualify path races, ACLs, provisioning durability or a production service identity.

XChaCha20-Poly1305 binds the twelve issuance field names, an explicit fixture-domain context and the selected key
version. The fixed-order, type-preserving JSON encoding is **synthetic**, not package canonicalization or validation.
A separate keyed fingerprint retains original-byte identity across rewrap; receipt IDs come from PostgreSQL's UUID
factory. A fixed-message HMAC continuity value retained outside the database lets a fresh process compare recovered
bytes without exporting the credential. No plaintext, keys, ciphertext or continuity tags enter host evidence/logs.
The reference manifest includes receipts, binding metadata, encrypted-material digests, tombstones, order and counts;
it is a fixture comparison record, not a complete independent issuance/audit journal.

| Direct probe | Observed result and boundary |
|---|---|
| Change each of twelve issuance fields | Every AEAD decryption failed. Cross-delivery envelope substitution, changed ciphertext/nonce/version and use of the authentication key also failed; an unknown version was unavailable. |
| Rewrap the second entry | Decrypting under v1 and encrypting the **same bytes** under v2 changed the envelope while retaining receipt, binding and keyed fingerprint. A new recovery matched the original continuity value. No successor credential was generated. |
| Roll back a rewrap transaction | Exact stored state remained unchanged. This is explicit SQL rollback, not a killed backend or unknown-COMMIT outcome. |
| Temporarily remove the old key | A fresh process could not recover the v1 entry and generated no replacement key; the v2 entry still recovered. An explicit operator rename restored the retained v1 key. This is not key-retirement policy or independent backup qualification. |
| Cleanup | Removed the first material while preserving both receipts/bindings, its tombstone, two lifetime reservations and high-water 20. The prior concurrent/idempotent SQL matrix was not rerun against this encrypted fixture. |

### Real older/current dumps, comparison and non-admission

An older custom-format dump was taken after the first delivery at order 10. A later dump followed the second delivery,
rewrap and first-delivery cleanup. The expanded run's dumps were **5,733 and 5,930 bytes**, retained only in the
PostgreSQL container's bounded `/tmp`; host evidence contains their lengths/digests, not dump bytes. Actual
`createdb` / `pg_restore --exit-on-error` restored each into a different database name/OID on the same server.

- The older restored database disagreed with the separately mounted final reference: it lacked the later receipt,
  reset observed order to 10 and contained the formerly removed material. This is a **rollback counterexample**,
  not proof that restore itself prevents resurrection. The comparison did not decrypt that restored material,
  repair it, publish a witness or authorize any use.
- The latest restored database exactly matched the fixture reference, retained the first tombstone/material absence
  and recovered the second credential's original bytes under v2. Fresh-process comparison/recovery repeated after
  controlled database-container restart. This establishes retained-volume continuity, not power-loss durability.
- Six actual SQL mutations separately changed receipt, binding, tombstone, high-water, reservation count and material
  in the restored database. Each made comparison fail; rollback restored the exact prior dataset. These are direct
  infrastructure probes, not seeded product-suite failures or tests that quality tools catch errors.
- Unmounting the reference and replacing its JSON with corrupt bytes each returned comparison unavailable without
  changing the database. Only an explicit operator action restored the reference. Content equality is named
  `fixture-content-match`, never ready/admitted. No incarnation-bound generation or permission is produced.

Different database OIDs/names identify these observed copies, **not an authenticated, never-reused storage
incarnation**. The reference is outside the restore target but on the same trusted engine and under the fixture
operator's control. There is no conflicting all-writer fence, independently authoritative complete history,
lossless forward repair, atomic witness publication, package invocation or delayed external effect here. The
[package restoration contract](#package-contract-inspected) still requires those guarantees. A content hash or
successful latest-dump comparison cannot stand in for them; ordinary writes must not need a new manual manifest.

### Isolation, failed attempt and retirement

PostgreSQL ran non-root UID 70, read-only root, network none with a loopback-only listener, 0.5 CPU, 512 MiB/no extra
swap, 64 PIDs, 16 MiB shared memory, 16 MiB `/tmp` and 1 MiB socket tmpfs. PHP clients joined only that exact network
namespace, ran UID 10001 with 0.5 CPU/128 MiB/no extra swap/64 PIDs/16 MiB shared memory, and had read-only root.
All containers dropped capabilities, used no-new-privileges, disabled Docker logging, 256 file descriptors and zero
core limit. Only bounded initializer/operator containers ran root with the recorded CHOWN/FOWNER/DAC_OVERRIDE
subsets. The sole host bind was the read-only instrument; no home, Git administration, Engine socket or Agent
workspace was mounted. At most one database and one short-lived helper ran concurrently.

Three owned named volumes held data, keys and reference evidence. They have **no hard byte quota**; finite input,
a three-entry fixture bound and small dumps do not qualify physical/WAL/engine allocation or aggregate retention.
No production credentials, live Agent/provider, shared-engine restart, host exhaustion or service enrollment occurred.

The first run `3b1045de-5c92-4984-b023-afee697ea337` failed when the driver requested `compare-older` but PHP accepted
`compare-old`; earlier crypto observations are retained, not full-run success. Correcting that selector produced
run `b8984b87-b9a1-47b6-ab8a-fd5867247deb` with 70 observations. The expanded run replaced its weak array-only
comparison samples with actual SQL mutations and added missing/corrupt-reference reader checks. All three exact
sources/results remain. They retired respectively **11, 14 and 17 containers**, each with three volumes, by matched
full identity/owner; every final owner inventory was empty. Both successful runs preserved observed main and
TASK-00044 service start times. Cleanup is ordinary logical deletion, not forensic erasure. Non-secret scratch remains.

R1 advances only for these encryption/recovery/comparison primitives. R2 uncertain effects, supported lifecycle and
aggregate accounting, and R3 independently authoritative required checks receive **no new qualification** here.
TASK-00139 remains blocked; independent review and applicable QA are separate from these builder observations.

## Schema-scoped restore admission fence — 2026-10-13 UTC

This **R1 / AC-1** follow-up qualifies a PostgreSQL role/transaction mechanism for **controlled replacement of one
schema**, not full-database restoration or package conformance. Run `d431931d-64f9-4399-9cd1-ec49e607ff47`,
22:26:39–22:26:51 UTC, passed **93 scoped observations**. Exact SQL/Python, independent-session commands and outputs,
blocking-backend observations, profiles and retirement are under `.runs/tasks/TASK-00138/restore-admission/` in the
authorized worktree. The first attempt passed; intentional denied operations and terminated clients are retained.

### Boundary and retained facts

The dump contains only `payload`: inert entries and local generation/high-water metadata. A separate, non-dumped
`control` schema holds an append-only fixture journal, sink-fact projection, generation sequence, incarnation registry
and admission gate. A non-login owner owns both schemas/functions. The connected runtime role has only CONNECT,
schema usage and EXECUTE for **stage, retire and status**; no direct table, schema-creation, temporary-table, role-
escalation or reconciliation privileges. Definer functions use an explicit `pg_catalog` search path and qualified
relation names. This is measured SQL privilege separation, not authentication of a deployed service: the trusted
operator uses local `docker exec`/Unix-socket sessions in an isolated, trust-authenticated fixture database.

Every fixture runtime operation locks the protected singleton gate **for the whole transaction**. Restore closure,
repair and publication take that same conflicting lock. This conservatively serializes all fixture operations,
including status; it is not a throughput recommendation. Closure commits before `pg_restore` may run. It withdraws
the witness and allocates a strictly newer sequence generation and PostgreSQL-generated UUID, retained in a unique
registry outside the dump. The gate binds that logical incarnation to the actual restored entry relation OID and
local metadata; runtime callers also supply the expected incarnation/generation. A relation OID alone is not a
never-reused identity. This mechanism assumes the trusted operator controls **every** schema replacement and does
not roll back `control`; it does not authenticate an arbitrary restored cluster or defeat a bypassing administrator.

Stage/retire atomically append exact inert after-images, a sequence/hash chain and protected count/head anchor while
updating the sink projection. Repair verifies the complete fixture chain against that anchor and projection, then
reconstructs the latest retained rows, original receipts, tombstones and maximum order. It creates neither new
receipts nor replacement material. Repair commits while closed; publication separately rechecks the history,
payload, incarnation, generation and relation identity under exclusion before publishing the witness. Ordinary
admitted writes extend the journal automatically, without manually renewing a content manifest or generation.

Staged payloads are public inert strings, **not encrypted credentials**. The sink projection is
another protected table, **not an external delivery sink**. Earlier inert values remain in journal history; this is
not a credential-erasure or key-retention design. These reduced state facts omit the complete package canonical
request, twelve-field issuance, Agent/Permission/key authority, leases and external-call histories. The journal's
source and projection share one database transaction/control plane; neither supplies independent off-host durability.

### Direct outcomes

| Probe | Observed outcome |
|---|---|
| Runtime privilege bypass | Eleven actual role-level attempts failed SQLSTATE `42501` without state change: payload/journal/gate writes, repair/publication/restore/private-helper calls, owner-role escalation, schema creation, temporary shadow table and replication-role bypass. These are the fixture's complete exposed paths, not all package writers. |
| Live writer versus close | Restore closure demonstrably blocked on the live stage transaction's backend. After writer commit, closure proceeded; the original subject was denied and the new generation/incarnation remained closed. |
| Interrupted operator before replacement | Controlled database restart preserved the closed gate, pending incarnation and journal. No cached startup readiness resumed writes. |
| Actual older restore | `pg_dump --schema=payload --format=custom` and `pg_restore --clean --if-exists --exit-on-error` restored the first-entry checkpoint. It resurrected the inert first material and omitted later entries/cleanup; the non-dumped journal was unchanged. Old local generation, attempted publication and both runtime writers remained denied. Restored inert material was inspected, not authorized for use. |
| Missing/corrupt history | Removing a journal event, changing its receipt, removing a sink projection or changing its terminal flag each denied repair. Privileged fault transactions rolled back exactly; no missing fact was interpreted as initial installation. |
| Forward repair | Exact retained later receipt survived; the first entry remained retired with null material; high-water stayed 30. Payload matched the journal's latest facts, but repair alone did not admit operations. |
| Publication before commit | A reader demonstrably blocked behind tentative publication. Killing that exact publication backend rolled the witness back; the released reader saw unavailable. The terminated client exited 2 and the gate remained closed. |
| Committed publication/result withheld | `psql` suppressed the publication result, committed and then slept. An independent role connection verified the exact current subject before the sleeping backend was terminated (client exit 2). This is controlled result withholding, not network-level unknown-COMMIT fault injection or inference from an attempted update. |
| Witness/identity faults | Missing/stale witness, swapped local incarnation and wrong relation OID each denied the guard; fault rollback retained exact prior state. A pre-restore subject remained denied after successful publication. |
| Ordinary recovery and next switch | Exact retry returned the original later receipt; the cleaned ID stayed retired. A new ordinary write appended history without a new manual witness. Another close advanced incarnation/generation; a previously established REPEATABLE READ snapshot failed with SQLSTATE `40001` rather than using stale readiness. That error still needs sanitized unavailable mapping in the real adapter. |
| Replacement process | After second repair/publication, a different database container on the same volume retained the exact gate, entries, journal and sink projection. A fresh role connection admitted only the current subject. This is process replacement, not replacing/restoring the whole dataset. |

Genesis was explicit operator provisioning of a fresh empty fixture with an initialized journal anchor and closed
gate, followed by comparison and publication—not runtime inference that absent history means a new installation.
There is no runtime privilege to manufacture this initialization. No real installation's external inventory was
established, and no production bootstrap is authorized.

### Isolation and remaining admission work

The pinned PostgreSQL 17.10 Alpine image is the preceding `742f40…` image. Database/replacement containers used
UID/GID 70, network none/no TCP listener, read-only root, all capabilities dropped, no-new-privileges, disabled
logging, 0.5 CPU, 512 MiB/no extra swap, 64 PIDs, 16 MiB shared memory, 16 MiB `/tmp`, 1 MiB socket tmpfs,
256 descriptors and zero core limit. Only the short volume initializer used root/CHOWN/FOWNER. No host bind,
credential or Engine socket was mounted. At most one database ran. Three containers and one owned named data
volume were removed by exact ID/name and owner; final owner inventories were empty and main/TASK-00044 service
starts were unchanged. The small schema dump lived only in that owned volume; raw non-secret observations remain.

The data volume has **no hard byte quota**. Twelve journal-event slots and 128-byte new material bound fixture
inputs, not WAL, indexes, dump allocation, shared Engine overhead or retained host evidence. No host exhaustion,
Engine restart, service enrollment, provider or Agent participated. Controlled restart is not power-loss durability.
R2 physical/lifecycle fencing is unchanged.

This establishes a candidate **schema-scoped gate/role/journal mechanism**, not a complete independent restoration
boundary. A restore of the whole cluster could roll back its control schema too; that topology is unsupported.
Package-compatible complete history, actual external sink/call draining, independently protected control-plane
recovery, installer/role coverage for every real writer, physical incarnation verification and supported lifecycle
remain required. Separate encrypted-storage and gate experiments cannot be combined into a conformance claim.
TASK-00139 remains blocked; no production check or credential use becomes ready from `fixture-published`.

## Disposition and remaining proof

Continue the protected-file candidate without Keychain enrollment. Minimum qualification still needs:

1. Effective file/ACL/parent-path protection and installation-owned mount exclusion with the actual service identity;
   supported automatic service/runtime lifecycle, unsafe-state cache invalidation and recoverable provisioning.
2. Qualify package-compatible issuance encoding, key-version lifecycle and independently protected backup/restore.
   The encrypted follow-up measures twelve-field AEAD rejection and original-byte-preserving rewrap under a synthetic
   encoding, not package conformance. Never decrypt authentication envelopes for recovery or auto-reissue after loss.
3. Complete encrypted immutable staging and exact durable receipts: concurrent retries, binding/order rejection and
   backend loss were measured in the earlier unencrypted SQL fixture; encryption/rewrap/dump recovery were measured
   separately. Their complete composition, package conformance, crash-loss durability and unknown commits remain open.
4. Complete cleanup/retention and capacity: the follow-up measures tombstone/high-water preservation and contended
   entry admission; physical storage quotas, restart accounting and production retention remain unqualified.
5. Independent incarnation-bound restoration admission and lossless reconciliation. The schema-scoped follow-up
   measures protected roles, conflicting transaction fences, fixture-journal repair and interrupted publication,
   not complete package history or a whole-dataset-independent control plane. Unknown commits, missing keys/receipts
   or partial reconciliation stay unavailable, not locally asserted readiness.

TASK-00138 owns minimum selection/qualification. TASK-00139–00141 own actual authority, storage and role/broker
composition before activation; TASK-00142 owns integrated parallel/recovery proof. Qualifying tagged adoption and
real consumer conformance remain prerequisites. No production service, Agent session, managed Workflow or launch
permission was introduced. Independent review must assess this replacement and its scoped evidence.
