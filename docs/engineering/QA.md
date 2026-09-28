# QA and visual evidence

The planned QA Engineer runs after independent technical acceptance and before accepted landing. An authorized
[draft PR for hosted evidence](LANDING.md#draft-publication-to-obtain-hosted-evidence) may precede QA while
disclosing that QA remains pending. Until managed QA exists,
an explicitly assigned independent QA session can supply the same evidence. No change to application behavior
or tests is allowed during this verification phase.

For UI changes, exercise the actual application in a headless or visible browser against isolated test services.
Cover the affected interactions and meaningful loading, empty, error, permission and responsive states. Record
actions and assertions, not just pictures. TUI changes use an actual terminal/PTY capture where supported; if
image capture is unavailable, record that limit and attach a sanitized transcript or recording rather than
inventing screenshots. A mockup is design evidence, not a capture of an implemented result.

Capture Before from the recorded baseline and After from the reviewed implementation, using comparable data,
viewport/terminal size and scenario. Record both commit identities, scenario, environment, capture time and
artifact references/digests. Keep QA data and output separate from the reviewed source. A newly introduced
screen may show the actual previous state or an explicit "Not present before" explanation. Never fabricate a
Before image. Redact credentials/personal data and use locations accessible to the intended PR audience; local
filesystem image paths are not usable GitHub evidence. Publishing private artifacts requires suitable access.

Use the PR template's simple **Before | After** table, with a short scenario caption for each pair. Include
multiple tables only when distinct interactions need them. For non-visual work remove the section or state N/A.
Missing required captures or failed scenarios remain explicit blockers/limits; screenshots alone do not prove
behavior. QA dispositions are pass, fail, incomplete or justified not-applicable, tied to the exact subject.

Mechanical documentation/Board reconciliation can reuse captures when the effective UI/runtime and dependencies
are unchanged and the provenance bridge proves this. Material changes rerun affected scenarios. Fresh broad
verification is justified by interactions or uncertainty, not by a changed commit ID alone.
