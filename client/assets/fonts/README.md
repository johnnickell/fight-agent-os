# Self-hosted typography

These are unmodified WOFF2 files from **Fontsource 5.3.0**, vendored deliberately rather than fetched at runtime
or during acceptance. Both pinned packages were downloaded from the npm registry and verified against their
published SHA-512 integrity. [checksums.json](checksums.json) records each selected file's SHA-256 and size;
the production artifact inspector verifies these bytes before publication.

| Family    | Package/archive                                                                                       | Archive SHA-256                                                    | Embedded font version | License                                            |
| --------- | ----------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ | --------------------- | -------------------------------------------------- |
| Ubuntu    | [@fontsource/ubuntu 5.3.0](https://registry.npmjs.org/@fontsource/ubuntu/-/ubuntu-5.3.0.tgz)          | `d69a09b1f2dcf4e470da62fd9c767175d957eb77d1e56767c94357abd3d48287` | `0.83`                | [Ubuntu Font Licence 1.0](ubuntu-LICENSE.txt)      |
| Open Sans | [@fontsource/open-sans 5.3.0](https://registry.npmjs.org/@fontsource/open-sans/-/open-sans-5.3.0.tgz) | `95e6dd90f5860d0de22f791b6d802598743762c62e4894bfa26f80b1ea0cf3f5` | `3.003`               | [SIL Open Font License 1.1](open-sans-LICENSE.txt) |

Upstream projects: [Ubuntu](https://design.ubuntu.com/font), [Open Sans](https://github.com/googlefonts/opensans).
Ubuntu copyright is retained from its embedded name table: Copyright 2011 Canonical Ltd. The package's license
text is unchanged beneath that added notice. Open Sans retains its package copyright/license verbatim:
Copyright 2020 The Open Sans Project Authors. These font licenses, not the application's MIT license, govern
the fonts. Do not sell the fonts by themselves, remove notices, imply endorsement or rename/modify the font
software without reviewing the respective license conditions.

## Selected faces and coverage

- **Ubuntu normal Medium 500**: headings (`h1`–`h6` and Bootstrap heading classes). No synthesized 600;
  heading `font-synthesis: none` prevents artificial bold/italic.
- **Open Sans normal 400, 600 and 700**: body, forms and controls. Regular and bold serve the current shell;
  600 also supports Bootstrap's semibold text utility without synthesis. Italics are not supplied.
- Each weight includes `latin`, `latin-ext`, `greek`, `greek-ext`, `cyrillic`, `cyrillic-ext`: **24 files,
  367,696 bytes total**. Unicode ranges are retained from the corresponding package's weight CSS in
  [the shared face definitions](../../styles/_fonts.scss). Only matching subsets/weights are requested.
  These are subset labels, not a claim of complete language coverage: for example Open Sans's Greek-extended
  file contains only six mapped codepoints. Font inspection and browser fallback remain necessary.
- Hebrew, Vietnamese, math/symbol packages, other weights and WOFF duplicates are not copied. Arabic, CJK,
  unsupported extended characters and missing faces use the system sans-serif cascade. System glyph availability
  varies by installation; this is not a complete international font bundle or a universal language guarantee.
- `font-display: swap` keeps text readable during loading/failure. No `local()` source bypasses the pinned face.
  System fallback is `system-ui, -apple-system, 'Segoe UI', sans-serif`. Font swaps can cause small layout shifts;
  local Chromium measurements belong in TASK-00158, not a latency guarantee.

## Reproduction and delivery

Download the exact archive URLs above into ignored scratch, verify the archive SHA-256, and compare
`package/files/<filename>` with every entry of `checksums.json`. The listed files are byte-for-byte copies;
there is no local subsetting/conversion step. FontTools can inspect each font's `name`, `OS/2.usWeightClass`
and `cmap` tables directly. Replacing bytes, subset ranges or licenses is an explicit source change, not setup.

The shared SCSS is compiled by ESBuild for production and Vite for Storybook. ESBuild emits content-hashed
WOFF2 beside the hashed CSS in `/build/`; its artifact policy admits only inventoried font inputs/bytes and
CSS references to that build's emitted fonts. The build copies both license/notices to hashed
`ubuntu-<hash>.LEGAL.txt` and `open-sans-<hash>.LEGAL.txt` in the same release. Keep these when distributing
`public/build/`. The static catalog finalizer also includes both notices at its output root. Source-tree
distributions retain this directory and its notices.

HTTP development and the HTTPS gateway serve only the qualified hashed font filename shapes as `font/woff2`,
with `nosniff` and `public, max-age=31536000, immutable`. Font notices are `text/plain` with the same cache policy.
The shell allows `font-src 'self'`; there is no Google stylesheet, CDN, preconnect, remote fetch or mutable
font resource. Missing build/template deployments retain an asset-free, system-font unavailable document.
Do not relax CSP or substitute remote fonts to repair an incomplete deployment; republish the complete build.
