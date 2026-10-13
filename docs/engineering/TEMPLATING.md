# Shared templating

Consumers inject `Fight\Common\Application\Templating\TemplateEngine`, not Twig's `Environment`.
`config/common/templating.php` registers the shared loader and environment and binds that application contract
to the existing `Fight\Common\Adapter\Templating\TwigEngine`. `ClientShellResponder` uses the same service
available to other consumers; it owns HTTP status, headers and safe fallback, not engine construction.
No new application wrapper, email delivery or code-generation feature is introduced.

## Parameters and defaults

`config/services.php` loads path parameters, then `config/parameters/templating.php`, before common services.
The factories read these container parameters when first resolved; set overrides before resolving a consumer.
Services are shared within that container. Changing a parameter after resolution does not reconfigure an
existing loader/environment; start a new container/process to apply it.

| Parameter | Default | Meaning |
|---|---|---|
| `app.templates_dir` | Project-root `templates/` | Base directory used by the default path map, defined in `config/parameters/paths.php` |
| `templating.paths` | `__main__` → `[templates/]`, `client` → `[templates/client/]` | Namespace → ordered list of directories; the first matching file wins |
| `templating.twig_options` | Options below | Passed to Twig's `Environment` constructor without a second hard-coded option set |

| Twig option | Default | Effect |
|---|---|---|
| `debug` | `false` | Debug compilation is off; this does not install a debug extension |
| `charset` | `UTF-8` | Template/output character encoding |
| `cache` | `false` | Compiled template filesystem cache disabled; a trusted writable cache directory may be selected |
| `auto_reload` | `true` | Check template freshness even when a compiled cache is enabled |
| `strict_variables` | `true` | Missing variables fail rendering instead of silently producing empty output |
| `autoescape` | `name` | Twig selects escaping from the template filename |
| `optimizations` | `-1` | Enable Twig's standard optimizations |

The path map and complete options array live in `config/parameters/templating.php`; service factories contain
no shell-specific path or Twig-option defaults. Additional supported Twig constructor options can be configured
there when needed. These are trusted installation parameters, never HTTP input or public runtime JSON.
When changing `app.templates_dir` after default parameter loading, replace `templating.paths` as well: it is
an already-computed map, not a deferred reference to that parameter.

For example, before resolving `TemplateEngine` or a consumer, an installation can enable a private cache:

```php
$container['templating.twig_options'] = array_replace($container['templating.twig_options'], [
    'cache' => $container['app.project_dir'].'/var/cache/twig'
]);
```

The runtime user needs write access to that cache directory. Keep it outside public assets; cache files contain
compiled template code. The supplied local Compose environment makes `var/` writable. Deployment-specific cache
lifecycle, read-only release handling and worker restarts remain the installation's responsibility.

## Templates and safe output

- Root-relative names such as `client/shell.html.twig` use the main search path; `@client/shell.html.twig` uses
  the configured namespace. Future directories can be addressed beneath the main root without a new engine.
- The inherited `profile` sample remains available under that exact name as the file `templates/profile`,
  retaining its rendered output for the existing `value` input. There is no second ArrayLoader environment.
- `name` escaping preserves HTML escaping for `.html.twig` and extensionless templates, selects JavaScript/CSS
  escaping for the corresponding names, and leaves `.txt.twig` as plain text. Other suffixes default to HTML
  under Twig's strategy; do not assume an arbitrary code-generation suffix disables escaping. Choose explicit,
  trusted template-local escaping appropriate to the output format when a real consumer is added.
- Keep HTML escaping and UTF-8 enabled for the public shell, whose HTTP documents declare UTF-8.
  Its inert configuration uses HTML-significant JSON hex
  encoding before its narrowly scoped `raw` output. Only validated asset paths and the fixed public allowlist
  are supplied by the responder. Do not put principals, credentials, request state or environment dumps into
  shared Twig globals/helpers; shared rendering does not make that data safe for another consumer.
- Missing configured directories are omitted from the loader so an incomplete deployment fails at render time,
  not while constructing unrelated services. Restore a missing directory and restart any long-lived process to
  rebuild its loader. A misspelled configured path also fails when a consumer attempts to render from it.
- Fight Common's adapter reports missing templates and render failures through its templating exceptions. Each
  consumer owns its safe failure policy. `/app` retains the asset-free HTML 503, then plain-text 503 if the
  unavailable template also fails, without exposing exception messages, paths or partial output.

## Verification

Response tests exercise actual public HTML, escaping, headers and safe failures through the common adapter,
plus the renderer contract's safe failure outcome. Validate parameter overrides, configured loading and caching
with direct disposable probes and the owning Twig runtime, not product-suite tests of container wiring or
configuration text. Run `./bin/phpunit --filter ClientShell` during iteration and `./bin/build` for the complete
gate. Browser/font delivery is unchanged by sharing the engine, but the revised candidate still requires
independent review and affected behavioral QA before landing.
