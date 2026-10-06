# Protected credential storage qualification

## Approved candidate and scope

John approved **encrypted PostgreSQL-backed ordered delivery storage with macOS Keychain key custody** for
qualification on October 6, after approving the [execution catalog](TERMINAL_AUTHORITY.md#permission-catalog-and-operator-confirmation).
This settles the R1 candidate selection, not its supportability, package adoption or production readiness.
[TASK-00138](../../planning/tasks/00138-TASK.md) remains in progress. No real credential enrollment is authorized.

PostgreSQL is the candidate for encrypted immutable delivery entries, exact binding metadata, opaque receipts,
permanent delivery tombstones and nondecreasing slot order. Keychain is the candidate for custody of wrapping and
refresh keys; it does not supply the ordered sink. Authentication-envelope and delivery keys remain separate.
Neither store is Agent-readable or mounted into candidate checks. A protected host component must mediate Keychain
access; the Linux PHP container cannot directly call macOS Security.framework. Its caller authentication, bounded
operations and host/container transport remain to be selected and qualified. Do not export keys through environment
variables or expose a generic decrypt/sign/secret-read endpoint to Agents.

Staged entries stay inert. Exact current package/destination identity, confirmed delivery and fresh fenced
enrollment authority govern activation; every subsequent use repeats current authority checks. A high-water pointer,
receipt, successful Keychain read or completed SQL transaction cannot independently authorize a launch.

## Package contract inspected

The source inspection is pinned to the already retained, **unreleased** Access Control candidate
`614c34b189fd1ab73a10ace173e206ce010bb2e3`. Three archived documents were freshly compared byte-for-byte with that
Git commit, excluding upstream working changes:

- [Protected delivery](https://github.com/johnnickell/fight-access-control/blob/614c34b189fd1ab73a10ace173e206ce010bb2e3/docs/agent-credential-delivery.md):
  delivery-ID-only idempotency, exact original tuple/bytes, immutable staging and independent receipt verification;
  sink calls occur outside package transactions and after confirmed admission.
- [Delivery conformance](https://github.com/johnnickell/fight-access-control/blob/614c34b189fd1ab73a10ace173e206ce010bb2e3/docs/agent-delivery-conformance.md):
  real concurrent adapter qualification, all twelve issuance fields, cleanup replay, original-byte recovery and
  separately qualified consumer activation/use. Package reference tests are not consumer proof.
- [Restoration safety](https://github.com/johnnickell/fight-access-control/blob/614c34b189fd1ab73a10ace173e206ce010bb2e3/docs/agent-restoration-safety.md):
  independent incarnation-bound admission evidence, lossless reconciliation and writer exclusion. The database
  being restored cannot certify its own freshness; Keychain availability alone is not that witness either.

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

## Disposition and remaining proof

**This specific ad-hoc helper is not a supported custody adapter.** The result does not establish that every
Keychain mechanism is unavailable, nor qualify a correctly signed application/helper. Before further custody
integration, select and demonstrate a supported caller identity, signing/access-group configuration and unattended
service lifecycle. Do not treat code-signing output or an entitlement declaration alone as successful authorization.
Any required enrollment/signing action needs its own explicit authority; no personal signing credential was used.

The PostgreSQL ordered sink has **not** been implemented or exercised in this checkpoint. Continuing qualification
must establish, with synthetic material and direct owning tools:

1. Successful exact-item custody across independent processes, unavailable/locked-key denial without prompts or
   fallback, caller/access-group exclusion, key version separation and supported restart/logout/boot behavior.
2. Authenticated encryption bound to every issuance field, corrupt/swapped material rejection and original-byte
   preservation through rewrapping and key outages. Do not decrypt authentication envelopes as delivery recovery.
3. Atomic immutable staging and exact durable receipt verification on independent PostgreSQL sessions: identical
   retry and lost response, changed tuple/bytes rejection, same-order collision, delayed predecessor and reassignment.
4. Cleanup retaining permanent tombstones and slot high-water; replay must not resurrect material. Bounded capacity
   denies new staging while preserving existing recovery and deduplication history.
5. Controlled restart/restore with independently retained history and incarnation-bound admission. Unknown commits,
   missing keys/receipts or partial reconciliation remain unavailable, not automatic reissue or locally asserted readiness.

These are remaining obligations, not executed outcomes. This TASK owns minimum selection/qualification;
TASK-00139–00141 own actual authority, storage and role/broker composition before activation. A qualifying tagged
package and real consumer conformance remain downstream prerequisites. R1 is open for mechanism proof and remaining
integration contracts; R2/R3 check-supervision, accounting and required-check qualification are unchanged. No
production service, Agent session, managed Workflow or launch permission was introduced.
