# Event Async — Deferred Delivery, Retry, Dead Letters, Quarantine

KDD-EA1..EA12 for `src/Zef/Framework/Event/` — the Event Source
lane's enterprise-phase-2 slice (ED12's named follow-up after the
wildcard phase 1): the async execution arm with its retry /
exponential-backoff / dead-letter / poison-pill failure regime,
the regime ED8 declared the synchronous fold would never hide.

## The map

| Unit | Question it owns |
|---|---|
| `DeferredDelivery` | the queue's own entry: which event, to which listener, on which attempt, due when |
| `AsyncDeliveryQueue` | the bounded book of pending entries and the due-selection fold |
| `RetryPolicy` | the attempts ceiling and the exponential backoff arithmetic |
| `DeadLetter` | the record of one delivery that exhausted its attempts |
| `DeadLetterBook` | the book of dead letters, attributed per listener |
| `ListenerQuarantine` | the poison-pill answer: which listeners future defers skip |
| `AsyncDelivery` | the orchestrator: admission (pool snapshot + quarantine filter + capacity) and the drain fold |
| `EventDispatcher::defer` / `drain` | the two facade verbs (nullable engine; sync-only construction stays legal) |
| `DeferralOutcome` / `DrainOutcome` | the observable answers of the two verbs |

## KDD-EA1 — "Async" means deferred with an explicit drain, never parallel

The roadmap's own definition ("sinkron (langsung) atau asinkron
(di-antri)") is the contract this slice implements: `defer()`
enqueues now, `drain()` delivers later, in the same process. No
fork, no event loop, no threads — a queue and a fold. The
persistent worker's idle loop is the named future consumer (the
worker-adapter lane), and a web host may drain after the response
is sent; both reach this arm one-way, exactly as ED11 promised.

## KDD-EA2 — The failure regime is per-delivery; sync stays byte-identical

ED8's declaration is honoured by separation, not amendment: a
listener's exception still bubbles through `dispatch()` (the
synchronous regime untouched), while the deferred arm catches per
entry — one listener's failure never stops its peers' deliveries
in the same drain. That per-delivery isolation is the entire point
of queuing the work.

## KDD-EA3 — The pool snapshot at defer time is the message

`defer()` freezes the same two things `dispatch()` computes — the
validated `Event` and the sorted delivery pool (exact + matching
wildcards, one fold both verbs share) — and admits one
`DeferredDelivery` per pool listener. A listener subscribed after
the defer does not receive the event; an unsubscribe after the
defer does not retract it. The dispatch-time subscription state IS
the message: consistency with the synchronous doctrine (the pool
is dispatch-call state), pinned by test.

## KDD-EA4 — Time is a drain parameter; the layer owns no clock

`drain(int $now)` and `readyAt` are plain integers. No clock
interface, no `time()` call, no sleep — the queue and the policy
are pure data plus arithmetic, deterministic by construction, and
the host (worker loop, test, CLI) supplies "now". The layer
depends on nothing, not even time; a test can drain the same
queue at t=100 and t=10_000 without mocking anything.

## KDD-EA5 — Exponential backoff is the named demand, spelled once

A delivery that failed as attempt *n* (1-based) and still has
attempts left becomes due again at `now + base * 2^(n-1)`
(seconds), capped at `PHP_INT_MAX`; `base = 0` degenerates to
"due at the next drain". `maxAttempts` counts TOTAL delivery
attempts (the first try included; a policy of 1 dead-letters on
first failure). Both are construction-validated: `maxAttempts >= 1`,
`baseDelaySeconds >= 0`, integers only.

## KDD-EA6 — Dead letters carry the whole story

When a delivery exhausts its attempts, its `DeadLetter` records
the event's name, the listener object, the attempts spent, the
failure's class and message, and `deadAt` — the drain's own `now`
(EA4). The book counts per listener (object identity), which is
the attribution the quarantine reads.

## KDD-EA7 — The poison pill is the LISTENER, and the answer is forward-looking quarantine

The vocabulary pin this record exists to make: a "poison pill" in
this failure regime is a listener whose deliveries keep dying —
and the answer is the `ListenerQuarantine`. When a listener's
attributed dead letters reach the threshold, the listener is
quarantined: every FUTURE defer skips it (reported in
`DeferralOutcome::skippedQuarantined()`). Already-admitted entries
still deliver — a quarantined listener's queued work may succeed
(transient failures are real), and silently dropping admitted work
would be a retraction this vocabulary refuses. The quarantine is
containment of future admission, never retroactive destruction.
(The other classic reading — a shutdown sentinel for the worker
loop — belongs to the worker-adapter lane, which owns the loop.)

## KDD-EA8 — Backpressure is a refusal at the boundary, not a silent drop

The queue is bounded (a long-lived worker must not grow without
limit). When a defer's entries would exceed the capacity, the
WHOLE defer is refused with the capacity, the occupied count and
the demanded count spelled — the producer learns, no partial
delivery happens, and draining is the host's job. The bounded
in-memory store is the rate-limit family's own precedent.

## KDD-EA9 — A drain is one pass over the due entries

`drain(now)` selects the entries whose `readyAt <= now` (in queue
order), delivers exactly those, and leaves the not-yet-due in
order. Requeued retries join the tail with their future `readyAt`,
so a `base = 0` retry is due at the NEXT drain, never re-delivered
within the same pass. Entries deferred DURING a drain (a listener
that defers) are next-pass material: the due list is a snapshot —
no self-feeding loop can keep a drain alive forever.

## KDD-EA10 — Validation stays at the producer's boundary

`defer()` refuses an undefined event (ED1) and a mis-shaped payload
(ED2) exactly as `dispatch()` does — at the CALL, before anything
is queued. The producer's mistake surfaces at the producer's own
line; a drain never discovers a payload problem.

## KDD-EA11 — The layer still depends on nothing

No new deptrac layer, no config knob, no container wiring: the
engine is layer-internal, the verbs join the facade, and every
future consumer (the worker adapter, the module `events` knob, the
traceability fold) reaches in one-way. A dispatcher constructed
without an engine is the legal sync-only shape; `defer`/`drain`
on such a dispatcher fail closed with the construction spelled.

## KDD-EA12 — Named follow-ups (the honest scope ledger)

- **Durable/serialized queue** — enterprise phase 3's
  serialization + cross-process bus; this slice's queue is
  in-process by declaration.
- **The worker-adapter drain loop** — the persistent worker's
  idle drain + the shutdown sentinel; owns the loop this arm
  feeds.
- **Traceability over drain outcomes** — enterprise phase 5; the
  counts are the seam (delivered/retried/deadLettered/quarantined/
  pending), exactly as `DispatchOutcome`'s count is ED9's seam.
- **A drain-scheduling policy** (when the host should call drain)
  is deliberately absent: the host owns time (EA4) and therefore
  owns the schedule.
