# Architecture

Fight Agent OS starts with the Slim composition from `johnnickell/project-slim`.

- `src/Domain/`: business state, invariants, value objects, and capability interfaces as introduced
- `src/Application/`: command/query handlers and application coordination as introduced
- `src/Adapter/`: HTTP Actions/Responders, persistence, external services, and runtime integrations
- `client/`: reserved for the planned React/TypeScript application
- `.pi/skills/`: fresh project-local skills; no workflow suite is implemented yet

Fight Common and Fight Access Control remain Composer dependencies. The inherited `App` namespace and
hello-world HTTP behavior are preserved for the initial scaffold. Framework migration is a future decision.

The proposed web application owns planning and workflow records; Pi performs assigned agent work. The browser
will support planning and observation first, and workflow commands as they become well defined. These are
design directions, not claims of implemented behavior. See [the foundation brief](planning/FOUNDATION.md).
