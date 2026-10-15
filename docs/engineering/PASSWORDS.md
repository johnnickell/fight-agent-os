# Shared password acceptance and hashing

TASK-00039 supplies a reusable library boundary, not an activation, reset, change or login endpoint.
Fight Access Control still owns identity transitions; no package policy is modified.

## Calling contract

Resolve the shared `App\Application\Security\Password\PasswordSetting` service. `rejection($candidate)` returns
`null` for acceptance or a `PasswordRejection` enum; it does not retain the candidate. `hash($candidate)` checks
acceptance before hashing and returns a sensitive encoded Argon2id string, or throws `PasswordRejected` with only
a safe enum reason. Infrastructure failure throws a generic exception without retaining the downstream cause.
These operations have no persistence, audit, metrics, delivery or network effects.

Later activation/reset/change orchestration must call this boundary before dispatching the package's password-setting
operation. When the package operation hashes internally, call `rejection()` first and pass the **original bytes**
only after acceptance; do not hash twice or feed a hash as plaintext. The package's shared `PasswordHasher` and
`PasswordValidator` both resolve to the same `Argon2idPasswords` instance. They deliberately do **not** enforce
new-password acceptance: verification and login-time rehash must continue to work for older passwords that do not
meet a later setting policy. Raw trusted in-process hashing is not authorization to bypass acceptance in a new
setting use case. No end-user permission or public transport is introduced here.

Do not log, audit, stringify, serialize, meter, snapshot or dump candidate strings, returned hashes or lookup
material. A returned hash is necessarily available to its authorized persistence consumer; the service cannot
prevent that consumer from printing a raw PHP string. Owned services retain neither candidates nor produced hashes,
mark sensitive parameters and redact exception arguments. The verified in-memory corpus uses compact 32-byte fingerprints (402,592 bytes per shared instance),
wrapped in `SensitiveParameterValue`,
redacted for ordinary dumps/exports and explicitly refuses serialization. This is not a guarantee against a debugger,
reflection, process-memory access or callers explicitly extracting sensitive values. PHP cannot guarantee immediate
zeroization of copied strings; candidate reachability is limited to invocation locals and the caller's own lifetime.

## Structural semantics

- Reject invalid UTF-8 before inspecting length or categories.
- Count Unicode **code points**, not bytes, UTF-16 units or grapheme clusters; accept 8–128 inclusive. Combining
  marks count independently, so canonically equivalent strings can cross the length boundary differently.
- Require `Lu` (uppercase letter), `Ll` (lowercase letter), `N` (any Unicode number), and at least one `P` or `S`
  (punctuation or symbol), using PHP PCRE Unicode properties. Titlecase `Lt` alone is not uppercase `Lu`.
- Spaces, newlines, combining marks, other valid Unicode and embedded U+0000 are allowed. Whitespace and marks
  alone do not satisfy punctuation/symbol. Argon2id preserves embedded NUL bytes; there is no bcrypt truncation.
- Never trim, normalize to NFC/NFKC, case-fold or otherwise transform the actual password before hashing or
  verification. Precomposed and decomposed spellings are distinct passwords. Paste/password-manager input is not
  filtered; this task adds no client interception.

## Local common-password corpus

The injected application capability `CompromisedPasswordChecker` returns only a boolean. The initial adapter
contains no HTTP client, network fallback or request diagnostic. The [manifest](../../resources/passwords/manifest.json)
pins Probable-Wordlists probable-v2 at commit `2df55facf06c7742f2038a8f6607ea9071596128`: 12,645 ASCII source
lines / 100,206 bytes become **12,581** unique fingerprints / 817,765 bytes. Attribution, the bounded license
review and the derived data's **CC BY-SA 4.0** license are in the [notice](../../resources/passwords/NOTICE.md).

Lookup v1 folds **ASCII A–Z only**, then computes SHA-256 of the exact remaining UTF-8 bytes. There is no trimming,
Unicode case-folding or normalization. The source is ASCII; its transformation rejects unexpected non-ASCII source
bytes instead of relying on locale or a Unicode-library version. Thus `P@ssw0rd` and `p@SSW0RD` match the common
fixture, while a surrounding space or fullwidth letter is a distinct lookup. Unicode uppercase/lowercase and
canonical equivalents outside ASCII remain distinct. This is intentionally a bounded common-password list, not a
complete breach corpus, strength estimator or protection against modified common passwords. It does not claim all
Unicode variants or whitespace-affixed variants are compromised. Updating coverage is a deliberate data/policy change.

