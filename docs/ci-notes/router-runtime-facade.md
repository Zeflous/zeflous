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
