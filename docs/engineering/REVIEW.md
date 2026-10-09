# Review Standards

Review is an independent attempt to disprove a TASK's acceptance claims. It reports findings and evidence; implementation, publication, and merge are separate.

## Target and independence

Read the TASK, its accepted decisions, the diff against the identified base, current status, and claimed tests. Disclose whether the reviewer authored, directed, repaired, or supplied evidence for the implementation. A material contributor cannot independently accept it. Include staged, unstaged, and untracked target changes in the reviewed scope; a clean committed target is preferable for a landing handoff.

A review of a rebased branch challenges the new base and effective diff, not just the old patch. A prior report may remain applicable through a **proven mechanical reconciliation** under [landing standards](LANDING.md); changed commit IDs alone neither prove a defect nor require another review. Substantive behavior changes or uncertain integration require a new independent review.

## Two named passes

Independently challenge the exact TASK, accepted decisions, exclusions, effective diff and claimed evidence in **Spec** and **Standards** passes. One independent reviewer may perform both passes; they are not two independent agents. Review owns this detailed assessment; work supplies TASK outcome evidence without duplicating the full matrix. Builder self-checks are never independent approval. Preserve the criterion IDs below across revision rounds; map each TASK acceptance criterion to at least one applicable ID and cite the specific requirement and observed evidence. Do not substitute a green build for semantic review.

| Spec ID | Question to disprove |
|---|---|
| SP-01 | Does the delivered use case satisfy each accepted outcome and stay inside its scope? |
| SP-02 | Are validation, rejection and authorization accounted for at all exposed entry paths, including target/ownership checks where applicable? |
| SP-03 | Do commands, queries, events, state changes and external side effects match the use-case contract? |
| SP-04 | Are failure, compatibility, recovery and security/secret-safety requirements met where applicable? |
| SP-05 | Do tests, direct checks and fresh verification actually establish every claimed acceptance outcome and limitation? |

| Standards ID | Question to disprove |
|---|---|
| ST-01 | Are Domain knowledge, package ownership, Application coordination, dependency direction and injection placed correctly? |
| ST-02 | Do HTTP/other adapters preserve transport scope, safe Views, mapping and server authority rather than recreate policy? |
| ST-03 | Are naming, PHP style, documentation and applicable local conventions followed? |
| ST-04 | Do tests prove meaningful owned behavior and important integration contracts at proportionate seams without testing tooling? |
| ST-05 | Are planning, delivery, verification, warnings, resource hygiene and review/merge boundaries accurate? |

Use the scoped [quality checks](QUALITY.md) to challenge tests under ST-04/SP-05, instruction and human-documentation
accuracy under ST-03, and code quality under the applicable ownership/behavior IDs. Test smells require contract
counterchecks, including legitimate interactions and replacement coverage. A stale descriptive claim and a code
violation of an accepted instruction require different corrections. These checks refine the existing IDs; they
do not add a score, independent verdict, edit authority or automatic cleanup pass.

