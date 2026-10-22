# Independent acceptance of a release

Use for `/skill:release <version>`, `/skill:review release <version>` and `/skill:qa release <version>` in the
target repository. A release is a review/QA subject without an implementation TASK. This protocol owns release
identity, report paths and routing; the target owns substantive requirements, gates and publication authority.
Resolve [target project context](../../../../docs/engineering/PROJECT_CONTEXT.md) first. Skill-provider planning,
application standards and verification receipts are not target facts.

## Frame and freeze the subject

Resolve the target repository and canonical base worktree through Git metadata. Normalize the optional `v`
prefix under the target's version policy; reject path separators, traversal and unsupported versions before
writing. Keep acceptance evidence at the canonical base, outside disposable candidate worktrees, in the existing
version-owned `.runs/handoffs/release-<version>/` directory:

- `candidate.md`: scope, requirements and candidate identity
- `ledger.md`: changing stage, approval, dispatch and delivery observations
- `review.md`: independent technical report
- `qa.md`: independent behavioral report
- `runs/<run-id>/`: unique attempts, prompts, history and raw evidence

Verify ignored status, containment and absence of symlinked output components before writing. Coordinate one
writer per artifact: the coordinator owns packet/ledger; only the independent reviewer and QA owner publish
their respective reports. Preserve prior packets/reports in unused run-history paths. Publish reports via a
regular temporary file in the same directory, atomic rename and read-back. Failed publication is an incomplete
handoff, not an actionable chat-only verdict. Target certification/signing paths remain unchanged; link them.

Record repository/package, normalized version, checkout/branch, full base/head OIDs, working-tree state, changed
scope, current stage and known PR. Distinguish preparation-diff base, published compatibility baseline and
release-PR integration base. Dirty subjects need file digests/retained patches as well as complete status.
Give each criterion a stable `REL-01`-style ID, authoritative source and expected evidence. Derive requirements
from accepted target decisions, support/compatibility promises, actual changes and applicable implementation
handoffs. Include required release-endpoint QA even for a prose-only preparation diff.

Freeze the packet during assessment and bind its exact bytes by SHA-256 in reports. Keep routine stage/approval
updates in the ledger, not the packet. Changed requirements or content require retaining the prior packet and
fresh affected assessment, or a proven target-authorized mechanical-reconciliation bridge. Never rewrite a
report to imply that it covers a later packet. Existing TASK acceptance can support unchanged implementation
with provenance; it cannot accept new release-policy changes or claim fresh release QA.

A standalone reviewer may assemble a missing packet from authoritative requirements; that is not implementation
authorship. A QA assessor may do so for diagnostic work but cannot claim PASS without applicable independent
technical acceptance of that packet. Neither may invent requirements or approvals. An ambiguous PR subject must
be resolved from its actual branch, diff and requirements; ask only for the missing identity, never borrow the
last completed TASK. A `status` request reads current state and may write owned status evidence, but does not
create/revise a candidate, dispatch, repair or replace independent reports.

## Apply target rules without inventing a TASK

For release subjects only, substitute this packet's release identity, criteria and paths for TASK identity,
criteria and paths throughout generic review/QA instructions and templates. Ordinary TASK invocations retain
their schemas and paths. An explicit target requirement for a release-owning planning record, a forbidden report
location or an enforced consumer that cannot read release reports is a concrete policy/capability conflict:
stop the affected action for the narrow owner decision/adapter, rather than silently waiving it or faking a TASK.
Record an authorized resolution once so later sessions do not ask again.

Apply all target Spec/Standards criteria with the existing Pass/Fail/Unverified/N/A rules. Support-window,
compatibility, authority and runnable-instruction edits are substantive even in Markdown. Proportionate checks
and permitted gate reuse can cover simple changelog dating; a release does not require re-reviewing every
previously accepted feature. Assess the actual release-PR integration diff and interactions as target policy
requires: a narrow preparation review must not conceal unreviewed integration changes. Separate preparation
acceptance from applicable QA, final-commit certification, signing and external publication. Future stages need
not have occurred for preparation review to accept; missing proof required at the assessed stage is Unverified.

## Publish independent reports and resume

Release technical reports use this header instead of the TASK version-3 header:

