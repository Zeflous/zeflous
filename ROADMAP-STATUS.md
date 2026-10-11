# ROADMAP Status

A living "roadmap meter": for every area described in [`ROADMAP.md`](ROADMAP.md),
this file records what is actually implemented in the repository today, with a
one-line evidence pointer (file / class / workflow). It is the single place to
see how far the code has moved against the roadmap.

**Legend**

| Status | Meaning |
|--------|---------|
| ✅ Implemented | The area is present and exercised by the test suite / CI. |
| 🟡 Partial | A working slice exists; the rest of the area is still open. |
| ⬜ Not started | No code yet. |

**Last updated:** 2026-10-11 · `main` @ `6ab59dc`

---

## Container (`ROADMAP.md` → "CONTAINER")

| # | Area | Status | Evidence |
|---|------|--------|----------|
| 1 | Registration & service definition | 🟡 Partial | `src/Zef/Framework/Container/Container.php` (`set()`), `ServiceStore.php` — closure/factory registration only; array definitions, aliases, scalar params, env values, parameter providers not yet present. |
| 2 | Autowiring | ⬜ Not started | No reflection-based resolution yet. |
| 3 | Autoconfiguration (attributes) | ⬜ Not started | — |
| 4 | Injector (property/method injection) | ⬜ Not started | — |
| 5 | Scope & lifecycle | 🟡 Partial | `ServiceStore::resolve()` memoises instances (singleton/shared); factory/prototype, per-request scope, lazy services not yet present. |
| 6 | Tags & grouping | ⬜ Not started | — |
| 7 | Decorator & proxy | ⬜ Not started | — |
| 8 | Compiler passes & compilation | ⬜ Not started | — |
| 9 | Architectural extensibility | ⬜ Not started | — |
| 10 | Standardisation & integration | 🟡 Partial | PSR-11 surface via `src/Contracts/Psr/Container/ContainerInterface.php`; `Container implements ContainerInterface`. |
| 11 | Performance | ⬜ Not started | — |
| 12 | Debugging & developer experience | ⬜ Not started | — |

## Router (Radix Tree)

| Area | Status | Evidence |
|------|--------|----------|
| Core engine, advanced routing, performance, DX, ecosystem | ⬜ Not started | No router code yet. |

## Configuration System + DSL + Radix Tree

