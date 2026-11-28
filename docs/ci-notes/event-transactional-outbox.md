# Event Transactional Outbox — The Emission The Transaction Owns, The Handoff The Engine Answers

KDD-TO1..TO9 for `src/Zef/Framework/Event/` + `src/Zef/Framework/Adapters/Event/`
— the Event arm's transactional/outbox lane, the lane DU1 named when it spelled
durability at-least-once ("Exactly-once is the transactional/outbox lane's
business"), DU12's fourth bullet carried ("the EntityManager port the database
adapter family will own; the exactly-once answer DU1 defers to"), DP9's second
bullet landed ("the `queue/`-style wiring and the exactly-once answer DU1
defers to: the store that feeds it now exists, composed over the same
connection engine; the lane itself (the same-transaction outbox write, the
relay) is its own slice with its own KDD") and the ROADMAP's Fase-4 promise
answered ("Transactional Event Dispatch" and "Outbox Pattern", the section the
roadmap marks PENTING): the emission made atomic with the host's own business
write, and the relay that hands the emitted rows to the durable arm as one
unit. The #936 deferral is honored in the same pull request: the `database`
store word joins `EventDeliveryStores`, the wired default composing the PDO
delivery store over the named connection the `events.deferred.connection` knob
spells — the psalm baseline entry the PDO answer carried retires, and the
store's own posture (a test-only consumer until the wiring lands) passes to the
outbox store, the exact posture the Redis answer's class carries.
`feat/transactional-outbox`.

**Verified locally before the PR:** `ci:static`'s lanes EXIT-0
(8199 tests / 19979 assertions, the six `ErrorsReplayTest` spawns the sandbox's
`posix_spawn` cannot answer — verified to fail on clean `main` too, the
environment, never the tree; smoke 20000 iterations at 2760 bytes; audit 0
advisories; zero-deps gate green) · psalm EXIT-0 with the baseline net
SHRUNKEN by three entries (the relay's handoff gave `DeferralOutcome`'s
`admitted`/`skippedQuarantined` and `DurableEventDispatcher::defer` their
first src consumers, retiring three) while adding the transactional
vocabulary's own test-only-consumer entries (the facade's three verbs, the
relay outcome's three counts, the dispatcher's `transactionalDelivery` beside
its durable twin) · PHPStan max EXIT-0 · archeology 0 error-level findings
(every fold under the 20 difficulty ceiling, the deepest at 19.34) · the
duplication and mess halves ride CI on the merge group (the sandbox's
`vendor/bin` stubs make the detectors exit 127 locally — the environment,
never the tree) · the coverage / infection halves likewise ride CI, watched
to green (the sandbox carries no pcov).

## The map

| Unit | Question it owns |
|---|---|
| `OutboxMessageId` | the outbox row's own identity: thirty-two lowercase hex, minted fresh per transactional defer, the `D`-anchored grammar (#786) |
| `OutboxMessage` | the row's own record: the identity, the event's name, the JSON-survival-walked values, the appended-at moment — construction the append's own refusal point |
| `OutboxStore` | the port: the append that rides the caller's own scope, the relay that answers one unit, the count the capacity arithmetic reads |
| `OutboxRelayOutcome` | one relay pass's own report: the rows relayed, the entries admitted, the listeners the quarantine skipped |
| `TransactionalEventDispatcher` | the facade: the defer/relay/pendingCount vocabulary composed one-way over the book, the outbox and the durable arm |
| `PdoOutboxTables` | the one table one prefix hangs, the name validated by the family's own identifier grammar |
| `PdoOutboxDialect` | the spellings the three drivers answer differently: the outbox DDL (MySQL's inline index), the index statement |
| `PdoOutboxSchema` | the layout posture: the table and its read-order index ensured before the first verb runs, idempotently, the honest halt on refusal |
| `PdoOutboxBoundary` | the port's family at the one seam every fold rides: the engine's typed refusals become the port's declared refusals, the original chained |
| `PdoOutboxEntries` | the outbox row's own write and removal verbs: the one-statement batch write, the idempotent batch removal |
| `PdoOutboxAdmission` | the batch's admission: the capacity count, the refusal whole with the counts spelled, the write that rides the caller's scope |
| `PdoOutboxPending` | the raw pending rows in the read order — appended-at then row identity, the DU9 doctrine the index carries for free |
| `PdoOutboxRecords` | the raw rows judged into messages: the cells judged by the string their column spells, the appended-at cell by the integer its column spells |
| `PdoOutboxPayload` | one row's payload decoded fail-closed, naming the row — the JSON spelling the append wrote, read back through the Event lane's own values walk |
| `PdoOutboxRelay` | one relay pass as one unit over the store's own connection: the read, the handoff, the removal — both-or-neither |
| `PdoOutboxStore` | the facade over the port: the three verbs as plain delegation, every engine touch already translated |
| `EventDeliveryClaimTimeout` | the `events.deferred.claim_timeout` knob's integer seam: native int passthrough, all-digit string fold, the at-least-one-second bound |
| `DatabaseConnectionWiring` | the database family's named-connection wiring: the per-purpose composition a family's own knob spells, the empty name folding to the default connection |

## KDD-TO1 — The emission is the transaction's own, and the store opens no scope of its own

The Fase-4 promise ("Event hanya dipancarkan setelah transaksi database
berhasil commit. Jika transaksi rollback, event tidak pernah dipancarkan") is
answered structurally, not by a flag: the append is a plain write on the
connection the store composes, so the row rides WHATEVER transaction the
caller's scope carries over that same connection — the business write and the
outbox row land together or neither lands, the engine's own atomicity, the
same-statement doctrine the #936 admission spelled ("SQL never lands half a
statement") lifted from the statement to the scope. A rollback discards the
row the same rollback discards the business write; the pin is the real-engine
tier's both-directions test (the commit lands the row AND the business row,
the rollback discards both). The append NEVER opens a scope of its own: a
scope of the store's own would make the row's atomicity the store's business,
which is exactly the promise the lane is named NOT to own. An append outside
any open scope is the auto-commit posture the database family's own standalone
calls spell — the row lands on its own, never silently inside a scope the
caller did not open.

## KDD-TO2 — The exactly-once handoff is the relay's own one transaction

The relay pass is ONE unit over the store's own connection: the pending rows
read, the handoff's writes and the relayed rows' removal both-or-neither —
`ConnectionInterface::transaction()` is the engine's own managed scope, rolled
back on any throw, the throwable propagating untouched. THAT is the
exactly-once DU1 defers to: no row enters the delivery store twice from the
outbox side (a refused handoff — the delivery store's capacity backpressure, a
shape the live definitions no longer answer — rolls the whole pass back and
every row stays pending, re-read by the next pass), and no relayed row is ever
lost (the removal only lands when the handoff's writes landed). The
composition demand is spelled at the port: the outbox and the delivery store
the relay feeds ride the SAME connection the host spells — the demand is a
docblock the host reads before the wiring spells it, and the end-to-end pin
composes the real stores over one real engine. The delivery entries then drain
under the durable arm's own at-least-once doctrine — listeners own their
idempotency, the store owns the survival, the transactional lane owns the
EMISSION atomicity alone: exactly-once INTO the store, at-least-once OUT of
it, the two promises never confused.

## KDD-TO3 — The pool snapshot is the relay's, never the append's

The transactional defer validates the payload against the live definition
(KDD-EA10: the producer's mistake surfaces at the producer's call — inside the
host's transaction the refusal IS the rollback) but takes NO pool snapshot:
the relay's defer owns it (KDD-EA3 read from the relay side — the subscription
state live when the relay runs IS the message). A listener subscribed between
append and relay joins, a listener gone before the relay does not — the DU11
doctrine ("the live definitions stay the one authority on what a deferred
entry means, in-process and across the boundary alike") carried to the
subscription state itself. The pin is the facade test's late-subscriber arm:
the row appended with one pool listener relays into TWO entries when a second
listener subscribed before the relay ran.

## KDD-TO4 — The read order is the index's own, the DU9 doctrine for free

The relay's pending read orders by appended-at then row identity — the same
ordering doctrine the delivery store's (ready_at, entry_id) index carries (DU9:
the order is a determinism contract, never a fairness promise), answered by
the outbox's own (appended_at, message_id) index for free: the same-index
spelling the #936 dialect fold pinned, MySQL's inline arm carried verbatim
(its CREATE INDEX owns no IF NOT EXISTS arm, so the table's own DDL carries
the index, a second constructor on a fleet never racing a refused DDL). The
appended-at moment is the host's clock spelled at the defer call (KDD-EA4: the
regime owns no clock — every verb that needs time receives it), the row's own
column, never a wall read the store takes for itself.

## KDD-TO5 — The fold split the ceiling forced, twice

The first spelling held the whole row lane in one fold and the archeology
gate refused it (PdoOutboxEntries at 35.08 difficulty, the ceiling 20) — the
#936 lesson replayed: "the first spelling held the whole store at 27.91 … the
boundary, the entries row lane, the due scan and the dead lane the ceiling
forced out". The split mirrors the delivery family's own geometry: the write
and removal verbs stay in the entry-row fold (18.42), the raw read moved to
`PdoOutboxPending` (9.00), the judgment to `PdoOutboxRecords` (15.89), the
payload decode to `PdoOutboxPayload` (9.38) — two questions the records fold
still owned when it measured 20.91, split again by the same doctrine. The
second forcing: the named-connection wiring pushed `DatabaseWiring` itself
over the line (19.34 → 20.22), so the per-purpose composition moved to its
own fold (`DatabaseConnectionWiring`, 9.82) reached one-way over the
composition seam `pdoConnection` became — the family's own folds and every
family's named ask compose over the one seam, so the composition itself is
still spelled once.

## KDD-TO6 — The port's family is the adapter's own boundary, and the store's own refusals stay native

The connection every fold rides passes through `PdoOutboxBoundary` first, so
every engine refusal arrives as the port's declared
`InvalidArgumentException` with the original chained, on every verb of the
connection contract alike — the KDD-DP5 division carried verbatim: the
engine's refusals translated once at the one seam, the adapter's own
refusals (the capacity arithmetic, the cells' judgments, the payload's
fail-closed decode, the construction grammars) answer the port's family
natively and never pass through. The relay's handoff refusals are the port's
family already (they ARE the handoff's own throws — the rollback the engine
answers), and the transaction's own rethrow never re-translates what the
boundary already translated.

## KDD-TO7 — The test tiers, and what each one is for

The DR7 doctrine replayed. The real-SQLite tier (the database family's own
`:memory:` harness) answers everything the engine can really do: the
construction refusals, the schema ensured idempotently, the same-transaction
write's two directions (the commit lands row and business row together, the
rollback discards both), the auto-commit posture, the capacity arithmetic with
its counts spelled, the read order with its identity tiebreak, the relay's
removal, the refused handoff's whole-pass rollback observed over the REAL
delivery store on the SAME connection (nothing delivered, everything still
pending), the relay feeding the delivery store for real, the end-to-end lane
test (defer inside a managed scope → relay → drain → the listener received,
all over one engine), the engine refusal translated, the corrupt payload and
the hostile cells refused. The scripted tier (the `ScriptedDeliveryConnection`
double the delivery battery already owns) pins what SQLite cannot: the two
foreign dialect DDL spellings (PostgreSQL's index statement, MySQL's inline
index and its null index answer), the hostile row shapes the engine contract
enforces, the count's shape refusal, and the boundary's translation observed
per verb. The Event-lane tier (the `InMemoryOutboxStore` double with its
action ledger) pins the facade's own protocol — the append, the relay's
aggregation, the pool-snapshot-at-relay doctrine, the quarantine counts, the
refused handoff's rollback — independently of any engine.

## KDD-TO8 — The wiring: the `database` word, the named connection, the census

The `events.deferred` family's census grows the database word's three knobs
(`connection`, `prefix`, `claim_timeout`) beside the file word's two —
family-wide, the `security.auth` block's own posture (the replay-store word's
redis knobs joined the block beside the file word's address, never a second
block per word). The word's own vocabulary fold refuses a typo with the known
words spelled; the claim timeout reads through its own integer seam (native
int passthrough, all-digit environment fold, the at-least-one-second bound
with its own refusal message); the connection folds to the default
connection exactly the migration runner's per-purpose name doctrine spells
(the empty knob never names a connection the map does not carry); the prefix
defaults to the framework's own namespace (`zef_`, the migrations family's
own posture — the framework's tables visibly the framework's). The wiring
composes the PDO delivery store over the named connection, and the psalm
baseline entry the store carried since #936 retires in the same pull request
— the store's first src consumer is the wiring itself, and the
test-only-consumer posture passes to the outbox store, the exact posture the
Redis answer's class carries.

