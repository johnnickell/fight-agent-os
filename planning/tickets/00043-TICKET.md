---
id: TICKET-00043
epic: EPIC-00006
title: Gather judgment evidence through authorized tools and analysis
status: ready-for-agent
---

# Gather judgment evidence through authorized tools and analysis

## Problem statement

Some questions need current MCP/application observations or qualified analysis rather than existing files alone.
Composing tools must retain the invoking principal's permissions and distinguish safe SQL access from ordinary
primary-backed business operations, without turning the judgment tool into a privileged execution escape.

## Solution and boundaries

Implement the MCP composition, repository/application analysis and SQL contracts accepted by
[WF-031](../wayfinder/tickets/WF-031-define-general-purpose-bounded-judgments.md), extending
[TICKET-00042](00042-TICKET.md)'s shared judgment capability. Harness gathers authorized evidence through declared
operations; Jev classifies that evidence using typed questions. There is no new authenticated Jev principal.

- Propagate server-trusted authenticated caller/delegation context through every MCP sub-command/sub-query,
  including Agent/assignment and repository/environment scope. A model-claimed caller identifier or ambient
  service identity is insufficient. Recheck downstream authority on each request, including revocation and
  delegated scope. Keep credentials outside prompts, Agent-visible results and ordinary logs.
- Existing MCP commands and queries are available according to the invoking principal's actual permissions.
  Preserve operation-specific approval, idempotency, effect, uncertain-outcome and recovery rules for authorized
  mutations as well as reads. A question is not permission to execute a generated command or arbitrary shell string.
  Tool composition does not change WF-015 peer-Agent delegation or grant one Agent another Agent's rights.
- Bound whole-composition depth, operations, input/output, time, concurrency and spend. Trace identity and sub-call
  receipts with safe provenance. On permission loss, cancellation, reload or partial failure stop dependent work,
  reconcile actual effects and never silently replay an uncertain command. Invalid/unavailable judgments remain
  explicit and retain their consumer's established failure policy.
- Support qualified static analysis of the current repository and separately qualified inspection of its running
  application locally or in production. Inspect analyzer configuration/plugins and potential code execution,
  path/network effects and resource use rather than assuming an analysis label implies safety. Bind deployment
  observations to the actual environment/revision; local source alone does not establish production identity.
- Data access does not authorize provider disclosure. Minimize/redact inputs and enforce independent egress policy
  before Jev evaluates gathered evidence. Return compact typed answers and authorized references by default;
  raw source/query/tool results use separate explicitly authorized evidence access.
- Prefer a read replica whenever a capability exposes Agent-controlled SQL, including MCP SQL tools. Primary SQL
  needs explicit applicable authorization, an enforced dedicated read-only database role and query time/resource/
  result limits. Physical credentials do not replace caller authorization. Enforce read-only behavior through
  qualified database controls rather than merely checking whether text starts with SELECT.
- Record actual SQL endpoint role, result freshness and replica lag or unknown values. If a replica is unavailable
  or too stale, report the limitation; no silent fallback to primary. Bound resource impact even for reads.
- Normal API/MCP business operations may use the production primary under existing domain permissions, including
  authorized writes and their normal approvals. Internal SQL does not transform such an operation into Agent-
  controlled SQL. Business API permission is not general SQL permission, and vice versa.
- Obtain live application/SQL evidence freshly by default. A prior immutable execution receipt can answer a question
  about that historical run only. Match all source/context/policy and current permissions for permitted reuse;
  never report old results as fresh or invoke an operation just to regenerate a cache entry without its authority.

Exclude arbitrary shell execution endpoints, new permission grants, production deployment, automatic database
provisioning/cutover, broad production access and duplicate package authority. A qualified replica/read-only
integration is required for the applicable SQL route, not presumed present. Static file judgment remains usable
without SQL readiness. Apply ADR 0001 ownership and ADR 0002's durable-effect/transaction boundaries; analysis
connections cannot pretend their writes belong to a business transaction.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Gather MCP evidence | Invoke a declared permitted command when required; preserve its native contract | Invoke permitted sub-queries and classify resulting evidence | Composition/sub-call/result observations plus the operation's own events | Only authorized underlying effects, bounded provider usage and receipts |
| Analyze repository or application | Run a specifically qualified analyzer/inspection operation | Read authorized analysis observations and deployed identity | Analysis completed/partial/failed | Qualified execution/read/network effects under the caller's scope |
| Analyze data through SQL | Submit bounded authorized read-only SQL; no data-changing command route | Inspect permitted results, endpoint role, freshness/lag | Query outcome and provenance observations | Database read load, bounded result access and optional permitted disclosure |
| Recover or inspect composition | Cancel/reconcile a pending composition; no blind retry | Inspect current authority, receipts and verified effect state | Cancellation/reconciliation/intervention facts | Verified continuation or stop, never fabricated success/replayed effects |

