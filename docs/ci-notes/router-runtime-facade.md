# Router runtime facade (Router roadmap, first slice)

## Scope

This change opens the enterprise Router area without relocating or duplicating
the Triad's radix engine. The public facade is
`Zef\\Framework\\Router\\Router`; the single source of truth for route
grammar, registration-time conflict checks, match semantics and URL generation
remains `Zef\\Framework\\Config\\Routing`.

The facade accepts three entry points:

- `fromConfig(Config)` for the configuration pipeline's already-loaded route
  table;
- `fromRoutes(array)` for Standard Route Schema entries;
- `define(Closure)` for the existing fluent `RouteDsl`.

All three converge on `RoutingRuntime`. Its radix tree is compiled lazily and
memoised once per facade instance. A new facade instance is the explicit
configuration-refresh boundary; an existing instance never silently rebuilds
from mutable external state.

## Reference and divergence

- **Reference:** `Zeflous/zef-framework`, `src/Adapters/Router/Router.php`,
  `RouteMatcher.php`, `RouteRadixIndex.php` and `UrlGenerator.php`.
  These establish a stable public router facade, collaborator-owned matching,
  and strict reverse routing.
- **Deliberate divergence:** the reference's adapter owns a separate mutable
  route collection and matcher. Zeflous already has a tested immutable radix
  engine under `Config/Routing` (see `docs/ci-notes/radix-engine.md`).
  This slice composes that engine instead of creating a second route
  registry or moving the configuration grammar back into the Router namespace.
- **Host-aware slices now implemented:** the Standard Route Schema accepts exact DNS hosts and whole-label host parameters such as `{tenant}.example.com`; host labels are canonicalised, host parameters are merged with path parameters, exact hosts outrank parameterised hosts and fallbacks, and ambiguous equal-specificity patterns are rejected. `Router::absoluteUrl()` safely generates absolute URLs from named host routes. `*` wildcard syntax, IP/port authority parsing and PSR-7 Host/URI integration are not claimed.
- **Basic Accept negotiation, API version header selection and PSR-15 adaptation are implemented:** route `produces` declarations are compiled and normalised; matching evaluates `Accept` ranges and `version` against `Accept-Version` / `X-API-Version`, exposing typed 406 outcomes and fail-closed version misses. `Middleware\\RouterRequestHandler` maps 404/405/406 to PSR-7 responses, adds `Vary: Accept`, executes route middleware fail-closed inside the global pipeline, and supports explicit-HEAD precedence plus GET fallback with response-body suppression. 404/405/406 responses use RFC 9457 `application/problem+json` (`about:blank` with title, status and detail); ETag/conditional requests and richer problem-type registries remain open. Deprecation headers, observability hooks, compiled cache files, CLI inspection and the broader advanced-routing roadmap remain open, so status stays **Partial**.

## Failure and runtime contract

- Invalid route declarations fail closed when the engine compiles the table.
- Unknown route names and invalid URL parameters retain the engine's
  `ConfigException` contract.
- A path miss and a method mismatch remain distinct `MatchResult` outcomes.
- The facade's `dispatcher(Container)` exposes the existing container-backed `RouteDispatcher` over the same memoized runtime. That dispatcher invokes handlers but deliberately does not execute route middleware; pipeline composition remains a separate adapter responsibility.
- No production dependency is introduced. Runtime requirements remain PHP 8.4+.

## Verification

`tests/Router/RouterTest.php` covers the facade's DSL/config/raw-table entry
points, matching outcomes, named route lookup, reverse routing, lazy tree
memoisation and invalid inputs. The strict repository pipeline remains the
authoritative gate; local execution must be recorded separately from CI.


## Conditional GET/HEAD responses

The PSR-15 router adapter evaluates conditional requests after route middleware
and handler execution. For successful (200) GET/HEAD representations, a matching
`If-None-Match` (weak comparison, including `*`) or, when that header is absent,
a valid `If-Modified-Since` condition produces a bodyless 304 response while
preserving representation metadata. `If-None-Match` takes precedence over
`If-Modified-Since`. The adapter does not generate ETags or Last-Modified
values; application handlers or middleware remain responsible for those headers.
The HTTP adapter tests were added, but CI validation remains pending.

## Maintenance pass: the gate-settling redistribution

The staged slice arrived with its quality gates unset: PHPStan carried 25
errors, Psalm 7 (plus two stale `PossiblyUnusedMethod` baseline entries the
new PSR-15 dispatch faces shrank away), three PHPUnit failures, and the
archeology gate refused six overloaded files (difficulty 24-38 against the
pinned 20 ceiling). The settling pass kept every behaviour and redistributed
the folds until each file answers one question at a size the gate accepts
(0 error-level findings):

