# Router Observability

**Scope:** the Router roadmap's Observability & Monitoring slice —
`ROADMAP.md` §3.2 ("Metrics Integration … untuk Prometheus", "Tracing
Support … OpenTelemetry", "Debug Mode … alasan kegagalan pencocokan
rute secara detail") — the lane audit carried as "Metrics, tracing,
detailed debug mode: absent from the audited Router surface" after
the deprecation lifecycle (#540). Decision record for
`src/Zef/Framework/Config/Routing/RouteMatchExplainer.php`,
`RouteMatchNeighborhood.php`, `RouteMatchExplanation.php`,
`src/Zef/Framework/Middleware/RouteMatchTelemetry.php`,
`RouteDebugMiddleware.php`, `RouteObservabilityFactory.php`, and
`src/Zef/Framework/Http/RouteObservabilityPolicy.php`.

## The posture

The lane's remaining order after #540 was *observability → CLI
tooling → ecosystem export*; this slice is the observability step.
The engines already existed — the observability campaign landed the
tracing core (the tracer, the span lifecycle, the OTLP export lane),
the meter (the bounded counter/observation store with the Prometheus
exposition), the correlation boundary (the request span under the
inbound W3C parent) and the metrics family (the `/metrics` endpoint
the exposition answers from). The router's job was to **join** them
without growing either engine of its own: the same one-way
composition every family before it carried — the engine never knows
the router exists, and the router's recording is best-effort by
doctrine so a failing engine can never replace the match's own
answer.

Three capabilities, three seams:

- **Metrics** — the router's own two series on the shared meter:
  `zef.router.matches.total` (every observed match, by method and
  the closed outcome vocabulary) and
  `zef.router.match.duration_seconds` (the route search time, the
  roadmap's own metric, measured on the telemetry clock's monotonic
  domain — the same seconds-domain naming the container lane's
  resolve duration established).
- **Tracing** — the match span `zef.router.match`, opened under the
  request span the correlation boundary exposed on the request (a
  fresh trace when no boundary carried one, the honest answer to
  both), carrying the request's routing surface, the outcome and the
  route's own name, ended exactly once whether the match answered or
  threw.
- **Debug mode** — the route match explanation stamped on
  routing-refusal responses in development: which stage of the
  pipeline refused, what each stage kept, and the "what you probably
  meant" neighborhood of the request's own text.

## KDD-OB1 — the explanation is the matcher's own pipeline, never a second opinion

`RouteMatchExplainer` runs the **same folds** `RadixTree::match()`
runs — the `RouteMatcher` walk, `RouteHostSelection`,
`RouteVersionSelection`, `RouteMethodAnswer` — in the same order,
over the same inputs. What it adds is the counting between the folds
(how many candidates each stage kept) and, when a stage emptied the
set, the stage's own name and the payload its refusal carries. The
suite pins the equivalence contract outright: `explain()`'s outcome
is `match()`'s own for the same inputs, for every outcome the
pipeline answers. A second, explanation-shaped matcher would drift
from the first the day one of them learned a new fold — the
diagnostic twin refuses to be that.

The neighbourhood (`KDD-OB2`) is the one deliberate departure: it is
a **static-only descent** — a node's prefix consumed character for
character (`PrefixConsumption`, the walk's own arithmetic), then the
static child whose run continues the path — with no parameter edges
taken and no backtracking performed. Its question is not "which
route matches" (the walk answered that: none) but "how far did the
request's own text track the table, and what lives past that point".

## KDD-OB2 — the neighborhood: shared heads and patterns-not-edges

A run the request path only **partially** continues still counts its
shared head (`SharedPrefixLength`, the tree builder's own measure)
and still names that run's subtree as the neighborhood: the
developer who typed `/users` against a `/users/{id}`-only table
learns *both* facts they can act on — how far the text tracked
(`/users`) and what lives past it (`/users/{id}`).

A parameter edge is part of what lives past the point, so the
subtree the neighborhood reports **includes** parameter edges — as
candidate patterns, never as descent steps: the descent never
guesses what a `{parameter}` would have captured, because a guess is
the second opinion this unit refuses to give. The candidates are
collected breadth-first (the shallowest — closest — spellings first)
and capped at eight: a neighborhood is a hint, not an inventory, and
a hint that grows with the table would grow the header it renders
into. Host claims ride the spelling (`/b @api.example.com`) — the
two facts a developer reads first.

The mutation adversary settled two structures here by demanding
equivalence-proof refolds: the descent's path-exhausted early return
was **redundant** with the child lookup past the path's end (the
child lookup at the path's own end answers none — the branch's
mutants were unkillable by construction, so the branch left), and
the collection's cap now sits at the **first route that would exceed
it** — the walk ends there, one bound in one place, exactly the
budget and never a node more.

## KDD-OB3 — the bounded development line

`RouteMatchExplanation::summary()` renders the line destined for the
`X-Route-Match-Debug` response header, and a header is a bounded,
line-oriented channel: every free-form rendering is clamped to its
own 128-**character** budget (multi-byte text clamped by characters,
never byte-sliced — pinned by test at the boundary itself: a
rendering of exactly the budget is carried whole) and stripped of
the control bytes a request path could carry — the dev surface must
not become the reflection surface. The closed vocabularies (the
stage names, the outcome names) render as-is; the counts are
integers by construction.

## KDD-OB4 — the status fold, the request span's own doctrine replayed

The match span's lifecycle status folds the outcome the way the
request span folds the answer (`CorrelationRequestSpan`: a 5xx
answer folds to ERROR, everything else to OK): a **found** match is
the ok one; the two **refusals the table itself gave** — method not
allowed, not acceptable — are the error one, because a trace should
surface a routing-layer refusal; a **not-found** is left unset — the
void answer, neither success nor failure. A throwing match (the
engine's contract says it cannot, but the observation is armoured
anyway) records the failure exactly the way the request span does —
the exception event, the error status with the failure's own type —
and still propagates it.

The duration the meter observes is measured **once**, on the
telemetry clock's monotonic domain, around the match call itself —
the same window the span's own timeline measures. The span's
timeline carries the tracing record; the explicit clock pair feeds
the histogram — one clock per consumer, no arithmetic shared between
the two engines.

## KDD-OB5 — the series' dimension policy

The two series are framework-owned (`zef.router.*`), so their
dimension allow-list joins the policy `MeterDimensions` carries:
exactly the request method and the **closed** match-outcome
vocabulary (found / not_found / method_not_allowed /
not_acceptable) — the two low-cardinality slices the series exist to
be read by, mirroring the `zef.http.*` request-counting series' own
two. A `router.route.name` dimension was considered and refused:
route names come from the host's table, unbounded by any grammar the
framework owns — the container lane's `zef.service.id` dimension is
bounded by its own 96-character grammar and folds to `[other]`, and
no such grammar exists for route names. Per-route diagnosis is the
debug mode's job (KDD-OB7), not a histogram dimension's.

## KDD-OB6 — the family ships knobs and a factory, deliberately no registration

`observability.router` joins the observability map as its **sixth
family** (`RouteObservabilityPolicy` over `RouteObservabilityKnobs`,
the strict-reading lineage, both arms shipping OFF — the correlation
family's own double fail-closed posture replayed: an armed family
over disarmed engines is work nobody can observe, and the debug
header is a disclosure a production deployment never asked to
publish). The framework profile mirrors the declaration; the suite
pins the two together.

The family ships **no service registration** — a deliberate
decision, not an omission: its collaborators compose at seams the
container does not yet name. The router facade is not a container
service, so the host — not a declarative entry — is the one who
holds the Router, builds the `RouterRequestHandler` over it, and
decides the stack's membership. `RouteObservabilityFactory` is the
composition seam instead: `telemetry()` answers the unit the
handler's optional construction argument carries (null when the arm
is off — the honest void, no allocation, no accounting), and
`debugMiddleware()` answers the disclosure the stack composes. The
registration arrives with the router's own future service wiring
(the CLI-tooling slice's neighbour), and the profile's docblock
records the decision.

## KDD-OB7 — the disclosure is double-gated and precisely attributed

`RouteDebugMiddleware` stamps the explanation on a routing-refusal
response (404/405/406) under a **double fail-closed gate**: the
family's `debug_match` knob must be armed (the factory answers the
middleware only when it is — a host cannot compose it by accident)
AND the environment verdict must be a development one
(`AppEnvironment::isDevelopment()`, read per request: an unset or
unrecognized `APP_ENV` answers not-development, so no spelling can
accidentally arm the header in production).

The attribution is **precise, never speculative**: the middleware
explains the request's own match and stamps nothing when the route
table matched — a 404 that came from a handler's own typed refusal
(or any other 404 the application answered) is not the router's
miss, and a speculative header would have claimed it was. The
request matcher's HEAD-over-GET fallback is **replayed on the
explanation itself** — the same rule, the same folds, so a HEAD
request whose only answer is the GET one is explained as the GET
match the dispatcher would have executed (and a HEAD request the
table has no path for is the path miss it is). The disclosure never
overwrites a header a lane before it already stamped — the last
writer wins everywhere else in PSR-15; the debug disclosure refuses
to be the second one. It composes *outside* the error boundary in
the global stack: it observes the boundary's rendered answer, and it
stamps a header on it — it never renders a second error shape (the
rule-17 posture: the boundary owns the refusal's rendering).

## KDD-OB8 — the architecture gate answered with the lane's own doctrine

The PhpCodeArcheology gate answered the slice's first cut with five
difficulty findings (the ceiling is 20): the neighborhood at 40.26,
the explanation at 23.25, and three files the slice had pushed over
baselines that already sat at 17–20 (the tree, the handler, the
telemetry). The settle is the fold split — every finding became a
single-question class under the ceiling: `RouteMatchDescent` and
`RouteSubtreePatterns` out of the neighborhood (the orchestrator
stays), `RouteMatchSummary` out of the explanation (which gains the
`outcomeOf` fold both engines read, so the outcome vocabulary has
one owner), `RouteTableCompiler` out of `RadixTree::compile()` —
answering the tree's *parts* rather than the tree, because the
first cut's compiler-to-tree construction grew a dependency cycle
the gate caught — `RouteMatchSpan` (the match span's lifecycle, the
`CorrelationRequestSpan` decomposition replayed) out of the
telemetry, and `RouteMiddlewareStack` (the fail-closed route
middleware resolution) out of the handler.

The settle taught a Halstead lesson recorded for the next lane: the
difficulty metric counts **distinct operands**, so splitting a class
into helpers that re-declare the same variables *raises* it — the
subtree collection first split into three methods and went from
23.43 to 30.29, then settled at 18.00 as ONE queue-draining walk
with a single cap bound (the for-index machinery was the expensive
part, not the loop body). Fewer names, one bound, one walk.

## Verification

- `composer ci:static` EXIT-0 — phpunit 4766 tests / 10608
  assertions (the suite grew by the slice's own 117), the
  persistent-worker smoke at 20000 iterations (2760 bytes growth),
  workflow concurrency 23, the roadmap document gate, the strict
  supply-chain audit.
- `composer coverage:gate` — **98.66%** line coverage (main carried
  98.62%): the slice's code fully covered, the tree's coverage
  unharmed.
- The architecture gate — **0 error-level findings**, every touched
  class under the difficulty ceiling (the folds at 3–19).
- Scoped Infection over the slice's fourteen new files and the
  three modified files: **307 mutants, MSI 100 / covered MSI 100,
  zero escapes, zero ignores** — the twenty first-round escapes
  were answered by the boundary tests (multi-byte tracked prefixes
  and clamps counted in characters, the budget inclusive at exactly
  its bound, the cap landing inside one node's own collection, the
  non-HEAD method refusal that pins the fallback rule to the HEAD
  request, the injected clock measured to its frozen step, the
  outcome fold's every vocabulary, the route middleware list's
  outer-to-inner composition) and the structural refolds of KDD-OB2
  — the coverage-attribution discipline the settle also taught:
  `CoversClass` restricts a test's credit, so a fold's strong
  assertions must carry the fold's own attribution.
- `composer bench` — flat: `RouterBench` 7.2–7.4μs on the 15- and
  300-route tables alike (`match()` is untouched by construction);
  the observed match adds work only when the family is armed.
