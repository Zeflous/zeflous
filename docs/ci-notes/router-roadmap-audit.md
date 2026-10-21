# Router Roadmap Audit

**Scope:** requirement-to-component audit for `ROADMAP.md` → **Router (Enterprise-Grade Radix Tree)** against `feat/router-runtime-facade`, inspected on 2026-10-21. This is an implementation audit, not a claim that the Router roadmap is complete.

## Existing implementation that can be reused

| Contract | Existing evidence | Assessment |
|---|---|---|
| Compressed radix tree and static-prefix splitting | `Config/Routing/RadixTree`, `RouteTreeBuilder`, `RouteNodeInserter`, `RoutePatternTokens` | Present; zero-allocation performance is not established. |
| Method/path matching, parameter capture and constraints | `CompiledRoute`, `RouteMatcher`, `RouteMethodAnswer`, `MatchResult` | Present; route captures include path and host parameters. Captures and result objects are created during matching. |
| 404 vs 405 and deterministic Allow methods | `MatchResult`, `RouteMethodAnswer` | Present in the engine. |
| Named routes, duplicate detection and reverse routing | `RouteTableRegistry`, `RadixTree::route()`, `RouteUrlGenerator` | Present for the path-only schema; URL parameters are constraint-checked. |
| DSL grouping and scoped middleware metadata | `RouteDsl`, `RouteDslScope`, `PendingRoute` | Present as route declarations/metadata; not a per-route middleware execution pipeline. |
| Handler dispatch | `RouteDispatcher`, `RouteHandlerResolver`, `DispatchOutcome` | Present in `Config/Routing` for primitive method/path input and container-resolved handlers. The new `Zef\Framework\Router\Router` facade does not expose it yet. |
| Compile once per runtime instance | `RoutingRuntime::tree()` memoizes `RadixTree::compile()` | Present lazily per instance. No public mutable tree builder is exposed by the facade. |
| Standard route schema | `Config/Schema/RouteSchema`, `Config/Routing/RouteEntryShape`, `RouteHost` | Optional `host` accepts exact DNS names and whole-label parameters such as `{tenant}.example.com`; host patterns are canonicalised and checked for ambiguity. Ports, IP literals and `*` wildcard syntax remain unsupported. |

## Roadmap gaps by phase

### Phase 1 — Core Engine & Foundation

- **Host-aware routing — exact and parameterised-host slices implemented in this branch:** `RouteHost` accepts exact DNS names and whole-label parameters (`{tenant}.example.com`), captures host parameters into the dispatch parameter map, and rejects repeated host/path parameter names. Exact hosts outrank parameterised hosts, which outrank host-agnostic fallback routes; equal-specificity overlapping host patterns are rejected at compile time. `Router::absoluteUrl()` renders a safe absolute URL from a named host route. `*` wildcard syntax, IP literals, ports, tenant-context object injection and PSR-7 Host/URI authority integration remain open.
- **Basic content negotiation — implemented in this branch:** the optional `produces` schema field is validated and normalised by `RouteAcceptNegotiator`; `Accept` ranges, wildcards and quality values are evaluated at match time; a mismatch yields a typed `MatchResult::notAcceptable()` and `DispatchOutcome::notAcceptable()` carrying the route's supported media types. Mapping that outcome to an HTTP 406 response / RFC 9457 envelope still belongs to the HTTP adapter.
- **PSR-7 / PSR-15 Router integration:** the framework has PSR HTTP/middleware infrastructure, but this facade takes primitive method/path strings; it is not a `ServerRequestInterface` adapter or `RequestHandlerInterface`.
- **Public dispatcher bridge — implemented in this branch:** `Router::dispatcher(Container)` exposes the existing `RouteDispatcher` over the same memoized runtime.
- **Registration semantics:** DSL prefix groups, named routes, constraints and duplicate checks exist. Host-scoped conflict keys, a public Router registration lifecycle, route budget and explicit freeze contract remain open.
- **Performance guarantees:** radix compression exists, but zero-allocation matching is not satisfied by current capture/result construction and requires measurement rather than assertion.

### Phase 2 — Advanced Routing & Flexibility

