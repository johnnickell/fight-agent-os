# Planning

Markdown is the current source of truth for Fight Agent OS planning.

- [CONVENTIONS](CONVENTIONS.md): Fight Common's EPIC → TICKET → TASK structure
- [Foundation brief](FOUNDATION.md): starting decisions and unfinished integrations
- [TASK Board](tasks/BOARD.md): execution priority and blockers
- [Roadmap](ROADMAP.md): strategic direction and live EPIC status
- [EPICs](epics/README.md), [TICKETs](tickets/README.md), [TASKs](tasks/README.md)
- [Wayfinder](wayfinder/README.md), [research](wayfinder/research/README.md), [ADRs](adr/README.md)

Grill an EPIC before decomposing it into TICKETs and TASKs. Existing planning skills may help conduct the
conversation, but repository terminology and templates govern the saved artifacts.

Run `./bin/planning-check --write`, then `./bin/planning-check` after editing records.
Archive only when explicitly requested. Scratch work belongs in ignored `.runs/` subfolders.

The future database planning system must prove import, identity preservation, history, export, and recovery
before an explicitly approved cutover retires this directory. Do not maintain two writable sources of truth.
