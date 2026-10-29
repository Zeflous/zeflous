# Event Durable Quarantine & Traceability — The Books Over The Store, The Drain Journal

KDD-DQ1..DQ8 for `src/Zef/Framework/Event/` +
`src/Zef/Framework/Adapters/Event/` — the Event lane's answer to
KDD-DU12's third named follow-up ("durable quarantine and durable
traceability -- the process-local books re-spelled over the store;
the drain outcome's five counts are the seam, exactly as EA12
spells"): the attribution and the quarantine the in-process arm
keeps in memory, re-spelled over the identity that survives the
process boundary, and the drain journal that records what every
completed pass answered with.

## The map

| Unit | Question it owns |
|---|---|
| `DrainTrace` | one completed pass's answer, timestamped: the five counts at the drain's own now |
| `DrainTraceEncoder` | the trace's JSON spelling -- the same record either side of the store choice |
| `DurableDrainJournal` | the durable pass's own last act: the outcome journalled, the trace appended |
| `DeferredDeliveryStore::recordAttributedDeath` | the attribution book's append, idempotent per entry |
| `DeferredDeliveryStore::deadLettersAttributedTo` | the count the quarantine threshold reads |
| `DeferredDeliveryStore::quarantineListener` | the containment act the crossing earned |
| `DeferredDeliveryStore::isListenerQuarantined` | the filter every future defer reads |
| `DeferredDeliveryStore::recordDrainOutcome` | the journal's append -- one record per completed pass |
| `DeliveryAttributionEncoder` | the file answer's attribution record's writing half |
| `DeliveryAttributionDecoder` | the reading half: fail-closed in every direction, the file named on every refusal |
| `DeliveryAttributionLane` | the file answer's `attribution/` book: where the records live, how they are written |
| `DeliveryAttributionScan` | the count the threshold reads: one walk over the lane's records |
| `DeliveryQuarantineLane` | the file answer's `quarantine/` book: one record per identity, its existence the filter |
| `DeliveryDrainJournal` | the file answer's `drains/` journal: one record per completed pass |
| `RedisDeliveryBooks` | the Redis answer's books: the attribution hash, the quarantine set, the journal list |
| `RedisDeliveryKeys` | the Redis answer's key vocabulary, grown by the books' three structures |
| `DeliveryFailure` (re-spelled) | the judgment: policy arithmetic only, both arms' one fold |

The port grew from six verbs to eleven: the entries' lifecycle
(save / requeue / takeDue / settle / deadLetter / pendingCount) plus
the books' and journal's own (recordAttributedDeath /
deadLettersAttributedTo / quarantineListener /
isListenerQuarantined / recordDrainOutcome). Every implementor --
the file adapter, the Redis adapter and the in-memory double --
answers all eleven in the pull request that spells the question.

## KDD-DQ1 — The books are keyed by the identity that survives

The process-local `DeadLetterBook` and `ListenerQuarantine`
attribute by object identity, and the object dies with the process
that minted it. The durable re-spelling keys both books by the
`ListenerIdentity` the listener itself declared (DU2's own
contract): the attribution record names the entry AND the identity
the death belongs to, and the quarantine record IS the identity.
A fresh process -- a fresh engine, a fresh worker, a fresh
deployment -- starts with the quarantine the store already holds,
never quarantine-free (DU7's "a fresh process re-learns by
attributing fresh letters" is amended by the answer its own
docblock named as the follow-up); the fresh-engine-over-one-store
test is the slice's headline pin. The judgment's attribution read
no longer consults the resolved listener OBJECT at all: the
envelope carries the identity, and a resolver that answers a
listener which declares none still delivers, still dead-letters,
and is still attributed by the identity its entry was admitted
under -- no new dead-letter cause, no refusal the regime never
spelled.

## KDD-DQ2 — The crossing read belongs after the append

The seam this slice moved: `FailureVerdict` no longer carries a
quarantine verdict, and `DeliveryFailure` no longer reads the
books -- the judgment is the policy's retry-versus-death arithmetic
alone, and BOTH arms compose the same fold verbatim (one
arithmetic, literally one class; the books it once held were the
in-process arm's own state wearing the judgment's clothes). The
crossing is the BOOK's own read, taken after the letter is appended
(in-process) or the death attributed (durable): the count the
threshold asks already includes the death this pass just wrote, so
a listener at threshold-minus-one quarantines on the death that
arrives, never one earlier -- the in-process equivalence is exact
(attributed-before + 1 >= threshold is attributed-after >=
threshold, because the append adds exactly this letter), and the
durable spelling is STRICTER than the +1 it replaced: a re-drained
entry whose attribution a crashed pass already wrote is counted
once, never twice, where a `+1` judgment would have crossed the
threshold one death early. The re-drain test pins both polarities:
the attribution stays at one, and the containment already recorded
is answered as not newly earned.

