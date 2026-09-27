# Scoped graph maintenance

Before a write, record the source roots, exclusions, output paths, available tool version/backend and intended
structural versus semantic work. Prefer the narrowest scope that answers the task. Root and nested graphs are
independent; refreshing one does not refresh all. Keep secrets, credentials, vendor/generated content, unrelated
private sources and graph outputs out of the extraction corpus. Preserve established per-scope exclusions.

Inspect this CLI's supported extraction commands and backend configuration without printing secrets. Structural
code updates and semantic document extraction are different operations. Where supported, `graphify update ROOT`
performs a structural refresh; it must not be reported as refreshed Markdown/PDF/image semantics. Some extraction
or clustering operations can call a model for semantics or labels: select an authorized backend and bounded input
before execution, and disable model labeling where appropriate using only flags supported by the installed CLI.

For semantic work, use a supported configured backend or separately qualified Pi-compatible adapter. Bound files,
concurrency, cost/time allowance and output paths; preserve source provenance and report failed/skipped chunks.
Do not assume a generic `Agent` tool or silently fall back to a provider/model. Missing capability means incomplete
semantic coverage, not a reason to invent a command. No automatic broad backlog, cloud upload or paid extraction.

Validate resulting JSON, source coverage and edge endpoints using the version's owning tools. Inspect changes,
unexpected size reductions and provenance before replacement. Record command, exit, version, source identity,
selected files, completed/failed scopes and actual usage or unavailable usage. A reported zero cost is not proof
of zero account allowance consumed. Preserve recoverable partial results and explain safe continuation.

Installation and hook/watch setup are separate requested operations. Inventory existing hooks and merge drivers,
preserve their ownership, and inspect the exact changes first. Explain whether automatic updates cover structural
code only. Never replace project hooks or broaden scope as a consequence of installing this local skill.

For this repository, absent an established graph-output policy, use an explicitly selected disposable source
scope under `.runs/graphify/` for qualification. Do not redirect a real project scan to an output location using
an unverified flag or claim that a copied sample represents the entire project. Establish full-project output
and ignore policy in its authorized maintenance operation.
