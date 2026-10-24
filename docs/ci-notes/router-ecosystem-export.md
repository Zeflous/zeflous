# Router Ecosystem Export

**Scope:** the Router roadmap's closing slice — `ROADMAP.md` §4.3
("Documentation Auto-Generation": OpenAPI 3.1 auto-generation,
Swagger UI dev-only integration, Postman v2.1 export) — the lane
audit carried as "OpenAPI / Swagger UI / Postman export: no Router
integration evidenced" and the roadmap meter held open after the CLI
inspector (#563). Decision record for `src/Zef/Framework/Export/`
(the `OpenApiDocument` family — `OpenApiInfo`, `OpenApiServers`,
`OpenApiPaths`, `OpenApiOperation` with its `OpenApiDeprecation` /
`OpenApiOperationExtensions` / `OpenApiResponses` / `OpenApiParameters`
folds, and the enrichment lane `OpenApiOperationEnrichment` /
`OpenApiOperationAttributeReader` / `OpenApiOperationMetaReader` /
`OpenApiOperationProse` / `OpenApiOperationAttribute`; the
`PostmanCollection` family — `PostmanItem`; `ExportJson`;
`ExportException`), the development endpoint
(`src/Zef/Framework/Middleware/OpenApiUiMiddleware` +
`OpenApiUiMarkup`), the CLI family under `tests/Support/Tooling/`
(`RouteOpenApiExport`, `RoutePostmanExport`) with the `bin/zef`
`route:openapi` / `route:postman` dispatch, the engine's read-side
seam (`RouteConstraints::pattern()`, settled as
`RouteConstraintDeclarations` + `RouteConstraintCompilability`), and
the verification lane (`tests/Export/` — the contract suites, the
SHA-pinned official schema fixtures, the `ExportSchemaFixture`
harness over `opis/json-schema`).

## The posture

The lane's last step was never going to be an engine: the table
already answers everything the documents carry. The export family's
job was to **render the compiled table into the two interchange
documents the wider ecosystem reads** — the OpenAPI 3.1 document
and the Postman Collection v2.1.0 — reading the same seams the CLI
inspector landed (`RadixTree::routes()`), never a parallel parse,
never a re-derivation. What the table does not own, the caller
declares: the API's identity (title and version) and the scheme its
server URLs are spelled with — the two things the OpenAPI
specification demands that a routing table cannot know.

The zero-dependency posture is the slice's own headline (the user's
own mandate: *zero if possible, minimal if forced*): the runtime
`require` block stays exactly `{"php": "^8.4"}` — the
`ZeroDependencyGate` keeps asserting it — and the ONE new composer
entry is `opis/json-schema` in `require-dev`, the meta-schema
validator the test side judges generated documents with. The
hand-rolled alternative was rejected for the VERIFICATION side
specifically: a JSON-Schema-validating engine is precision
machinery, not a shim, and the house already carries the
verification-tooling class there (phpstan, infection, psalm) — while
the DOCUMENT GENERATION side hand-rolls (as the reference repository
itself does, `src/Domain/OpenApi/` without zircote: 8 runtime
dependencies this family refused).

## KDD-EX1 — the documents render the table's own truth, never a guess

Every field is a translation of a declared value: `operationId` is
the route's reverse-routing name (omitted for multi-method routes
and unnamed routes rather than an invented per-method spelling —
the specification demands document-wide uniqueness the table cannot
promise); `deprecated` + `x-zef-deprecation` carry the deprecation
declaration's own instants as ISO-8601 UTC; `parameters` carry the
declared constraint pattern (the new `RouteConstraints::pattern()`
read seam — verbatim, unanchored) wrapped in the `^(?:...)$`
anchors the engine's whole-segment semantics spells (the closest
ECMA-262 translation of `\z` anchoring; the pattern itself stays
PCRE as its author wrote it — a spelling ECMA rejects is a spelling
the table already owns); `responses` is the one response the
specification requires, its description telling the reader the
truth ("the table declares the method, not the response's schema")
with the producible types as honest empty media-type objects — the
schema field absent rather than invented.

The `x-zef-*` extensions carry what the table knows and the
specification has no field for — the handler, the host claim, the
API version, the route middleware — verbatim. Host grammar: ONLY
exact hosts become `servers` entries (under the caller-declared
scheme — the table is URL-free, so the scheme is a declaration, not
a derivation); parameterized and wildcard hosts stay in the
`x-zef-host` extension because the specification demands a server
variable default the engine does not own, and an invented default
tenant is exactly what this family refuses to ship.

## KDD-EX2 — the enrichment is first-party, fail-closed, and mutually exclusive

The documentation beyond the structural truth lives in the two
places a declaration can: the handler method's own
`OpenApiOperationAttribute` (`#[OpenApiOperationAttribute(summary:,
description:, tags:)]`, reflected at EXPORT TIME ONLY over the
`Class@method` form the engine's resolver grammar spells — the
runtime never sees it) or the route's `meta['openapi']` map (the
table-side spelling, the only one a service-id handler can carry).
Both present = two declarations for one operation = the ambiguity
refusal, with the route's identity in the message. A handler the
reflection cannot read carries no attribute enrichment, silently
and by design: whether a service id resolves is the dispatcher's
judgment, not a judgment the exporter re-makes.

