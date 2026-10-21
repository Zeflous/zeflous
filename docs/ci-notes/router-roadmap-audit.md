# Router Roadmap Audit

**Scope:** requirement-to-component audit for `ROADMAP.md` → **Router (Enterprise-Grade Radix Tree)** against `feat/router-runtime-facade`, inspected on 2026-10-21. This is an implementation audit, not a claim that the Router roadmap is complete.

## Existing implementation that can be reused

| Contract | Existing evidence | Assessment |
|---|---|---|
| Compressed radix tree and static-prefix splitting | `Config/Routing/RadixTree`, `RouteTreeBuilder`, `RouteNodeInserter`, `RoutePatternTokens` | Present; zero-allocation performance is not established. |
| Method/path matching, parameter capture and constraints | `CompiledRoute`, `RouteMatcher`, `RouteMethodAnswer`, `MatchResult` | Present for method/path. Captures and result objects are created during matching. |
| 404 vs 405 and deterministic Allow methods | `MatchResult`, `RouteMethodAnswer` | Present in the engine. |
| Named routes, duplicate detection and reverse routing | `RouteTableRegistry`, `RadixTree::route()`, `RouteUrlGenerator` | Present for the path-only schema; URL parameters are constraint-checked. |
| DSL grouping and scoped middleware metadata | `RouteDsl`, `RouteDslScope`, `PendingRoute` | Present as route declarations/metadata; not a per-route middleware execution pipeline. |
| Handler dispatch | `RouteDispatcher`, `RouteHandlerResolver`, `DispatchOutcome` | Present in `Config/Routing` for primitive method/path input and container-resolved handlers. The new `Zef\Framework\Router\Router` facade does not expose it yet. |
| Compile once per runtime instance | `RoutingRuntime::tree()` memoizes `RadixTree::compile()` | Present lazily per instance. No public mutable tree builder is exposed by the facade. |
| Standard route schema | `Config/Schema/RouteSchema`, `Config/Routing/RouteEntryShape` | Current fields are path, method, handler, name, middleware, constraints and meta; host/Accept fields are not part of the schema. |

## Roadmap gaps by phase

### Phase 1 — Core Engine & Foundation

- **Host-aware routing:** absent from the route schema, `CompiledRoute`, duplicate-claim signature and match API. Host-scoped routes cannot currently coexist on the same method/path.
- **Content negotiation:** no route-level supported MIME contract or Accept negotiation / 406 outcome exists in the audited matching/dispatch path.
- **PSR-7 / PSR-15 Router integration:** the framework has PSR HTTP/middleware infrastructure, but this facade takes primitive method/path strings; it is not a `ServerRequestInterface` adapter or `RequestHandlerInterface`.
- **Public dispatcher bridge:** the existing `RouteDispatcher` resolves and invokes handlers, but the new public facade does not expose a stable factory for it.
- **Registration semantics:** DSL prefix groups, named routes, constraints and duplicate checks exist. Host-scoped conflict keys, a public Router registration lifecycle, route budget and explicit freeze contract remain open.
- **Performance guarantees:** radix compression exists, but zero-allocation matching is not satisfied by current capture/result construction and requires measurement rather than assertion.

### Phase 2 — Advanced Routing & Flexibility

- **Subdomain wildcards and tenant context:** no host pattern matcher or request tenant context.
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
- **Reverse-routing safety:** named lookup and constraint validation exist; remaining encoding and end-to-end round-trip cases need contract-level coverage.
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
3. **Host-aware core:** define host grammar, exact/wildcard precedence, conflict signatures, host parameter handling and exhaustive tests.
4. **Negotiation and HTTP adapter:** define route MIME contracts and 406 semantics, then integrate request/response mapping.
5. **Middleware lifecycle:** compose route middleware around the existing dispatcher with fail-closed resolution and explicit ordering.
6. **Production/tooling:** cache, observability, CLI and exports as separate scoped slices with tests and roadmap evidence.

## Verification limits

The hosted GitHub connector is the available repository surface for this run. A local clone previously failed because GitHub DNS resolution was unavailable; therefore no local `composer ci:strict`, PHP test suite, static analysis, mutation run, or benchmark is claimed. CI results must be read from the PR head after each pushed commit. PR #512 remains Draft until the implemented scope and required gates are reviewed.
