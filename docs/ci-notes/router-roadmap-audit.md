# Router Roadmap Audit

**Scope:** requirement-to-component audit for `ROADMAP.md` → **Router (Enterprise-Grade Radix Tree)** against the target branch `feat/router-runtime-facade` as inspected on 2026-10-21. This is an implementation audit, not a claim that the Router roadmap is complete.

## Existing implementation that can be reused

| Contract | Existing evidence | Assessment |
|---|---|---|
| Compressed radix tree and static-prefix splitting | `Config/Routing/RadixTree`, `RouteTreeBuilder`, `RouteNodeInserter`, `RoutePatternTokens` | Present; performance/zero-allocation claim is not established. |
| Method + path matching, parameter capture and constraints | `CompiledRoute`, `RouteMatcher`, `RouteMethodAnswer`, `MatchResult` | Present for method/path. Matching creates result/capture values; not zero-allocation. |
| 404 vs 405 and deterministic Allow methods | `MatchResult`, `RouteMethodAnswer` | Present in the engine. |
| Named routes, duplicate-name/pattern detection and reverse routing | `RouteTableRegistry`, `RadixTree::route()`, `RouteUrlGenerator` | Present for the supported path-only schema; URL constraints are checked. |
| Declarative route grouping and scoped middleware metadata | `RouteDsl`, `RouteDslScope`, `PendingRoute` | Present in DSL output; this is metadata registration, not execution of a middleware pipeline. |
| Constraint parsing and validation | `RoutePath`, `RouteConstraints`, `CompiledRoute::fromArray()` | Validation exists at route compilation; eager validation at the public declaration/registration boundary must be verified for every entry path. |
| Compile once per runtime instance | `RoutingRuntime::tree()` memoizes `RadixTree::compile()` | Present lazily per instance. No public mutable tree builder is exposed by the facade. |
| Public facade over the existing engine | `src/Zef/Framework/Router/Router.php` | Initial slice only; it delegates match/name/URL operations and does not dispatch handlers. |

## Roadmap gaps by phase

### Phase 1 — Core Engine & Foundation

- **Host-aware routing:** missing from the target route schema, `CompiledRoute`, tree registration and match signature. A route's host cannot currently scope a path/method conflict.
- **Content negotiation:** no route-level supported MIME contract or Accept negotiation / 406 outcome is exposed by the current engine.
- **PSR-7 / PSR-15 integration:** the facade accepts primitive method/path strings and returns `MatchResult`; it does not accept `ServerRequestInterface` or implement a request-handler/middleware contract.
- **Handler dispatch:** absent from the facade; `handler` remains a string in route metadata.
- **Registration semantics:** the DSL supports path prefix groups and middleware metadata, and the compiler detects duplicate registrations, but there is no Router-level mutable registration API, host-scoped conflict key, route budget, or freeze lifecycle equivalent to the reference facade.
- **Performance guarantees:** radix compression exists, but zero-allocation matching is contradicted by capture/result construction and has not been demonstrated by benchmark evidence. Immutability is a runtime usage property, not yet an explicit Router freeze contract.

### Phase 2 — Advanced Routing & Flexibility

- **Subdomain wildcards and tenant context:** missing; no host pattern matching or request tenant context.
- **Isolated tenant route tables:** missing as an explicit API.
- **Locale prefix routing / locale negotiation / localized URL generation:** no Router integration is present in the audited facade. Existing DSL groups can express a static prefix but do not negotiate a locale.
- **API versioning:** path versioning can be declared as ordinary paths, but version-header matching and deprecation response middleware are missing.
- **Per-route middleware execution:** middleware identifiers can be stored on a route, but the Router does not resolve or execute them, enforce fail-closed resolution, or order them with a global pipeline.

### Phase 3 — Performance & Production

- **Revision-aware compilation:** one runtime instance memoizes its tree; no revision signature or automatic invalidation/rebuild API is exposed at Router level.
- **Frozen-tree optimization / compiled router cache / route cache drivers:** not implemented by the Router facade. Configuration caching is not evidence of a compiled radix-tree cache.
- **Metrics, tracing, detailed debug mode:** absent from the audited Router surface.
- **ETag, conditional requests, HEAD response-body handling:** HTTP response semantics are not part of this engine and are not integrated by the facade.

### Phase 4 — Developer Experience & Tooling

- **CLI route list/test/tree dump:** no Router-specific command or inspector was found in the audited entry points; verify repository-wide command inventory before claiming a global absence.
- **Reverse-routing safety:** name lookup and parameter constraint checks exist; end-to-end self-validation and full encoding corner cases still need contract-level tests.
- **OpenAPI / Swagger UI / Postman export:** no Router integration evidenced in the audited entry points.
- **Problem Details, CSRF and rate-limit hooks:** no Router-level integration evidenced. These may belong in HTTP/middleware adapters rather than the domain engine.

### Phase 5 — Ecosystem & Integration

- **Long-running workers:** per-instance lazy compilation supports reuse in principle, but worker lifecycle/reset guarantees need explicit tests and documentation.
- **Async/Fiber and Swoole/Hyperf:** no dedicated integration contract evidenced.
- **PSR-11 handler injection / model binding / contextual binding:** absent from the Router facade.
- **Route events and listeners:** absent from the audited Router surface.

## Architecture decision

Do not copy the reference repository's Router collaborators wholesale. First map each reference responsibility to an existing target component, then add only missing seams. Keep route grammar, conflict rules and radix matching in `Config/Routing`; put request adaptation, handler dispatch and PSR-15 middleware execution in the HTTP/adapter boundary. Host and content-negotiation requirements need an explicit route-schema decision before implementation because they affect conflict signatures and match outcomes, not just the public facade.

## Implementation sequence

1. **Audit baseline (this commit):** record existing behavior and gaps without overstating completeness.
2. **Route schema and core matching:** define host semantics and conflict signatures; implement host-aware matching with exact/wildcard precedence and exhaustive tests.
3. **HTTP adapter contract:** add request adaptation and dispatch only after the repository's PSR interfaces and handler conventions are identified; add explicit 404/405/406 mapping.
4. **Middleware and registration lifecycle:** connect route middleware metadata to the existing pipeline, then add route budgets/freeze semantics if they fit the established configuration-first architecture.
5. **Production/tooling contracts:** cache, observability, CLI and exports as separate scoped slices, each with tests and roadmap evidence.

## Verification limits

The hosted GitHub connector is the available repository surface for this run. A local clone previously failed because GitHub DNS resolution was unavailable; therefore no local `composer ci:strict`, PHP test suite, static analysis, mutation run, or benchmark is claimed by this audit. CI results must be read from the PR head after each pushed commit. The PR remains Draft until the implemented scope and all required gates are reviewed.
