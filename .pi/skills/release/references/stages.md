# Release stages and recovery

## Preparation and documentation

Derive readiness from canonical TASK/TICKET/EPIC records, accepted review evidence, source changes since the last
release, changelog and actual remote state. Enumerate unresolved requirements rather than equating a clean tree
with release readiness. Respect the target's planning vocabulary and version policy.

For an uncut release, prepare the authorized documentation and branch from the policy's freshly fetched base.
Reuse a matching existing release branch and PR. Verify source/base/remote ownership before updating it. Preserve
merge-commit requirements and independent review; do not commit directly to protected integration branches.
Find a matching PR again immediately before creation. A similar title alone does not identify the release.

Update the changelog's dated version section and compare links, README examples/requirements/version claims,
upgrade or migration notes, compatibility/support documents and release-related planning prose where affected.
Separate breaking changes, deprecations, fixes and follow-ups using the repository's format. Preserve Unreleased
content belonging to later work. Do not stamp the release version into unrelated examples or add a Composer
`version` field merely to publish a tag-derived package. Verify dates and claims; don't announce effects that
haven't happened. Search roadmap, EPICs and TICKETs for stale “remaining,” “follow-up” and “not yet released” prose.
Keep historical checkpoints explicit instead of erasing their history.

Run the target's generated-view/docs checks and complete gate for actual changes. Prepare commit/PR content before
requesting any missing publication approval. A later docs repair still follows the target's delivery rules.
After a release is tagged/published, repair docs on an appropriate new branch; never rewrite the published tag.

## Integration and certification

Use the existing release PR and independent acceptance. Honor local gate/hosted-check policy and actual branch
protection. A required hosted check that is unavailable is unresolved, not green. Merge only within authorization.
After merging, fetch and verify the actual merge commit against the PR and release line. Do not guess its hash
from a branch name or claim the local `main` tip is the current remote tip.

Certify in the required clean checkout/worktree using the repository-owned command. Record start/end timestamps,
exact commit, command, direct exit, logs and required artifact identities. Preserve failed evidence and warnings.
Read the owning schema and validate the resulting receipt and required files; an existing directory is not success.
For an expensive prior receipt, reuse only when the target explicitly allows it and its exact identity/integrity
and required freshness are established. Never label inherited proof as a fresh run.

Before generating a signing handoff, require the receipt to name the final commit that the tag will identify.
Candidate certification before a merge does not certify the distinct merge commit, even with an identical tree.
If the approved release commit or relevant evidence changes, reconcile and recertify under target policy before
signing. Do not run an alternative smaller “certifier” or upgrade dependencies to get past a failed gate.

## Signing and remote tag

Follow [the signing handoff](signing.md). A signed local tag, a remote tag object, and a GitHub release are three
different states. Existing matching effects should be verified and reused. Conflicting object IDs, lightweight
tags where signed annotated tags are required, unknown signers or wrong peeled commits stop the mutation.
If another release has advanced the integration branch, reconcile the historical release commit and approvals;
do not retarget an existing tag to today's tip.

## GitHub release

Determine the expected GitHub repository from the verified remote. Read the tag's existing release, draft/published
state, notes, asset inventory, prerelease/latest choices and required immutability policy. Distinguish a confirmed
not-found from authentication, rate-limit and transport errors. A draft is not publication, and a tag alone is not
a GitHub release. Inspect provider state after uncertain writes before retrying them.

Prepare notes from the reviewed changelog and verified public facts. Use a notes file for multiline CLI input.
Determine assets from the current project contract: zero may be valid. Validate every required asset's content/hash
and public suitability; never upload raw private run logs as an assumed evidence bundle. Do not overwrite assets
or change repository release settings without authority.

For an authorized new release, use the installed CLI's supported draft creation with explicit repository/tag and
`--verify-tag` so it cannot auto-create a tag. Inspect the completed draft, notes and assets before the separately
authorized publication. Set prerelease/latest deliberately according to release-line policy, especially maintenance
releases. Reuse and reconcile an existing draft; a published release is not a draft to recreate.

After publication, independently verify the release URL/ID, tag, published state, observed assets/digests, required
immutability and available attestations through provider tooling. A requested setting is not proof of the result.
Also verify the remote annotated tag object and peeled commit against the approved local identities. If publication
succeeded but verification failed, report “published, verification incomplete” and reconcile; don't publish again.

The CLI documents [automatic tag creation and `--verify-tag`](https://cli.github.com/manual/gh_release_create).
GitHub's [immutable-release contract](https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases)
explains why any intended assets must be settled before publication. These mechanics do not grant publication authority.

## Packagist and consumer evidence

Derive the package name from the certified `composer.json`; confirm registry/package ownership and repository URL.
Use read-only registry metadata to check the exact normalized version and source reference against the published
commit; check the dist reference when present. Record URLs, observation time and identities. Do not count a version
string or successful HTTP response alone as a matching projection.

Prefer the Composer v2 `https://repo.packagist.org/p2/<vendor>/<package>.json` endpoint. If its response declares
minified metadata, expand it using the supported metadata-minifier/Composer tooling before inspecting inherited
fields; never compare raw abbreviated entries as complete package records. The general package JSON API may lag
due to caching. Follow the [Packagist API documentation](https://packagist.org/apidoc).

Check once, then use a bounded retry window (normally at most three observations over two minutes, with waits of
no more than 60 seconds). If still missing, mismatched or inaccessible, preserve the observed result and hand off
the next check. Do not silently trigger a Packagist update/webhook, change credentials, or create a background monitor.
An update request is a separate external effect when authorized.

Registry projection is not installed-consumer proof. When the request or target requires the latter, run its owning
consumer qualification in an approved isolated environment against the registry version; prove the resolved version,
reference and public behavior. A local path repository or previously certified local archive does not establish
installation of the published registry package. Record unavailable consumer qualification separately.

## Merge-back, cleanup and durable handoff

For a current-line GitFlow release, inspect whether the actual released `main` commit has already reached
`develop`. Prepare the remaining merge-back PR through the target's review/gate/merge rules when authorized.
Preserve newer development changes and later Unreleased notes. Maintenance lines use their documented forward-port
path; never merge an old line wholesale over a newer one.

Audit chronology again against actual release and merge facts. Deliver necessary tracked corrections through the
normal branch process; record resulting commit/PR identities in ignored receipts to avoid commits that must name
their own hash. No recursive bookkeeping commits solely to restate the next head.

Before authorized cleanup, prove ownership, merged reachability, clean worktree state and absence of unique work.
Preserve certification/signing/publication receipts outside a worktree that will be removed. Delete only the named
completed branch/worktree; do not force-remove changes, reset the human checkout or archive planning automatically.
If cleanup is blocked, report it as a remaining phase rather than overstating release completion.
