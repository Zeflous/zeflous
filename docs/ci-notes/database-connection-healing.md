# Database connection healing and the pooling question — the decision record

**Status:** the design record landed first (draft #705, codeless, so the
implementation arrived against a written contract); the KDD numbering
below is the implementation's refinement of that record, arrived with
the code.

## The slice in one paragraph

The open ledger's item — "the pooling posture the lazy-handle truth
documents" — was TWO questions bundled under one word, and the record
split them apart before any code existed: **(a) mid-flight
lost-connection healing**, this slice's landed scope, and **(b) socket
pooling**, parked with the opening condition that would justify it
named. The lazy handle's own truth (`PdoHandle`, the lazy singleton
KDD-DW1 spelled: one socket per connection identity, materialised at
first use) is the honest baseline both questions are asked against.
The landed answer to (a) is one fold, `PdoHealing`, bracketing every
ask verb of `PdoConnection` — the adapter's verbs, the port's
contract, nothing widened.

## KDD-CH1: the verdict — heal-and-propagate, never blind replay

On a classified lost-connection failure the ask's socket is **dropped**
(`PdoHandle::drop()`: the handle returns to null, the lazy
singleton's own `??=` doctrine replayed at heal time — the next ask
re-materialises over the same connection identity), the savepoint
depth **resets with it** (`PdoTransactions::reset()` →
`PdoTransactionDepth::reset()`), and the classified exception
**propagates untouched** — the SAME instance the ask threw, never
wrapped, never swallowed, never converted into a success. The caller's
retry policy (the UnitOfWork's, or the host's own) decides whether the
NEXT ask lands: an adapter-side blind replay of an `INSERT` can
double-write, and the tree never answers a question that dangerous by
default. Healing is about the next ask landing fresh, not about
pretending the failed one succeeded — Laravel's `DetectsLostConnections`
posture (classify, disconnect, let the caller's retry land) translated
into this tree's vocabulary.

## KDD-CH2: the evidence walk — raw or one-hop chained, never deeper

The fold's classification reads the driver failure the adapter
actually saw: the thrown throwable itself when it IS a
`PDOException` (the identity verb's raw escape — `PdoIdentity` maps
nothing, so a dead socket's `lastInsertId()` surfaces the driver type
as-is), else its **one-hop previous** when that is a `PDOException` —
exactly the evidence the typed family's mapping contract chains
(`PdoStatementRun`/`PdoPrimitive`/`PdoConnect`: every PDOException
mapped with the original as previous). The walk stops there,
deliberately: the adapter's own mapping is one hop by construction, and
a causality walk past it would classify STALE evidence — a domain
exception chaining a PDOException from a failure long since healed
would drop a healthy socket on a ghost. The raw-evidence arm also
carries the honest lanes the typed wrappers lose: the string SQLSTATE
spelling (the `(int)` cast in the mapping folds a Postgres `'08006'`
into a lie) and the `errorInfo` slots only a `PDOException carries.

## KDD-CH3: the lost-connection subset — and the deadlock boundary

The verdict runs on the engine-side taxonomy reached one-way from the
adapter (`RetryTaxonomy::matches()` over `RetryCandidates` and
`RetryMessages` — the same one-way reach the audit emission's meter
contract carries), over the LOST-CONNECTION SUBSET of the taxonomy's
evidence: the code lane `['2006', '1040']` (CR_SERVER_GONE /
CR_SERVER_LOST, and the server shedding the connection under load)
plus the message lane spelled ONCE —
`UnitOfWorkRetryPolicy::DEFAULT_RETRYABLE_MESSAGE_PATTERNS`, the
field-proven fifteen the retry policy's default already owns, reused
by reference so the two spellings of "the socket died" can never drift
apart. The deliberate boundary: the retry taxonomy's deadlock, lock
and serialization codes (40001, 40P01, 55P03, 1213, 1205) do NOT heal
— those failures leave the socket alive, the caller's rollback story
and retry verdict own them, and dropping a healthy handle for them
would reconnect for nobody. The retry question ("is it worth trying
again?") and the heal question ("is the socket gone?") are different
verdicts over overlapping evidence; only the second one drops
anything. The libpq family needs no code-lane entries of its own: the
message lane answers every one of its spellings.

## KDD-CH4: the depth resets with the handle — the phantom-level refusal

A socket lost inside a transaction means the transaction is GONE — the
engine's own rollback-on-disconnect already ended it, there is nothing
left to climb down from. A depth that survived its own handle would
answer every later `beginTransaction` a phantom level: at phantom 3 a
new begin issues `SAVEPOINT zef_sp3` over a socket that never opened
one, and a commit at phantom 2 releases a savepoint instead of
committing — the guard vouching for a level no socket carries. The pin:
a socket lost at depth 2, healed, answers the next `beginTransaction`
level 1, never a phantom 3 — and because the reset rides the SAME
classified verdict as the drop, an unclassified failure (a deadlock,
a syntax error) leaves the depth exactly where the caller's rollback
story expects it.

## KDD-CH5: the bracket's reach — seven verbs, and the one outside

Every verb that asks the socket rides the fold: `execute`,
`fetchAll` (fetchOne riding it), `lastInsertId`, `beginTransaction`,
`commit`, `rollBack`, and `transaction` — the last bracketed as a
whole, so the orchestration's own begin/commit/rollback asks heal too
(a socket that dies between the closure's end and its commit is the
managed case the outer bracket owns: the mapped `TransactionException`
chained over the classified original heals, the depth resets from 1,
the next begin lands fresh). `transactionLevel()` sits deliberately
outside the bracket: it reads the depth fold and never touches a
socket — no ask, no socket, no heal. The brackets nest honestly (the
closure's inner asks each bracketed inside the `transaction()`
bracket): the inner heal drops and resets, the outer re-classifies the
same evidence and re-runs the same two no-op actions — idempotent by
construction, because a dropped handle stays dropped and a reset depth
stays zero.

## KDD-CH6: the unwind interplay — the level-zero guard, the named open edge

When the closure's own ask heals, the unwind's rollback answers the
depth it finds: level zero, because the heal already reset it — so
`PdoTransactionRun`'s catch-propagation surfaces the level-zero
guard's `rollBack() called outside a transaction.` in place of the
closure's original throwable. That replacement is the pre-existing
shape the unbalanced closure (one that committed or rolled back its
own verbs) always answered — the heal case reaches the same honest
guard because the transaction the engine already ended IS gone.
Distinguishing the two callers of the guard would take a healed-flag
the record's scope refuses: a state-tracking seam to preserve a
message. The guard's exception still propagates through the outer
bracket unclassified (code 0, no pattern) — and the connection it
leaves behind is healed: level zero, the next begin landing fresh.
Recorded here as the slice's open edge, pinned by test exactly as it
answers.

## KDD-CH7: question (b) — pooling, parked with the opening condition named

PHP's shared-nothing request model gives a web request nothing to
pool: one connection per identity per request is already the honest
shape, and the persistent worker (the event drain lane) already holds
its connection through the same lazy singleton. A pool answers
CONCURRENT use of one identity from one process — a question no lane
currently spells. The parking rests on the doctrine every knob
carries, extended to sockets: **a pool with no concurrent consumer is
dead weight the tree would maintain for nobody.** The opening
condition is named, not scheduled: a parallel-consumer lane (a worker
pool over the process-parallel runtime the dev tree already carries)
arriving with a real concurrency requirement re-opens question (b) as
its own slice, over its own record. The heal changes nothing about
this verdict: a pool of dead sockets heals one socket at a time.

## KDD-CH8: what this slice deliberately does not carry

Blind replay (refused in KDD-CH1, permanently); a ping/keepalive verb
(a socket proven alive one millisecond ago can still die — the honest
answer to a stale socket is the heal, not the prophecy); pooling
itself (question (b), parked in KDD-CH7); per-ask connection
factories (the transient-forbidden doctrine KDD-DW1 already spelled
for connections); the `08*` SQLSTATE class and the Postgres shutdown
codes in the code lane (the message lane answers every libpq spelling
the record's references carry — a code-lane widening is its own
refinement over its own evidence); a deeper causality walk (refused in
KDD-CH2); and an unwind-skip verdict for the healed closure (the
open edge KDD-CH6 records, reachable honestly through the pre-existing
guard).

## Verification

- `PdoConnectionHealingTest` — nine tests, the design record's three
  pins and the boundaries around them: the code lane (2006, the
  patternless spelling; 1040), the message lane (the codeless libpq
  spelling), the depth-reset pin (lost at depth 2 → new begin answers
  level 1, never a phantom 3), the propagate-untouched pin over the
  raw identity escape (assertSame on the thrown instance) and the
  mapped escapes (assertSame on the chained original), the deadlock
  boundary (no heal: the depth stays 2, the next ask still reaching
  the same handle), the managed commit death (heal inside the
  transaction bracket, the next begin landing fresh, the steered
  identity answer proving the re-materialised socket), the closure's
  domain throwable (no driver evidence, no heal — the steered `'777'`
  proving the preset handle still held), and the unwind interplay
  (the level-zero guard, pinned exactly).
- The dead-socket arm on `RecordingPdo`: sticky by construction (every
  driver ask on the double throws the same armed instance — prepare,
  exec, the three primitives, the identity verb), because a socket
  that died mid-flight stays dead: only a test whose SECOND ask lands
  on a re-materialised handle can ever observe a success after it.
- Scoped mutation round over the five touched source files: **76
  mutants, 76 killed, MSI 100/100, zero escapes, zero new ignore
  entries, zero new baseline entries.** The kill tests declare
  `CoversClass` over every class a mutant can land in — the
  attribution lesson the query-logging round paid for.
- Full battery on the branch head: 7177 tests / 17450 assertions;
  coverage 98.59% (the five touched classes at 100%); phpstan 0
  errors; psalm 0 errors; deptrac 0 violations (the fold's one-way
  reach rides the lane's existing Adapters → Database allowance);
  archeology 0 error-level findings; the formatter, phpcs and rector
  all settled; zero-deps, smoke (2760 bytes / 20000 iterations),
  workflow-concurrency and the roadmap gate green.
