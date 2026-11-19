# Router Roadmap Audit

**Scope:** requirement-to-component audit for `ROADMAP.md` → **Router (Enterprise-Grade Radix Tree)** against `feat/router-runtime-facade`, inspected on 2026-10-21. This is an implementation audit, not a claim that the Router roadmap is complete.

## Existing implementation that can be reused

| Contract | Existing evidence | Assessment |
|---|---|---|
| Compressed radix tree and static-prefix splitting | `Config/Routing/RadixTree`, `RouteTreeBuilder`, `RouteNodeInserter`, `RoutePatternTokens` | Present; zero-allocation performance is not established. |
| Method/path matching, parameter capture and constraints | `CompiledRoute`, `RouteMatcher`, `RouteMethodAnswer`, `MatchResult` | Present; route captures include path and host parameters. Captures and result objects are created during matching. |
| 404 vs 405 and deterministic Allow methods | `MatchResult`, `RouteMethodAnswer` | Present in the engine. |
| Named routes, duplicate detection and reverse routing | `RouteTableRegistry`, `RadixTree::route()`, `RouteUrlGenerator` | Path URL generation validates path parameters; `Router::absoluteUrl()` additionally renders validated host parameters for named host routes. Round-trip boundary coverage still requires CI verification. |
| DSL grouping and scoped middleware metadata | `RouteDsl`, `RouteDslScope`, `PendingRoute` | Present as route declarations/metadata; not a per-route middleware execution pipeline. |
| Handler dispatch | `RouteDispatcher`, `RouteHandlerResolver`, `DispatchOutcome`, `Router::dispatcher()` | The public facade exposes the container-backed dispatcher over the same memoized runtime. |
| Compile once per runtime instance | `RoutingRuntime::tree()` memoizes `RadixTree::compile()` | Present lazily per instance. No public mutable tree builder is exposed by the facade. |
| Standard route schema | `Config/Schema/RouteSchema`, `Config/Routing/RouteEntryShape`, `RouteHost` | Optional `host` accepts exact DNS names and whole-label parameters such as `{tenant}.example.com`; host patterns are canonicalised and checked for ambiguity. Ports, IP literals and `*` wildcard syntax remain unsupported. |

## Roadmap gaps by phase

### Phase 1 — Core Engine & Foundation

- **Host-aware routing — exact and parameterised-host slices implemented in this branch:** `RouteHost` accepts exact DNS names and whole-label parameters (`{tenant}.example.com`), captures host parameters into the dispatch parameter map, and rejects repeated host/path parameter names. Exact hosts outrank parameterised hosts, the most-specific parameterised pattern wins, and host-agnostic routes are the final fallback; equal-specificity overlapping host patterns are rejected at compile time. `Router::absoluteUrl()` renders a safe absolute URL from a named host route. `*` wildcard syntax, IP literals, ports, tenant-context object injection and PSR-7 Host/URI authority integration remain open.
- **Basic content negotiation — implemented in this branch:** the optional `produces` schema field is validated and normalised by `RouteAcceptNegotiator`; `Accept` ranges, wildcards and quality values are evaluated at match time; a mismatch yields typed 406 outcomes carrying supported media types. The PSR-15 adapter maps this to RFC 9457 `application/problem+json` and adds `Vary: Accept`; richer application-specific problem types remain open.
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
- **ETag and conditional requests — partial support implemented:** the PSR-15 adapter honors `If-None-Match` using weak comparison (including `*`) and `If-Modified-Since` for successful GET/HEAD responses, returning a bodyless 304 and giving `If-None-Match` precedence. The adapter does not generate validators; application handlers/middleware must provide `ETag` and/or `Last-Modified`. Preconditions such as `If-Match` / 412, range requests, cache directives and broader RFC conformance remain open. CI validation is pending.

### Phase 4 — Developer Experience & Tooling

