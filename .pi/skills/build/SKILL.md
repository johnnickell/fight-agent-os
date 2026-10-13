---
name: build
description: Coordinate one approved TASK through Engineer work, fresh independent review, QA and automatic land to an open PR with applicable screenshots; accept an unambiguous TICKET input.
disable-model-invocation: true
---

# Build

Act as Team Lead for one TASK under [Team roles](../../../docs/engineering/TEAM.md). Delegate implementation,
independent technical review, behavioral QA and publication to their respective roles. Coordinate and inspect
evidence; do not implement or issue an independent verdict yourself. Fresh Pi processes separate conversation
context, not OS permissions or credentials. Managed PHP authority, durable execution and browser attachment
remain separately planned. No subagent extension or global installation is needed with the qualified Pi 1.2.0 CLI.

Use `/skill:build TASK-NNNNN` or `/skill:build TICKET-NNNNN`. Optional instructions may specify checkout, models
and a positive revision allowance up to ten. The default is **five shared revision cycles** across technical
review and QA. Invocation explicitly delegates work, review, QA and `land`, including owned commits, non-force
push, PR creation/update and suitable capture uploads. Continue these stages without another approval prompt.
Ask only for missing decisions or authority beyond that scope. Never merge, approve the PR, force-push, release,
deploy or continue to another TASK. A question about planning does not invoke this workflow.

## Resolve and record

1. Read the supplied record, parent, [Board](../../../planning/tasks/BOARD.md) and
   [planning conventions](../../../planning/CONVENTIONS.md). A TICKET input selects its sole eligible TASK only
   when no other child is active or ambiguously owned. Inspect live/archived children and state the selected ID.
   With zero or multiple candidates, ask for the missing prerequisite/choice; do not decompose, choose by lowest
   number or build the entire TICKET. Check approved scope, readiness and integration of blockers on the base.
2. Follow [execution standards](../../../docs/engineering/EXECUTION.md). Reuse the operator's selected checkout;
   otherwise ask main checkout versus isolated worktree before implementation. Verify repository/remote, branch,
   ancestry, ownership and existing work. Never switch, stage or publish unrelated changes. A shared branch
   containing unrelated unaccepted commits needs branch/ownership resolution before dispatch, not a broad push.
   Search existing PRs by branch and then uniquely evidenced TASK ownership; ambiguity holds.
3. Resolve the canonical base worktree through Git common-dir/worktree metadata. Use `umask 077` and an ignored
   run beneath `<base>/.runs/build/<TASK-ID>/`. Acquire an exclusive `mkdir` lock at that directory's `active`
   child and record coordinator session, checkout, branch and run path. An existing lock requires inspecting
   its owner/processes before continuation; never automatically delete it. This is advisory local coordination,
   not a managed claim. Release only your lock after proving children stopped; uncertain liveness retains it.
4. Keep a private ledger containing original authorization, TASK/base/head, model, effective/consumed/remaining
   cycles, stage session IDs, canonical reports, decisions, PR identity and incomplete effects. Check installed
   `pi --version` and `pi --help` for the options below. Missing Pi, authentication, project trust or required tools
   is a prerequisite hold. Use existing provider authentication without printing credentials; do not change
   global settings, install extensions or auto-trust unfamiliar project resources.

Before first dispatch, inspect existing canonical Review/QA and any earlier build ledger. Resume from the phase
warranted by current evidence; do not rebuild accepted implementation. Continue the original consumed allowance
on resumed or repeated invocation for the same delivery, including after QA failure. A fresh process, directory
or command does not reset the counter. An exhausted allowance needs an explicit human continuation grant.

## Dispatch fresh roles

Launch one child at a time from the TASK checkout using the shell tool. Allocate a fresh attempt with `mktemp -d`
under the private run directory. Never pass `--continue`, `--resume`, `--session`, `--fork`, the parent transcript
or its session file. Each new empty session directory supplies fresh context; Review and QA must have no prior
implementation contributions. Repairs receive the current findings and dispositions explicitly.

Write `request.md` in the attempt directory before launching. Include role, TASK, exact checkout/base/head,
absolute owning skill path, permitted effects, relevant evidence paths, original authorization, remaining repair
allowance and expected handoff. Tell the child to read that skill and references, execute only its stage, publish
the required report, then stop without delegating other roles or invoking `build`. Do not copy Engineer dialogue
into reviewer prompts or suggest a verdict. Explicitly carry delegated land and capture-publication authority
into the Release Manager prompt.

