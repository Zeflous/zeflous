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
  This first slice composes that engine instead of creating a second route
  registry or moving the configuration grammar back into the Router namespace.
- **Not claimed by this slice:** PSR-7 request adaptation, host-aware routing,
  route-level middleware execution, observability hooks, compiled cache files,
  CLI inspection and the broader advanced-routing roadmap. These remain future
  slices and the roadmap status therefore stays **Partial**.

## Failure and runtime contract

- Invalid route declarations fail closed when the engine compiles the table.
- Unknown route names and invalid URL parameters retain the engine's
  `ConfigException` contract.
- A path miss and a method mismatch remain distinct `MatchResult` outcomes.
- The facade does not resolve or invoke handlers and does not execute
  middleware; those are separate adapter/pipeline responsibilities.
- No production dependency is introduced. Runtime requirements remain PHP 8.4+.

## Verification

`tests/Router/RouterTest.php` covers the facade's DSL/config/raw-table entry
points, matching outcomes, named route lookup, reverse routing, lazy tree
memoisation and invalid inputs. The strict repository pipeline remains the
authoritative gate; local execution must be recorded separately from CI.
