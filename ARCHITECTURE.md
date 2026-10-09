# Architecture

Fight Agent OS starts with the Slim composition from `johnnickell/project-slim`.

- `src/Domain/`: business state, invariants, value objects, and capability interfaces as introduced
- `src/Application/`: command/query handlers and application coordination as introduced
- `src/Adapter/`: HTTP Actions/Responders, persistence, external services, and runtime integrations
- `client/`: React/strict TypeScript non-product shell at `/app`; Routes/Layouts/Pages/Components, typed public
  boot config, Docker-pinned tooling, typed API/CSRF service boundary, and a semantic production-component
  Storybook catalog; injectable fail-closed authority cache, shared permissions, guarded outlets/actions and
  bounded in-memory navigation intent are available but not mounted into the public shell; an independently
  scoped semantic system/light/dark presentation selector and CSP-compatible prepaint are mounted; production `/me`,
  authentication integration, product journeys and final visual design remain planned
  (TASK-00027's transport and TASK-00028's injected authority foundation have independent review/scoped QA acceptance)
- `.pi/skills/`: local planning, engineering, Graphify and instruction-writing guidance; managed runtime execution remains planned
- `harness/pi/`: installable terminal identity; future typed Workflow client capabilities remain separate

Fight Common and Fight Access Control remain Composer dependencies. The inherited `App` namespace and
hello-world HTTP behavior are preserved for the initial scaffold. Framework migration is a future decision. The
accepted package boundary and its focused automated checks are summarized in
[application ownership enforcement](docs/engineering/OWNERSHIP.md).

The proposed web application owns planning and workflow records; Pi performs assigned agent work. The browser
will support planning and observation first, and workflow commands as they become well defined. These are
design directions, not claims of implemented behavior. See [the foundation brief](planning/FOUNDATION.md).

Human-readable architecture and interfaces come first; tests prove behavior rather than dictate the design.
See [engineering standards](docs/engineering/STANDARDS.md), [Team roles](docs/engineering/TEAM.md) and
[isolated TASK startup](planning/tickets/00032-TICKET.md). PHP owns deterministic workflow transitions and
sandbox provisioning requests; model-generated instructions cannot expand permissions or container policy.

The [terminal execution contract](docs/engineering/TERMINAL_EXECUTION.md) records the approved terminal-first
sequence, measured local sandbox primitives and remaining launch blockers.
