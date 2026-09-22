# Review Standards

Review is an independent attempt to falsify an implementation's acceptance claims. It reports evidence and a verdict; implementation and landing remain separate work.

## Independence and target

Disclose whether the reviewer authored, directed, repaired, or supplied acceptance evidence for the target. Any material contribution prevents an independent verdict. Identify the approved TASK, exact base and head, working-tree state, and claimed evidence before judging the change. Ambiguous scope or provenance limits the verdict explicitly.

Use repository files and fresh command output as primary evidence. A summary, prior green receipt, generated status, or inherited test proves only what can be traced to the reviewed target.

## Coverage

Attempt to disprove every applicable area:

- **Scope:** acceptance is complete; exclusions and unrelated files remain untouched; planning claims match observable state.
- **Architecture:** dependency direction, Domain/Application/Adapter ownership, injected capability boundaries, and stated transaction or delivery guarantees hold.
- **CQRS and HTTP:** commands, queries, events, Actions, Responders, safe Views, validation, mapping, and error behavior keep their accepted responsibilities.
- **Security:** authorization is server-enforced; credentials, secrets, sensitive data, and diagnostics are handled safely; negative and abuse paths fail closed.
- **Behavior and tests:** success, rejection, failure, regression, and side effects are proved at the narrowest useful boundary without tests that merely mirror implementation.
- **Evidence:** commands are fresh and reproducible; counts and warnings are accurate; failures, skips, deprecations, unavailable checks, and uncertainty are disclosed.

Mark a coverage area not applicable only with a reason tied to the TASK and diff.

## Findings

A blocking finding is an acceptance, correctness, security, authorization, data-integrity, scope, or evidence defect that prevents trust in the claimed result. Rank it `critical`, `high`, `medium`, or `low` by plausible impact and reach.

A non-blocking finding is traceable improvement work that does not invalidate acceptance. A subjective preference is not a finding unless an approved standard or requirement establishes the expected behavior. A residual risk records an evidence limit or untested uncertainty without asserting a defect.

Every actionable finding names the affected requirement, reproducing evidence, expected and observed behavior, and required correction. Findings without traceable evidence do not support a `revise` verdict.

## Verdict

Return `revise` when any blocking finding remains or evidence is too incomplete to establish acceptance. Return `accept` only when every acceptance criterion is supported, applicable coverage has been challenged, warnings and limitations are disclosed, and no blocking finding remains.

Neither verdict marks planning complete or grants PR approval, landing, push, merge, release, or deployment authority.
