# Local operational archives and temporary restore

## Question

For [WF-039 — Define searchable retention and verified archives](../tickets/WF-039-define-searchable-retention-and-verified-archives.md),
can planned local SeaweedFS support verified compressed archives, and what must Agent OS own for retention,
preservation and temporary browser search?

## Short answer

SeaweedFS is a plausible private local object-storage candidate, not a qualified archive or search engine.
Its documented object APIs can carry compressed segments; Agent OS still needs verified transfer, a recoverable
catalog, current authorization and existing hold/purge-owner integration. Temporary browser search requires a
qualified search-backend restore/import path, not an assumed S3 RestoreObject or SelectObjectContent capability.

John selected 90-day ordinary archive retention, local-only initial storage and browser search through temporary
restore. These choices do not prove capacity, durability, access isolation or API compatibility.

## Evidence scope

Read-only research on 2026-10-15. GitHub advertised SeaweedFS **4.48**, published 2026-09-28; its tag resolves to
`530be3e37337488ecc34d58441e0bc476e121c93`. Inspected that release's LICENSE, relevant `mini.go` defaults,
`auth_credentials.go` startup authentication and `s3api_object_handlers_put.go` checksum/ETag handling.
Wiki pages were fetched from upstream's moving wiki; observed wiki HEAD was
`c0c76e9c9994685c1c68c223ca7e7b649608b723`, but individual raw wiki requests were not pinned atomically to it.
Wiki claims may therefore differ from the tagged release. No image was pulled, package installed, service started,
live data accessed, or API/storage/restore experiment run. No version is selected for Agent OS.

## Findings

### Required object primitives are documented, not end-to-end archival receipts

The upstream API matrix documents PutObject, GetObject, HeadObject, ListObjectsV2, DeleteObject, conditional headers,
metadata, versioning and Object Lock. It explicitly lists **RestoreObject and SelectObjectContent as unsupported**.
S3-shaped storage is not searchable archival UI and does not establish backend compatibility. [S1]

The inspected PUT implementation distinguishes plain-upload MD5 ETags from multipart composite ETags and supports
additional checksum verification. Neither an ETag nor a copied metadata checksum proves that the stored compressed
payload can be read, decompressed, framed and restored. Use independently expected byte size/digest and bounded
readback/content validation; require a separate actual restore qualification. Do not generalize AWS durability
claims to this local server. [S2]

### Local mini mode needs explicit security and physical resource qualification

`weed mini` is described as a single-process small/development deployment of master, volume, filer and gateways.
Its defaults include a `0.0.0.0` bind, WebDAV/Admin UI enabled, volume count auto-sized from free space and several
unbounded transfer/cache settings. The inspected authentication startup permits all S3 access when no identities
and no config file are supplied; a supplied empty config instead enables fail-closed authentication. These are
upstream defaults, **not a finding about a running Agent OS installation**, which was not inspected. Require private
networking, configured scoped credentials, disabled unnecessary interfaces and measured finite limits. [S3][S4]

Source LICENSE is Apache-2.0; distribution/image/plugins and dependencies still require exact-release licensing
and security qualification. Existing WF-023 selects SeaweedFS for planned Artifacts, not a deployed/qualified archive.
Reuse that private storage boundary if qualified; do not introduce another general object store by inference. [S5]

The bucket-quota wiki describes periodic enforcement that marks over-quota buckets read-only, not a synchronous
hard physical allocation. Quota failures can appear as HTTP 403. Automatic whole-host free-space sizing and delayed
quota checks are not proof of isolation from Artifact/DB storage or WF-038's collection limits. [S3][S6]

### Native expiry does not know application holds or original collection time

The lifecycle wiki measures ordinary expiration from the latest PUT, not Agent OS's trusted original collection
time. It describes scheduled evaluation, versioned delete markers and later physical vacuum reclamation. Thus a
90-day bucket rule is not automatically the accepted 90-day policy, especially on retry/rewrite/restore or mixed
hold-bearing segments. [S7]

Object Lock is documented, but its storage protection does not establish application Workflow/Planning hold
participation or controlled sensitive-content purge. Do not select irreversible compliance retention merely as a
shortcut for application preservation. Owner-aware deletion must fence against newly acquired holds and restore
leases, and metadata loss cannot be interpreted as permission to delete. [S8]

