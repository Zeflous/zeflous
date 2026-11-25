# Event Durable PDO — The Family's Own Engine, The Claim The Row Owns

KDD-DP1..DP9 for `src/Zef/Framework/Adapters/Event/` — the Adapters
lane's fourth slice over the Event arm, the port's own named
follow-up (the `DeferredDeliveryStore` docblock: "the Redis and PDO
answers the roadmap's cross-process phase names as its own
follow-ups over this same port", DR8's ledger carried verbatim):
the database family's answer to the persistence port, composed over
the query engine the family already owns — the builder, the
condition objects, the identifier grammar, the connection contract —
so the only SQL this adapter ever spells beyond the engine is the
clause the three drivers spell apart, and it lives in exactly one
fold. The claim is the exclusivity the file adapter declared
absent, spelled as the two columns the delivery row owns; the
record spelling is the Event lane's own encoder, unchanged — the
record is the same JSON either side of the store choice.
`feat/pdo-delivery-store`.

**Verified locally before the PR:** `ci:static`'s lanes EXIT-0
(8058 tests / 19702 assertions; smoke 20000 iterations at 2760
bytes; audit 0 advisories; zero-deps gate green) · psalm EXIT-0
with the baseline net SHRUNKEN by six entries (the claim fold's
condition leaves and the boundary's connection verbs all gained
their first src consumers — the store carries the one
test-only-consumer entry the Redis answer's own class carries) ·
PHPStan max EXIT-0 · archeology 0 error-level findings (every fold
under the 20 difficulty ceiling, the deepest at 19.20) ·
duplication and mess zero violations · the coverage / infection /
phpcs halves ride CI on the merge group, watched to green (the
sandbox carries no pcov and its phpcs install cannot finish even
one file — the environment, never the tree).

## The map

| Unit | Question it owns |
|---|---|
| `PdoDeliveryTables` | the table vocabulary one prefix hangs (delivery / dead / attributed / quarantine / journal), each name validated by the family's own identifier grammar |
| `PdoDeliveryDialect` | the spellings the three drivers answer differently: the delivery DDL (MySQL's inline index), the index statement, the two conflict tails |
| `PdoDeliverySchema` | the layout posture: the five tables ensured present before the first verb runs, idempotently, the honest halt when the engine refuses |
| `PdoDeliveryBoundary` | the port's family at the one seam every fold rides: the engine's typed refusals become the port's declared refusals, the original chained |
| `PdoDeliveryUpsert` | the conflict-tail write seam: the builder's INSERT with the dialect's tail appended, insert-once and insert-rewrite the only two doctrines |
| `PdoDeliveryEntries` | the delivery row's own verbs: the one-statement batch write, the idempotent removal, the records read |
| `PdoDeliveryAdmission` | the batch's admission: the capacity count, the refusal whole with the counts spelled, the write the engine answers all-or-nothing |
| `PdoDeliveryDueScan` | the due identities in the index's own order — ready-at then entry identity, the DU9 doctrine the (ready_at, entry_id) index carries for free |
| `PdoDeliveryClaim` | which due entries THIS drainer holds exclusively: one conditional UPDATE per due entry, the WHERE the whole contract, the affected-rows the verdict |
| `PdoDeliveryRecords` | the claimed identities become the deliveries they spell: the cells judged, the decode fail-closed naming the entry, the vanished row skipped |
| `PdoDeliveryDeadLane` | the operator's dead lane: one row per spent entry, the same story rewritten, never doubled |
| `PdoDeliveryBooks` | the attribution book (insert-once per entry), the quarantine book (membership is the containment), the journal (one minted-named row per completed pass) |
| `PdoDeliveryStore` | the facade over the port: the eleven verbs as plain delegation, every engine touch already translated |

The fold count is the difficulty ceiling's own demand, the DR7
lesson replayed: the first spelling held the whole store on one
class at 27.91 and the whole records read at 24.75 — the gate
refused both, and every seam above is the question a ceiling
forced out (the boundary, the entries row lane, the due scan, the
dead lane). The deepest survivors stand at 19.20 and below, the
gate answers zero error-level findings.

## KDD-DP1 — The engine is the family's own, and the store owns no second spelling of it

The zero-library doctrine has a database spelling this slice plants:
the store composes the query engine the database family already
owns — `QueryBuilder` for every INSERT/SELECT/UPDATE/DELETE,
`ConditionGroup::any()` with `ConditionNull`/`ConditionComparison`
leaves for the claim's grouped WHERE, `IdentifierQuoting::forDriver()`
for the quoting the connection's own driver word keys, `SqlQuery`
for the statement and its ordered params, `ConnectionInterface` for
the socket — so the adapter adds no query grammar of its own. The
psalm ledger records what that composition did: the condition
leaves (`ConditionComparison`, `ConditionNull`, `ConditionGroup::any`,
`QueryBuilder::whereIn`) and the connection's transaction verbs all
gained their first src consumers, and six baseline entries retired
in this one pull request — the exclusion set only shrinks, and this
slice shrank it by six while adding one (the store's own
test-only-consumer entry, the exact posture the Redis answer's
class carries: the `queue/` wired default answers the file adapter,
the database wiring lands with the transactional/outbox lane's own
slice).

## KDD-DP2 — The claim is the row's own two columns, and the engine is the arbiter

The exclusivity the file adapter declared absent and the Redis
answer spelled as claim keys (`SET NX PX`, the drainer token, the
own-claim re-take, the expiry) lands here as the delivery row's own
`claim_token` and `claim_expires_at`: ONE conditional UPDATE per due
entry whose WHERE is the whole contract — `claim_token IS NULL`
(unclaimed), `OR claim_token = ?` with this drainer's own token (the
port's promise that an entry taken but not settled is re-taken by
the next pass), `OR claim_expires_at <= ?` (the visibility timeout
that hands a crashed drainer's entry to the next one) — and the
affected-rows answer is the verdict: one row written is the claim
won, zero rows is a claim another drainer holds. No second
statement ever reads a row into a state another drainer owns; the
engine's own row atomicity is the arbiter the Redis server's own
single-threaded atomicity played. The expiry arithmetic is the
regime's own unit (`now + claimTimeoutSeconds`, the seconds
`RetryPolicy` spells), and `ready_at` and `claim_expires_at` ride
BIGINT because a ready-at of `PHP_INT_MAX` is a spelling the
envelope round-trip already pins.

## KDD-DP3 — The dialect fold is the only SQL spelled beyond the engine

What the three drivers spell apart, one fold owns: MySQL carries the
due-scan index inline in the delivery DDL (its `CREATE INDEX` owns
no `IF NOT EXISTS` arm, and a second constructor on a fleet would
race the first one's index into a refused DDL) while SQLite and
PostgreSQL answer `CREATE INDEX IF NOT EXISTS`; the conflict tails
(the standard's `ON CONFLICT ... DO NOTHING / DO UPDATE SET ... =
excluded...` against MySQL's `ON DUPLICATE KEY UPDATE`) differ per
driver; everything else — the queries the builder composes, the four
books' DDL — is shared and never passes through the fold. The
MySQL and PostgreSQL spellings are pinned as strings over the
scripted tier, the schema-reflection dialect's own recorded posture
carried verbatim (CI's socket reaches SQLite alone; the live-socket
fixture stays the family's named follow-up). The upsert seam is the
one composition both tails ride: the builder's INSERT spelling with
the tail appended, so the conflict clause is the only string the
adapter interpolates and it lives in exactly one place.

## KDD-DP4 — The crash windows are the row's own atomicity

The Redis answer had to ORDER its commands around every crash
window (the record before the index member, the zombie member the
records walk completes quietly); the SQL answer inherits the
ordering for free, because the row is one atom: settle is one DELETE
whose absent row answers idempotently; requeue is one INSERT under
the retry's fresh identity; the admission is ONE multi-row INSERT
the engine answers all-or-nothing — the mid-write compensation the
Redis fold spells by hand (`$begun`, the stranded count) is SQL's
own statement boundary, a half-landed batch is a state the engine
cannot produce. The one window the row cannot close is the stale
drainer's late settle: a drainer that claimed, crashed, and woken
after its claim expired still holds the envelopes it read, and its
settle deletes the row a fresh drainer just claimed — the records
read answers the missing row by SKIPPING it (the entry's business
is finished; the duplicate the next pass would have delivered is
the one the at-least-once doctrine already budgeted), the SQL twin
of the zombie index member the Redis answer completes quietly,
pinned by driving the claim fold directly and settling behind its
back.

## KDD-DP5 — The port's family is the adapter's own boundary, spelled once

The port's docblocks declare `InvalidArgumentException`; the
database engine answers `DatabaseException` (the typed family the
database lane's rule 17 spelling owns). The two existing adapters
answer the port's family natively (the file lane's I/O refusals,
the Redis wire's reply refusals); the database answer cannot change
the engine's family, so it translates — at ONE seam: the connection
every fold rides passes through `PdoDeliveryBoundary` first, and
every engine refusal arrives as the port's declared family with the
original chained, on every verb of the connection contract alike.
The store's own refusals (the capacity arithmetic, the count shape
judgments, the decode's fail-closed walk, the construction
grammars) already answer the port's family and never pass through.
The first spelling wrapped every one of the store's eleven verbs in
a translating lambda — the difficulty ceiling refused it at 27.91 —
and the boundary the ceiling forced out is the better design on
its own terms: one translation instead of eleven, the store's verbs
plain delegation.

## KDD-DP6 — The books ride the conflict tails the dialect owns

The attribution book is insert-once per entry (the at-least-once
redelivery re-attributes the SAME entry, never doubles the book —
the moment deliberately not the book's to carry, the dead record
carries the whole story); the quarantine book is insert-once per
identity (the membership IS the containment, restoring a listener
the operator's own row removal); the dead lane is insert-REWRITE
(the same entry dead-lettered twice rewrites the same story — the
latest death the record carries). Three verbs, two tails, one seam:
the books and the dead lane compose the upsert fold and never spell
a conflict clause of their own. The journal is a plain append under
a freshly minted entry-style identity — two passes at the same now
are two rows, never one overwritten — with the record the Event
lane's own trace encoder spells, the same bytes the file journal
writes and the Redis list appends.

## KDD-DP7 — The test tiers, and what each one is for

Two tiers, the DR7 doctrine replayed. The real-SQLite tier: the
database family's own harness (the in-memory `:memory:` connection
`PdoConnectionTest` rides), answering everything the engine can
really do — the schema ensured (twice, idempotently), the claim
columns the row carries, the batch admission with its counts
spelled and its all-or-nothing, the requeue that is never an
admission, the due order with its exactly-due boundary, two stores
on one connection spelling the cross-drainer stories (a foreign
live claim respected, this drainer's own claim re-taken, an expired
claim handed on), the stale settle's vanished row skipped, the
corrupt record refused naming the entry, the dead lane rewritten,
the books idempotent, the journal appended, the engine refusal
translated (a DROP TABLE behind the store's back — the operator-DEL
doctrine carried from the Redis lane). The scripted tier: a
`ConnectionInterface` double whose driver word the test spells,
whose statements are recorded, whose fetch answers arrive from a
script, and whose every verb refuses on demand — the MySQL and
PostgreSQL DDL and tails pinned as strings, the hostile row shapes
the narrowing folds refuse, the count shapes the contract refuses,
and the boundary's translation observed per verb. The double answers
from the SERVER's side of the contract, never by replaying what the
fold under test would have said.

## KDD-DP8 — The gate round's own lessons

- **The ceiling forced the best seams out.** The one-class store
  measured 27.91 and the records read 24.75; the boundary, the
  entries row lane, the due scan and the dead lane the refusals
  forced out are each better questions than the classes that held
  them — the boundary especially: eleven translating lambdas became
  one seam, and the store became plain delegation (13.44).
- **The psalm ledger is a consumer census, and composition shrinks
  it.** Six baseline entries retired because the claim fold's WHERE
  and the boundary's verbs reached existing classes no src consumer
  had reached — an adapter that composes the engine answers
  analyzers the same way it answers SQL: by reference, never by
  duplication.
- **The scripted tier's refusal mode is the boundary's test.** A
  decorator with ten verbs is ten translation sites; the double's
  refuse-everything mode drives all ten in one loop, and the
  passthrough leg (the received connection, the recorded statement)
  kills the delegation-removal mutants the refusal legs alone
  cannot see.
- **The count-shape judgments are the port's family, not the
  engine's.** The first spelling threw `QueryException` from the
  admission's own count judgment (the `RepositoryAggregate`
  precedent — a Database-lane fold); this adapter's folds answer
  the port's family natively, and only the ENGINE's refusals ride
  the boundary's translation. The family line stays clean: engine
  refusals translated once, adapter refusals declared once.

## KDD-DP9 — Named follow-ups (the honest scope ledger)

- **The RabbitMQ and Kafka answers** — the bus transports after
  Redis, each over the same port with its own claim semantics
  (DR8's own ledger, carried; each is a wire-family slice the
  RESP2 answer's own size, the PDO answer's composition lesson
  riding along).
- **The transactional/outbox lane** — the `queue/`-style wiring and
  the exactly-once answer DU1 defers to: the store that feeds it
  now exists, composed over the same connection engine; the lane
  itself (the same-transaction outbox write, the relay) is its own
  slice with its own KDD.
- **A reconnect policy stays deliberately the host's** — one
  connection, one lifetime (the Redis answer's own doctrine,
  carried verbatim): a dead wire is a refusal every later verb
  answers, and the deployment's own glue decides what a dead
  connection means. The boundary translates the healing fold's
  refusals the same way it translates every other engine refusal.
- **The live-socket fixtures for MySQL and PostgreSQL** — the
  dialect's two spellings stay pinned as strings until CI's socket
  reaches them, the schema-reflection dialect's own recorded
  posture, carried.
- **A drain-scheduling policy stays deliberately absent** (DU12's
  own closing bullet, carried verbatim): the host owns time and
  therefore owns the schedule.
