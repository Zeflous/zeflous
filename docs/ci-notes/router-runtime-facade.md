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

## KDD-VP1 — one policy for every spelling of the path (issue #794)

The audit's asymmetry: a path carrying only unversioned routes quietly
ignored an explicit request version and matched anyway, while a path
carrying versioned routes failed the same unknown version closed --
two answers for one request shape, and the docblock ("the fallback
only when no explicit version was supplied") describing only the
second. Worse, a versioned request whose version no route answered
*and* whose method no route answered collapsed into a bare 404: the
client could not tell a wrong version from a wrong method.

The fix enforces the docblock's own policy uniformly: an explicit
version is a binding intent whether the path carries versioned
routes, unversioned ones, or both -- `RouteVersionSelection::select`
answers the unversioned fallback only a versionless request, and an
explicit version selects only the versioned routes answering it (the
unknown spelling failing closed exactly as before, now on both
shapes). The empty selection's answer is no longer a bare 404: the
pipeline routes it through the new `RouteVersionMiss` fold -- the
method judgement over the whole candidate set -- so a method no
candidate answers reports the 405 with the Allow every route at the
path answers (`RouteAllowedMethods`, extracted from
`RouteMethodAnswer`'s private walk because the miss shares the
payload), while a method the path does answer leaves the version
itself as the fail-closed 404 that never serves a route at a version
the request did not name and never advertises which versions would
have answered.

The diagnostic twin stays the twin: `RouteMatchExplainer` runs the
same miss fold over the same candidates, and its new
`versionMissMethodRefusal` explanation carries the 405's outcome and
Allow payload under the *version* stage's own name -- the narrowing
that emptied is what failed -- so the match/explain outcome
equivalence holds on the new arm (the battery gained the mirrored
provider case). The debug header's bounded summary renders the arm
for free: `stage=version survivors=path:2,host:2,version:0
allowed=GET`.

The gate rounds the fold went through (the C6 lessons replayed on
the router's own tree): the nested ternary Sonar refused and the
125-character line phpcs refused both fold out; the covers-filter
lesson (KDD C4) surfaced again -- Sonar's twelve uncovered lines
were the new collaborators no `#[CoversClass]` list named, the
attribution fixed by naming them; and the archeology difficulty
ceiling (20) refused the explainer's grown mirror (31.89 -- the
helper's six parameters carried the operand mass exactly the way
C4 said they would), the redistribution the gate forced being the
one the lesson prescribes: the mirror folds into `RouteMatchStages`
-- the shared staged product both the hot path's `match()` and the
explainer's `explain()` read -- so the diagnostic twin runs the
matcher's own spelling *literally* (by construction, not by a copy
that can drift), the pipeline becomes its thin result reader
(difficulty 2.38), and the explainer keeps only the counting and
the outcome arms (16.00, the stages fold itself at 16.43).

The battery pins the three faces the audit asked for: the pair on an
all-unversioned path (the unknown version now failing closed where it
used to match, the versionless request keeping the fallback), the
mixed/unmixed wrong-method pairs (both answering the 405 with Allow:
GET), and the fail-closed half (a method the path answers never
serving behind a version miss).

## KDD-AN1 — the unparsable header is absence, not refusal (issue #782)

The audit's availability finding: one garbage `Accept` header closed
the entire route table. `RouteAcceptRanges::parse()` skipped every
invalid range -- the docblock's own doctrine, "an untrusted header is
runtime data, not a defect" -- and the skipped-to-empty list then
reached `RouteAcceptMatch::accepts()`, whose honest zero-quality
answer read as a total refusal: `Accept: banana` answered 406 on every
route that declares `produces`, while the absent header
(`accept === null`) permits everything. The doctrine answered the
entry-level question (skip, don't throw) and never answered the
boundary question it created: what IS a header whose every entry was
skipped?

The fix names the boundary in the negotiator: a header that parses to
no range expresses nothing, so nothing is enforced -- the empty parse
result is answered exactly like the absent header, and the blank-header
short-circuit folds into the same seam, one judgment for "no
preference expressed" wherever it comes from (absent, whitespace,
separators, all-garbage). The boundary is the grammar, not the mood:
one valid range decides the whole header (`banana, text/html` against
an `application/json` route still answers the 406, and a well-formed
all-media zero-quality refusal still closes the table), so the new
leniency cannot be reached by a header that said anything at all --
only by a header that said nothing.

The battery pins the boundary from both sides: the audit's own repro
(`banana` answers found), the separator-only and whitespace-only
spellings (previously the trim seam, now the same empty-parse seam),
the type-wildcard-with-concrete-subtype alone (previously a 406 the
audit named, now expresses-nothing), the multibyte quality value
alone (the skip's own end state) and beside a valid zero-quality
exclusion (the skip pinned with teeth: had the multibyte quality
parsed, the range's specificity would have overridden the exclusion),
the mixed garbage-plus-valid header (the valid range still decides),
and the well-formed total refusal (unchanged).

Verification: the static lanes all pass locally (lint, PHPStan with
the ratchet, Psalm level 1, Deptrac, the fixer and phpcs, the
duplication and mess gates, the roadmap gate) with the full suite
green (7680 tests, 18905 assertions); the coverage and mutation
verdicts are the CI lanes' to answer on this checkout (no pcov driver
in the sandbox's portable engine), the changed-set mutants the new
guard would wake -- the empty-list comparison, the removed trim arm,
the null and no-produces arms -- each facing the pin that kills it.

## KDD-AN2 — the round's own gate answer: the accumulator, not the fourth return

The CI round answered before the mutation lanes did: SonarCloud's php:S1142
refused the negotiator's grown four returns (the fast exit, the new
empty-parse boundary, the loop's first-match early return, the final
refusal), and the house's own recorded idiom for exactly this refusal --
"<=3 return per method, combined via a result variable" (the radix-engine
lane's S1142 round) -- names the fix. The boundary folds into the verdict
the loop already accumulates: the empty parse SEEDS the verdict permitted
(a header that expressed nothing permits everything), each declared media
type joins with `||` over its `RouteAcceptMatch` answer, and the single
tail return reads the accumulated verdict alone -- the shape
`RouteAcceptMatch` itself already wears (the quality verdict tracked and
returned once).

The refold buys back more than the return count: the produces validation
runs for every present header again (the fourth-return spelling skipped it
whenever the parse came back empty, so a garbage header silenced the
route-field defense), and the produces side gains the twin pin the ranges
side already had -- a later DECLARED media type still negotiating after a
non-matching one, the battery that kills the `||`-to-`&&` mutant the
accumulator woke.

The mutation surface the refold adds: the seed comparison (killed by the
`banana` repro both directions -- a flipped seed sends the garbage header
back to the 406 lane), the seed operand removal (same kill), the `||`
operand (the later-declaration twin above), and the accumulated return
itself (every 406 pin the family already carries).