## KDD-TO9 — Named follow-ups (the honest scope ledger)

- **The outbox's own wired default** — the outbox store composes by hand over
  the same engine the `database` word spells (its constructor parameters the
  host's own wiring, the same explicitness the file and Redis adapters carry);
  the `events.outbox` family and its service identity land with the relay
  worker loop slice that first asks for a wired outbox (DU10's
  census-moment doctrine, carried; the `WorkerDrainLoop` precedent: the worker
  integration slice that owns its own lane's first wired ask).
- **The relay worker loop** — the persistent worker's idle relay, the
  `WorkerDrainLoop` mirror (`relay()` beside `drain()` on the worker's own
  schedule port); the relay VERB is this slice's, the LOOP the worker
  integration's own.
- **The RabbitMQ and Kafka answers** — DR8's ledger carried: the bus
  transports after Redis, each over the same `DeferredDeliveryStore` port with
  its own claim semantics.
- **The cross-relay exclusivity** — the relay assumes it is the store's only
  relay (the single-worker doctrine the worker loop family spells; the port's
  docblock spells the demand); the claim columns the delivery rows own are
  the named spelling a many-relay deployment composes when one actually asks
  (the file adapter's own honesty posture: the exclusivity it declared
  absent, the follow-up the first real consumer names).
- **A drain-scheduling policy stays deliberately absent** (DU12's own closing
  bullet, carried verbatim): the host owns time and therefore owns the
  schedule — the relay's own schedule is the relay worker loop slice's own
  question, never this lane's.