- **CLI route list/test/tree dump:** no Router-specific command was found in the audited entry points; repository-wide command inventory is still needed before claiming a global absence.
- **Reverse-routing safety:** path reverse routing validates constraints and encodes parameters; `Router::absoluteUrl()` now renders host-pattern parameters as DNS labels. Round-trip and boundary cases still require CI validation.
- **OpenAPI / Swagger UI / Postman export:** no Router integration evidenced in the audited entry points.
- **Problem Details, CSRF and rate-limit hooks:** generic RFC 9457 Problem Details (`about:blank`, title/status/detail) is implemented for router-generated 404/405/406 responses. Application-specific problem types, CSRF and rate limiting remain middleware concerns and can run in the global or route stack.

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
5. **HTTP adapter — implemented in this branch:** `RouterRequestHandler` maps match outcomes to PSR-7 responses, emits RFC 9457 Problem Details for 404/405/406, composes route middleware, implements `HEAD` fallback/body suppression, and handles conditional GET/HEAD requests with 304 responses when supplied validators match. Validator generation and the rest of HTTP caching/precondition semantics remain open.
6. **Middleware lifecycle:** compose route middleware around the existing dispatcher with fail-closed resolution and explicit ordering.
7. **Production/tooling:** freeze/budget semantics, cache, observability, CLI and exports as separate scoped slices with tests and roadmap evidence.

## Verification limits

The hosted GitHub connector is the available repository surface for this run. A local clone previously failed because GitHub DNS resolution was unavailable; therefore no local `composer ci:strict`, PHP test suite, static analysis, mutation run, or benchmark is claimed. CI results must be read from the PR head after each pushed commit. PR #512 remains Draft until the implemented scope and required gates are reviewed.

## KDD-RA1 — the match-time arm the compile grammar already owns is gone (issue #799, finding 1)

`RouteAcceptNegotiator::accepts()` carried a dead `ConfigException` throw
for a `produces` entry that is not a type/subtype pair — dead because
`RouteProduces::normalize()` refuses that entry at compile, before any
route exists, so the match-time walk never meets one. The arm's contract
contradicted the match pipeline's own doctrine (`RadixTree`: match-time
inputs never raise; a `ConfigException` is a route table defect's words):
had the arm ever been reached, the throw would have escaped through a
boundary that does not hold it. The arm now answers the doctrine the
skipped ranges beside it already spell: an ungrammatical produce
contributes nothing to the verdict, and the well-formed half decides
alone. The compile-time refusal (the two-slash produces entry and its
grammar test) is unchanged — registration is the moment to judge it,
and the honest spelling of an unreachable raise is its removal, never a
second owner.

## KDD-RA2 — the anchoring escapes its own delimiter, and the engine's failure answers a diagnostic (issue #799, finding 2)

Two halves of the constraint lane's own honesty. THE DELIMITER: the
first spelling wrapped every declared pattern in the `/^(?:...)\z/`
delimiter verbatim, so a pattern carrying a `/` of its own (a regex may
legitimately match a slash) closed the delimiter early and the
compilability probe refused a pattern the engine could have compiled,
with a message that named no delimiter. The anchoring now walks the
pattern byte-wise and escapes every `/` no preceding backslash already
escaped (an even backslash run leaves the slash unescaped — the
delimiter to escape; an odd run has already escaped it — the pattern's
own spelling, kept verbatim), so the wrapper's grammar never decides
the probe: the pattern's own grammar does. THE DIAGNOSTIC: at match
time `RouteConstraints::check()` read a runtime PCRE failure — the
backtrack limit a catastrophic pattern's hostile segment exhausts — as
an ordinary constraint rejection, so the ReDoS-shaped request answered
404 with no diagnostic anywhere; the engine unable to answer is not the
segment's verdict. `preg_match`'s `false` now carries its own typed
refusal: an `InternalException` (the internal-fault signal whose author
owns the message) naming the parameter and the PCRE error — never the
segment value, runtime data stays runtime data. The pinned repro runs
under a starved backtrack budget with the alternation shape
(`(a|a)+c?` — the identical-branch alternation that defeats PCRE2's
auto-possessification, where the textbook `(a+)+b` shape completes
cleanly), so the arm the diagnostic answers is exercised, not assumed.

## KDD-RA3 — the host claim scope is a nested tuple, never a borrowed separator (issue #799, finding 3)

