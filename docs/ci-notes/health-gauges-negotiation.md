# The Health-as-Gauges Consumer — KDD

**Scope:** the HTTP Layer row's own named open slice -- the
health-as-gauges `render` consumer (`HealthExposition::render()` the
API surface awaiting one since #386 landed it, the reference's own
surface): the health surface's three endpoints now answer the
Prometheus exposition spelling when the request's own Accept line
explicitly prefers the text format over the JSON, branch
`feat/health-gauges-negotiation`.

**Verified locally before the PR:** the scoped batteries (the
negotiator's own matrix, the metrics middleware's endpoint pins, the
health aggregator's render pins -- 66 tests, 157 assertions green),
the format check and PHPStan max over the touched files answered
green in the authoring sandbox; the archeology, coverage, scoped
infection, bench, psalm and deptrac halves ride CI on the merge
group, watched to green -- the honest partial posture #924 carried
(the archeology engine does not complete a scoped run in this
sandbox; its verdict rides CI).

## The map

| Unit | Question it owns |
|---|---|
| `HealthContentNegotiator::prefersExposition()` | the representation judgement -- which Accept spellings arm the gauges, which answer the JSON default |
| `MetricsMiddleware::healthResponse()` | the consumer itself -- the negotiated render under the status code both representations carry |
| `HealthExposition::render()` | the landed API surface (since #386) -- the gauges the judgement now answers, unchanged |

## KDD-HG1 — the consumer is a representation negotiation, not a second endpoint

The reference landed `HealthAggregator::toPrometheus()` unconsumed
(the v2.31.0 docblock's own "a Prometheus exposition format" -- its
archive's `MetricsHandler` renders the meter alone, its
`AggregateHealthHandler` the JSON alone; the surface waited for a
consumer there too), and #386 ported that posture faithfully: the
render arrived as an API surface awaiting its consumer. This slice is
the tree's own composition, the doctrine the replay-redis lane spelled
("a composition over the seam rather than a fork"): the health
surface the family already serves answers the gauges when the
request's own Accept line prefers the text format -- no new endpoint,
no new knob, no routing change, the same three paths the
`HEALTH_VIEWS` table already spells. A Prometheus instance scraping
the health surface (its own Accept line carries the text spelling at
a quality the universal wildcard does not reach) reads the gauges
over the same endpoint the load balancer polls.

## KDD-HG2 — the JSON is the default, and the tie answers the default

The health surface's contract is the load balancer's poll, and the
representation a poll reads is the JSON the surface has always
answered: an absent header, a wildcard-only header, a garbage header,
a browser's HTML line, an explicit JSON line and a tie all answer the
JSON. The gauges arm only when the text side's quality STRICTLY
outranks the JSON side's (the error boundary's own
`ErrorContentNegotiator` greater-than lineage, ties included), so no
consumer ever meets a representation it did not ask for -- the
fail-closed direction is toward the representation every consumer
already reads.

## KDD-HG3 — the status code, the no-store and the one probe pass ride both representations

A gauge reading is a poll too: the 200-ok/503-degraded the traffic
router gates on answers the negotiated exposition verbatim, so a
collector reading `zef_health_status 0` on a 503 carries the verdict
twice (its own `up` check and the gauge line), and the `no-store`
stays because a gauge reading is a live reading exactly as the JSON
is. The probes aggregate ONCE per request -- the gauges render the
same bounded report the JSON renders, the second representation
riding the poll the surface already runs, never a second probe pass
(the views keep their membership: a negotiated live answer carries
the live declaration alone, pinned).

## KDD-HG4 — the vocabulary is the surface's own, the parser the shared seam

The judgement reads the media types the surface actually answers: the
exposition side `text/plain` (the exposition's own content type) and
its `text/*` wildcard; the JSON side `application/json` (the health
answer's own content type) and its `application/*` wildcard; the
universal wildcard the shared floor both sides fold to -- which is
exactly why a lone universal wildcard is a tie and answers the
default. The most specific spelling decides a side's quality (RFC
7231's own specificity: `text/plain;q=0.3` under `text/*;q=0.9` stays
0.3, the client's voice for exactly this representation), the
coalescing order specific type, family wildcard, universal floor --
the compression negotiator's granted-quality idiom, `qualityFor`'s
fallback walk replayed as coalescing. `AcceptHeaderParser` is the
shared bounded seam the error boundary's negotiator already rides
(the 8192-byte bound, the lowercase fold, the malformed range
skipped): this judgement adds no parsing of its own, and a header too
broken to parse carries no preference at all.

## KDD-HG5 — the scrape endpoint never negotiates, and the rejected alternative

The judgement belongs to the health surface alone: the `/metrics`
scrape answers the meter's own exposition whatever the request's
Accept line prefers (pinned), so the meter's empty-snapshot honesty
("no HELP or TYPE line a series did not earn") stays untouched by the
health gauges. The alternative design -- appending the health gauges
to the `/metrics` exposition -- is recorded as rejected: it would
couple the scrape cadence to the probe pass (every scrape running
every probe, the meter's read surface becoming a health poll), and it
would break the exposition's own honesty doctrine (the gauges'
HELP/TYPE lines a series never earned). No `Vary` header either: both
representations answer `Cache-Control: no-store`, the answer that
already refuses every cache -- a `Vary` beside it would guard a cache
the surface itself just refused, where the compression family adds
`Vary` because its answers are cacheable and this surface's are not.
