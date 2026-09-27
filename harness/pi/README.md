# Fight terminal for Pi

Version 0.1.0 supplies the approved Fight Agent OS welcome mark and compact working header. Qualified with Pi
0.87.1. This is a presentation resource, not the managed Runner or an execution sandbox.

## Install

From the repository root, install for every Pi session:

```sh
pi install ./harness/pi
```

Pi stores its package declaration in personal settings. Restart Pi to load the package; an already running
session is unchanged. A local install resolves to this directory, so moving/removing the checkout requires
removing the old declaration and installing from the new location. Source updates take effect in later sessions.
No npm registry publication is required. The manifest is private to prevent accidental publication.

For repository-only use, use `pi install --local ./harness/pi` instead. To remove the personal installation:

```sh
pi remove /absolute/path/to/fight-agent-os/harness/pi
```

An install changes only Pi's package declaration. It does not select models, alter credentials, install skills,
authorize tools, or overwrite another theme. Pi's normal package/project trust rules still apply.

## Appearance

The default `auto` mode shows the refined F welcome and switches to a one-line identity at the first Agent run.
Resumed conversations start compact. Choose another mode for the current session with `/fight-header`, or use
`/fight-header port`, `wordmark`, `compact`, `ascii`, `auto`, or `off`. `off` restores Pi's own header.

Use `pi --fight-header compact` for a quiet startup, or set `FIGHT_PI_HEADER=compact` in the launching environment.
The CLI flag takes precedence over the environment. Preferences are not silently written to personal files.
Unknown options produce a warning; an invalid startup option falls back to `auto`.

Narrow terminals use a short ASCII identity. `TERM=dumb` or `NO_COLOR` also uses unstyled ASCII. Pi's built-in
light/dark themes use the approved orange accent; custom themes keep their own accent. Other colors follow the
active theme. JSON/print/RPC output receives no header. The editor, footer, status indicators and error messages
remain owned by Pi.

The mark is an approved terminal adaptation of this repository's `docs/assets/fight-agent-os-dark.svg`, with
symmetric light squares above/below the orange entry point. It uses terminal block characters, not an image
protocol. The separate prototype is not a runtime dependency.

## Verification and packaging

`./bin/harness-check` (from the repository root) runs owned presentation tests in the pinned Node container. The
full `./bin/build` includes this check. If the check image is missing, explicitly fetch it first:

```sh
docker pull node@sha256:dd9d21971ec4395903fa6143c2b9267d048ae01ca6d3ea96f16cb30df6187d94
```

For direct development with Node 22 or later, `npm test` in this directory runs the same tests. Pi supplies the
declared peer dependencies when loading extensions. There are no third-party runtime dependencies to install.
Use `npm pack --ignore-scripts --pack-destination <output-directory>` to create a distributable tarball; package
contents are limited to the manifest, source, README and license. Keep local build artifacts under `.runs/`.

Test coverage of the pure presentation policy is separate from Pi integration qualification. Real terminal/font
rendering still varies; a browser design mockup must not be presented as a native terminal screenshot.
