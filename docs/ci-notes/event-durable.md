# Event Durable — The Serialized Delivery, The Store Port, The File Adapter

KDD-DU1..DU12 for `src/Zef/Framework/Event/` +
`src/Zef/Framework/Adapters/Event/` — the Event Source lane's
enterprise-phase-3 slice (EA12's first named follow-up after the
async arm #623): the serialized spelling of one pending delivery,
the persistence and resolution ports, and the first adapter over
the local filesystem — the seed of the ROADMAP's Adapters lane,
the port/adapter pair the whole tree's hexagonal story grows
from.

## The map

| Unit | Question it owns |
|---|---|
| `ListenerIdentity` | the spelling a listener is recorded durably by |
| `DurableEventListener` | the contract a listener declares its identity through |
| `DeliveryEntryId` | the entry's own identity, minted fresh per entry |
| `SerializableValues` | whether a payload map survives the JSON round trip, and where it does not |
| `DeliveryEnvelope` | the serialized spelling of one pending delivery |
| `DeliveryEnvelopeEncoder` | the record's writing half (pending and dead-letter spellings) |
| `DeliveryRecordShape` | whether a decoded JSON is a pending record's object shape at all |
| `DeliveryRecordValues` | the record's values map, proven string-keyed |
| `DeliveryEnvelopeDecoder` | the record's reading half, fail-closed in every direction |
| `DeferredDeliveryStore` | the persistence PORT: save / requeue / takeDue / settle / deadLetter / pendingCount |
| `EventListenerResolver` | the resolution PORT: an identity into a listener, or null |
| `DurableAdmission` | what one durable defer admits (the pool spelled as entries) |
| `DurableEntrySoundness` | whether a due entry is sound to deliver, or already dead |
| `DurableSettlement` | how every answered entry leaves the store (the write-then-settle ordering) |
| `DurableVerdictAnswer` | what the judgment's verdict becomes in the store and the books |
| `DurableDrainPass` | one pass over the store's due entries |
| `DurableDelivery` | the durable regime's composition root |
| `DurableEventDispatcher` | the durable vocabulary a host asks its dispatcher for |
| `DeliveryStoreDirectory` | where the file store may live (the queue store's own privacy posture) |
| `DeliveryStaging` | how one record file becomes a file (the atomic write discipline) |
| `DeliveryFiles` | which file an entry is (the root layout and the scan) |
| `DeliveryDeadLane` | the `dead/` subdirectory an operator reads |
| `DeliveryBatchWrite` | how a batch is admitted (the capacity backpressure and the compensation) |
| `DeliveryDueScan` | which pending entries are due, decoded and ordered |
| `FileDeliveryStore` | the filesystem's answer to the persistence port |

The fold count is the difficulty ceiling's own demand, the
EA11a/ED11a precedent replayed twice over: the first spelling
held the encode and the decode on one codec at difficulty 25.67,
the whole file answer on one store at 28.57, the file mechanics
on one fold at 27.95, and the drain pass at 23.38 (its `run()`
alone at 21.92) -- the code-archaeology gate refused all four.
Every fold above is a seam that ceiling forced out, each named
for the question it owns; the deepest survivors stand at 16.36
and below, and the gate answers zero error-level findings.

## KDD-DU1 — Durability is spelled at-least-once, never exactly-once

The crash-window table is the slice's whole honesty: `takeDue()`
READS (a crash between taking and answering re-takes the entry on
the next pass), every drain answer is written BEFORE the spent
entry settles (`DurableSettlement` owns the ordering -- a retry
is requeued first, a dead letter recorded first), settling is
idempotent (a settled entry settles again as a no-op), and a
mid-write refused admission is compensated whole (the batch or
none -- with the stranded count spelled when even the
compensation cannot complete). Every window costs a DUPLICATE,
never a loss: listeners own their idempotency, the store owns the
survival. Exactly-once is the transactional/outbox lane's
business, spelled against the EntityManager port the database
adapter family will own.

## KDD-DU2 — The identity is the listener's own declaration

A durable record spells what survives the process boundary;
object identity does not. `DurableEventListener` is the contract a
listener declares its `ListenerIdentity` through -- a service
knows its stable name, and only that name lets a later process
resolve the delivery back into the object that must answer it.
A pool that carries an anonymous listener is REFUSED at the
durable defer, the class named: the in-process arm stays
available for exactly that pool. Two arms, two honesty rules --
the refusal is the vocabulary telling the producer which arm is
which, never a silent downgrade.

## KDD-DU3 — The record is data, validated in both directions

`DeliveryEnvelope` is pure data: the event's name, the validated
values, the listener identity, the attempt number, the
ready-at -- construction refuses what cannot survive (the walk
below), and the codec pair translates it to JSON and back.
The boundaries the spelling pins on purpose: floats keep their
zero fraction (`1.0` never collapses to `1` -- a float-kind field
stays a float-kind field across the boundary, `PRESERVE_ZERO_FRACTION`
both halves carry, pinned by test on the pending AND the
dead-letter record), a ready-at of `PHP_INT_MAX` (the retry
arithmetic's overflow cap) round-trips as the same integer, and
the decode is fail-closed in every direction -- invalid JSON, a
non-object, an unknown key, a missing key, a wrongly-typed field,
the bounds, and the record's depth ceiling (the 510-deep record
decodes; the 511-deep one is refused with the engine's own
reason -- both sides pinned by test). A record that decodes on a
guess is a delivery that was never admitted.

## KDD-DU4 — The walk names the value that cannot survive

The shape's five kinds admit values the JSON grammar cannot
carry: a list field holds whatever the producer put in it, and
PHP strings are byte bags that may not be UTF-8.
`SerializableValues::offense()` walks the map and names the FIRST
offense by kind and path -- `stdClass at tags[1]`,
`resource (stream) at handle`, `NAN at ratio`, `a string that is
not valid UTF-8 at memo` -- refusing at the envelope's own
construction, never as an opaque `json_encode` failure at the
adapter's write. Non-finite floats and invalid UTF-8 are refused,
not substituted: a durable spelling must round-trip byte
identically or it is a lie. Nested integer keys are legal (PHP
itself casts numeric string keys, and a list round-trips as a
list); only the payload ROOT must stay a map, because the field
names are the record's own vocabulary.

## KDD-DU5 — The port is the question, the adapter is the answer

`DeferredDeliveryStore` and `EventListenerResolver` are the Event
lane's own ports -- the layer still depends on nothing (deptrac
ruleset untouched), every future consumer reaching in one-way.
The ANSWER is an adapter's own business, and the first one --
`FileDeliveryStore` under `Zef\Framework\Adapters\Event` -- seeds
the ROADMAP's Adapters lane with the whole tree's first
port/adapter pair: the adapter depends on the port and on the
storage layer's document-root fold, the hexagonal direction the
ROADMAP's own `src/Adapters/Event/` sketch spelled. The Redis and
PDO answers are named follow-ups over this same port -- their
claim semantics and their indexes, exactly as this adapter's
plain-file honesty is its own.

## KDD-DU6 — takeDue reads, settle writes: the protocol's ordering

The port's verbs mirror the in-process queue's own grammar:
`save()` is the admission (the capacity backpressure with the
counts spelled -- the whole batch or none, EA8 carried over),
`requeue()` is the retry arm (NOT an admission: the in-process
`admit`/`requeue` split mirrored, so a store draining at capacity
can still retry the work it already holds), `takeDue()` is a read
(the entries stay until they are settled -- the at-least-once
window), `settle()` is idempotent, and `deadLetter()` records the
spent entry's story keyed by its own identity (the same story
rewritten is the same file replaced). The file adapter's honesty
ledger: single-drainer by declaration (two concurrent drainers
both take the same due entries -- the duplicate the idempotency
doctrine owns; the exclusivity is the bus adapter's claim
semantics), staging files carry a leading dot (a crash between
staging and rename strands a dot-file the scans ignore -- an
operator may sweep them while no writer runs), and the scan reads
every pending record (the capacity bound keeps that pass
operator-sized; an index is the future adapters' business).

## KDD-DU7 — The failure regime is reused verbatim

The drain's arithmetic is the in-process arm's own code:
`DurableEntrySoundness` composes the `DeferredDelivery` the
in-process `DeliveryAttempt` runs and `DeliveryFailure` judges --
the backoff, the attempt ceiling, the dead-letter story and the
quarantine attribution are byte-identical by construction, one
regime over two books. The in-process `DeadLetterBook` and
`ListenerQuarantine` the durable engine keeps are process-local
by declaration (a fresh process starts quarantine-free and
re-learns by attributing fresh letters -- the durable answers are
the named follow-ups); the durable story an operator reads lives
in the store's own dead-letter records.

## KDD-DU8 — Three dead-letter causes, attributed honestly

A spent entry's story names one of three causes: the listener's
own failure (the real exception class and message), the
`unresolved-listener` cause (no listener in THIS deployment
answers the identity -- the null the resolver port answers), or
the `stale-shape` cause (the event is no longer defined, or the
payload no longer answers the shape the definition NOW demands --
the shape's own refusal message spelled, because the definition
moved and the operator should read exactly how). The last two are
NOT attributed to a listener (the service retired, the definition
moved -- neither is the listener's death to answer for), so they
never cross the quarantine threshold. The `dead/` lane IS the
roadmap's dead-letter-queue demand: a directory of records an
operator reads, never a silent loss.

## KDD-DU9 — The rebuild is the drain's own judgment, never a trust

A loaded record is re-validated against the definitions that are
LIVE at drain time: `EventBook::shapeFor()` over the envelope's
values, exactly as the defer boundary validated them at defer
time. A long-lived durable store WILL outlive schema changes --
the stale-shape dead letter is the operator-inspectable answer,
not a crash. The same honesty answers the drain-snapshot
doctrine: entries saved during a pass are next-pass material (the
due list is the store's own snapshot -- the self-feeding
listener is pinned by test, EA9 carried over), and among entries
due at the same moment the order is ready-at then entry identity
(cross-process admission order is not observable; the file
store's book is the directory itself).

## KDD-DU10 — The file store's directory is the queue store's own privacy posture

`DeliveryStoreDirectory` owns WHERE the store may live: existing,
writable, non-symlink, owner-only, outside the public document
root -- because the entries it holds are the application's
business data spelled in the clear, and a world-readable or
web-served directory would turn a durability promise into a
disclosure. The document-root judgment itself is the storage
layer's own fold (`StorageDocumentRoot`, the one spelling of "a
web-served tree is a disclosed tree" the whole tree already
shares), reached one-way -- the adapters lane's first composition
of a core fold. The `files/**` route-map default (a `queue/`
lane) is deliberately absent: a lane joins the vocabulary in the
pull request that lands its first wired default, and the events
knob that would read it lands with the module system's reader --
the storage-layout slice's own census-moment doctrine.

## KDD-DU11 — The facade is the composed one-way vocabulary

`EventDispatcher::durableDelivery($store, $resolver, $policy,
$threshold)` is the one-way composition, the
`deferredDelivery()` precedent mirrored: a dispatcher that was
never asked for the durable arm simply has no durable verbs to
call -- the sync-only and in-process-only shapes stay legal,
answered structurally instead of by a refusal. The event book the
plan already reads is the same book the soundness fold
re-validates each loaded record against, so the live definitions
stay the one authority on what a deferred entry means,
in-process and across the boundary alike.

## KDD-DU12 — Named follow-ups (the honest scope ledger)

- **The worker-adapter drain loop** — the persistent worker's
  idle drain + the shutdown sentinel; owns the loop this arm
  feeds (the HTTP shim's Fase 2.2 worker context, KDD-EA1's own
  named consumer).
- **The cross-process bus adapters** — Redis/RabbitMQ/Kafka over
  the same `DeferredDeliveryStore` port, with the claim semantics
  and the indexes the file adapter's honesty ledger names; the
  ROADMAP's `RedisEventTransport` sketch.
- **Durable quarantine and durable traceability** — the
  process-local books re-spelled over the store; the drain
  outcome's five counts are the seam, exactly as EA12 spells.
- **Transactional dispatch / outbox** — the EntityManager port
  the database adapter family will own; the exactly-once answer
  DU1 defers to.
- **The `queue/` storage lane + the module `events` knob** — the
  wired default and its configuration reader, the knob that
  lands with the module system's own slice.
- **A drain-scheduling policy** stays deliberately absent: the
  host owns time (EA4 carried over) and therefore owns the
  schedule.