### Archive recovery requires more than payload files

The upstream data-backup guide explicitly treats volume bytes and filer metadata as separate and calls for pausing
writes in its example to avoid a mismatched copy. It labels the example limited, not a universal consistent-backup
recipe. The separate asynchronous metadata-backup documentation concerns metadata only. Neither guide supplies an
Agent OS archive catalog/hold/purge restore receipt. [S9][S10]

John's local-only boundary does not select a backup product or host-disaster protection. Persistent object storage,
archive catalog and owner lifecycle metadata must recover consistently after ordinary restarts; damaged or missing
state blocks unsafe cleanup/republication. An older catalog or restored index must not resurrect purged records or
invent current permissions. Whole-host loss may destroy all local copies, as explicitly accepted.

## Engineering direction

- Archive continuously from captured records, independently of search availability, rather than exporting only at
  index expiry. Use bounded compressed, versioned segments, private provenance manifests and verified readback.
- Keep original collection time/identity through retries and temporary import. Preserve emitted bytes with native
  raw/fragment encoding, not arbitrary parsed-and-reserialized text claimed to be exact raw output.
- Deletion eligibility must consume existing preservation owners. An outage does not waive 90-day age or holds;
  queue eviction under WF-038 is distinct from shortening already-verified archive retention.
- Restore an authorized bounded window into an isolated temporary search scope. Backend qualification must prove
  that old timestamps are accepted without re-aging live history, authorization is enforced and expired scopes
  cannot be queried. Do not assume a particular backend can import or isolate old data.
- Qualify physical quotas, storage synchronization, conditional writes, crash recovery, unauthorized reads,
  readback corruption, restore cancellation/expiry, deletion/hold races and actual local resource consumption.
  Documentation/source facts are not fresh behavioral evidence.

## Sources

- [S1 — SeaweedFS S3 API matrix](https://github.com/seaweedfs/seaweedfs/wiki/Amazon-S3-API)
- [S2 — Tagged PUT/checksum implementation](https://github.com/seaweedfs/seaweedfs/blob/530be3e37337488ecc34d58441e0bc476e121c93/weed/s3api/s3api_object_handlers_put.go)
- [S3 — Tagged mini command/defaults](https://github.com/seaweedfs/seaweedfs/blob/530be3e37337488ecc34d58441e0bc476e121c93/weed/command/mini.go)
- [S4 — Tagged S3 authentication initialization](https://github.com/seaweedfs/seaweedfs/blob/530be3e37337488ecc34d58441e0bc476e121c93/weed/s3api/auth_credentials.go)
- [S5 — Tagged LICENSE](https://github.com/seaweedfs/seaweedfs/blob/530be3e37337488ecc34d58441e0bc476e121c93/LICENSE)
- [S6 — Bucket quota](https://github.com/seaweedfs/seaweedfs/wiki/S3-Bucket-Quota)
- [S7 — S3 lifecycle](https://github.com/seaweedfs/seaweedfs/wiki/S3-Lifecycle)
- [S8 — Object Lock and retention](https://github.com/seaweedfs/seaweedfs/wiki/S3-Object-Lock-and-Retention)
- [S9 — Data backup](https://github.com/seaweedfs/seaweedfs/wiki/Data-Backup)
- [S10 — Async filer metadata backup](https://github.com/seaweedfs/seaweedfs/wiki/Async-Filer-Metadata-Backup)
- [Release 4.48](https://github.com/seaweedfs/seaweedfs/releases/tag/4.48)
- [Existing evidence owner: TICKET-00094](../../tickets/00094-TICKET.md)
- [Existing runtime/storage decision: WF-023](../tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md)

## Remaining qualification

WF-040 must qualify the composed collector/archive/backend path and exact versions against WF-038/039. Resource
measurements, real storage API/durability/authorization behavior, local metadata recovery, temporary-import support
and faithful query results remain unverified. If qualification needs a live experiment, obtain separately bounded
authority. No archive/backend deployment, runtime grant or upstream upgrade is selected by this note.