- **Subdomain patterns and tenant parameters — partially implemented:** whole-label `{tenant}` host captures are included in the matched parameter map and usable in absolute URL generation. Explicit isolated tenant tables and a tenant-context service/object remain open.
- **Isolated tenant route tables:** no explicit API.
- **Locale prefix routing / locale negotiation / localized URL generation:** a static prefix can be expressed with DSL groups, but locale negotiation and locale-aware URL generation are not integrated.
- **API versioning:** path versioning can be expressed as ordinary paths; version-header matching and deprecation response middleware are missing.
- **Per-route middleware execution:** identifiers are retained on matched route metadata, but dispatch does not resolve or execute that middleware list, order it with the global pipeline, or enforce fail-closed resolution.

### Phase 3 — Performance & Production

- **Revision-aware compilation:** one runtime memoizes its tree; no route revision signature or automatic invalidation/rebuild API is exposed at Router level.
- **Compiled radix-tree cache / route cache drivers / frozen-tree optimization:** not demonstrated by the facade. Configuration caching is not evidence of a compiled tree cache.
- **Metrics, tracing, detailed debug mode:** absent from the audited Router surface.
- **ETag, conditional requests, HEAD response-body handling:** response semantics are not integrated into the router dispatch path.

### Phase 4 — Developer Experience & Tooling

- **CLI route list/test/tree dump:** no Router-specific command was found in the audited entry points; repository-wide command inventory is still needed before claiming a global absence.
- **Reverse-routing safety:** path reverse routing validates constraints and encodes parameters; `Router::absoluteUrl()` now renders host-pattern parameters as DNS labels. Round-trip and boundary cases still require CI validation.
- **OpenAPI / Swagger UI / Postman export:** no Router integration evidenced in the audited entry points.
- **Problem Details, CSRF and rate-limit hooks:** no Router-level integration evidenced; these are likely adapter/middleware concerns.

### Phase 5 — Ecosystem & Integration

- **Long-running workers:** per-instance memoization supports reuse in principle; worker lifecycle/reset guarantees need explicit tests.
- **Async/Fiber and Swoole/Hyperf:** no dedicated integration contract evidenced.
- **PSR-11 handler injection:** the existing dispatcher resolves handlers through the container; model binding and contextual route binding are still absent from the facade.
- **Route events and listeners:** absent from the audited Router surface.

## Architecture decision

Do not copy the reference repository's Router collaborators wholesale. Reuse the existing `Config/Routing` engine and `RouteDispatcher`; expose missing stable integration seams from the Router facade. Keep request-to-response adaptation and PSR-15 pipeline composition in the HTTP/adapter boundary. Host and content-negotiation support require explicit schema, conflict-signature and outcome decisions because they affect matching semantics, not only the facade.

## Implementation sequence

1. **Audit baseline:** map existing behavior and gaps without overstating completeness.
2. **Public integration seam — implemented in this branch:** `Router::dispatcher(Container)` returns the existing `RouteDispatcher` over the same memoized runtime. `tests/Router/RouterTest.php` adds an execution-path contract test; execution of the test suite is pending because the local clone is unavailable.
3. **Exact-host core — implemented in this branch:** host schema, canonicalisation, host-scoped duplicate claims, exact-host match filtering and host-precedence tests are added. CI execution remains pending.
4. **Host-pattern slice — implemented in this branch:** whole-label parameters, exact > parameterised > fallback precedence, ambiguity refusal, host/path parameter collision refusal, host capture and absolute URL generation. `*` wildcard syntax and authority parsing remain a distinct decision.
5. **HTTP adapter:** integrate the typed 406 outcome and request/response mapping through PSR-7/15 boundaries; add standards-compliant error rendering.
6. **Middleware lifecycle:** compose route middleware around the existing dispatcher with fail-closed resolution and explicit ordering.
7. **Production/tooling:** freeze/budget semantics, cache, observability, CLI and exports as separate scoped slices with tests and roadmap evidence.

## Verification limits

The hosted GitHub connector is the available repository surface for this run. A local clone previously failed because GitHub DNS resolution was unavailable; therefore no local `composer ci:strict`, PHP test suite, static analysis, mutation run, or benchmark is claimed. CI results must be read from the PR head after each pushed commit. PR #512 remains Draft until the implemented scope and required gates are reviewed.