Assess [QA applicability](QA.md#applicability) in the handoff after technical acceptance. Identify changed
observable contracts and useful adversarial scenarios for independent QA, or give criterion-specific reasons
that no behavioral exercise applies. No UI is not a reason to skip QA. Technical review and behavioral QA retain
separate verdicts; QA need not repeat the structural quality assessment.

For **each** ID record `Pass`, `Fail`, `Unverified`, or `N/A`, with an exact location, command/result or traceable evidence and any limitation. `N/A` needs a reason grounded in scope; silence or missing proof is not N/A. `Unverified` means necessary evidence is missing, not that a defect has been demonstrated. `Fail` needs a demonstrated violation. Account for all TASK criteria even when a checklist category contains several. A documentation-only change need not invent runtime paths or product tests; direct inspection, links, planning validation and the required gate can establish its claims.

For planning-path changes, independently audit **all** planning documents (including archived records, Wayfinder notes, templates and generated views): check Markdown links/anchors and code-span/plain-text local artifact paths and absolute checkout paths against their *intended* targets and any [scratch relocation manifest](SCRATCH.md). Classify historical transcripts, templates and external links; disclose missing local/remote evidence. Report incorrect or unclassified stale references as findings rather than inferring validity from `./bin/planning-check` or the builder's summary. Do not edit the planning records or the independent receipts during review.

Check server authority, secret handling, success/rejection/failure, persistence/transaction effects and protocol compatibility only where relevant, without inventing new policy or endpoints. Follow [engineering standards](STANDARDS.md) and the [ownership ADR](../../planning/adr/0001-application-ownership-and-orchestration.md); a passing dependency checker cannot prove package or business-policy ownership. Inspect actual diff and directly affected consumers, not unrelated debt. Challenge unsupported claims at the narrowest useful boundary; run the canonical gate when acceptance depends on it. Surface actual counts, warnings, skips and unverified areas. Do not fix implementation or change planning status while reviewing.

## Local gate and optional hosted checks

The mandatory verification gate is the target repository's canonical local command, normally `./bin/build`.
A successful local gate plus sufficient acceptance evidence can support independent acceptance without a hosted
run. Hosted CI is required only when an accepted repository policy or TASK explicitly designates it as an
acceptance gate. Cite that requirement before treating a missing hosted run as `Unverified` or blocking acceptance.
A workflow file, repository visibility, previous CI usage, or the ability to query GitHub does not create a gate.

When hosted CI is optional, report it as not run, unavailable, pending, or observed, as appropriate. Its absence
alone does not lower the review verdict or require publication, extra permission, or a replacement pipeline.
If an optional run reveals an actual implementation defect, assess that defect on its evidence; optional status
does not excuse broken behavior. Keep actual hosting/branch-protection requirements separate from technical
acceptance: local acceptance cannot waive a required merge check. Unknown hosting configuration is not proof
that merge is allowed, but does not invent a hosted acceptance requirement either.

An existing explicit hosted criterion remains binding until the user authorizes its amendment. Record that
policy decision in the owning planning records and reassess acceptance independently; never relabel an absent
run as passed or rewrite historical review evidence. Establish the scope of required proof under the
[acceptance-candidate and closeout contract](LANDING.md#acceptance-candidate-and-administrative-closeout).
A literal-final-head acceptance prerequisite cannot silently become a delivery-only check. The following
hosted-evidence path applies only where the requirement remains explicit.

## Findings and verdict

A blocking finding is a traceable acceptance, correctness, security, data-integrity, scope, or evidence defect. Give severity (`critical`, `high`, `medium`, `low`), affected criterion, reproducible evidence, expected versus observed behavior, and correction. Separate non-blocking improvements from unproved residual risks. `accept` requires **Pass on every applicable Spec and Standards criterion**, every TASK acceptance criterion accounted for, and no blockers; any Fail or Unverified requires `revise`. Do not average away a missing requirement or score a N/A as a pass. Findings must identify which pass/ID and TASK criterion they affect. An independent review does not approve a PR or authorize merge.

## Missing hosted evidence and focused continuation

This continuation establishes initial independent acceptance when required hosted proof was missing. It does
not apply to administrative closeout after acceptance; land owns that delivery verification below.

Distinguish an implementation correction from an external evidence action. When the only unresolved acceptance
items are required hosted checks, record `revise`, keep those items `Unverified`, and state **Awaiting hosted
verification; no implementation correction identified**. Identify the missing checks, the criteria they satisfy,
and the next action. Do not invent a hosted requirement when the TASK or repository does not require one.

Inspect the actual CI triggers. If a PR is needed to produce the evidence, route to the authorized draft-publication
path in [landing standards](LANDING.md#draft-publication-to-obtain-hosted-evidence), not back to `work` for an
empty repair pass. The canonical report must establish that every other applicable requirement passes; a pending
hosted check cannot conceal a code defect, missing local verification, or unrelated uncertainty. The reviewer
remains read-only and does not grant publication authority.

On continuation, an independent reviewer verifies the original report's identity and independence, the current
diff and intervening changes, and the new hosted evidence. Record the run URL, event, PR head/base, actual tested
commit, required job conclusions and warnings. A PR merge commit is distinct from its source head: establish
which reviewed head and base it integrates, and require additional evidence if the accepted contract requires a
literal source-head run. Never substitute a branch name, an old green run, or a skipped required job for proof.

For unchanged reviewed content, retain the previous findings and clearly identified inherited checks; inspect
the missing evidence without repeating the entire code review or local gate solely because CI is now available.
Apply the existing mechanical-reconciliation rules when relevant; substantive changes require review of the
affected implementation and interactions. Replace the complete canonical report atomically with the current
assessment. Only change to `accept` when all required evidence passes. A code failure routes to `work`; a queued,
missing, cancelled or infrastructure-failed run stays an evidence action, with its actual cause and next step.
Do not repeatedly poll an unchanged external state or create empty commits to retrigger it.

After accepted candidate A, route administrative closeout B to land's [final delivery verification](LANDING.md#verify-and-publish).
Land verifies the administrative diff and required run provenance, records delivery results in its ignored
receipt and PR body, and marks the PR ready when the publication requirements are satisfied. Pending delivery
neither rescinds A's acceptance nor requires a delivery-only `revise` report. No additional independent review
or canonical review update is required solely for administrative closeout, changed commit IDs or newly available
delivery CI results. Preserve the distinction between A's independent acceptance and B's delivery evidence.
Substantive changes, demonstrated defects or unproven integration still require independent review; defects
first route to `work` for repair. These rules do not waive initial acceptance or required final-head checks.

## Durable handoff

Publish the complete report at `<base-worktree>/.runs/reviews/<TASK-ID>/review.md`; chat is only a pointer. Resolve the base via Git's common directory and registered worktrees, and keep the ignored report directory within that base without following symlinks. Existing numbered `review-<NNNNN>.md` files are historical evidence: preserve them, but do not create new sequence entries. Replace only the canonical report, using a temporary file in the same directory and atomic rename. If writing or read-back fails, report an incomplete handoff and do not return an actionable chat-only verdict.

New reports use this small header:

```yaml
---
review_handoff_version: 3
task: TASK-NNNNN
target_branch: feature/task-00130-example
base_commit: <full Git OID>
head_commit: <full Git OID>
target_status: clean
verdict: accept
---
```

`target_status` is `clean` or the full `git status --porcelain=v1 --untracked-files=all` snapshot (use a YAML block scalar for multiple lines). For dirty reviews, identify each reviewed file version by path, state and digest or retained patch so a later session can detect drift; identify any selected ignored evidence even when Git status is clean. Include reviewer relationship, exact target/base, separate Spec and Standards tables with all stable IDs, TASK-criterion-to-evidence mapping, findings (or `None`), fresh commands/results, limitations, risks, and final verdict in the body. Include the next action and its owner, distinguishing implementation repair,
hosted evidence collection, independent evidence review, and ordinary landing. Read the canonical report back before handoff.

Older version-2 reports remain valid handoffs; read their canonical file and verify its declared branch, base/head, status, verdict, and any recorded snapshot against the target. Keep existing immutable history untouched. Only the canonical `review.md` supplies the latest verdict; never pick an older numbered file to override it.