`RouteMethodClaims` built the host-claim scope as
`$path . '|' . ($version ?? '*')` — collision-free only because the
version grammar happens to refuse the pipe while the path grammar lets
a static segment carry one: the safety borrowed from a sibling grammar
the scope never owned, the exact hazard the claims map's own docblock
names ("no separator grammar can alias two different tuples onto one
string") for the method claims it already spelled as a nested tuple.
`RouteHostClaims::claim()` now takes the path and the version as its
own arguments and the claims map walks path => version => method, so
the scope identity is the tuple itself and no separator grammar —
current or future — can alias two scopes onto one string. The
pipe-carrying static segment pins the discipline end to end: the
path registers, claims its host scope beside the exact-host twin and
the versioned twin, and matches.

## KDD-RA4 — the engine's capability lane answers: de-scoped, explicitly (issue #799, finding 4, completing #782's finding 6)

The audit named four idiom gaps a migration from another framework
would meet, and the lane's decision is an explicit de-scope — each with
its own reason, recorded here so the roadmap's promise never silently
outgrows the engine's grammar:

- **Multi-segment catch-all (`/files/{path}`)** — de-scoped: a
  parameter captures exactly one segment, because a parameter boundary
  and a slash boundary must agree for a captured value to stay
  decodable (`PathSegment`'s own doctrine). A catch-all would capture
  across slash boundaries, so its values would need re-encoding before
  any nested path grammar (dispatcher, URL generator) could read them
  back — a second grammar the engine does not own. The idiom answers
  at the host seam (prefix groups + a parameterised host) or the
  handler's own suffix walk, both already supported.
- **Optional parameter (`{param?}`)** — de-scoped: two routes spell the
  optional half explicitly (`/users` and `/users/{id?}` becomes two
  declarations), because the one-segment-one-parameter grammar and the
  decodable-capture doctrine above make an optional segment a third
  grammar shape (a tree node both ending the path and continuing it)
  whose weights and precedence the declarative spellings already own
  without it.
- **Auto-OPTIONS** — de-scoped: the engine's posture is explicit
  declaration, not synthesis — a 405 names the methods the matching
  routes actually answer (the `Allow` union the method-claims family
  already computes), and an OPTIONS the operator never declared is a
  claim about the resource the engine must not invent. The route that
  wants to answer OPTIONS declares it; `Allow` stays honest.
- **Query-string generation** — de-scoped: the URL generator's
  vocabulary is the route's own identity — the path and host parameters
  the grammar captures; extra parameters and query strings are the
  caller's own encoding, and refusing extras (the generator's current
  behaviour) is the identity boundary, not a gap. RFC-wise the query is
  not the route's identity; `http_build_query` beside the generator is
  the host's own fold.

The per-call allocations the same finding names (`new RouteMatcher()`
per match, `new RouteUrlGenerator()` per url) stay acknowledged, not
amended: the roadmap's zero-allocation promise is a target the current
capture/result construction does not yet satisfy, and the honest record
is this note's own line — the measurement lane (`CI Bench`, the
`phpbench.json` suites) owns the number, and no claim here outruns it.

## KDD-RA5 — the round's own gate: the anchoring leaves the declarations' fold, and its counter meets its kills (issue #799, the gate round)

The landing lane's own answer to its red `CI Infection` and
`CI Static` runs: the round's four escaped mutants and two
error-level architecture findings, answered at their own seams rather
than argued away.

THE EXTRACTION (the two architecture findings): the anchoring walk
lived inside `RouteConstraintDeclarations` as a private fold, and the
archeology gate scored the declarations' own difficulty over the
ceiling with it (`RouteConstraintAnchoring` now owns the single
question in its own class — the delimiter escape is a vocabulary of
its own, and the declarations' validation reads it through the same
one-line call the probe always did).

THE COUNTER'S KILLS: the walk's backslash-run counter now dies by its
own boundary pins instead of escaping — the pattern that OPENS with
the delimiter (`/[0-9]+` declared and compiled) kills the counter's
initial zero (a misread opening run would close the wrapper's own
delimiter at the pattern's first byte), and the round's own
delimiter-carrying pattern kills the run-reset zero. The counter's
parity question (`% 2 === 0`) is the one thing the walk never lets a
test observe through sign — PHP's modulo answers parity faithfully
under either sign, so the increment's `--` rewrite is value-equivalent
by construction and rides the `Increment` ledger, scoped by a
whole-line source regex.

THE SPLIT'S DOCTRINE: the walk spells its byte split
`mb_str_split($pattern, 1, '8bit')` — the house's byte-semantics
doctrine (the encoding argument owns the byte semantics), honest to
the walk's own docblock: the PCRE grammar is byte-wise, and the
character-map default would group a multi-byte sequence into one step
(a broken lead byte could swallow the very backslash the grammar
counts). The mutant's `str_split($pattern, 1)` answers the same
single-byte units for every byte string there is — the equivalence
the `MBString` ledger entry carries, verified by the complete suite
under the manually applied rewrite.

THE LEDGER'S OWN LESSON rides the same round and lives in
`docs/ci-notes/mutation-observation.md` (KDD-MO9): the view family's
flushed-tail ignore had never matched anything — the matcher anchors
every source regex to a whole diff line, and the substring spelling
the #787 round landed rode main's rounding tolerance undetected.
