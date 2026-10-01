# Attribute-owned API contract

PHP `OpenApi\Attributes` are the single authored OpenAPI **3.0.3** source. TASK-00153 replaces the former
handwritten `docs/api/openapi.yaml`; no independently maintained YAML or tracked generated specification remains.
TASK-00021's YAML receipts are historical evidence, not the current input. The document describes **only
`GET /api/v1/auth/csrf` and the public-safe `GET /api/v1/validations/{form_name}`**. See
[validation metadata](VALIDATIONS.md) for its explicit private-artifact export, read and future form-registration
contract. Its `info.version: 0.1.0` labels the representative contract, not an authentication release.

## Export and validate

Install locked development tools explicitly and start Docker first:

```sh
./bin/composer install --no-interaction --prefer-dist --no-progress
./bin/up
./bin/openapi export
./bin/openapi check  # also the default when no argument is supplied
./bin/build
```

`export` generates and validates a complete JSON document, writes and checks identical temporary bytes, then
atomically replaces **`.runs/openapi/openapi.json`**. The directory is ignored and outside `public/`; the file
is mode 0600 and a newly created output directory is 0700. Source and artifact locations are fixed, symlinks
are rejected, and the command accepts no arbitrary scan/output path. Export neither installs dependencies nor
rewrites source, loads application configuration, connects to services, or enables an HTTP route.

`check` regenerates/validates in memory, requires byte-for-byte equality with the existing artifact, then runs
focused real HTTP and failure-mapper tests. Missing/stale output fails with instructions to export; check never
repairs it. Both modes print the content SHA-256 only on success. Export failure returns nonzero with a safe
stage diagnostic, cleans its temporary output, and does not replace the old artifact. **A retained file is not
proof of a successful current export.** Correct the reported stage and rerun export/check; never package a stale
file after a failed invocation. These local mode bits are not a sandbox claim: the qualified Docker Desktop
bind mount also allowed the container's `nobody` user to write. Protect the host checkout and Docker access;
future HTTP authorization is a separate boundary.

The JSON embeds `x-source-sha256`, computed from sorted relative source names and bytes, `composer.json`,
`composer.lock`, and the generation scripts. Object keys are sorted; list order is preserved. There are no
clocks, host paths or environment values in generated output. Preserve the successful command's content hash
and source hash with release evidence outside tracked source. A clean regeneration of identical inputs produces
identical bytes. Source changes during generation fail rather than certifying a mixed snapshot.

The owning implementation is `scripts/OpenApi/Document.php`, with CLI publication in `scripts/openapi.php`.
It uses attribute-only reflection without copying PHP docblocks into the public description, fails on generator
warnings, permits only document-local `$ref`s before resolution, and validates structure/references against the
installed 3.0 meta-schema. It uses the validator library API in memory: the locked vendor CLI's STDIN path cannot
resolve local references without an absolute base. Direct file validation remains available:

```sh
./bin/exec php -d allow_url_fopen=0 vendor/bin/php-openapi validate .runs/openapi/openapi.json
```

### Qualified dependencies

Composer pins the generator and validators; all transitive versions are locked. Qualification used PHP 8.5.10
in Docker with Composer's platform fixed to 8.5.4. The deliberate update adds three packages and upgrades none:

| Package | Version | License | Source commit |
|---|---|---|---|
| `zircote/swagger-php` | 6.11.0 | Apache-2.0 | `f998f7e712658fba61f86fccbdcf8e301a982175` |
| `radebatz/type-info-extras` | 1.0.9 | MIT | `2d9f01d60d642f890d9f170c0d8fde1f75ab17fb` |
| `symfony/type-info` | 8.1.8 | MIT | `18e1d891f0b8776141f281f7b6f5e67e4df9d9cd` |
| `devizzent/cebe-php-openapi` (unchanged) | 1.1.5 | MIT | `6e5fcc8810bfe8ad55d1b40764bff6417f485984` |
| `league/openapi-psr7-validator` (unchanged) | 0.24 | MIT | `10675b6eb7eb100ebe99378206272a963ed764e7` |

