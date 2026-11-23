# The Error Family's Route Dimension — KDD

**Scope:** the roadmap-vs-code audit's R2 second slice (#905's narrowed
open list): the metrics `route` dimension the Error Handling bullet carried
as remaining open -- "the throw site that feeds it is live since #533"
(the audit's own verification), what was missing is the dimension itself.
`zef.http.errors.total` now carries `http.route` as its fourth dimension,
read off the error boundary's own request context, branch
`feat/error-route-dimension`.

**Verified locally before the PR:** the authoring sandbox carries no PHP
runtime -- the full verdict (phpunit battery, php-cs-fixer, phpstan,
psalm, deptrac, the scoped infection round) rides CI on the merge group,
watched to green, the same honest posture the uploads, temp and
query-feeder lanes' PRs carried (#908, #911, #914).

## The map

| Unit | Question it owns |
|---|---|
| `ErrorRequestContext::setRoute()` / `::route()` | the matched pattern's carrier -- written once by the router, read by the boundary, the trace-id adoption's own seam |
| `RouterRequestHandler::handle()` | the write itself -- the found match's pattern onto the boundary's minted context, best-effort decoration never a dispatch dependency |
| `ErrorFaultMetricsRecorder::routeDimension()` | the dimension's value -- the carried pattern, or the closed `unrouted` spelling every no-route refusal answers |
| `MeterDimensions::routeDimension()` | the dimension's grammar -- the errors total's second named extra, bounded and sanitised the exception type's own way |

## KDD-RD1 — the pattern rides the carrier the trace id already owns

The dimension's hard question is not the metric fold -- it is how a value
the ROUTER knows reaches a recorder the BOUNDARY owns. A plain request
attribute cannot flow upward (the boundary's request is the one it
decorated at entry; everything a deeper layer adds dies below it), and a
holder of the active request is the recorded doctrine's own refusal
(`ErrorRequestContext`: "never stored on a persistent worker service" --
the same line the query-feeder slice just re-affirmed). The
`ErrorRequestContext` IS the inner-to-outer carrier: a mutable
request-scoped object the boundary mints, the deeper layers the request
reaches writing through it by reference -- `setTraceId()`'s exact
discipline, now carrying the matched pattern. One new seam opened (a
setter beside the trace id's), none invented.

## KDD-RD2 — the closed spelling, never the raw target

The roadmap's own example spelled the dimension
`route="/api/users"` -- but a 404's truth is that no route matched, and
the raw request target (`/api/users/92`, a path carrying captured values)
must never become a label: it is a series-per-path cardinality bomb AND a
privacy leak (the log context's own exclusions spell the doctrine). The
unrouted arms therefore answer one closed word -- `unrouted` -- the
lifecycle family's `other`-fold precedent: the series is sliced by the
deployment's own route vocabulary, and "no route" is itself that
vocabulary's honest word. The 405 and 406 arms carry no route on their
match result either (the path bucket's methods and the producible types
are all they hold), so they answer the same closed word -- the pattern is
written only by a FOUND match, at the match the router handler already
computed.

## KDD-RD3 — the dimension is the errors total's own named extra

The meter's dimension policy is name-keyed data: the `zef.http.*` prefix
keeps its two low-cardinality slices (method, status) for every series,
while `zef.http.errors.total` alone carries its two named extras -- the
exception type (the attribute-by) and now the route (the slice-by). The
fold mirrors `exceptionDimension()` character-for-character: kept only
when string, bounded at 128 with the sanitiser's own ellipsis cut, never
widening the prefix's allow-list (a custom `zef.http.requests.total`
family still drops the route). The OTLP key and the Prometheus renderer
stay dimension-agnostic -- the composite key and the label set are the
store's own machinery, the route just another entry.

## KDD-RD4 — what this slice deliberately does not carry

No route dimension on the router's own series (`zef.router.*` already
slices by method and the closed match-outcome vocabulary -- a pattern
dimension there would double every miss's label set for no dashboard
that reads it); no route on the log context (the record's method and
path are the privacy posture the lane already pinned, the pattern adds
no diagnostic the path does not carry); no write on the refusal arms
(the closed word IS the honest answer, and the match result's own
docblock refuses to fabricate a route the bucket never held); and no
knob -- the dimension rides the meter the `observability.metrics`
family already arms, the series' own shape, never a configuration a
deployment would owe.