## KDD-EX3 — the official schemas judge the generated artifacts

The verification lane's spine: the generated documents are validated
against the OFFICIAL interchange schemas — the OpenAPI Initiative's
own `schema/2022-10-07` artifact and Postman's own v2.1.0 draft-07
artifact, vendored as fixtures and pinned by SHA-256 (the test
asserts the digest: a swapped artifact fails the suite). Validation
runs on the JSON round-trip of the generated document (the artifact
the export actually ships) through `CompliantValidator` — the plain
`Validator` APPLIES the schema's `default` values into the data
while validating, and the OAS parameter object then fails its own
`unevaluatedProperties` on the values opis just injected (the probe
that found this is recorded below). The CLI's own subprocess output
is validated too: what `bin/zef route:openapi` ships is what the
ecosystem can import.

**The one mechanical rewrite:** the official OAS schema's four
`{"$dynamicRef": "#meta"}` Schema-Object references rely on the
2020-12 rule that a `$dynamicAnchor` also serves as a static
`$anchor` when no dynamic scope carries the name — and
opis/json-schema 2.6 implements the dynamic walk but not that
fallback, resolving the fragment to the document root (a
`required: ["openapi"]` error on a parameter schema object). The
harness rewrites the four nodes to the statically equivalent
`{"$ref": "#/$defs/schema"}` — the same target the specification's
own fallback selects, over the byte-identical artifact the pin
asserts.

## KDD-EX4 — the empty table is the honest empty, corrected by the meta-schema itself

