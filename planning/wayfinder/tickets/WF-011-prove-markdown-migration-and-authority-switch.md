# Prove the Markdown migration and authority switch

**Labels:** `wayfinder:prototype`
**Mode:** AFK + HITL
**Status:** Open
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

Write this only after the disposable proof and human cutover-policy decision are complete. A successful prototype is evidence, not authorization to migrate.