- `RouteHost` became the host-grammar family: `RouteHostGrammar`
  (canonicalisation), `RouteHostRequest` (untrusted input),
  `RouteHostParameters` (capture and names), `RouteHostRendering` (reverse
  routing) and `RouteHostPrecedence` (claim semantics), with
  `RouteHostBinding`, `RouteHostClaims`, `RouteHostAmbiguities`,
  `RouteHostSelection` and `RouteHostPatternBucket` carrying the compile-time
  binding and match-time precedence.
- `RouterRequestHandler` became the composing shell over
  `ServerRequestMetadata`, `RouterRequestMatcher` (the HEAD-over-GET
  fallback), `RequestAuthority`, `ConditionalRequests`, `EntityTagMatch`,
  `LastModifiedMatch` and `NotModifiedResponse`.
- The Accept lane became `RouteProduces` (compile-time validation),
  `RouteAcceptRanges` + `RouteAcceptRange` (parsing) and `RouteAcceptMatch`
  (the per-media-type verdict -- whose no-match and q=0 cases agree on the
  quality answer, so the return reads the quality alone).
- `RadixTree::match()` narrows to walk -> host bucket -> version -> method
  through `RouteHostSelection` and `RouteVersionSelection`, and
  `CompiledRoute` assembles its fields through `RouteHostBinding` and
  `RouteFields`.

The changed-set mutation run opened at 103 escapes and closed at 584
mutants, 554 killed, 30 documented ignores, 0 escaped. The killer battery
pins the boundary and witness semantics the slice left open (the 253-byte
and 127-label host ceilings, the label-count mismatch in both directions,
the port boundary series, the Kelvin-sign and U+0130 case-fold witnesses,
the specificity tie series, the claim-scope separation on equally specific
hosts, and the version and conditional arms), and the thirty ignores follow
the established scoped-proof pattern -- every one argues its equivalence
from the grammar that bounds its reachable inputs, in the same voice the
 neighbouring entries already use.

## Verification (settling pass)

`composer ci:static`, `coverage:gate` (98.70%), the archeology gate (0
error-level findings), the changed-set mutation run (MSI 100/100) and the
five local mutation batches over `Config/Routing`, `Config/Schema`,
`Router`, `Middleware` and `Http` (5436 mutants, MSI 100 each) all pass
locally; the full-tree CI Infection lane remains the authoritative
verdict for the untouched trees.

## Verification (CI round one settling)

The first ready CI round failed `CI Static` on the phpcs exit code 3: the
seventy-five line-length warnings the local pass had read as "warning-only,
the normal state" are fatal to the lane -- `main` carries zero phpcs
findings, so any warning is a regression. The same round failed SonarCloud
on seven new issues: six `php:S1142` methods that had grown a fourth
return, and one `php:S6353` character class.

The line-length debt is repaid by wrapping (signatures, throws, route
tables and request builders in the tests). The return-count findings are
refolded rather than suppressed: `RouteHostParameters::parameters()` hands
its label loop to a `labelledParameters()` fold; `RouteHostRequest` folds
the brace guard into a ternary; `RouteVersionSelection` merges its two
identical unversioned-fallback guards; `RequestAuthority` merges the
malformed-authority and port-range guards (and writes `\d` for the port
class); `RadixTree::match()` becomes a flat walk -> host -> version ->
method pipeline whose every narrowing stage answers an empty selection
with the same not-found result; and `RouteDispatchHandler` splits its
handle at the executed boundary -- the handler answers the executed
representation, a `problemFor()` fold answers the 406/405/404 arms with
the branch shape the file already owned. `RouteHostPatternBucket::offer()`
takes the Slevomat early exit the CI log had shown all along. Every
refold is behaviour-preserving, and the scoped mutation run over the
eight touched files (127 mutants, 126 killed, 1 documented ignore) keeps
MSI 100/100.

The first refold attempt for `RouteDispatchHandler` moved the arms into a
`match (true)` behind a null coalesce -- and the archeology difficulty
rose from 19 to 23, failing the SARIF lane a second time. Adding folds to
the *same* class only adds operators to the aggregate: `match`, `true`
and `??` raise the distinct-operator count, and three extra wrapper
methods raise it further (21.57 -> 23.13 across the two attempts). The
shape that passes is the one that reuses the file's existing operator
vocabulary -- an if-chain split at the executed boundary -- landing at
difficulty 19.17. The lesson stands next to the campaign's earlier ones:
the difficulty gate rewards redistributing *between* classes, not
proliferating methods *within* one, and a `match (true)` refold is not
free the way a branch split is.
