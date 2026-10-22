# Router Deprecation and Sunset Headers

**Scope:** the Router roadmap's API-versioning completion — the
"Deprecation Middleware" item of `ROADMAP.md` §2.3 ("Menandai rute versi
lama sebagai 'deprecated' dengan header respons standar") the lane audit
carried as open after the host wildcard (#528) and the error integration
(#533). Decision record for
`src/Zef/Framework/Config/Routing/RouteDeprecation.php`,
`src/Zef/Framework/Middleware/RouteDeprecationHeaders.php`, and the
declaration carriage they sit between.

## The posture

A deprecated route answers every executed representation with the three
standard lifecycle headers:

- **`Deprecation: @1688169599`** — RFC 9745's structured-field date
  spelling (`@` + Unix time), the instant the resource was (or will be)
  deprecated;
- **`Sunset: Sun, 30 Jun 2024 00:00:00 GMT`** — RFC 8594's HTTP-date, the
  instant the resource is expected to become non-operational;
- **`Link: <https://example.com/deprecations/legacy>; rel="deprecation"`**
  — RFC 8288 link with the `deprecation` relation RFC 9745 registers,
  pointing at the deprecation's documentation.

A route declares its own lifecycle once, per route, and every response
from the route carries it — including the conditional lane's bodyless 304s
(`NotModifiedResponse` rewrites status and body only, so a cached
representation's lifecycle metadata stays current, which is where a
deprecation notice is most useful: on the responses clients keep serving
themselves).

## KDD-DS1 — declaration grammar is narrower than the headers it feeds

`RouteDeprecation::canonical()` accepts exactly two date shapes: a
calendar date (`2024-06-30`, pinned to that day's midnight UTC) or a full
ISO-8601 instant with an explicit zone designator (`Z` or `±HH:MM` — an
offset keeps its own frame, exactly like `HttpDate` honours a zone
designator). A date-time without a zone is ambiguous and refused rather
than guessed. The parse is warning-checked the way `HttpDate` parses: a
value the engine would roll over (`2024-02-30`, a second of `60`) is
rejected, because a rolled-over date is not the one the declaration named.

One engine behaviour had to be pinned explicitly: `createFromFormat()` on
a date-only grammar fills the unspecified time components from the
process's current wall clock, so the calendar form is `setTime(0, 0, 0)`
before the instant is answered — the declaration stays deterministic
whatever hour the route table compiled at.

`Sunset` must name an instant strictly after the deprecation date: a
resource that retires before (or exactly when) it is deprecated is a
retirement, not a deprecation, and the declaration line says so.

## KDD-DS2 — the link is judged by the field it must render into

The documentation link is a URI-reference (RFC 8288 allows relative
targets, so `/deprecations/legacy` is a legal declaration), but the bytes
the `Link` field's own syntax reserves — whitespace, control bytes, and
the `<`/`>` angle brackets that wrap the target — are refused on the
declaration line. A value carrying them could never render as the link it
claims to be; repairing it silently would move the route's documentation
target without the declarant's knowledge.

## KDD-DS3 — the declaration rides the schema, the cache never learns it

The deprecation is a Standard Route Schema field (`deprecation`, optional
non-empty map: `date` required, `sunset` and `link` optional), declared
twice over like every schema field: the config layer's `RouteSchema`
validates the map's shape ('values' kind — the same trust boundary `meta`
uses, because the members' RFC grammar is engine semantics), and the
engine's `RouteEntryShape` shape-checks the members and their types before
`RouteFields::fromEntry()` canonicalises them through
`RouteDeprecation`. The compiled route carries the canonicalised
timestamps (`CompiledRoute::deprecation()`), and the DSL gains the
first-class verb (`->deprecated(date:, sunset:, link:)`, declare-once,
blank members refused on the declaration line) because marking an old
route is exactly the ergonomics the roadmap item asks for.

The compiled tree cache needed zero new code: the artifact carries the raw
route table and recompiles through `CompiledRoute::fromArray()` on load,
so the declaration rides the entries and survives the round trip through
the same fail-closed net (`RouteTreeCacheTest` pins it). The cache never
learns the field exists — the same property that let `version` and
`produces` ride it before.

## KDD-DS4 — declaration is the default posture, the handler's answer wins

The header fold (`RouteDeprecationHeaders`, called from the terminal
dispatch beside the `Vary: Accept` fold) fills `Deprecation` and `Sunset`
only when the response does not already carry them, and appends the
deprecation `Link` beside whatever links the response already carries
(skipping only an identical target-with-relation). A handler that answers
its own deprecation dates — a representation-specific deprecation — keeps
them, exactly like a handler's own `Vary` tokens survive the route-level
fold. The route declaration is the default posture, not an override of
the handler's judgement; refusing the combination outright would forbid
the one legitimate case (per-representation lifecycles) to save a
conflict nobody observed.

`Link` appends because RFC 8288 link fields are list-valued by
construction; replacing would erase the handler's own `rel="self"` links.
The identical-value check is case-insensitive, matching the way link
relations and schemes are compared in practice.

## KDD-DS5 — refusals do not carry the billboard

The deprecation headers ride the executed representation only. A refusal
from a deprecated route (405 on a deprecated path, 406 on a deprecated
negotiation) renders through the typed family and the error boundary's
own RFC 9457 posture — the boundary is not the route's lifecycle
billboard, and pinning deprecation metadata onto problem documents would
invent a second error shape the error system's consumers would have to
learn. The error-system integration duty (CONTRIBUTING.md rule 17) is
discharged where it applies: the declaration's failure paths are typed
`ConfigException`s raised on the declaration/compile line, and the
dispatch decoration introduces no new HTTP failure path. The
configuration-system integration is discharged by construction: the
declaration is a schema field flowing through the same
DSL-to-config-to-compile carriage every other route field uses.

## KDD-DS6 — no automatic 410, no sunset policy

RFC 8594 permits a server to return `410 Gone` after the sunset date, and
some deprecation policies demand minimum notice windows. The router does
neither: the headers are the contract, and turning a declaration into
request-time policy (retiring routes behind the deployer's back, or
refusing tables whose notice window is "too short") would make the route
table's meaning depend on the clock. What happens after sunset stays the
deployer's decision — the router's job is to let clients see the
lifecycle, not to enforce it.

## Verification map

- `RouteDeprecationTest` — the grammar matrix (both date shapes, offset
  frames, rolled-over refusals, sunset ordering, the three unrenderable
  link forms).
- `CompiledRouteTest` — the shape side (member vocabulary, `date`
  presence, member types) and the canonicalised accessor.
- `RouteDslTest` — declare-once, blank-member refusals, the frozen
  schema emission (only declared members ride).
- `RouteSchemaTest` — the config layer's map judgement.
- `RouteTreeCacheTest` — the round trip (KDD-DS3).
- `RouterRequestHandlerTest` — the dispatch posture end to end: the three
  headers, the date-only shape, the handler-wins fills, the Link append
  and de-duplication, the 304 carriage, and the refusal without the
  billboard (KDD-DS5).