## KDD-DQ3 — The death's write ordering: attribute, contain, record, settle

The durable verdict-answer fold composes four writes in one order:
the attribution first (the book's append, idempotent per entry),
the containment act the crossing earned second (only when newly
earned), the dead record third (the operator's whole story), the
settle last (the spent entry's own removal). Every crash window in
the sequence self-heals through the settle that has not happened
yet: the next pass re-takes the unsettled entry, re-attempts it,
re-judges it, and re-answers it -- the attribution lands again as
the idempotent no-op its entry key makes it, the crossing is
re-read from a book that already holds the letter, the containment
already recorded is answered as not newly earned, and the dead
record is rewritten (the same entry identity, the same file)
rather than doubled. Every window costs the duplicate redelivery
the at-least-once doctrine already owns, never a doubled book and
never a lost story. The settlement fold's own encapsulated
orderings (`retry` before `settle`, `dead` before `settle`) are
untouched: the verdict answer composes AROUND them, and the
soundness path (the unresolved identity, the stale shape) still
calls `dead()` directly -- the two causes that are nobody's death
to answer for never touch the books, pinned by tests that assert
the empty attribution after every unsound death.

## KDD-DQ4 — Attribution is idempotent per entry

The in-process book appends a letter per death verdict; the durable
book writes one attribution record per ENTRY identity. The
redelivery arithmetic a crashed pass leaves behind (an entry taken,
attributed, and never settled) re-attributes the same entry on the
next pass -- the same key rewritten, the count unchanged. The file
adapter spells it as the lane's own geometry (`attribution/`
carries one `<entryId>.json` per attributed death, the rewrite the
staging discipline always owned); the Redis adapter spells it as a
hash whose fields ARE the entry identities (HSET rewrites the same
field); the in-memory double keys the same array. The quarantine's
own idempotency is each adapter's native semantics: the file's
one-record-per-identity rewrite, the Redis set's SADD, the
containment act that answers nothing when already contained.

## KDD-DQ5 — The trace is the outcome, timestamped

EA12 spelled the seam ("the counts are the seam -- delivered /
retried / deadLettered / quarantined / pending, exactly as
`DispatchOutcome`'s count is ED9's seam"); this slice answers it:
`DrainTrace` is one completed pass's `DrainOutcome` at the drain's
own now, and `recordDrainOutcome` appends it to the store's journal
-- the file answer one owner-only record per pass under a freshly
minted entry-style name (two passes at the same now are two
records, never one overwritten), the Redis answer one RPUSH onto
the journal list in completion order. The pass's own LAST act is
its trace: a pass that did not complete writes none (an interrupted
pass is a crash window the at-least-once protocol owns, and a
half-pass's counts would be a story the drain never answered
with), and the ordering is pinned by the action ledger (the trace
follows every settle). The trace's JSON spelling is the Event
lane's own encoder -- the same bytes either side of the store
choice, exactly as the entry record is the entry either side of the
store choice -- and the record is written to be read, never
re-decoded by the regime, the dead record's own docblock doctrine
carried to the journal.

## KDD-DQ6 — Each adapter's own lanes, each answer's own honesty

The file adapter answers the books with three new lanes under the
store root, each created owner-only on first use (the dead lane's
own posture carried): `attribution/` (one record per attributed
death, the count a fail-closed scan whose decode names the file and
demands every key of the shape, the depth ceiling the engine's own
decoder family pins -- a record beyond it refused with the engine's
own reason, both sides pinned by test), `quarantine/` (one record
per identity whose EXISTENCE is the filter's own answer -- the
content is the operator's story, and restoring a listener is the
operator's judgment, spelled by removing the record), and `drains/`
(the append-only journal). The lanes are invisible to the entry
book (the scan skips directories by its own is_file discipline, the
`dead/` precedent). The Redis adapter answers with three structures
the key vocabulary names: the attribution hash (fields the entry
identities, values the listener identities -- the count an HVALS
walk), the quarantine set (SISMEMBER the filter), and the journal
list (RPUSH the append, LRANGE the operator's read); the mock
server's own command semantics grew the real spellings of the six
commands (HSET / HVALS / SADD / SISMEMBER / RPUSH / LRANGE) --
the oracle, not a mirror, answering the real semantics every time.
The two-tier test posture carries verbatim: the socket doubles pin
the command streams, the real-server tier pins the cross-process
stories (a second store reads the first store's books; the
quarantine a fresh connection filters by).

## KDD-DQ7 — DU13's lessons, replayed on this slice's own gates

The vacuous-green hazard (DU13's covers-attribution doctrine) never
arrived: every exercised collaborator is named in the covering
tests' `CoversClass` lists from the first run (the lanes, the
journal, the trace encoder, the re-spelled folds), and the scoped
mutation battery answered 340 mutants at MSI 100/100 with zero
escapes only AFTER the round its escapes demanded -- the round's
own lessons: the lane's directory mode is an observable (the
0o700 assertion the dead lane's own test already spelled, demanded
again here), the decode's shape refusal is per-key observable (a
record whose entry id is an integer, whose identity is a boolean,
whose attribution moment is a string -- each refused by its own
test, because a compound `||` the mutant rewrites is only as
observable as its weakest arm), the decode's depth ceiling is
observable only when the JsonException arm carries the engine's own
reason (the arm's message is the test's -- a refusal that hides
behind the shape's message cannot tell the depth error from the
shape error, and both ceiling mutants lived until the message was
split), the retry arm's false is the fold's own contract (an
answer the pass does not read on its retry path is only observable
by the fold's own caller, and the direct test pins it beside the
empty books it leaves), and the decode's answer is the IDENTITY
(the whole decoded record was returned for one key's sake -- an
unread array is an ArrayItemRemoval escape waiting, so the fold
answers the string the count reads and the shape's validation
stays the test's to pin). The three BitwiseOr escapes on the
records' JSON flags joined the equivalence-by-construction ledger
(infection.json5's own essay): the attribution, quarantine and
trace records are integers and grammar-bound ASCII by construction,
so PRESERVE_ZERO_FRACTION has no float to preserve and
THROW_ON_ERROR has no failure to throw -- the same class the
write-length guard and the credential grammar already own.

**The difficulty ceiling forced five more seams, and the stale
report fail-open returned.** The first CI round reddened the
archeology gate with six "Difficulty is too high" findings the
local battery had called green -- because the local gate read the
STALE report the last analysis left behind (the #445 lesson,
replayed: `composer archeology:gate` reads the directory's report,
not the source tree -- the analysis itself must run first, and it
now does before every gate call). The findings the fresh analysis
named, and the seams they forced out: both drain passes' `run()`
spelled past the ceiling the moment the crossing and the trace
joined them (the in-process crossing became
`DrainPass::crossesTheThreshold()` -- the book's own read, named
for the question it owns; the durable pass's tail became
`DurableDrainJournal`, the pass's own last act as its own fold),
the attribution lane's first spelling held the write, the scan,
the read and the decode on one class at 31.35 (the entry record's
own family geometry answered it: `DeliveryAttributionEncoder` the
writing half, `DeliveryAttributionDecoder` the reading half,
`DeliveryAttributionLane` the where-and-how-written,
`DeliveryAttributionScan` the count -- four questions, four
seams, the `DeliveryDueScan` mirror), and the Redis store's books
became `RedisDeliveryBooks` (the attribution hash, the quarantine
set and the journal list -- the store back to the entry lifecycle
it owns). The lesson generalises #445's: a class aggregate does
not shrink when a method merely moves -- the seams must split the
QUESTIONS, and the family's own geometry (the entry book's
encoder/decoder/scan/lane quartet) is the map the books' family
now mirrors.

## KDD-DQ8 — Named follow-ups (the honest scope ledger)

- **The RabbitMQ and Kafka answers** — the bus transports after
  Redis, each over the same port with its own claim semantics
  (DR8's own ledger, carried).
- **The PDO answer** — the database family's own store over the
  same port, with the transactional/outbox lane it feeds (DU12's
  fourth bullet, the EntityManager port).
- **The `queue/` storage lane + the module `events` knob** — the
  wired default and its configuration reader, landing with the
  module system's own slice (DU10's census-moment doctrine,
  carried verbatim through three ledgers now).
- **A drain-scheduling policy** stays deliberately absent (DU12's
  own closing bullet, carried verbatim): the host owns time and
  therefore owns the schedule.
- **The journal's read verbs** stay deliberately absent: the
  operator reads the lanes and the list directly, the dead lane's
  own doctrine -- a read verb would be a second door to a room the
  operator already holds the key to.