```yaml
---
review_handoff_version: 4
subject_kind: release
release_version: <normalized version>
repository: <verified repository identity>
target_branch: <actual branch>
base_commit: <full preparation-base OID>
head_commit: <full candidate OID>
target_status: <clean or full porcelain snapshot; YAML block scalar if multiline>
candidate_packet_sha256: <SHA-256 of candidate.md>
verdict: <accept or revise>
---
```

Retain the target review body: independence, applicable Spec/Standards IDs, criterion-to-evidence mapping,
findings, fresh versus inherited checks, limitations, QA applicability and next action. A contributor to the
implementation or its submitted evidence cannot independently accept. Missing required proof means `revise`,
not an omitted report. A version-4 release report is not a TASK report or input to a TASK-only report consumer.

Release QA uses the [QA report body](../../../../docs/engineering/QA_REPORT_TEMPLATE.md) with repository/version,
packet digest and release criteria in place of TASK fields. Include the technical report's digest and actual
source/runtime identity. Preserve prior evidence and publish INCOMPLETE for the new run before probes.
PASS/FAIL/INCOMPLETE/N/A retain their meanings: missing independence, acceptance or required probes is INCOMPLETE,
not N/A; demonstrated defects remain FAIL. A prose-only preparation diff cannot erase required package-endpoint
QA. Instruction walkthroughs must be labeled; they do not prove live dispatch, signing or publication.

Return subject, verdict/disposition, absolute report path and digest to the coordinator. It reads the report,
verifies independence, packet/content identity, criteria and findings against current state, and rejects stale,
wrong-subject or incomplete handoffs. New failure cannot fall back to an older PASS. Source/requirement changes
need fresh affected review/QA or an evidenced target-policy bridge; final certification still names the exact
tag commit. Independent assessors remain read-only over source/planning and never repair their findings.

The coordinator repairs only authorized release-preparation defects, reruns affected checks and obtains renewed
independent acceptance. Product defects require bounded implementation ownership and regression evidence, not
hidden scope expansion. Missing evidence routes to collection, not an empty repair cycle. Preserve any configured
shared revision allowance across review/QA and resumes; absent one, allow at most two correction cycles, recorded
before repair. Exhaustion or repeated unchanged prerequisite failures require a human decision, not a new ledger.

Delegated assessors return to the coordinator without launching another stage. Standalone review/QA ends with
`Next: /skill:release <actual-version> resume` (use the actual version), not TASK `work` or `land`. Release owns
subsequent authorized delivery under target policy; acceptance itself grants no merge or publication permission.

## Coordinate only supported independent sessions

An authorized release invocation owns coordination through review and applicable QA, not self-acceptance. Use
the provider's supported dispatch workflow only when it explicitly supports release subjects, independent report
paths and role binding. Resolve its capability, target context, model policy, finite revision allowance,
accounting and evidence contract before launch. Pass exact checkout/base/head, packet path/digest, absolute
provider skill/context paths, permitted effects and expected report to a fresh reviewer, then separate QA after
acceptance. Do not include author conversation history or suggest a verdict. Never change a candidate while it
is assessed or run competing canonical writers. Preserve observed exits, session/model identity and reports;
a successful process exit alone is not acceptance.

The current [Build workflow](../../build/SKILL.md) dispatches TASK stages, not release subjects. This instruction
change does not extend its launcher, role/subject parser or automated report consumers. Do not put a release
version into its TASK argument, invent a bookkeeping TASK, set trusted role variables yourself or replace its
monitor with an ad hoc `pi --print`/SDK executor. A CLI flag's availability does not qualify release dispatch.
A future supported release dispatcher must preserve the provider's role, budget, accounting and monitoring
contract; no Pi version equality gate or promise of future compatibility is implied.

When no qualified release dispatch path exists, record that specific capability blocker and prepare the exact
standalone `/skill:review release <version>` or `/skill:qa release <version>` invocation for a fresh independent
session in the target checkout, with packet/context paths. The operator selects the role; prompt/RPC text cannot
promote it. This fallback holds automatic coordination, not the ability to perform an authorized standalone
assessment. Resume from the durable report afterward. Missing target policy, tools or profile capabilities hold
only the affected action; do not install extensions, change global configuration or reset accounting to proceed.
Native tools and fresh sessions are not sandbox isolation or managed Agent authority.
