# Prove the Markdown migration and authority switch

**Labels:** `wayfinder:prototype`
**Mode:** AFK + HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-010](WF-010-define-authoritative-planning-domain-and-lifecycle.md)

## Question

What import, validation, reconciliation, rollback, and explicit cutover protocol can move this repository's complete planning history into PostgreSQL without identity loss or two writable authorities?

## Must decide

- A complete inventory and mapping for live and archived maps, WF decisions, research links, EPICs, TICKETs, TASKs, metadata, relationships, authored narratives, generated views, and historical completion evidence.
- Identity preservation, source fingerprints, revision provenance, link repair, ordering, and handling of malformed or ambiguous historical records.
- Disposable rehearsal and comparison evidence proving counts, identities, statuses, dependencies, parentage, decisions, archives, and rendered meaning before cutover.
- Transition write policy, drift detection, reconciliation, freeze window if any, approval roles, authority marker, and the exact point after which database operations are authoritative.
- Rollback before and after the authority switch, including what happens to writes made after cutover.
- Optional Markdown export/snapshot behavior that cannot become a second writable source of truth.
- Treatment of repository files after cutover: preserve by default, with no automatic removal or archive operation.

## Resolution boundary

This decision may create a disposable importer/reconciler prototype under `.runs/` and a durable evidence note linked here. It must not alter authoritative Markdown, create production migrations, write to a production database, switch authority, or remove/archive planning files.

## Preferences required

John must approve the cutover trigger, tolerated reconciliation risk, rollback window, and post-cutover Markdown visibility. The recommendation should require a rehearsal against a disposable database, deterministic comparison, an explicit human authority switch, and read-only Markdown afterward unless exported to a clearly labeled snapshot.

## Resolution

The [disposable migration and cutover proof](../research/WF-011-markdown-migration-prototype-evidence.md) classified all 144 planning Markdown files at source commit `4086d24cf676223373923c39e0779a7aa0cee8bf`. It imported 122 aggregate roots into a disposable SQLite stand-in, separated 41 generated blocks, retained 1,104 sections, 875 links, and 237 typed relationships, reconstructed exact source content, and produced identical source/database semantic digests. Idempotency, identity-collision, source-drift, dangling-parent, link-integrity, and exclusive-authority checks passed. The proof changed no authoritative Markdown, production database, application code, or authority setting.

The production importer must inventory live and archived locations, classify templates as inventory-only, and treat generated boards, indexes, and child tables as projections. It mints typed Fight Common COMB IDs once, preserves existing human references through an immutable import mapping, and creates one self-contained baseline event per aggregate. Each event-store-owned payload contains every typed field and relationship, recognized narrative section, ordered supplemental Markdown for unknown sections, unclassified catch-all content, the complete authored Markdown excluding regenerable generated blocks, and source provenance. Repository files are never replay dependencies. Git preserves legacy revision provenance; the migration does not manufacture semantic events from historical commits.

Use one understandable adoption operation rather than a deployment control plane. An authorized human commits current planning, pauses planning edits, and reviews a dry-run import into an empty database. The comparison covers counts, identities, statuses, parentage, dependencies, authored content, supplemental content, and historical completion evidence. Any duplicate or missing identity, unknown lifecycle value, unresolved relationship, lost authored content, or unclassified ambiguity blocks cutover. Generated-view and non-semantic formatting differences are expected; there is no waiver framework.

After a clean report, the human invokes the explicit apply operation. One PostgreSQL transaction imports the baseline and changes the repository's planning-authority mode from Markdown to database. Transaction failure leaves Markdown authoritative. There is no dual-write period, scheduled switch, or agent-only switch.

Before any new database-authoritative planning write, an authorized human may return to the untouched Markdown snapshot and discard the imported database state. After the first database-authoritative write, v1 supports database backup/restore and forward repair only; it does not implement reverse migration or automatic fallback.

Preserve the tracked Markdown unchanged and read-only during a short stabilization period. Retirement is a later explicit operation, never part of cutover. Its dry run shows the exact deletion set; apply requires database authority and a clean checkout, deletes migrated records and generated views, retains active policy/architecture material plus a small authority tombstone, creates and pushes `planning/cutover-<short-id>-retire-markdown`, and opens an unmerged PR. The operational `CutoverId` owns that branch; repository adoption does not require a manufactured TASK, although an existing implementation TASK may be linked. Human merge of the retirement PR declares the repository forward-only while Git retains historical evidence.

After cutover, Twig renders read-only Markdown on demand from typed database views. API, MCP, terminal, download, or direct `.md` routes may serve it with database-authority and aggregate-revision metadata. Rendered Markdown is not synchronized back into Git automatically and cannot be submitted as mutation input.

Prototype aggregate state remains fully replayable after an external prototype file is purged under the WF-010 retention policy. Keep artifact identity, provenance, digest, and purge tombstone; do not require an obsolete prototype file to reconstruct Planning state.

This resolution approves a migration protocol only. It does not authorize a production import, schema, command, database write, authority change, Markdown deletion, branch publication, or PR. Those capabilities require downstream EPIC → TICKET → TASK planning selected after this umbrella map reaches WF-019.
