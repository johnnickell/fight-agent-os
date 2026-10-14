# Public form validation metadata

`GET /api/v1/validations/{form_name}` is an anonymous, read-only API-v1 Action (`ReadValidationAction`). It requires no Bearer token or `VIEW_DASHBOARD`; TASK-00117 must put **this exact Action** on its explicit public allowlist. It grants no authority to submit the named form and makes no change to server validation, CSRF, credential-flow protections or CORS. There is no enumeration endpoint. Only real owning journeys may register forms; currently the complete catalog intentionally contains **zero** forms. Until registration, any canonical name returns 404.

## Build and deployment

With locked Composer packages installed and the web Compose service running:

```sh
./bin/validations export
./bin/validations check
./bin/openapi export
./bin/openapi check
```

`export` projects the explicit `PublicForms::registrations()` list against each real Action's `#[Validation(formName: ..., rules: ...)]` using the installed Fight Common `RulesParser`. It produces the complete, single private `.runs/validations/catalog.json` generation (not a static public file). `check` independently recomputes bytes and refuses absent/stale output. The CLI takes no source/output path and neither installs packages nor connects to a database. Store the artifact outside `public/` and package it **with the exact source tree and Composer lock** from a successful export/check; the ignored local `.runs/` artifact is not automatically shipped by Git. Record both the CLI's source SHA-256 and artifact SHA-256 with deployment evidence. Generate and check again after changes to registrations, Actions or locked dependencies. Failed projection/check is not a valid release receipt even if an old artifact survives. Export uses a private temporary file, flushes and atomically renames it over the previous generation; it never deletes the last complete file on failure. A directory/file symlink is rejected. Host checkout and Docker access remain trusted: the unkeyed revision detects accidental changes, **not** malicious replacement by a writer with access to the artifact and code.

HTTP reads one fixed private path per request; no PHP reflection, export or arbitrary path resolution occurs at request time. Absent, corrupt, oversized, unsupported, incomplete or name-set-mismatched artifacts fail closed with sanitized JSend 500. A structurally valid older artifact with the same name set cannot be identified as stale by the runtime reader: release packaging must enforce `check` against the exact source tree. Only a complete, explicitly empty catalog is valid. Deployments must copy a complete generated artifact before enabling reads; they must not treat a missing file as an empty success. A 200 response has `application/json`, `Cache-Control: no-store`, `X-Correlation-ID` and no cookie or CORS grant. A malformed bounded path yields 400, unknown canonical name 404, wrong method 405 (routing failure); encoded separators/traversal that do not match the route yield 404. Body, Content-Type and query input are rejected by the existing API validation boundary. All failures are sanitized JSend with no machine paths or rules.

## Wire contract and registration

The success envelope is `{"status":"success","data":{"schema_version":1,"revision":"<64 lowercase hex>","form_name":"...","fields":{...}}}`. `revision` is SHA-256 of canonical UTF-8 JSON of `{schema_version,forms}` for the **whole** complete catalog, without clock, host path or environment values. A deployment that changes any published form invalidates all previous metadata. Field keys are lowercase snake_case server wire names; each explicitly maps to a bounded camelCase `client_field`. Each field has an ordered nonempty `rules` list; each rule supplies `type`, string `args`, one public plain-text `message` and `depends_on` (client field names). Multiple rules yield multiple independent public messages; consumers must not assume just one error per field. `Same` carries its selected peer's server wire name in `args` and its client field name in `depends_on`.

Registration is a list rather than a name-keyed map so duplicate names can be rejected. Each entry names a real Action with a single `handle` Validation attribute, exact `formName`, selected source fields and public `client_field`. For each field, select zero-based parsed rule indexes and spell out each *exact* parser-produced safe message. Unselected fields/rules stay private. The projection rejects duplicates, unmapped comparison peers, unsupported selected rules, ambiguous fields or messages that do not match parsed output; it never publishes a named attribute just because it exists. Current qualified rule subset: `Required` tests presence only (present null is not missing); `Type[string]` tests PHP string type; `MinLength`/`MaxLength` use Fight Common UTF-8 `mb_strlen` on string-castable values with bounded decimal argument 0–9999; `Same` compares two present fields with PHP strict equality and skips when either is absent. Client consumers must apply the same source-specific wire types and Unicode-codepoint length semantics; this metadata is not a security validator. Other type/format rules (including email), regex, uniqueness, database, credential and password policy are **not** qualified for public projection. Unsupported selections fail export rather than claiming JavaScript parity. Future real journey owners must qualify and register their needed safe subset, prove nonempty PHP→export→read→client/server behavior, and keep operation authorization independent.

For a named registered Action, the input-validation middleware matches real Fight Common failures to the
explicitly selected, safe parsed rule messages for that Action and field. It emits those messages only when the
parsed error text identifies one rule unambiguously; unselected, ambiguous and unregistered failures remain
`Invalid value.`. The response may contain both published messages and that generic fallback on one field.
Structural input failures remain generic. Clients must still check exact registered field paths and published
messages against the loaded schema; a generic/private error is form-level feedback, not public rule metadata.
A future owning journey must verify its registered POST response against its actual published catalog.

The repository OpenAPI contract describes only the implemented GET and safe responses. No authentication form,
body-bearing endpoint or Swagger route is implied; the Formik integration is available for future owning journeys.
