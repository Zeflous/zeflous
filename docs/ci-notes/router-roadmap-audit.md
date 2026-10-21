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

- **Host-aware routing — exact and parameterised-host slices implemented in this branch:** `RouteHost` accepts exact DNS names and whole-label parameters (`{tenant}.example.com`), captures host parameters into the dispatch parameter map, and rejects repeated host/path parameter names. Exact hosts outrank parameterised hosts, the most-specific parameterised pattern wins, and host-agnostic routes are the final fallback; equal-specificity overlapping host patterns are rejected at compile time. `Router::absoluteUrl()` renders a safe absolute URL from a named host route. `*` wildcard syntax, IP literals, ports, tenant-context object injection and PSR-7 Host/URI authority integration remain open.
- **Basic content negotiation — implemented in this branch:** the optional `produces` schema field is validated and normalised by `RouteAcceptNegotiator`; `Accept` ranges, wildcards and quality values are evaluated at match time; a mismatch yields typed 406 outcomes carrying supported media types. The PSR-15 adapter maps this to a plain-text 406 and adds `Vary: Accept`; RFC 9457 rendering remains open.
- **PSR-7 / PSR-15 integration — implemented in this branch:** `Middleware\RouterRequestHandler` implements `RequestHandlerInterface`, extracts method/path/Host/Accept from `ServerRequestInterface`, and maps routing outcomes to responses. It composes inside the existing global `MiddlewarePipeline`; handler results must be `ResponseInterface`.
- **Public dispatcher bridge — implemented in this branch:** `Router::dispatcher(Container)` exposes the existing `RouteDispatcher` over the same memoized runtime.
- **Registration semantics:** DSL prefix groups, named routes, constraints and duplicate checks exist. Host-scoped conflict keys, a public Router registration lifecycle, route budget and explicit freeze contract remain open.
- **Performance guarantees:** radix compression exists, but zero-allocation matching is not satisfied by current capture/result construction and requires measurement rather than assertion.

### Phase 2 — Advanced Routing & Flexibility

- **Subdomain patterns and tenant parameters — partially implemented:** whole-label `{tenant}` host captures are included in the matched parameter map and usable in absolute URL generation. Explicit isolated tenant tables and a tenant-context service/object remain open.
- **Isolated tenant route tables:** no explicit API.
- **Locale prefix routing / locale negotiation / localized URL generation:** a static prefix can be expressed with DSL groups, but locale negotiation and locale-aware URL generation are not integrated.
- **API versioning — header selection implemented in this branch:** optional route `version` declarations are canonicalised, same path/method variants may coexist across versions, `Accept-Version` takes precedence over `X-API-Version`, and an absent version selects an unversioned fallback when one exists. Unknown or malformed explicit versions fail closed as not-found. Deprecation/Sunset response headers and version-aware OpenAPI output remain open.
- **Per-route middleware execution — implemented in this branch:** `RouterRequestHandler` resolves each matched route's middleware service from the container, rejects missing or non-PSR-15 services, and composes the route list around `RouteDispatchHandler`. The global pipeline wraps this adapter, so route middleware runs inside global middleware and preserves route declaration order.

### Phase 3 — Performance & Production

- **Revision-aware compilation:** one runtime memoizes its tree; no route revision signature or automatic invalidation/rebuild API is exposed at Router level.
- **Compiled radix-tree cache / route cache drivers / frozen-tree optimization:** not demonstrated by the facade. Configuration caching is not evidence of a compiled tree cache.
- **Metrics, tracing, detailed debug mode:** absent from the audited Router surface.
- **ETag and conditional requests:** not implemented. `HEAD` fallback/body suppression is implemented in the PSR-15 adapter, but conditional semantics and other response caching rules remain open.

### Phase 4 — Developer Experience & Tooling

- **CLI route list/test/tree dump:** no Router-specific command was found in the audited entry points; repository-wide command inventory is still needed before claiming a global absence.
- **Reverse-routing safety:** path reverse routing validates constraints and encodes parameters; `Router::absoluteUrl()` now renders host-pattern parameters as DNS labels. Round-trip and boundary cases still require CI validation.
- **OpenAPI / Swagger UI / Postman export:** no Router integration evidenced in the audited entry points.
- **Problem Details, CSRF and rate-limit hooks:** CSRF/rate limiting remain middleware concerns and can run in the global or route stack. Router-generated 404/405/406 bodies are currently plain text; RFC 9457 rendering remains open.

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
5. **HTTP adapter — implemented in this branch:** `RouterRequestHandler` maps match outcomes to PSR-7 responses, composes route middleware, and implements `HEAD` fallback/body suppression; RFC 9457 rendering and ETag/conditional semantics remain open.
6. **Middleware lifecycle:** compose route middleware around the existing dispatcher with fail-closed resolution and explicit ordering.
7. **Production/tooling:** freeze/budget semantics, cache, observability, CLI and exports as separate scoped slices with tests and roadmap evidence.

## Verification limits

The hosted GitHub connector is the available repository surface for this run. A local clone previously failed because GitHub DNS resolution was unavailable; therefore no local `composer ci:strict`, PHP test suite, static analysis, mutation run, or benchmark is claimed. CI results must be read from the PR head after each pushed commit. PR #512 remains Draft until the implemented scope and required gates are reviewed.