These are development tools, not request middleware. OpenAPI 3.1 is not adopted. No network, external `$ref`
fetch, CDN validator or remote example is needed after installation. No Fight package contracts are duplicated.

### Response checks and gate coordination

`tests/Support/ApiContract.php` generates a fresh in-memory projection once per PHP test process using the same
owning generator/validator, then the pinned PSR-7 validator compares actual responses with its operation/schemas.
It never trusts a pre-existing artifact or writes one. Existing coverage includes issuance, nonce reuse/replacement,
body/content-type/cookie/query rejection, HTTPS/origin/Fetch Metadata rejection, preflight denial, routing misses
and a failed dependency through the real bootstrap. Exact content type, no-store, safe correlation and absent
CORS are checked separately. Mapper-only output remains checked at its existing unit boundary.

`./bin/build` already runs these behavioral tests through PHPUnit; it does not export/package an artifact.
Standalone artifact freshness/validation remains an explicit `./bin/openapi check` requirement until
[TASK-00033](../../planning/tasks/00033-TASK.md) integrates the owning command into the complete gate. Do not add
another orchestrator or call export as an acceptance side effect. Direct tooling checks, not product tests of
attributes, generated JSON, wrappers or deliberately corrupted validator fixtures, qualify generation.

## Explicit input sources (TASK-00155)

On an Action's `handle`, use argument-free `#[JsonBody]` for POST/PUT/PATCH JSON objects or
`#[QueryString]` for GET query fields, followed by Fight Common `#[Validation(rules: [...])]`.
Each rule's `field` is the **snake_case wire key** and the sole field allowlist; no DTO constructor,
reflection hydration, fallback source, implicit default, type cast or form-schema publication is involved.
A source without Validation, duplicate/conflicting markers, unsupported method or invalid rule declaration
fails as a server error. A no-source Action accepts neither body nor Content-Type; ordinary no-source
Actions also reject query. CSRF bootstrap retains its separate query guard and cookie/origin policy.

After successful validation the Action reads `$request->getAttribute(JsonBody::class)` or
`$request->getAttribute(QueryString::class)` as a checked field map. This is an adapter request attribute,
not an authority grant; map to the actual package/use-case message explicitly, and never reread
`getParsedBody()`/`getQueryParams()` to dispatch. JSON requires Content-Type `application/json` (optional
UTF-8 charset), a nonempty unique-key object within 65,536 bytes and scalar/null fields. JSON retains
native types. The query string has a 4,096-byte maximum, at most 32 `&`-separated flat fields, zero
bracket/nesting depth, valid percent-encoding/UTF-8 and unique decoded lowercase snake_case names. When
available, the original server `REQUEST_URI` query is checked before PSR URI normalization can hide bad
percent triplets; later real-route owners must verify that server-provided target with HTTP requests. Values
are strings (including `"false"`); empty string is not missing, and query has no null representation.
An `&` separates fields; each field has zero or one `=`, and `+` decodes as a space. Unknown,
repeated, malformed, oversized or wrong-source input receives sanitized `400` JSend field errors
under `body`/`body.<declared_field>` or `query`/`query.<declared_field>`, never arbitrary submitted keys.
Only declared endpoint rules can allow pagination, ordering, filters or boolean literals; they never
confer permission to see deleted records or choose raw database expressions. Nested/list shapes and
implicit conversions are deferred until a concrete endpoint qualifies them.

The locked `johnnickell/fight-common` **v1.2.0** source at `a2cd615d9b5064c9c30e994655536176249cd73b`
provides `Validation`, `RulesParser` and `ValidationService`. Qualification through the installed service
confirmed `required` distinguishes missing from present null, other single-field rules skip absent fields,
`type[int]` accepts JSON integers but rejects query numeric strings, `type[bool]` accepts JSON booleans but
rejects `"false"`, and `in_list[true,false]` accepts the exact query literals without truthy casting.
`digits|min_number[1]|max_number[100]` validates bounded positive query-number strings; the Action must
still map them deliberately. `type[?string]` can allow JSON null; adding non-null rules can reject it.
Rules such as comparison and enum must be qualified with a real consumer's representations before use;
there is no global conversion or duplicate rule implementation. Direct middleware checks are foundation
seam evidence, **not** a delivered product route or HTTP proof of future list/authentication operations.