The file contains only bytewise sorted, unique lowercase 64-byte SHA-256 hex strings, each followed by LF. These
unsalted public dictionary fingerprints are **not** password-storage hashes or a confidentiality guarantee for
low-entropy dictionary entries. They avoid shipping the plaintext list; they can still be enumerated offline.
The adapter visits the whole verified list with fixed-size `hash_equals()` comparisons and no early match return;
it does not promise formal constant-time execution of PHP or expose positions, prefixes or lookup values.

Construction verifies the complete file checksum, entry count, exact encoding, ordering and uniqueness before
any membership check. Constants pin the production checksum/count. `config/application/passwords.php` eagerly
resolves both corpus and hashing support in the normal glob-loaded graph, so **every HTTP/Console boot in every
environment fails closed** on missing/corrupt data or unavailable Argon2id. Direct construction also verifies data
before use. A shared instance uses its verified in-memory snapshot; a later file change cannot change that snapshot.
Restart/rebuild the container graph to adopt a new corpus. Runtime reads only committed `resources/`, never `.runs/`.
The mandatory check is pre-use/startup; Composer installation alone does not boot the application.

## Reproduction and installation

Install through the [normal isolated setup](BUILD.md). The committed hashed file, manifest, notice and license must
be included in deployment artifacts; a packaging process must not strip `resources/passwords/`. No plaintext
source is needed at runtime. For deliberate offline transformation v1, download the **pinned** URL in the manifest
into ignored task scratch, then use the repository wrapper (with the selected Compose project):

```sh
mkdir -p .runs/tasks/TASK-00039/runs/corpus var/passwords
curl --fail --location \
  https://raw.githubusercontent.com/berzerk0/Probable-Wordlists/2df55facf06c7742f2038a8f6607ea9071596128/Real-Passwords/Top12Thousand-probable-v2.txt \
  --output .runs/tasks/TASK-00039/runs/corpus/source.txt
./bin/exec php scripts/password_corpus.php \
  .runs/tasks/TASK-00039/runs/corpus/source.txt var/passwords/rebuilt.sha256
cmp resources/passwords/common.sha256 var/passwords/rebuilt.sha256
```

The script checks source SHA-256 `ea4c906ebb0b26790c549a047962573f72ccc26f42212b83d70165d9c03fb72b`,
line count and ASCII encoding before transforming; it has no network access itself. Expected output SHA-256 is
`03e2cbdce73b1aac0d667fd2ba18899141c3456b1f7c82be333f39d3e939cc04`. Do not commit the source list or
include it in logs. The manifest also pins the retrieved upstream license declaration and full license text.

## Argon2id and rehash

`Argon2idPasswords::OPTIONS` explicitly selects **19,456 KiB memory (19 MiB), 2 iterations, 1 lane**. This is the
[OWASP Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html#argon2id)
minimum recommended Argon2id profile (checked 2026-10-15), not PHP defaults or a claim of optimal production tuning.
Production operators must benchmark capacity and protect later public operations with their separately planned
rate/concurrency limits; increased cost requires an intentional code/config review. No weak environment override
is accepted. Startup checks algorithm availability and performs a random probe hash, verification and rehash check.
The platform supplies a fresh random salt per password. Verification delegates to `password_verify`; rehash detection
uses the explicit algorithm and exact options. Older algorithms/costs request rehash, but verification does not
retroactively apply the setting policy. No periodic expiry, password history, administrator password selection or
remote breach query is added.

## Verification boundary

Product tests prove structure, shared acceptance/hash behavior, real local membership, format/integrity failure,
redaction and Argon2id contracts. Container boot/shared identity, installed resource packaging, source reproduction
and network-none execution are checked directly with owning tools, not product tests of configuration/tooling.
There are no public routes, UI changes, sessions or package transitions in this task; those require their later
owned authorization, transaction and end-to-end tests. `./bin/build` remains the mandatory complete local gate.