Use this supported dispatch shape. Populate quoted variables from the ledger; `attempt` is the fresh directory,
`task_checkout` the selected checkout, and `task_id`/`role` the actual stage identity. Pi's shell tool supplies
`PI_PROVIDER`, `PI_MODEL` and `PI_REASONING_LEVEL`; require all three or resolve the configured selection first.
Honor explicit role-specific model choices instead of inheritance; never silently switch providers.

```bash
(
  umask 077
  cd "$task_checkout" || exit 1
  if pi --print --session-dir "$attempt/sessions" --name "$task_id $role" \
    --provider "$PI_PROVIDER" --model "$PI_MODEL" --thinking "$PI_REASONING_LEVEL" \
    -- "@$attempt/request.md" > "$attempt/final.txt" 2> "$attempt/stderr.log"; then
    stage_exit=0
  else
    stage_exit=$?
  fi
  printf '%s\n' "$stage_exit" > "$attempt/exit-code"
  exit "$stage_exit"
)
```

Use a bounded shell-tool timeout suitable for the stage. Observe the child; do not detach it into an unobserved
background process. If the host yields a running process, wait and give useful progress updates without launching
the next stage. Inspect exit code, stderr, persisted session and required artifacts. Exit zero or a reassuring
final response is not acceptance. Missing exit, timeout, cancellation, provider error, truncated output or
uncertain liveness is incomplete. Reconcile/stop the original child before another attempt. Cancellation does
not guarantee cancellation of remote effects. On restart, read the ledger and original Git/report/PR state;
this skill does not guarantee unattended survival after terminal/host exit.

## Advance by evidence

| Stage | Owning skill and required result | Next action |
|---|---|---|
| Engineer | [work](../work/SKILL.md): owned committed implementation, full gate receipt, scope, head and limitations | Confirm identity; dispatch fresh Review |
| Senior Engineer | [review](../review/SKILL.md): canonical independent report with separate Standards and Spec passes | `accept` to QA; confirmed blockers to repair; missing evidence to its owner |
| QA Engineer | [qa](../qa/SKILL.md): current canonical PASS or justified criterion-specific N/A, scenarios and inspected applicable captures | Satisfied QA to land; FAIL defects to Engineer; INCOMPLETE to prerequisite recovery |
| Release Manager | [land](../land/SKILL.md): eligibility, final checks, non-force publication, verified PR and capture access | Inspect original remote result; hand the open PR to the human |

Read canonical `<base>/.runs/reviews/<TASK-ID>/review.md` and `<base>/.runs/qa/<TASK-ID>/qa.md`, including subject,
independence, current verdict/disposition, evidence and limitations. Never prefer an older PASS over a newer
failure. Retain per-attempt copies/digests. Compare actual Git/source/runtime identity before advancement;
material repairs, changed requirements or stale subjects require fresh applicable evidence. Use the existing
[landing provenance bridge](../../../docs/engineering/LANDING.md) for proven mechanical changes. Only explicitly
required hosted proof can use land's authorized draft path; a draft awaiting proof is not final delivery.

One repair cycle is one Engineer attempt addressing the current confirmed blocking set, then fresh Review and
affected QA. Record consumption before repair dispatch; initial implementation is not a repair. Technical and
QA findings share the counter: **QA FAIL never resets it**. Non-blocking preferences and missing evidence do not
authorize speculative edits. After the effective allowance is consumed (five by default), allow that cycle's
Review/QA and publication if it passes; remaining blockers require a human decision. Do not increase limits
automatically. Evidence recovery consumes
no repair cycle, but repeated unchanged failures are a prerequisite hold, not unlimited retries. Explicit larger
allowances must be within the ceiling and recorded before use.

For UI/TUI changes require comparable inspected Before/After captures through real interaction under
[QA standards](../../../docs/engineering/QA.md). Release Manager uploads sanitized captures using
[landing standards](../../../docs/engineering/LANDING.md), verifies references/audience access and reconciles
partial uploads or uncertain PR creation before retrying. Local paths alone are not PR evidence. If terminal
image capture is unavailable, retain real transcripts and report the image limitation; do not fabricate images
or silently waive requested captures. Non-visual work uses appropriate behavioral evidence, not decorative
screenshots. Missing required/requested proof holds delivery rather than weakening QA.

## Hand off

Success requires a verified open, ready PR against the correct base, exact published remote head, current
review/QA provenance, required checks and accessible applicable captures. Return its clickable URL, concise
verification/limitations and revision usage. End with `Next: Review and merge <actual PR URL> on GitHub` under
[handoff rules](../../../docs/engineering/HANDOFFS.md), then stop after this TASK.

If incomplete, retain useful resources and record phase, subject, remaining cycles and exact recovery action.
Report the actual prerequisite or human decision. A child's suggested next skill is not a reason to stop when
its evidence is complete and `build` already delegated that continuation.
