# Foundation Planning Brief

## Accepted starting points

- A standalone Fight Agent OS repository, independent of Ideaverse and the existing SoftwareFactory
- Slim as the initial framework; reconsider Symfony later if a concrete need warrants it
- PHP Domain/Application/Adapter architecture, CQRS, dependency injection, and domain-owned business behavior
- A customized Pi terminal for engineering execution, with a companion dashboard
- Browser-based planning, research, grill sessions, prototypes, and eventually an explanatory AI conversation
- EPIC → TICKET → TASK planning now in Markdown, eventually in project-scoped database records
- A workspace/tenant boundary with multiple projects is a proposed model to refine during grill
- Fresh skills in `.pi/skills/`; no imported Factory agent workflows or automation
- Ignored `.runs/notes/`, `.runs/handoffs/`, `.runs/worktrees/`, `.runs/archive/`, and `.runs/artifacts/`

## Inherited foundation

The starter provides Slim bootstrap, explicit Fight Common container bindings, middleware, a basic endpoint,
Docker wrappers, the primary Composer lockfile, and integration/functional tests. Its endpoint still reports the
upstream starter greeting. Inherited framework-support receipts and lowest-lock artifacts were removed from this
application foundation because they are not proof of this application's future capabilities.

Fight Access Control is already a Composer dependency. Login, persisted users/agents, permissions, and tenant
isolation are not implemented application journeys. No React application, Swagger UI, Pi integration, or
database planning system has been built.

## Unfinished integrations to plan

- [ ] Review inherited dependency pins and starter qualification tooling; define this application's full gate
- [ ] Define Docker services, persistence, migrations, runtime isolation, and LocalDevelopment enrollment
- [ ] Build `client/` React + TypeScript with ESBuild, Bootstrap, linting, formatting, and frontend tests
- [ ] Establish Route/Layout/Page responsibilities and safe API data mapping
- [ ] Wire HTTP Actions/Responders, request validation, OpenAPI descriptions, and Swagger UI
- [ ] Integrate Fight Access Control, authentication, authorization, and tenant/project boundaries
- [ ] Define fresh Pi skills, extensions, session ownership, and terminal/browser responsibilities
- [ ] Capture activity, evidence, interruptions, and usage with readable drill-down views
- [ ] Define database planning records, revisions, dependencies, and the eventual Markdown migration

These are planning inputs, not executable TASKs. No EPIC, TICKET, TASK, or Wayfinder decision has been created
on John's behalf. Use the templates and a grill session to settle scope and sequence.

## First session

Start by using grill on the smallest useful application foundation. Explain what already exists, recommend answers
to consequential questions, and write the EPIC only after its decisions are settled. Keep Pi workflows and
database planning as explicit downstream boundaries unless John deliberately includes them.

## Initial verification observations

The inherited full `./bin/build` completed with exit 0 for the initial scaffold before support-artifact cleanup.
The main suite passed 33 tests / 205 assertions; each dependency lane ran 26 tests / 152 assertions. The lowest
lane reported 5 deprecations, and Composer warned about the inherited pinned Fight Common commit reference. These
are historical bootstrap observations, not the current application gate. Complete logs and the direct exit result
are retained locally under `.runs/notes/bootstrap/`.
