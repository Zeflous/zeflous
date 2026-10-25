# Event Dispatcher — Core Slice Decision Record

KDD-ED1..ED12 for `src/Zef/Framework/Event/` — the Event Source
lane's first slice: the core synchronous dispatcher the roadmap's
"1.A Core Event Dispatcher" demands (listener prioritization, sync
execution, payload validation). This record spells why the slice
is shaped the way it is, and what it deliberately leaves named.

## ED1 — The dispatcher is define-before-anything, fail-closed

Both `subscribe()` and `dispatch()` refuse an undefined event name,
and `define()` refuses a duplicate. The roadmap's payload-validation
demand is really a typo-prevention demand: a listener subscribed to
`user.cretaed` that never fires, and a dispatch to an event nobody
defined that silently delivers nothing, are the two classic event
bugs. Both are refused at the call site with the name spelled.

## ED2 — Payload validation lives at the dispatch boundary

`EventShape::validate()` runs before the first listener is invoked.
A mis-shaped payload — an unknown field, a missing demanded field,
a field answering the wrong kind — is refused with the field, the
demanded kind and `get_debug_type()`'s found kind named, and no
listener observes the event. The alternative (validate inside each
listener) is exactly the bug the roadmap says it wants to prevent:
the producer's mistake surfacing far away from the producer.

## ED3 — The shape vocabulary is five kinds plus nullability

`string`, `int`, `float`, `bool`, `list`, optionally `?`-prefixed.
The list kind is the compound escape hatch (its items stay whatever
the listener narrows them to) — a framework event is a notification,
not a serializer. Richer shapes belong to a domain's own event
class when a producer outgrows the map form; that surface is the
event-sourcing follow-up, not this slice.

## ED4 — Empty shape refused

An empty shape would validate every payload and catch nothing — the
same dead-contract posture the framework refuses elsewhere. An
event that carries no data still names one field (a sentinel or the
time it happened), or it is not an event this vocabulary recognises.

## ED5 — Priorities are unbounded integers, higher first

The roadmap's "logging before error handling" doctrine is a policy
the application spells. The framework does not guess a bound or
reserve magic numbers. Equal priorities keep registration order:
the sort is stable and a removal never re-shuffles the survivors —
two listeners that never asked to be ordered against each other
observe the only order they could have reasoned about.

## ED6 — A boolean priority is refused

PHP would silently coerce `true` to standing `1`. The priority
constructor refuses non-integers with `get_debug_type()` named —
the boolean is the one value that most often arrives by accident
(a flag argument transposed into a priority argument).

## ED7 — Duplicate subscription refused; removal is honest

The registry refuses a second standing for the same listener object
(double delivery is near-always a plugin's re-registration bug; the
fix is unsubscribe-then-resubscribe). `remove()` answers a boolean
instead of throwing: an unsubscribe for a listener nobody registered
is the caller's own stale book, a state the caller can act on, not a
contract violation.

## ED8 — Synchronous by declaration; exceptions bubble

A listener's exception reaches the dispatch caller and the later
listeners of that dispatch do not run. The retry / exponential
backoff / dead-letter-queue / poison-pill regime the roadmap's
enterprise phase 2 demands is a **named follow-up** of the async
slice — hiding a retry policy inside the synchronous fold would be
a silent behavior change for every caller. The async slice will own
that regime; this slice owns the doctrine it will wrap.

## ED9 — `EventListener::handle()` returns void

Two listeners answering the same event with two different answers
is not a value a dispatcher could pick between. The dispatch
outcome counts deliveries (`DispatchOutcome`: name + delivered-to
count) — the synchronous dispatch's whole observable result, and
the seam a later observability fold will timestamp.

## ED10 — Name grammar is narrow on purpose

`^[A-Za-z][A-Za-z0-9._-]{0,63}$`: letter-first, then letters, digits,
dots, dashes, underscores, at most 64 characters. Every name stays
printable, byte-comparable and free of the signature characters
(`*`, `>`, spaces) that a future wildcard matcher (enterprise phase
1's `User.*`) must own unambiguously — the grammar reserves them
now so the wildcard slice inherits a vocabulary with no collisions.

## ED11 — The layer depends on nothing (Storage posture)

`deptrac.yaml` carries the Event layer with an empty ruleset, the
same innermost posture as Storage: construction-time
`InvalidArgumentException` refusals only (preseded by the two live
validators the storage slice cited), no ZEF-layer dependencies, no
config knob, no container wiring. Every future consumer — the async
queue, the module system's `events` knob, the telemetry wiring, the
CQRS buses — reaches it one-way. The slice ships no runtime wiring
on purpose: a knob without a reader is a dead key (the #555
doctrine), and the module-integration lane's `events` config knob
lands with its reader.

## ED11a — The EventBook fold (the difficulty ceiling's own demand)

The dispatcher first shipped holding the definition maps itself
(shapes + registries, three refusal arms) and the code-archaeology
gate refused it at difficulty 20.26 against the ceiling of 20 — the
same fold-forcing the storage root's document-root split spelled.
The entry-keeping concern moved out into `EventBook` (one entry per
defined event: the shape and the listener registry, with the
duplicate-write refusal and both undefined-name arms — the dispatch
arm and the subscribe/unsubscribe arm), leaving the dispatcher a
thin four-verb facade at difficulty 12.00. Two questions, two
seams: "was this event defined, and what does it demand" is the
book's; "validate this payload and deliver to the ordered
listeners" is the dispatcher's.

## ED12 — Named follow-ups (the honest scope ledger)

Deliberately not in this slice, each a named future slice:

- **Wildcard listeners** (`user.*`) — enterprise phase 1; the name
  grammar already reserves the signature characters (ED10).
- **Priority grouping** ("all logging listeners before processing")
  — expressible today as a priority band convention; a first-class
  grouping API waits for a real consumer.
- **Async execution + retry + DLQ + poison pill** — enterprise
  phase 2; owns the failure regime this slice's ED8 declares.
- **Serialization + cross-process bus** — enterprise phase 3.
- **Transactional dispatch + outbox** — enterprise phase 4.
- **Event traceability + mock-listener test helpers** — enterprise
  phase 5; the Telemetry lane's span vocabulary is the foundation
  it will wire to (the dispatch outcome's count is the seam).
- **EventStore / AggregateRoot / snapshotting, CQRS buses** — the
  advanced roadmap sections above the enterprise phases.
- **Module system `events` knob** — the module-integration lane's
  own slice, lands with its reader (ED11).