| Area | Status | Evidence |
|------|--------|----------|
| Configuration layer (runtime repository) | ✅ Done | `src/Zef/Framework/Config/Config.php` + `ConfigMutations` — immutable dot-notation repository (`get`/`has`/`split`/`toArray` plus copy-on-write `withSet`/`withUnset`/`withAppend`/`withPrepend` via `DotWriter`/`DotRemover`/`DotListWriter`/`DotListLocator`, fail-closed `ConfigException`); `Config/Loader/` adds the PHP file & directory adapters (`PhpFileLoader`, `DirectoryLoader`), the `ConfigLoaderInterface` format seam and the `ConfigLoader` director with dot-notation prefix nesting — the file adapter executes only allowlisted basenames on `realpath()`-canonicalised paths (#176); `Config/Merge/` adds the Default < App < Env layering (#184) — `ArrayMerger` (maps recurse, later layer wins; lists are full declarations, never concatenated), `EnvSource` (exact-prefix `__` fold to nested addresses, raw string values, fail-closed malformed-address rejection) and `LayeredConfigBuilder` (copy-on-write `with*` layers, the director's first in-repo consumer, `build()` → frozen `Config`); `Config/Schema/` adds strict fail-closed schema validation (#195) — `SchemaViolation` (pure path/expected/actual data with the EXPECTATIONS vocabulary the layer quotes), the generic `ConfigSchema` engine (field-rule map, pure `validate()` / fail-closed `assert()` with complete ordered path-carrying violations, unknown keys rejected with the schema's field list), `SchemaValueRules` (the four value kinds: `string`, `strings`, `string-map`, `values` — degenerate emptinesses are violations) and `RouteSchema`, the Standard Route Schema of roadmap step 1 (`path`/`method`/`handler` required; `name`/`middleware`/`constraints`/`meta` optional); `Config/Cache/ConfigCache` adds the compiled cache (#202) — `dump()`/`load()` over a plain PHP artifact (`var_export` behind a generated-file header), identity round trip, mtime-free by decision (KDD-6: rebuild is explicit tooling, never a runtime heuristic), fail-closed on every failure with the path in the message. The `ConfigBootstrapper` (#205) closes the pipeline — load + merge via the layered builder, validate via `RouteSchema::assertList()` over the routes key, cache hit short-circuits the builder entirely / miss compiles after validation — and `Kernel::__construct(?Container, ?Config)` + `config()` carry the frozen repository into the kernel (backward-compatible, deptrac Kernel += Config); `ConfigBench` measures the dot-notation price (~1.4μs vs ~0.12μs direct) and `docs/ci-notes/config-system.md` records the KDD-1..7 decisions with the PHLAK/Config (MIT) attribution. |
| DSL layer, radix tree layer | ✅ Done | `src/Zef/Framework/Config/Routing/` — the radix tree layer's grammar slice: `RoutePath::parse()` (path-level shape rules: non-empty, leading `/`, root `/`, no trailing slash), `RoutePathGrammar` + `PathBraceScanner` (balanced braces, whole-segment `{name}` parameters, unique names) and `PathSegment` (static/parameter value object), fail-closed via `ConfigException`. Landed as #211 under a mis-scoped `Zef\Framework\Router` namespace and relocated to the configuration system by the corrective refactor — the engine compiles plain route arrays (the configuration system's output, `ROADMAP.md` Triad step 3); the `Router` layer itself is still untouched. `CompiledRoute::fromArray(mixed)` (#217, reworked onto this namespace) adds the engine's consumption view of the Standard Route Schema — `RouteEntryShape` (known fields, required path/method/handler), `RouteEntryCollections` (single/list/constraints-map value rules), `RouteEntryMeta` (free-form meta map) and `RouteConstraints` (fully-anchored, name-validated, compilability-probed patterns), with methods upper-cased, de-duplicated and sorted at compile time. `RadixTree::compile()`/`match()` complete the engine core: `RoutePatternTokens` + `RouteTreeBuilder`/`RouteNodeInserter`/`StaticRunInserter`/`SharedPrefixLength` build the prefix-compressed immutable tree (duplicate canonical patterns and names rejected at compile time), and `RouteMatcher`/`PrefixConsumption`/`StaticDescent`/`SegmentCapture`/`LeafRoute` walk it depth-first with full backtracking (static child first, parameter edge second, one non-empty segment per capture, constraints judged at the leaf) — `MatchResult` answers found / notFound / methodNotAllowed with the sorted allowed methods. Reverse routing completes the engine (`RouteUrlGenerator` + `RadixTree::route(name)` over the compile-time name index): generation is fail-closed (unknown name / missing or excess parameter / non-scalar-non-Stringable / empty / constraint-violating values all raise with the route and parameter in the message), values are `rawurlencode`d, and generated URLs round-trip back to their route (`tests/Config/Routing/RouteUrlGeneratorTest.php`); `benchmarks/RouterBench.php` measures the walk's flat cost (tree ~5.5μs on 15 and 300 routes alike vs a linear scan that grows 16x) and `docs/ci-notes/radix-engine.md` records the KDD-R1..R9 decisions with the scope correction and the legacy-repo attribution. The DSL layer completes the Triad (`RouteDsl::define(Closure)` — the fluent compiler of roadmap step 4): the seven verbs plus `match()` draft a chainable `PendingRoute` whose path is composed from the group prefixes in force and validated by the grammar on the declaration line; `group()` composes a validated `RoutePrefix` and restores prefix and middleware state when it closes (a throwing closure included), while scope middleware flows forward only — the roadmap's outer-to-inner stacking (global → group → route); `name`/`where`/`middleware`/`meta` fill the optional schema fields declare-once (middleware append-only), and `resource()` expands one declaration into the seven-route CRUD table (names from the path's static segments, only `{id}` constrainable) with prefix and middleware inheritance through the façade's own public surface (#240, #243, #247). The engine was amended for the schema's one-entry-per-method model: routes may share a pattern with disjoint method sets — `RouteMethodClaims` rejects only a method claimed twice, `LeafRoute` narrows candidacy route by route, and `RouteMethodAnswer` selects the answer with the sorted 405 union (#243, KDD-R5 amended). `RouteDslBench` records the build economics (define() linear, paid once — the runtime's flatness is RouterBench's claim) and `docs/ci-notes/dsl-builder.md` records KDD-D1..D8, the cross-lane engine discovery, and the local-vs-CI archeology yardstick. |

## Middleware Pipeline

| Area | Status | Evidence |
|------|--------|----------|
| Core architecture, execution engine, security, container integration, DX, performance | ⬜ Not started | — |

## HTTP Layer

| Area | Status | Evidence |
|------|--------|----------|
| Core message, security & hardening, parsing/formatting, observability | 🟡 Partial | `src/Zef/Framework/Message/Message.php` — the immutable message envelope exists; the HTTP request/response layer itself is not started. |

## HTTP Layer — "Zero-Library Shim"

| Area | Status | Evidence |
|------|--------|----------|
| Shim infrastructure, runtime adapter, security, performance | 🟡 Partial | `src/Contracts/Psr/Container/*` is the first zero-library PSR shim; the HTTP shim is not started. |

## Event Source System

| Area | Status | Evidence |
|------|--------|----------|
| Core event source, enterprise features | ⬜ Not started | — |

## Error Handling System

| Area | Status | Evidence |
|------|--------|----------|
| Standardisation, handler chain, observability, DX | 🟡 Partial | `src/Zef/Framework/Exception/ZefException.php`, `InvariantViolationException.php` — the exception root exists; the handler chain is not started. |

## Module Integration System

| Area | Status | Evidence |
|------|--------|----------|
| Core module engine, extension points, isolation, DX | 🟡 Partial | `src/Zef/Framework/Contracts/ServiceProviderInterface.php` — the provider contract exists; the module engine is not started. |

## Adapters Layer

| Area | Status | Evidence |
|------|--------|----------|
| View engine, database, adapters roadmap | ⬜ Not started | — |

## Version rules

| Area | Status | Evidence |
|------|--------|----------|
| Versioning policy | ✅ Implemented | `src/Zef/Framework/Version.php` (`Version::current()`), `tests/VersionTest.php`. |

---

## Cross-cutting: quality gates & CI (not in `ROADMAP.md`, tracked here)

| Item | Status | Evidence |
|------|--------|----------|
| Strict local pipeline | ✅ Implemented | `composer ci:strict` → `ci:static` + `ci:coverage` + `ci:infection` + `ci:bench` (`composer.json`). |
| CI lane (split workflows) | ✅ Implemented | Heavy lanes split into their own workflows: `.github/workflows/ci-static.yml`, `ci-coverage.yml`, `ci-infection.yml`, `ci-bench.yml` — each with its own status check, so one lane can be retried/re-run alone. `.github/workflows/ci-strict.yml` is the aggregator that keeps the required `CI Strict` check (it polls the four lane checks and fails unless all are green). |
| PR title gate | ✅ Implemented | `.github/workflows/pr-validator.yml` (job `PR Validator`), taxonomy in `scripts/changelog/conventional.py`. |
| Architecture gate | ✅ Implemented | `php-codearch-config.yaml`, `scripts/ci/archeology-gate.php`, `.github/workflows/archeology.yml` (job `PhpCodeArcheology SARIF`). |
| Static analysis | ✅ Implemented | PHPStan max + ratchet, Psalm level 1 + baseline, Progpilot (PHAR, SHA-256 pinned). |
| Supply chain | ✅ Implemented | `composer audit` strict gate, SBOM workflows, dependency review. |
| Zero-dependency gate | ✅ Implemented | `composer zero-deps` (part of `composer ci:static`) → `src/Zef/Framework/Tooling/ZeroDependencyGate.php` asserts `composer.json` `require === {php: ^8.4}` and `composer.lock` `packages === []`. |

---

## How to update this file

1. When a roadmap area gains code, change its **Status** and add the concrete
   **Evidence** pointer (file, class, or workflow) — never mark something
   Implemented without a pointer a reviewer can open.
2. Keep the **Last updated** line current: date + the `main` commit you checked.
3. Add a row to the cross-cutting table when a new gate/tool lands.
4. Do not restate the roadmap's prose here — link to `ROADMAP.md` and record
   only status + evidence.
