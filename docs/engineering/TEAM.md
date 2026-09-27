# Agent team and workflow

These are the accepted team responsibilities for the planned managed runtime. Local skills exist separately;
this document does not assert that Agent provisioning, dispatch or browser QA is implemented.
Harness owns revisioned Agent profile templates, presented as **Team roles**. Templates request capabilities;
Access Control grants actual Agents direct Permissions. A job title, prompt or Skill grants no authority.

| Team role | Responsibility | Boundary |
|---|---|---|
| Project Manager | Roadmap, requirements, Wayfinder and EPIC → TICKET → TASK planning | Human decisions govern scope and priorities; no implementation acceptance |
| Explorer | Scout files, trace source, research and bounded disposable experiments | Prefer a validated faster/cheaper model; cite sources and uncertainty; no authoritative Planning or delivery writes |
| Team Lead | Claim the approved TASK through PHP, prepare the initial execution plan, delegate, reconcile handoffs, route findings and bring decisions to John | Main human contact; no implementation, technical verdict or publication; never extend scope or limits silently |
| Software Engineer | Design architecture/interfaces for humans, implement, test and revise | Own meaningful tests and coverage; cannot independently accept own work |
| Senior Engineer | Independent technical review, architecture and security assessment | Fresh review session, no contribution to implementation or its submitted acceptance evidence; no repairs during review |
| QA Engineer | After technical review, exercise browser/UI or TUI behavior, failure states and acceptance scenarios; capture evidence | Separate session and disposable test data; cannot repair the reviewed source or grant technical acceptance |
| Release Manager | Run `land` for accepted TASKs; prepare and coordinate package release or application deployment runbooks with Team Lead | Separate grants for PR publication, merge, signed tags, release publication, deployment and cleanup; never handles passphrases in transcripts |
| Hotfix Engineer | Senior-capability incident diagnosis, containment proposal and smallest repair | Explicit emergency scope, isolated execution and regression evidence; cannot self-approve or inherit deployment authority |

Architect is a Senior Engineer specialization, not a mandatory additional participant. A design contributor is
not eligible to independently review that implementation. Multiple Agents may share a template; identities,
credentials, sessions and permissions remain separate. Unsupported model choices are reported without silent
substitution. Record requested/configured/provider-reported model identity and unavailable cost honestly.

## Sequence and authority

Project Manager plans; Team Lead is John's execution contact. Explorer assists only where useful. Deterministic
PHP validates grants, claims and transitions, provisions/verifies the isolated workspace, dispatches bounded
Agent sessions, tracks attempts and limits, and reconciles external effects. Team Lead supplies judgment and
delegation requests through those operations; it does not improvise provisioning or become workflow authority.

The first production slice remains **one TASK → Team Lead → Software Engineer → durable Awaiting review**.
EPIC-00008 extends it to **Senior Engineer review → QA Engineer verification → Release Manager land → human PR
handoff**. Documentation-only or otherwise non-interactive work records a reasoned QA-not-applicable disposition;
it does not launch a browser just to satisfy ceremony. UI/TUI work requires relevant QA evidence.

Blocking technical or QA findings return through Team Lead to Software Engineer. Material repairs receive a new
technical review, then rerun affected QA scenarios; unchanged evidence can be referenced with explicit provenance.
QA cannot revise source or append tests to the accepted commit: proposed test changes go through the Engineer.
A QA failure blocks publication independently of technical acceptance. Mere new QA evidence does not invalidate
the preceding review. Later facts that establish a technical blocker do invalidate it. Mechanical reconciliation
uses [landing standards](LANDING.md), preserving applicable technical and QA evidence without another model cycle.

## Release and emergency work

TASK landing remains bounded PR publication, not release or deployment. Release Manager prepares project-specific
runbooks naming exact commits, versions, checks, signing mechanism, effects, rollback/forward options and human
steps. Package signed tags and release publication are distinct from application migrations, deployment and health
qualification. Team Lead coordinates decisions. The local [release skill](../../.pi/skills/release/SKILL.md),
delivered by [TASK-00143](../../planning/tasks/00143-TASK.md), coordinates package release stages using target-owned
policy and certification. Managed execution and application deployment still require their own accepted plans.

Hotfix is a short path: identify incident and authority, record one bounded bug TASK and impact, isolate, repair,
run focused regression and applicable gates, obtain independent Senior Engineer review, then targeted QA where
relevant and the separately authorized release/deploy operation. Skip unrelated discovery, broad refactoring and
unnecessary decomposition. If urgency prevents a required check, a human must explicitly accept the specific
exception; record risk, containment/rollback and follow-up before publication. Never fabricate green evidence.
Emergency authority is time/scope bounded and never disables sandboxing, credential separation or audit history.

## Terminology migration

Planner → Project Manager; Coordinator → Team Lead; Builder → Software Engineer; Reviewer → Senior Engineer;
Publisher → Release Manager. Explorer remains; QA Engineer and Hotfix Engineer are explicit additions. Planning
uses these names now. Runtime implementation must preserve stable stored identities and historical labels through
aliases/versioned mappings rather than rewriting event history or granting new permissions on a rename.

Technical and QA repairs share the Workflow revision-cycle allowance; QA cannot create an unbounded second loop.
Only persisted blocking findings consume a repair cycle; mechanical evidence reconciliation does not.
