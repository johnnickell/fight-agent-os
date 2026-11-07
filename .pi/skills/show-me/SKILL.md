---
name: show-me
description: Use only on explicit /skill:show-me to render a focused, source-linked Python Diagrams view of a named existing feature, source area or proposed change, with local image and browser preview.
disable-model-invocation: true
---

# Show me

Explain one bounded architecture question visually, then stop. Require a named feature/path and question, or an
exact proposed diff/design context. Ask for missing scope; do not scan the whole repository to choose it. This is
read-only explanation, not [architecture exploration](../architecture/SKILL.md), implementation or acceptance.
Do not change application source, durable docs, Git/index/branches/commits, PRs or external services. Write only
ignored `.runs/` preview/evidence files. Never upload source/images, use external rendering, install global
packages, change settings/trust or pin Pi. A separate authorized Work invocation owns durable changes.

## Trace the selected scope

1. Resolve the selected checkout and read its instructions. Follow the [native workflow rule](../../../harness/pi/WORKFLOW_RULE.md)
   for bounded discovery. Read the [Diagrams contract](../../../docs/engineering/DIAGRAMS.md) before authoring;
   it owns view selection, edge semantics, source traces, limitations and image inspection. For PHP also read
   [the architecture contract](../../../docs/engineering/STANDARDS.md#architecture); actual placement is not
   certification of the approved target. Do not load unrelated planning or generate a repository graph.
2. Record `git rev-parse HEAD` (full SHA), branch and `git status --short` before work. Inspect only the represented
   classes/files and necessary route/composition bindings. Check cited committed files with `git show SHA:path`.
   If relevant source is dirty, name those paths and label the view **working tree over SHA**, retaining a
   bounded scratch diff or digest; never label dirty content as that commit. Do not stage, restore or clean it.
3. Pick the smallest useful class/layer, dependency, runtime/data-flow, system-context or separate before/after
   view. Use actual class/file names and ownership groups, not guessed collaborators. Static references are not
   calls; compressed bus hops must be labeled indirect and traced through registration. Generic boxes do not
   prove UML inheritance, cardinality, members or complete class ownership. If labels/groups cannot communicate
   the requested ownership clearly, simplify/split; if still inadequate, report the representation gap and ask
   for a narrower view or explicit decision instead of presenting an unclear picture as verified.
4. For a proposal, name the supplied requirement/design/diff and its approval state. Mark the image **PROPOSED —
   not implemented**, label proposed nodes/edges in text (not color alone), and distinguish unchanged current
   context. Never invent approval or infer implementation from a plan. Keep current and proposed traces separate.

## Render in scratch with the existing owner

Use the [local renderer and native project contract](../../../docs/engineering/DIAGRAMS.md#render-locally).
Require Docker/Compose, the selected checkout's already prepared native configuration and its Diagrams image.
No application, DB, HTTPS or ingress startup is needed. If prerequisites are missing, stop with the exact
operator setup action (`./bin/diagrams setup` when authorized); do not implicitly provision, download or substitute
an old PNG. In a linked worktree, check the canonical base's `var/worktrees/<project>/override.yaml` and `output/`
exist before invoking Compose: the runtime wrapper otherwise prepares local configuration. Obtain the exact
project with read-only `./bin/runtime inspect`; resolve the canonical base from registered Git worktree metadata.
Reuse that project across roles, never guess a new project name or retire another consumer's resources.

Allocate a unique preview directory beneath canonical `.runs/tasks/<actual-TASK-ID>/runs/` when task-owned;
otherwise use canonical `.runs/cache/show-me/` with a unique request subdirectory. Confirm it is ignored using
`git -C "$base" check-ignore` on the selected scratch path. Resolve real paths: no symlink escaping `.runs/`,
no overwriting another request. Keep Python source, PNG, caption/source table, arrow traces and any local HTML
preview together there. Use `Path(__file__)` for adjacent output, explicit fonts/stable node IDs and `show=False`.
Review the trusted Python before execution; no source/credential reads, remote icons, network or app operations.
Python is executable and the renderer is not a sandbox.

`bin/diagrams render` deliberately accepts only `docs/*.py`; **do not use it for scratch or write into docs**.
Instead, from the selected checkout, use the SAME prepared Compose service with its mount replaced for this
one invocation. Set `preview_dir` to the absolute ignored directory just allocated and author `view.py` there:

```sh
export FIGHT_AGENT_OS_USER="$(id -u):$(id -g)"
./bin/diagrams versions
./bin/runtime docker compose --profile diagrams run --rm --no-deps --pull never -T \
  --volume "$preview_dir:/docs" diagrams /docs/view.py
```

Compose's run-volume at `/docs` replaces the service's documentation bind; only this scratch directory is
writable there. Keep the service's existing image, network-disabled mode, limits and invoking user. No new
renderer, wrapper, persistent override or source mount is needed. If the installed Compose cannot replace the
mount or the command fails, report the exact capability/error and stop rendering; do not broaden mounts or
silently fall back to durable paths. Record actual dependency versions and exact command/exit result.

## Inspect and show

- Include a feature/question title, existing/PROPOSED label, full source SHA (or adjacent full-SHA caption), visible
  edge legend, source paths/links with methods or line anchors, and important-arrow evidence. State inspected,
  inferred/UNVERIFIED and omitted portions. A source trace is not runtime, security or acceptance proof.
- Open EVERY generated PNG with Pi's native image-capable `read` using its absolute path. Inspect full labels,
  grouping, arrow direction, legend, clipping/overlap and legibility. Simplify and rerender unreadable output;
  do not claim success from exit status alone. Keep the image and a concise source-linked explanation together.
- Record observed `pi --version`, active terminal/mode and actual image result as evidence, not a compatibility
  gate. Native `read` returns an image to Pi; inline terminal display also depends on active UI settings and
  Kitty/iTerm2 support along the whole terminal path. Print/JSON/RPC output, a Markdown image link, environment
  detection or successful tool return alone does not prove a human saw an inline image. Do not force protocols,
  change global settings or claim unseen display success. Report unsupported, unobserved or unsuitable honestly.
- When inline is unavailable, unobserved, too small or otherwise unsuitable, provide a local browser fallback:
  write a self-contained `preview.html` in the same scratch directory with the PNG, revision/caption, source
  paths/links, trace and limits. No CDN, remote assets or scripts. Give the absolute `file://` URL (properly URL
  encoded) and use an available local browser to open it; inspect a browser screenshot with native `read`,
  verifying the image loaded and text is readable. Use existing browser tooling only. If browser capability is
  missing, state that prerequisite and give the local file URL without claiming inspected browser success.

## Finish

Compare source status/diff with the starting snapshot; preserve pre-existing changes and confirm only ignored
preview files were created by this invocation. Return the diagram, exact artifact paths/browser URL, revision,
source/arrow trace, selected view and omissions, render/display evidence and any uncertainty or prerequisite.
Keep files while the user or independent roles need them; do not tear down a shared native project or delete
another request. Apply [readable handoff rules](../../../docs/engineering/HANDOFFS.md#readable-handoff-text).
End with `Next:` naming the actual human inspection/decision or missing prerequisite. Do not dispatch another
skill, publish durable diagrams or treat this explanation as architecture approval.