## Owning feature conventions

When implementing an API operation:

1. Put its operation attribute on the actual Action's `handle` under `src/Adapter/Http/Action/Api/`.
   Give it an explicit stable operation ID, actual path/method, input/security and response contracts.
2. Put endpoint response schemas with their Responder under `src/Adapter/Http/Responder/Api/`.
   Shared safe failures, headers and document/security metadata live under `src/Adapter/Http/Api/OpenApi/`.
   These three directories are the bounded scan scope, not vendor code or arbitrary filesystem input.
3. Author descriptions explicitly in attributes. Use exact JSend/snake_case, status/header and sensitive-input
   semantics; reuse shared local references. Never infer an endpoint from a response schema or publish a future
   route as available. Synthetic examples must contain no credentials, personal data or private configuration.
4. At each Action or Responder class/method site order applicable attribute groups **OpenAPI → input source
   → Validation → access requirements → other metadata**. Keep one attribute per block, related groups together,
   deliberate repeatable order intact and attributes on their supported targets. Responders normally have only
   OpenAPI schema/response metadata. This is presentation order, not middleware execution or permission order.
5. Preserve real response-to-contract checks and add the owning operation's behavior evidence. Run export,
   check and the canonical build; review the operation inventory and hashes before handing off.

Shared routing responses and mapper-only schemas are not operations. The public-safe validation read has a
separate security boundary from protected Swagger. It is the second actual operation in this contract; no
separate specification is introduced. The migration makes object types inferred by the
generator explicit in the output of existing `allOf` object constraints; their accepted real response shapes
are unchanged.

## Packaging and exposure

For a future package/deployment that consumes this artifact, the build stage must install the locked dev tools,
export, check and record source/content hashes from the exact source tree being packaged. Carry the private
artifact and receipt together outside public assets. Do not install generation tools or reflect attributes per
HTTP request. A runtime installed with `--no-dev` needs no generator to execute current Actions; PHP leaves their
OpenAPI attributes uninstantiated. An independently copied old artifact never substitutes for current build proof.

No Swagger UI assets, HTTP spec endpoint, static copy, alias or symlink are enabled in **any environment**,
including `local`. Unknown documentation/diagnostic requests retain safe routing failures; public PHP error
display stays disabled and CORS stays absent. Repository access and local filesystem access are the only current
document access boundaries. Do not submit tokens or cookies to an external viewer.

[TASK-00154](../../planning/tasks/00154-TASK.md) separately owns the themed viewer and guarded document reads:
exact `APP_ENV=local` **and** current authoritative `READ_SWAGGER` are required. Neither a role claim, APP_DEBUG,
public validation metadata nor this export command enables that future permission boundary.

## Current versus future security

Bootstrap accepts no body, Content-Type or query. It creates/reuses an HttpOnly nonce cookie and returns a
short-lived MAC proof, not authentication. Origin is optional but exact when present; Fetch Metadata is optional
but must be `same-origin` when present. These are current GET semantics, not mutation exemptions.

The unused `FutureAccessBearer` scheme describes **memory-only access-token transport**. Opaque refresh
credentials are browser-managed HttpOnly cookies; their eventual exact cookie name and operations are not
invented here. `x-future-browser-security` retains the accepted cookie/mutation policy from
[ADR 0003](../../planning/adr/0003-browser-authentication-security-profile.md). No cookie `apiKey` scheme invites
manual refresh credentials, and no credential examples are published.

The generic `ValidationFail` dictionary describes supported dotted snake_case paths, which OpenAPI 3.0 cannot
constrain by key name. Bootstrap narrows this to `body` or `cookie`. No body-bearing operation exists yet, so
HTTP dotted-field matrices and authenticated 401/409/422 journeys remain deferred to their owning TASKs.
Schema checks do not prove MAC correctness, server authorization or a complete authentication journey;
existing focused security tests cover CSRF policy.