The first design spelled an empty table as an omitted `paths` key
(the specification's `required: ["openapi", "info"]` alone). The
official schema's own `anyOf` judges otherwise: at least one of
`paths` / `components` / `webhooks` MUST be present — so the empty
table renders `"paths": {}` (a present, empty map — the same honest
empty the CLI inspector spells for a zero-route table). The PHP-side
`stdClass` detail is recorded here because it bit twice: an empty
PHP array encodes to `[]` (a JSON array), not `{}` — the empty
paths map and the empty media-type objects are `stdClass` instances
by construction. Prove empirically, don't assume: the meta-schema
found the design error the reading of the spec missed.

## KDD-EX5 — the Postman identity is deterministic over the table

The collection's `_postman_id` is the SHA-1 of the table's own
canonical encoding (the fields the engine declares, in declaration
order), rendered in the 8-4-4-12 spelling: the same table always
answers the same id (a re-export is a no-op in the collection
history), a changed table a visibly different one. The id
identifies the TABLE, not the export run — a rendered hash, not a
generated GUID.

## KDD-EX6 — the development endpoint is the double gate, and the served spec IS the CLI's document

`OpenApiUiMiddleware` (the `RouteDebugMiddleware` doctrine replayed)
is double-gated: the composition arm (the host's own composition
root constructs it with the identity and asset it chooses — a stack
that does not carry it serves nothing) and the environment arm
(`AppEnvironment::isDevelopment()`, re-judged per request: an unset
or unrecognized `APP_ENV` answers not-development, so no spelling
serves the endpoint in production). The document renders ONCE, at
construction (the table is frozen behind the router's memoized
tree), so the served spec and `bin/zef route:openapi` can never
drift — one rendering, two carriers, pinned by test
(`testTheServedSpecIsTheCliAnswersDocument`). The Swagger UI shell
loads from the CDN-pinned asset base (constructor-overridable for
self-hosting, `https`-only), the title HTML-escaped, the spec path
hard-coded — no JS ships in this repository.

## The coverage attribution (the round the CI had to teach twice)

The first CI round answered one finding with a bigger one hiding
inside it: psalm's six errors were the visible defect, but the
coverage numbers the SonarCloud gate judged (`new_coverage` 22.1% on
a locally-green `coverage:gate`) exposed the real mechanism —
**PHPUnit 13 with `requireCoverageMetadata="true"` filters a test's
coverage to the code units its `CoversClass` metadata names**
(`CodeCoverage::append()` runs `applyCoversAndUsesFilter()` over the
raw data): every fold the document folds executed *transitively* was
collected by pcov, returned by the driver (proven by patching the
driver to log `collect()`'s raw arrays), and then **discarded before
the report** because no test named it. The fold classes read 0% while
their callers read 96%+ — and `RouteConstraints`/`RouteMatchPipeline`
had never shown the hole because their tests always named them (the
`#[CoversClass]` lists `CompiledRouteTest` and `RadixTreeTest` carry
are the house's own precedent, spelled for exactly this reason).

The fix is the house's own: every fold the family owns is named in
the `CoversClass` list of a test that exercises it, and the same
`ExportJson` fold the document tests never encoded is named where the
middleware encodes it. The attribution bug also **masked 284
mutants**: the "77 mutants, MSI 100/100" the first runs reported was
the same filter in infection's own initial coverage — mutants on
0%-covered lines are `Not Covered` and silently excluded from MSI.
With the attribution honest, the scoped run is **361 mutants**, every
one killed, and the escape rounds below are the real ones.

## The mutation adversary's rounds

The scoped run (361 mutants after the attribution fix, MSI 100/100)
answered every escape in kind, two scoped ignores with the house's
proof-comment discipline:

- **The identifier's hash formatting** — the golden-id literal pin
  (a fixed table's exact id) plus the path-only distinction test
  killed the offset and splice mutants; the five `MBString`
  equivalents (mb_substr ≡ substr on the SHA-1 hex digest, ASCII by
  construction) took the house's scoped-ignore-with-proof
  (`infection.json5`, the `RedactionCandidate` measurement argument
  verbatim — the ignore is scoped to `PostmanCollection::identifier`
  alone; the neighbouring name guard stays under the mutator).
- **The name and asset ceilings** — the boundary pins (256/257
  characters, 2048/2049 characters) killed the ceiling shifts; the
  multibyte pins (a 130-character two-byte name, a
  1029-character two-byte asset) killed the `mb_strlen`→`strlen`
  mutants by pinning the CHARACTER semantics the guards spell (the
  messages say characters, the tests prove it) — and the same pins
  landed for the identity and prose grammars (a 256-character title,
  a 2048-character summary, 1200-character two-byte spellings) once
  the attribution fix exposed those guards to the mutator at all.
- **The HTML shell (47 concat mutants on one line)** — the golden
  document pin: the shell's exact output as a nowdoc literal,
  `assertSame` verbatim, kills every concat, operand-removal and
  splice mutant at once; the `ENT_QUOTES | ENT_SUBSTITUTE` bitwise
  operand is pinned by a title carrying double quotes (a flags
  operand that loses ENT_QUOTES leaves the quotes bare).
- **The guards that hid equivalences** — restructures, not ignores:
  the attribute reader's separator split exploded whole (a
  two-separator handler is not a `Class@method` pair; the count
  judgement refuses it) so the limit operand has no mutant; the
  reader's count guard split from the reflection guard so no
  `LogicalOr` join exists to flip; the enrichment's null-fold spelled
  as two `if`s (the `??` flip is value-equivalent — attribute XOR
  meta by the ambiguity refusal above); the exact-hosts walk's map
  of `true` became a plain list (`in_array`), where a `TrueValue`
  mutant cannot hide behind `isset`'s any-value semantics; the asset
  guard unified into one refusal so the empty-string `Throw_`
  removal is observable (the split version's second guard re-fired
  with the identical message — a mutant rescued by its own
  redundancy).
- **The walks and slices** — the exact-hosts walk's every skip
  (duplicate claim, host-agnostic route, wildcard) lands in the
  MIDDLE of one test, before another exact host the walk must still
  reach: a `continue`→`break` mutant loses the tail; the multi-
  parameter route pins BOTH parameter schemas so an `ArrayOneItem`
  slice cannot answer one where two were declared.
- **The honest-absence handlers** — a bare class name (no
  separator), a loadable class with a missing method, and a handler
  with two separators each carry no enrichment by their own guard:
  the mutants that would skip a guard crash on the undefined operand
  or the reflection that should never run.
- **The method fold's ignore** — `OpenApiPaths::grouped`'s
  `mb_strtolower` is the scheme/version folds' own argument: the
  PSR-7 method grammar is closed ASCII (the ServerRequest
  constructor refuses a non-ASCII token before any fold runs), so
  the two lowerings agree on every method the table can ever hand
  the fold — the scoped ignore carries that proof.

## The architecture gate's settle

Three files crossed the difficulty ceiling on the first
measurement (`RouteConstraints` 24.89 after the pattern seam, the
operation 20.90, the enrichment 32.35) — settled the house way,
folds over facades:

- `RouteConstraints` (→ 14.50) now fronts
  `RouteConstraintDeclarations` (12.96 — the validation loop's own
  fold) and `RouteConstraintCompilability` (4.20 — the probe);
- the operation (→ 20.00 exactly) fronts `OpenApiDeprecation`,
  `OpenApiOperationExtensions` (5.94) and `OpenApiResponses` (7.20);
- the enrichment (→ 10.36) fronts `OpenApiOperationAttributeReader`
  (19.50), `OpenApiOperationMetaReader`, and
  `OpenApiOperationProse` (18.00 — the shared grammar both readers
  judge through).

One operational note from the settle: the `+=` array-merge operator
RAISED Halstead difficulty (a new distinct operator) where the
foreach merge did not — the merge loops spell it.

## The deptrac widenings

`Export` is a new layer (`src/Zef/Framework/Export/`) depending on
`Exception` + `Config` — the same one-way, engine-blind posture the
Telemetry layer carries. `Middleware` widens to `Export` for the
development endpoint (the composition lane, exactly how it widened
to `Telemetry` for the correlation family); `Tooling` widens for
the two CLI renderers (read-side composition over the same boot,
the inspection lane's own doctrine).
