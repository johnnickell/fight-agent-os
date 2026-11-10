# Source-verified feature diagrams

Use a diagram when ownership, dependency direction or an important interaction is hard to follow in prose.
Choose the smallest view that answers the reader's question; omit diagrams that add no useful information.
This standard owns the source/image contract for maintained feature documentation, not PR automation or a Pi skill.
The [CSRF bootstrap example](browser-auth-requests/README.md) qualifies the class-ownership view.

## Select and label a view

| Question | Useful view | Show explicitly |
|---|---|---|
| Where does this responsibility live? | Class/layer ownership | Real classes, interfaces, namespaces and Domain/Application/Adapter groups |
| What depends on what? | Static dependency | Consumer → referenced type; distinguish interface implementation from use |
| What happens for this request? | Runtime interaction or data flow | Entry, ordered calls/messages, branching, failure/commit boundaries and the direction of data |
| Which systems participate? | System/infrastructure context | Owned versus external components and actual trust/network boundaries |
| What will change? | Separate before/after views | Existing revision versus **PROPOSED**, approved requirement and unimplemented portions |

Do not force every view onto one canvas. A runtime call to an injected implementation does not imply a static
outward dependency. A dependency edge does not establish execution order, successful dispatch or an effect.
For PHP, [STANDARDS.md](STANDARDS.md#architecture) owns the normative architecture; source is evidence of the
implementation, not permission to weaken that contract or move classes to make a picture conform.

For Agent OS, use Python [Diagrams](https://diagrams.mingrammer.com/) for authored views. Labeled `Node` boxes and `Cluster`
groups work for class ownership; infrastructure icons, C4 or programming nodes may suit other questions.
Do not imply UML cardinalities, inheritance or generated source completeness from generic boxes.
If the building blocks or layout cannot make the requested view readable, report the gap for a decision instead
of presenting an unclear image as qualified.

When shared skills operate on another project, follow its declared diagram format through
[project context](PROJECT_CONTEXT.md). For authored or embedded Mermaid, apply
[Mermaid validation](#mermaid-validation); the Python/PNG tooling below is Agent OS-specific.

Each view needs:

- A feature/question title and **existing implementation** or **PROPOSED** label, plus the full source commit SHA
  in the image or its immediately adjacent caption (a unique short label may point to that full SHA).
  For proposals, also name the approved planning record and distinguish existing versus proposed elements.
- Real class/file names and ownership groups. Explain abbreviations and package/external boundaries. Keep a
  nearby source table with local links and the exact revision, so `git show SHA:path` retrieves the cited source;
  immutable hosted links are another option. Line anchors or method names should identify important evidence.
- A visible legend. Default: solid directed edge = static reference, dashed open arrow = implements,
  dotted labeled edge = runtime interaction. Labels must state direction/operation; color alone is insufficient.
  Number runtime steps if ordering matters. Label compressed bus/pipeline hops as indirect, never as direct calls.
  Show return/data arrows separately if needed rather than silently reversing a dependency arrow.
- Trace entries for important arrows: endpoints, method/configuration and observed meaning. State what was
  inspected, what is inferred, and what is unknown. Omit uncertain arrows or mark them **UNVERIFIED** with the
  missing evidence; do not invent collaborators from names or portray approved future architecture as current.
- Explicit scope, omissions and limitations. A partial dependency view is not the whole class graph; a static
  source trace is not a runtime observation. No diagram proves correctness, security, acceptance or deployment,
  or replaces behavior tests and independent review.

## Mermaid validation

Apply this whenever the selected work authors, changes or republishes Mermaid in documentation or a PR body.
Keep the maintained editable diagram with its owning feature documentation; an inline PR copy is a publication
of that source. Preserve the represented revision, scope and source links when making a syntax-only correction.

1. Validate each complete Mermaid block from the actual document and prepared PR body with an available Mermaid
   renderer. Extract the block verbatim into ignored execution scratch; checking a shortened example or only the
   documentation copy does not validate an edited PR copy. Record the actual tool/version, command and result.
   Use target-owned tooling when provided; missing tooling is an explicit setup prerequisite, not permission to
   install or upgrade global tools or claim a pass. Inspect the rendered labels, arrows, branches and legibility.
2. Follow Mermaid's [sequence-diagram escaping rules](https://mermaid.js.org/syntax/sequenceDiagram.html#entity-codes-to-escape-characters).
   A literal semicolon separates statements even inside message text. Prefer a comma or encode an intended
   semicolon as `#59;`: `Common->>Storage: Identity lookup#59; compare or append`. HTML `&...;` escaping is not a
   substitute for Mermaid syntax validation. Rerender the complete affected blocks after correction.
3. During authorized publication, inspect the exact final PR body in GitHub's rendered view and follow the
   pinned documentation link to check its diagram too. GitHub can use a different Mermaid version from the local
   renderer; [check its supported syntax/version](https://docs.github.com/en/get-started/writing-on-github/working-with-advanced-formatting/creating-diagrams#checking-your-version-of-mermaid)
   when diagnosing a mismatch. API readback confirms saved text, not rendering. A local render or successful edit
   alone does not prove the published diagram works. Recheck after publication transformations or body edits.
4. Correct both the maintained source and affected PR copy within the authorized scope. A pinned historical file
   cannot be repaired by editing the PR body: commit the source correction and update its artifact link, retaining
   the implementation revision it describes. If a copy, renderer or hosted view cannot be corrected or checked,
   identify that exact remaining gap in the existing handoff; do not claim diagram verification is complete.

These are documentation checks through the owning renderer and viewer. They add no application-suite tests,
new renderer wrapper or publication authority; retain scratch only while needed under the existing resource rules.

## Keep source and image with the owner

Use the existing feature documentation as the entry point. For a standalone document such as
`BROWSER_AUTH_REQUESTS.md`, keep a sibling feature directory such as `browser-auth-requests/` containing
`README.md` (caption, links, traces and omissions), `csrf-ownership.py` and `csrf-ownership.png`. For a feature
that already owns a documentation directory, put these files there instead. Link/embed the image from the
owning documentation and link its Python source. Use one descriptive stem per view and commit source and PNG
together. PNG is the maintained display format; generated DOT, previews and execution logs are scratch, not
another authored authority. Do not copy the assets into a parallel architecture catalog.

Use `Path(__file__)` for a stable adjacent output path, `show=False` to avoid opening viewers, explicit fonts,
stable node IDs, and a readable legend. Do not read credentials, execute application operations or fetch remote
icons/source at render time. Render only trusted, reviewed Python: it is executable code. The renderer is not
an OS security boundary, and its documentation mount is writable.

## Render locally

Prerequisites: host POSIX shell/Python 3 for the repository runtime wrapper, Docker with Compose, and the
repository's normal [storage admission](RESOURCE_LIFECYCLE.md#configuration-and-readiness). No host Diagrams,
Graphviz, pip installation, application dependencies, database, HTTPS setup or running application is required.
From the selected checkout:

```sh
./bin/diagrams setup       # Explicit image/dependency setup; network downloads only here
./bin/diagrams versions    # Record actual Python, dot, font and Python-package versions
./bin/diagrams render docs/engineering/browser-auth-requests/csrf-ownership.py
```

`setup` builds only the opt-in `diagrams` Compose service, from
[`etc/docker/diagrams/Dockerfile`](../../etc/docker/diagrams/Dockerfile). It uses a digest-pinned Python 3.12
Bookworm image, Debian Graphviz and DejaVu fonts, and a hash-locked
[Python dependency set](../../etc/docker/diagrams/requirements.txt). Python's `graphviz` package is a binding,
not a substitute for the `dot` binary. Debian packages follow Bookworm security updates; setup is repeatable,
not a promise of bit-identical OS packages or layout across future rebuilds/platforms. Reinspect regenerated
images after dependency changes. TASK-00549 qualified Linux arm64: Python 3.12.16, Diagrams 0.25.1, Python
Graphviz 0.20.3, `dot` 2.43.0 (Debian package 2.42.2-7+deb12u1), fonts-dejavu-core 2.37-6.

`render` requires the prepared image, uses a one-shot `--rm --no-deps` container with networking disabled and
writes to the mounted checkout's `docs/` as the invoking user. It neither starts the app nor uploads source to
a hosted renderer. A missing image, dependency error or nonzero render leaves verification incomplete: run
explicit setup or report the exact prerequisite; never substitute an old PNG as a fresh render.
Docker availability and source permissions still apply. See [native resource lifecycle](RESOURCE_LIFECYCLE.md)
for project selection: inside a registered worktree the same native project is reused across roles.
Retire it only when no subsequent role needs it, using `./bin/down --worktree /absolute/registered/checkout`;
never main down or shared prune for task cleanup.

For a deliberate Python dependency refresh, edit `etc/docker/diagrams/requirements.in`, then use an explicitly
available `uv` (qualification used 0.11.19) to regenerate the committed lock; uv is not needed for normal setup:

```sh
uv pip compile --python-version 3.12 --generate-hashes --no-header \
  etc/docker/diagrams/requirements.in -o etc/docker/diagrams/requirements.txt
./bin/diagrams setup
```

Requalify rendering and inspect the image after changing the base digest, Debian requirements or Python lock.
Do not install host/global packages or silently upgrade tools while consuming existing evidence.

## Verify an update

1. Select the exact source revision and bounded feature. Read represented classes and composition/route bindings;
   compare them to the intended architecture. Record mismatches rather than editing application source to fit.
2. Update the Python view, source/arrow traces, revision and limitations together. Render with the commands above.
3. Open **every generated image** with an image-capable native reader. Check complete labels, legible text,
   group membership, edge direction/legend, clipping, overlap and useful size. Inspect the full image, not only
   successful renderer exit. Simplify or split an unreadable view and render/inspect again.
4. Follow local links and retrieve the cited source. Verify each displayed class and important arrow against
   that revision, including bus registration when a hop is compressed. If source changed, update the claimed
   revision or explicitly retain a historical view; do not relabel without reinspection.
5. Record exact render commands, dependency versions, source/image inspection and remaining limits in the
   owning TASK. A same-environment rerender should leave no unintended image diff; differing output requires
   inspection, not an automatic acceptance claim. Check diff hygiene and applicable planning records directly.
   Do not add product-suite tests for diagrams, renderer tooling or generated files.
