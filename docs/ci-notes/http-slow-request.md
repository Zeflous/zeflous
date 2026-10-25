# HTTP Slow-Request Detection — KDD

**Scope:** the `observability.slow_request` family — ROADMAP.md "HTTP
Layer" Phase 4.1 (Request Lifecycle Tracing)'s closing item: the
automatic record a request whose total duration exceeds the configured
bar answers. The phase's other two items landed with the observability
campaign (the unique request id with the correlation family, the timing
metrics with the timing family); this slice closes the phase's promise.
Six source classes over the strict-reading lineage, one middleware
family, `feat/http-slow-request`.

**Verified locally before the PR:** `ci:static` EXIT-0 (5,283 tests /
12,932 assertions; smoke 20,000 at 2,760 bytes; concurrency 23
workflows; audit 0 advisories) · psalm 0 errors · progpilot `[]` ·
scoped infection over the family's six source files, 71 mutants,
MSI 100/100, 0 escapes · archeology 0 error-level findings (CC
1..5, MI 0 per unit) · bench subject green (the measured-under-the-
bar round trip, ~0.72µs per request).

## The family

| Unit | Question it owns |
|---|---|
| `SlowRequestKnobs` | the knob census (`observability.slow_request`: `enabled`, `threshold_ms`) and the unknown-key rejection |
| `SlowRequestPolicy` | the strict reading into one immutable value object (`TimingPolicy`'s lineage) |
| `SlowRequestBounds` | the threshold's corridor (1..3600000 ms), asserted at the constructor (the `SessionBounds` pattern) |
| `SlowRequestRecord` | the record fold: the bounded privacy-by-default context and the write through the containment seam |
| `SlowRequestMiddleware` | the PSR-15 boundary (the twentieth face): the measured span, the verdict, the finally both outcomes share |
| `SlowRequestFactory` | the config seam the `observability.slow_request` service registration names, over the error lane's own log address |

## KDD-SR1 — the record fires on the throw path too, and the timing family's doctrine does not transfer

The timing family lets a handler's throw propagate untouched, on the
recorded reasoning that "the span belongs to a response, and there is
none to stamp". That reasoning is about the *header* — a response-side
disclosure with nowhere to land. The slow-request record is a
server-side log line, not a response header: there is a place for the
span to land even when the pipeline never answered, and the throw path
is the pathology the family most exists for (the hung dependency that
ends in a 500 after thirty seconds). The middleware therefore holds
the verdict in ONE `finally` seam both outcomes share: the request
that answered slowly records, the request that threw slowly records
and then propagates the throw untouched. The finally is also the
mutation run's own observable: unwrapping it kills the throw-path
record, so the test battery pins the slow-throw case explicitly.

## KDD-SR2 — the verdict is strictly-exceeds, held in integer nanoseconds

The roadmap spells the promise as "melebihi" — exceeds. A span that
exactly MEETS the bar is not slow, and the comparison is held in
integer nanoseconds (`elapsedNs > thresholdMs * 1_000_000`) rather
than post-division floats, so the boundary is exact rather than a
float's neighbour: the test battery pins the span that exactly meets
the bar and the span one nanosecond over it. The float appears only
at the rendering fold (`elapsed_ms`, two decimals — the timing
family's own `%.2fms` precision, the number a log consumer graphs).

A broken clock seam whose second reading lands before its first
answers a negative span, which no positive bar can ever exceed: a
broken measurement can fabricate nothing, the same clamp-by-geometry
the timing family's `ResponseTime` documents.

## KDD-SR3 — the record's sink is the error lane's address, not a knob of the family's own

The family deliberately owns no `log_path` knob: the record travels
to `http.error_handling.log_path` — the deployment's ONE structured
log address — read here the same way `ProcessErrorBoundaryFactory`
already reads it (the cross-lane read is the established doctrine,
guard and message included). Two knobs for one sink would be a
fragmentation an operator must then reconcile; one address means the
slow-request records and the error records land in the one sink and
JOIN on the request identifier. An unconfigured address leaves the
logger null and the record's own writer (`ErrorFaultLogWriter`)
carries the SAPI fallback — the containment the error lane already
owns, reused rather than duplicated: a logging failure can never
convert a slow-but-successful request into a failure, which matters
double here, since the verdict runs in a `finally` on the response
path.

## KDD-SR4 — the privacy posture is inherited, not renegotiated

The record's request facts are exactly the error lane's own
(`DiagnosticContextFactory`, `docs/error-handling.md`,
privacy-by-default): method, path (capped at the same 2048), and the
correlation identifier — no query strings, no IP addresses, no
user-agent strings, no request bodies. The identifier rides
`CorrelationId::for()`: a request that claimed an `X-Request-ID`
records under the claim the response echoes, so the record joins the
error lane's records on the same identifier; a request that claimed
none records under a fresh one, exactly as the error lane's own
records do. A trace id is deliberately absent for the same reason
the diagnostic lane's records omit it: the record correlates through
the request id alone. The two duration facts (`elapsed_ms`,
`threshold_ms`) are the family's own — the threshold fact exists so
an operator reading records against a bar they did not mean sees the
number to correct.

## KDD-SR5 — the corridor cannot read intent, and does not pretend to

The threshold's corridor (1..3600000 ms) refuses the nonsense
spellings: the zero-millisecond bar that would record literally every
request (a metrics family's job, not a slow-request log's), the
negative bar, the hour-deep bar and beyond. A legal-but-mistyped
unit value stays legal — a threshold spelled `2` where two seconds
was meant judges every request slow at two milliseconds — because no
corridor can read an operator's intent, and the honest answer to a
flood of records against a wrong bar is the record's own
`threshold_ms` fact naming the bar in force. The corridor holds at
BOTH construction paths (the map reading and the programmatic
spelling, the `SessionBounds` pattern), so a host hand-spelling an
out-of-corridor value gets the same named halt an operator's typo
gets.

## KDD-SR6 — the arm defaults ON, and the disclosure question does not arise

The family ships armed, following the timing family's doctrine: a
deployment that composes the middleware into its stack is asking to
be told about pathology, and the record is server-side — there is no
attacker-visible side channel, so the ZEF-DX-08 disclosure posture
that keeps the timing header OFF does not apply here. The inert
posture still holds its own geometry: a disarmed family never reads
the clock (the timing family's "no observable difference between
measured-and-discarded and never-measured" discipline), pinned by
the read-count assertion. The default bar is 1000ms — one full
second, the weight where a request stops being an answer and starts
being a question.

## KDD-SR7 — the observability map's seventh family, the census's own landing order

The family joins `ObservabilityFamilies` after the router's own
observability slice (`slow_request` seventh, in landing order), and
the census's spelled-out known-families list grows with it — the
nine existing tests that pin the census message were updated to
carry the new spelling, the same one-line follow-up every census
widening pays. The `http` map's own census (`HttpFamilies`) is
untouched: the family is an observability family, not an HTTP one,
exactly as the phase's other two items (correlation, timing) are.

## KDD-SR8 — the record's level is the family's own vocabulary decision, not a knob

A slow request records at `warning` — an operational pathology, not
a failure (the error lane owns failures). The level is a constant,
not a knob: a knob would let a deployment spell the same fact at
`error` or `notice`, fragmenting the one vocabulary every consumer
of the record filters on. The message is the family's own
`'Slow request'` spelling, following `ErrorFaultLogWriter`'s own
message-lane doctrine ("a stored record's origin is readable without
its context") — the writer already carries the exception lane's
`'Request failed'` and the diagnostic lane's `'Native diagnostic'`
spellings, and this family names its own the same way.
