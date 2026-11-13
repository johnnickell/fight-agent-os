# Author a comparison report

This is report guidance, not a comparison runner. Read the static HTML layout and author only the current
run's artifact. Preserve its offline, responsive and print protections when adapting it. Remove all sample
prompts before delivery; empty sections must say why evidence is unavailable or not applicable.

## Content and references

Lead with the decision and highest-impact actions, not a score. Include:

1. Executive summary: recommended direction, major caveats and unapproved status.
2. Requested versus inspected scope: active configuration/selection mode, project availability, configured
   and canonical paths where safe, sanitized expected/observed sources, full revisions, branches, dirty-tree
   qualifications, observation time and changes during inspection. Include denied/missing/mismatched entries.
3. Comparison matrix: equivalent responsibilities in all six dimensions, sampled boundaries, observations
   per project and gaps. Use words, not a visual score implying correctness.
4. Prioritized findings: stable run-local IDs, class, impact, practical consequence, evidence and strongest
   counterevidence, relevant local requirement, confidence/reason, recommended direction and tradeoffs.
5. Justified differences and unresolved questions: include owners and the precise missing decision/evidence.
6. Proposals grouped by project: unapproved outcome, rationale, dependency, observable acceptance and existing
   work links. Do not allocate planning IDs. Avoid duplicate proposals for work already owned elsewhere.
7. Evidence index and limits: exact project/path/line or symbol plus revision, bounded excerpts where useful,
   what was not read/run, and distinction between source traces and observed runtime results.
8. Continuation and delivery: concrete permitted next action, exact output paths and independent analysis,
   HTML, PDF and copy statuses. Generation is not inspection or independent acceptance.

Give references stable internal anchors such as `e1`, linking findings to the evidence index. For committed
public source with verified credential-free GitHub source consistency, optionally add an escaped HTTPS
`/blob/<full-OID>/<encoded-path>#L<line>` link. Keep project, full revision, path and lines visible as text so
references remain useful offline and in print. Dirty bytes need a working-copy label, observation time and
bounded excerpt, not a committed permalink purporting to show those bytes. Do not embed whole repositories
or private files. Remote links are optional navigation only; nothing loads until the user follows a link.

## Safe HTML and visual inspection

Keep the document script-free and self-contained. No remote fonts, CSS imports, images, iframes, forms,
embedded documents or active SVG. Preserve the restrictive Content Security Policy and referrer policy.
Escape **every** repository/configuration-derived string using a standard HTML escaper (for example Python
`html.escape(value, quote=True)`) before inserting it into text or quoted attributes. Never insert raw Markdown
or source as HTML, including code blocks. Attribute escaping alone does not make a URL safe: derive optional
source URLs only from verified source components, allow only credential-free HTTPS GitHub blob links, and
percent-encode path segments. Use generated internal IDs, not source text, for anchors. Show rejected links as
escaped text. No `javascript:`, `data:`, protocol-relative or source-supplied executable links.

Use semantic headings, a contents navigation, labeled focusable table scroll regions, table captions and
row/column headers. Keep body text at a comfortable size and line length; use high-contrast ink on paper,
restrained accents and textual status labels. Check the actual artifact at approximately 1440px and 390px,
including long project names/paths, wide matrix scrolling and visible keyboard focus. Inspect offline with
external requests blocked/observed; verify source-like `<script>`, attribute quotes and ampersands display
literally without nodes, execution or requests. Do not leak real secrets through security fixtures.

## PDF, copy and survival

PDF is opt-in through an explicit user request, not a selection flag. Discover already installed authorized
local export capabilities without installing anything. Use the actual HTML and print CSS; export alongside it
as `report.pdf` without overwriting. Inspect the actual PDF pages with available local PDF viewing/rendering
tools: pagination, repeated table headings, all columns, readable text, complete evidence references and final
continuation. Print CSS alone or successful export is not inspection. If unsupported, disclose the exact missing
capability, retain HTML and mark PDF unavailable; if exported but uninspected, say so. Keep source references as
visible text; never depend solely on clickable links. Split large tables rather than shrink them to illegibility.

Desktop delivery needs an explicit request for selected formats. Resolve the user's actual Desktop through an
authorized OS capability or an explicitly supplied destination; `~/Desktop` is not universally correct. If
resolution/access is unavailable or denied, ask for the destination or report the hold, without bypassing it.
Check the canonical destination and authorization, including symlinks. Do not create a missing Desktop or follow
an unexpected redirection without its owner's decision. Choose a unique name containing the run ID and use
atomic exclusive creation, not an existence check followed by ordinary copy (`open(destination, 'xb')` is one
local Python option). On collision choose another unique name; never overwrite. Copy bytes only, verify size
and SHA-256 against the source, then report the actual destination. A partial failed owned copy is not delivery;
remove only that newly created partial file when safe, leaving originals and pre-existing files intact.

Keep HTML success independent of PDF/copy failures. If a disposable implementation checkout contains requested
outputs, use the existing scratch relocation procedure before cleanup: review the exact source/destination,
check ownership/symlinks/collisions/consumers, copy exclusively to an authorized surviving handoff location,
verify digests, and record old/new paths and exclusions in the existing TASK handoff. No archive or registry.
Do not claim preservation merely because a path outside the checkout was proposed.
