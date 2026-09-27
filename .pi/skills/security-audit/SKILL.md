---
name: security-audit
description: Assess a named security boundary through safe read-only threat and code tracing, separating demonstrated findings from uncertainty and planned controls.
---

# Security Audit

Establish the repository/revision, selected boundary, protected assets, plausible actors and their existing
privileges, entry points, deployment assumptions and permitted evidence methods. Read applicable local security
requirements and [engineering standards](../../../docs/engineering/STANDARDS.md). Reuse authorized scope; clarify
only missing decisions. Internal endpoints and developer tools still have reach and privileges worth tracing.

Follow untrusted input to its actual authority check, state change or external effect. Select concerns that fit
the target: authentication/authorization and ownership; injection and unsafe rendering; secrets in storage/logs;
filesystem/process/network access; dependency versions; resource exhaustion; and failure/recovery behavior.
Check alternative callers and rejection paths, not just the normal request. Inspect tests and configuration as
evidence of intent, while distinguishing them from runtime verification. Dependency presence alone is not proof
of an exploitable version or reachable path.

For Agent execution, read [Team roles](../../../docs/engineering/TEAM.md) and the
[isolated-startup contract](../../../planning/tickets/00032-TICKET.md). Trace repository/tool/model output as
untrusted data; neither an instruction nor a role name grants permission. Examine the deterministic PHP checks,
trusted provisioner boundary, worktree/shared Git metadata, actual mounts, credentials, networks, limits, resource
ownership and stale-worker fencing when implemented. A container name, prompt restriction or desired profile does
not prove isolation. Unimplemented controls remain planned; unsupported enforcement must not be reported safe.

Countercheck each candidate against the strongest mitigating evidence. Report the exact source path/symbol/line,
preconditions, asset and impact, trace or safe reproduction, counterevidence, practical correction and verification.
Use [review severity](../../../docs/engineering/REVIEW.md) by impact; report confidence separately with its reason.
A certain low-impact issue is not critical. Separate confirmed defects, unresolved questions and recommended
hardening. A no-findings result applies only to the inspected sample.

Deliver the threat/scope summary, prioritized evidence, limits and proposed bounded follow-ups. Keep secrets and
exploit-sensitive private details out of portable output. Use authorized ignored `.runs/` evidence when necessary.
No external exploitation, automatic scanner/install, remediation, credential/permission change, vulnerability
publication or compliance certification is included. If stronger evidence requires such effects, stop that path
with the exact gap and required authorization while completing safe analysis.

A selected defect can go to [fix](../fix/SKILL.md); accepted broader changes use
[planning conventions](../../../planning/CONVENTIONS.md). Independent implementation review remains separate.
Primary references and adaptation limits are recorded in the
[source comparison](../../../docs/engineering/SKILL_GAPS.md#architecture-and-security-source-decisions);
refresh relevant upstream security guidance when relying on version-sensitive claims.