Commands/queries retain their existing owner's semantics and events; do not rename package operations merely to
wrap them. A Jev classification is an observation, not a replacement for execution exit/receipt or acceptance.

## Validation and permissions

Use actual authenticated invocation context, scope every hop and reject forged/lost delegation. Validate capability
allowlists, environment/repository identity, protected credentials, disclosure restrictions and operation-specific
approvals. Apply deterministic execution constraints and the applicable TICKET-00038 guard policy to covered tool
effects; nested calls cannot bypass controls. SQL read-only roles and query/resource bounds require qualification
against the actual database integration, including attempts to obtain writes or unsafe effects through indirect
execution. Unsupported guarantees block that route, not permission to use a more privileged identity.

## Acceptance and evidence

- Trace a permitted multi-step evidence inquiry with its original principal and per-call authorization; demonstrate
  forged identifiers, lost context, mid-chain revocation and cross-repository/environment requests fail safely.
- Demonstrate existing approval/idempotency behavior for authorized MCP command effects and uncertain outcomes,
  cancellation/reload and late responses without effect replay or credentials in model-visible evidence.
- Qualify safe analyzer behavior against configuration/plugin/code/network effects; prove actual deployment/source
  provenance and independent read/disclosure checks. Malicious source instructions cannot become executable actions.
- Verify replica preference, explicit primary-read authorization, enforced read-only credentials plus caller checks,
  resource/result bounds and honest unknown/stale lag. Replica failure must not silently select primary.
- Demonstrate ordinary primary-backed API/MCP operations retain their existing domain behavior and that their
  permissions do not authorize arbitrary SQL. Actual integration receipts, not model judgments, establish effects.
- Measure bounded useful answers, coverage, cost/latency and failure handling. Qualify infrastructure directly,
  test owned composition/authority behavior and run applicable gates; no production use occurs during planning.

## Sequencing and TASK readiness

Consume TICKET-00042's authenticated shared entrypoint, typed result/provenance and budget contracts, plus existing
operation authorities. Decompose static analysis, MCP composition and SQL by actual independently deliverable
capabilities. Exact permission/capability mapping, analyzer policies, replica integration and query ceilings need
qualification before affected TASK acceptance. Do not block TICKET-00044/00045 static assessment on production SQL.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00174](../tasks/00174-TASK.md) | Gather MCP evidence under the invoking principal | ready-for-agent |
| [TASK-00175](../tasks/00175-TASK.md) | Run qualified repository analysis for judgments | ready-for-agent |
| [TASK-00176](../tasks/00176-TASK.md) | Inspect running applications for fresh judgments | ready-for-agent |
| [TASK-00177](../tasks/00177-TASK.md) | Analyze data through constrained SQL access | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this split on 2026-10-08. WF-031 remains the full accepted source for composition/analysis/SQL rules.
That TICKET decomposition defined requirements without invoking a command/provider/SQL, provisioning a database,
changing permission or creating implementation TASKs. Existing sandbox and first-milestone guard owners remain unchanged.


### Approved TASK decomposition — 2026-10-09

John approved four independently deliverable outcomes targeting **Pi 1.1.0**:

- [TASK-00174](../tasks/00174-TASK.md): compose declared MCP commands and queries under the original invoking
  principal, blocked by TASK-00171/TASK-00161. Preserve per-hop authority, covered nested guards, effect approvals,
  aggregate budgets and uncertain-outcome recovery rather than replaying effects for evidence.
- [TASK-00175](../tasks/00175-TASK.md): qualified repository analysis, blocked by TASK-00174. Validate actual
  analyzer/configuration/plugin effects and source identity; a static-analysis label does not establish safety.
- [TASK-00176](../tasks/00176-TASK.md): fresh running-application inspection, blocked by TASK-00174. Qualify local
  and production targets independently and retain actual environment/deployment/time identity with separate egress.
- [TASK-00177](../tasks/00177-TASK.md): constrained Agent-controlled SQL, blocked by TASK-00174. Prefer the replica,
  require explicit applicable primary-read authorization, enforce database read-only/resource controls plus caller
  scope, and report actual freshness without silent target fallback.

The composition slice depends on the guard because it includes mutations and covered nested effects. The three
analysis slices are independent after composition; neither compaction, optional cache delivery nor whole-ticket
SQL readiness is a prerequisite for static judgments or TICKET-00044/00045 assessment. All four TASKs remain
unranked with explicit blockers, leaving prior TASK scope and executable Board ordering unchanged.

Each route requires actual authority, runtime, target and enforcement qualification before acceptance. Missing
production/replica access remains an affected-route acceptance gap; this approval grants no production access,
provisions no database, deploys nothing and invokes no provider or analysis operation. Implementation, formal
review, publication and runtime activation remain separate.

Next: `/skill:to-tasks TICKET-00044`
