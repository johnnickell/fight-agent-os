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

On a host with Git and Docker, run `./bin/planning-check --setup` once to prepare the local image (and
again when its Dockerfile changes). Then run `./bin/planning-check` to validate, or
`./bin/planning-check --write` to refresh views and close eligible parents. Both use an offline one-shot
container, with no application services, host PHP, or Composer. Build and GitHub invoke the same check
with native PHP: `php -n scripts/planning_portfolio.php`. See the [entry-point diagram](../docs/engineering/planning-validation/README.md).

Archive only when explicitly requested. Prepare the same image before using `./bin/archive-planning`
with `--apply`; its dry run does not need Docker. Scratch work belongs in ignored `.runs/` subfolders.

The future database planning system must prove import, identity preservation, history, export, and recovery
before an explicitly approved cutover retires this directory. Do not maintain two writable sources of truth.
