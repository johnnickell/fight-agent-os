# WF-011 Markdown migration prototype evidence

## Question

Can the current Markdown planning portfolio be inventoried, parsed into typed aggregate state, round-tripped through a disposable relational store, and moved through an exclusive authority-state model without losing authored content or relying on the original files for replay?

## Disposable setup

A single-use Python prototype and its generated SQLite database remain under ignored scratch at:

```text
.runs/prototypes/WF-011-markdown-cutover/
```

Run it from the repository root with:

```bash
python3 .runs/prototypes/WF-011-markdown-cutover/prototype.py
```

The prototype is explicitly not production importer code, a PostgreSQL schema, or cutover authorization. It reads `planning/**/*.md`, invokes the read-only `./bin/planning-check`, and writes only beneath its ignored `evidence/` directory. Delete the scratch directory after this durable note is no longer needed for local inspection; do not promote its script or SQLite schema.

The accepted evidence run used source commit `4086d24cf676223373923c39e0779a7aa0cee8bf` and tree `403a455ebc4760f2d63a3b283ca4971ec5372f78`. Its inventory manifest SHA-256 was `ac28c9345f6e2bdfeb2de94d4e392b2d1342e1738674193f547a701d41e3810b`.

## Inventory

The run classified all 144 planning Markdown files and identified 122 aggregate roots:

| Aggregate | Count |
|---|---:|
| EPIC | 4 |
| TICKET | 22 |
| TASK | 69 |
| Wayfinder map | 2 |
| Wayfinder ticket | 22 |
| Research note | 3 |

No archived aggregate roots existed in this source tree. Archive locations were still part of discovery. The importer separated 41 generated blocks from authored content and retained 1,104 sections, 875 Markdown links, and 237 typed hierarchy or dependency relationships.

The first prototype run incorrectly treated `_MAP_TEMPLATE.md` as a map and its placeholder links as broken records. Correcting that failure established an important migration rule: templates are inventory-only inputs, placeholder links are not historical relationships, and templates can never become aggregate roots merely because they contain representative metadata.

## Verification results

The corrected run passed every focused check:

- Typed human references were unique.
- Every parent and dependency target resolved.
- Every non-template local Markdown link resolved.
- Authored and generated parts reconstructed the exact source bytes.
- The source and disposable-database semantic models had the same SHA-256: `02c07308176960d32ab3c2c76aa8abb5e9826fdef6b97ab7ea866a7fb70efa65`.
- A same-source retry was idempotent across documents, aggregate roots, sections, links, and relationships.
- Relational constraints rejected a simulated identity collision.
- A changed source fingerprint invalidated the simulated cutover precondition.
- A simulated dangling parent was rejected.
- `./bin/planning-check` passed with 95 EPIC/TICKET/TASK records and current generated views.
- The authority-state simulation allowed Markdown writes only in Markdown mode, rejected both channels during the short freeze, and allowed database writes only after the database mode switch.

No authoritative Markdown, production database, repository authority setting, or tracked application code changed during the prototype.

## Accepted interpretation

The proof supports a deliberately small migration protocol rather than the prototype's disposable schema or a deployment-style control plane:

1. Classify every source file, but import only recognized Planning aggregates and supporting aggregates.
2. Mint typed Fight Common COMB IDs once while preserving existing human references in an immutable mapping.
3. Create one self-contained baseline event per aggregate. Its event-store-owned payload contains all typed fields and relationships, recognized authored sections, ordered supplemental Markdown for unknown sections, unclassified catch-all content, the complete authored Markdown excluding regenerable view blocks, and source provenance.
4. Never require a repository file to replay aggregate state. Source path, commit, and fingerprint are provenance rather than content pointers.
5. Preserve Git as legacy edit provenance instead of inventing semantic domain events from historical commits.
6. Exclude generated boards, indexes, and child tables from authored state and regenerate them from database projections.
7. Stop the dry run for any identity ambiguity, unknown lifecycle value, broken relationship, authored-content loss, or unpreserved completion evidence. Fix the source or importer and rerun rather than building a waiver system.

## Accepted cutover and retirement protocol

An authorized human pauses planning edits, reviews one readable dry-run comparison against an empty database, and invokes an explicit apply operation. The apply operation imports the approved baseline and changes the repository's single authority mode from Markdown to database in one transaction. Failure leaves Markdown authoritative; there is no dual-write interval.

Before any new database-authoritative planning write, the untouched Markdown snapshot remains an immediate recovery option. After database writes begin, the first version supports database backup/restore and forward repair only. It does not implement reverse migration.

Markdown remains visible and read-only during a short stabilization period. A later explicit retirement command may preview and delete the migrated record corpus and generated views while retaining active policy or architecture documents and a small authority tombstone. It uses a branch such as `planning/cutover-<short-id>-retire-markdown`, pushes that branch, and opens—but never merges—a PR. A `CutoverId`, not a manufactured TASK, owns this operational branch; an implementation TASK may be linked when one already coordinates repository adoption. Merging the human-reviewed retirement PR declares the repository forward-only while Git still preserves historical evidence.

Post-cutover Markdown is rendered on demand from typed database views through Twig. API, MCP, terminal, download, or direct routes such as `/tasks/00010.md` may return `text/markdown` with authority and aggregate-revision metadata. Rendered Markdown is never synchronized back into the repository automatically and is not accepted as mutation input.

Prototype aggregates remain replayable from their event state even when disposable prototype files are eventually purged under the accepted retention policy. Artifact identity, provenance, digest, and purge tombstone remain after payload deletion.

## Verdict

**Supported with constraints.** The current portfolio can be migrated without semantic or authored-content loss, and the authority switch can remain understandable: one dry run, one human confirmation, one transactional switch, no dual writers, and an optional later retirement PR. Production implementation must use the WF-010 event and identity model, not the disposable SQLite schema or synthetic UUIDv5 values used by the prototype.
