# The Database Orchestration — KDD

**Scope:** the persistence lane's orchestration family over the #665 port —
the recorded "NEXT slice's port" KDD-DB8 named and this slice answers:
`TransactionManagerInterface` with its `TransactionManager` (managed
scopes + after-commit hooks), `UnitOfWork` with its opt-in
`UnitOfWorkRetryPolicy` (the deferred-write queue and its retrying
replay), `Migrator` with `MigrationInterface` (versioned migrations over
the single-row lock machine), and the `Repository` table-data-gateway
base — all under `src/Zef/Framework/Database/`, the lane's own
config-blind posture unchanged (`Database: [Exception]`, no deptrac
widening needed), branch `feat/database-orchestration`.

**Attribution:** the reference repository's own v2.22.x family
(`reference/zeflous-framework`, v2.22.0 "Transaction orchestration &
UoW-lite" and v2.22.1 issue #65 items 2–3 — the retry policy and the
cooperative slow-hook guard): the port signatures, the hook lifecycle's
semantics, the lock walk's TTL authority, the retry taxonomy's
SQLSTATE codes and the messages are the reference's, adapted to this
tree's disciplines below. The reference's `PdoJobQueue` (its v2.30.0)
is NOT carried: this tree has no Job lane, and the adapters lane's
recorded follow-up discipline keeps each future adapter its own slice.

**Verified locally before the PR:** the full `ci:static` battery green
(6630 tests / 16066 assertions; smoke 2760B/20000 iterations; workflow
concurrency 23; roadmap:gate pass; audit 0 advisories; duplication and
mess gates clean) · psalm EXIT-0 over a regenerated baseline (the
orchestration family joins the "consumer arrives with the wiring"
ledger the port carried — five new entries) · progpilot `[]` ·
archeology 0 error-level findings after SIX settle rounds (15 → 12 → 8
→ 4 → 2 → 0) that KDD-DO5 records · coverage 98.53% against the 90%
floor (the lane's own aggregate 100% on every new fold) · scoped
infection over the Database lane with the full suite as the killer,
MSI 100/100 (921 mutants: 918 killed, 3 timeout-detected, 0 escapes,
12 documented equivalents in the ledger KDD-DO6 records).

## The map

| Unit | Question it owns |
|---|---|
| `TransactionManagerInterface` | the orchestration port: `withTransaction` (nesting collapses into savepoints), `afterCommit`, `inTransaction`, `level` — the two capabilities the raw primitives deliberately do not provide |
| `TransactionManager` | the port's default answer: every verb a thin delegation over the scope state `TransactionHooks` owns, the transaction mechanics delegated to the connection primitive |
| `TransactionHooks` | the managed scope's own state: the scope depth, the FIFO hook queue across the nested scope tree, the clear-before-drain discipline, the inline-while-flushing mode, the failed-scope discard |
| `HookDrain` | the drain walk itself: every queued hook in queue order, the cooperative slow-hook timing when a threshold is configured |
| `HookDuration` | the slow-hook guard's two pure judgements — the nanosecond-to-millisecond fold (ceil) and the strict threshold comparison — pinned at their exact boundaries, a boundary no sleep-based test can spell |
| `UnitOfWork` | the UoW-lite facade: record/recordQuery/flush/flushRetrying/pending/discard, every verb a delegation over the queue and the two flush folds |
| `UnitOfWorkQueue` | the queue's own state: the mid-flush recording refusal, the two arm spellings (take-and-clear for the plain flush, preserve-until-success for the replay), the disarm the finallys walk |
| `UnitOfWorkFlush` / `UnitOfWorkReplay` | the FIFO execution walk; the retrying replay under the policy with the backoff waits read through the sleeper seam |
| `UnitOfWorkWait` | the default blocking wait behind the seam — the engine's own microsecond sleep over the millisecond backoff |
| `UnitOfWorkRetryPolicy` | the opt-in retry knobs (validation in the `RetryBackoffPolicy` posture) with the transient-failure taxonomy delegated |
| `RetryTaxonomy` / `RetryCandidates` | the taxonomy's judgement (class-name match, SQLSTATE match over a non-empty filter); the candidates fold — getCode() plus the errorInfo slots, every spelling normalised to string (the MySQL int driver code) |
| `RetryBackoff` | the exponential backoff compute: the multiplier's exponent, the cap, the jitter |
| `MigrationInterface` | one versioned migration's contract: version/name/up/down/getLockTtl |
| `Migrator` | the runner's facade: register/applied/pending/plan/migrate/rollback, the two walks lock-scoped through the lock machine's own `scoped()` |
| `MigrationRegistration` | the registration grammar: the 14-digit version stamp, the 1..128-**byte** name corridor, the duplicate guard — booking the accepted map |
| `MigrationBookkeeping` | the `zef_migrations` table: schema, the ascending applied read (fail-closed on a malformed row), the apply/rollback row statements |
| `MigrationPending` | the registered-minus-applied walk, ascending, and the plan's own version spelling |
| `MigrationStep` | the transactional apply/revert: the migration's own body and its bookkeeping row commit or roll back together |
| `MigrationRun` | the two lock-scoped walks over the runner's fixed context (connection, lock, clock, default TTL) — the migrate loop and the rollback loop, each step preceded by the heartbeat |
| `MigrationLock` | the single-row lock machine: the held flag, the reentrancy guard, the heartbeat renewal, the release no-op — the acquire/steal walk delegated |
| `MigrationAcquire` | the acquire walk: ensure the table, claim the row, and when the claim is refused, decide the steal and take over |
| `MigrationLockRow` | the lock row's verb fold: the table's schema, claim/rewrite/remove/read — pure statements, no judgement |
| `MigrationLockAge` | the steal judgement: the age the injected clock answers against the row's own decoded TTL (`decodedTtl` the non-numeric string folds to zero), the malformed row the runner halts on |
| `MigrationTtl` | the TTL corridor: the runner default's positivity, the effective per-step TTL (the migration's override validated positive) |
| `MigrationRollback` | the target selection (newest first, at most $steps, the too-few refusal) and the registered-migration lookup behind an applied version |
| `MigrationException` | the runner's declaration/rollback refusals — the `ModuleException` posture, a new member of the lane's typed family |
| `Repository` | the table-data-gateway base: insert/find/findOneBy/findBy/count/exists/update/delete, each a delegation over the three gateway folds |
| `RepositoryCriteria` / `RepositoryAggregate` / `RepositoryPage` | the criteria walk (null cells spell IS NULL); the COUNT spelling over the `SqlExpression` escape hatch and its numeric decode; the order map and the limit/offset pair the builder's own verb spells |

## KDD-DO1: the orchestration layering — state owns one place, walks own none

The reference's `TransactionManager` carried scope depth, hook queue
and the drain loop in one class; this tree answers the same behaviour
the way the #665 settle rounds taught: `TransactionHooks` owns the
state (depth, queue, flushing), `HookDrain` owns the walk, `HookDuration`
owns the judgement, and `TransactionManager` shrinks to the port's
four verbs as thin delegations — the facade doctrine the query
builder's own KDD-DB6 recorded. The same split replays across the
family: `UnitOfWork` delegates to `UnitOfWorkQueue` + `UnitOfWorkFlush`
+ `UnitOfWorkReplay`; `Migrator` delegates to seven folds and owns only
the registered map; the `Repository` gateway delegates to three. Every
fold answers one question, and the archeology ceiling held every one
of them under 20.

## KDD-DO2: the honest divergences from the reference

The reference's API surface met this tree's lean verb doctrine
(KDD-DB3) and the adapter's fold spellings, and the port adapted
rather than re-imported:

- `insert($row)` became `insertRows([[$row]])` — the one-row list the
  builder's own verb spells; `count()` travels as the explicit
  `SqlExpression('COUNT(*) AS aggregate')` the escape hatch owns, its
  alias pinned by the decode fold;
- `limit($l)` + `offset($o)` ride `limit()`'s own parameter — the
  paging fold's pairing invariant, never a standalone `offset()` verb;
- the `Repository` constructor validates its table through
  `SqlIdentifier::table()` (the same refusal the builder spells), and
  the unbounded-write guards (`update()/delete()` without criteria,
  the offset without a limit) are typed `QueryException` — the lane's
  family, not the reference's bare `InvalidArgumentException`;
- `Migrator`'s injected clock keeps the `?Closure(): int` spelling the
  telemetry clock family already carries, and `time(...)` the
  first-class default.

## KDD-DO3: the exception taxonomy — three posts, each its own failure story

The reference threw `InvalidArgumentException` and `RuntimeException`
from the runner's refusals; this tree splits them by the failure they
tell: **`MigrationException`** (new, the `ModuleException` posture —
a runner refusing a declaration it cannot book honestly: the version
grammar, the name corridor, the duplicate, the TTL corridors, the
rollback guards, the unregistered version) joins the lane's typed
family over the framework root; **lock misuse stays
`TransactionException`** (the reentrancy, the live holder) and
**malformed bookkeeping stays `QueryException`** (the non-string
version, the corrupt lock row) exactly where the #665 family put
them; and **the retry policy's knob validation keeps
`InvalidArgumentException`** — the telemetry `RetryBackoffPolicy`
precedent, a value object's own corridor, not a lane failure. The
byte-corridor guard itself carries the KDD-DB5 lesson forward: the
1..128 ceiling counts BYTES in the `mb_strlen($name, '8bit')`
spelling the automated migration cannot rewrite again, pinned by the
65-two-byte-character boundary test.

## KDD-DO4: two seams the suite observes without waiting

The retrying flush's backoff wait and the slow-hook guard's timing
are the two places the family blocks or measures, and both answer the
seams this tree already owns: **the sleeper seam** —
`UnitOfWork::__construct(?Closure $sleeper)` with
`UnitOfWorkWait::block()` the default, the `BatchSpanProcessor`
precedent — so the suite asserts the backoff's own delays
(`[100, 200, 250, 250]`) without sleeping them, while a 50 ms
elapsed floor on the default seam kills the `usleep` removal; and
**the pure timing fold** — `HookDuration::milliseconds()` (ceil,
never floor) and `exceeded()` (strict at the boundary) — pinned at
their exact edges by their own boundary battery, because no
sleep-based test can spell "one nanosecond past a millisecond" or
"exactly on the threshold" deterministically.

## KDD-DO5: six settle rounds — the ceiling's curriculum, replayed

The first spelling reddened the archeology gate 15 times (the whole
family over the ceiling at once), and the rounds each taught the
#665 lessons again:

1. The reference's one-class `Migrator` (306 lines) reddened at
   difficulty 31.37 — the fluent-API anti-shape is the god-class
   anti-shape; folding the registration grammar, the bookkeeping and
   the lock machine out was the first move.
2. The **circular dependency** `Migrator ↔ MigrationLock` (the
   `LOCK_TABLE` const the lock machine read off its consumer) fired
   the container-namespaces rule again — the const moved to the row
   fold that owns the table, `MigrationLockRow::LOCK_TABLE`.
3. `$this` repetition is the operand tax (KDD-DB6's own lesson): the
   run folds' six-argument closures halved their `$this` reads when
   `MigrationRun` became a service over the runner's fixed context,
   and `Migrator` fell 24.92 → 22.00 only when the closures shrank to
   one argument each.
4. **Class difficulty is aggregate volume, not method complexity** —
   every `Migrator` method measured under 11 while the class sat at
   22: the operand count pools across methods, so the fix was fewer
   callees and fewer `$this` reads per verb, not simpler bodies. The
   clock property became a constructor local; `applied()`'s
   ensure-table walk folded into `readApplied()`; `plan()`'s
   `array_map` closure moved to the pending fold's own `versions()`.
5. The try/finally lock scoping moved INTO the machine
   (`MigrationLock::scoped()`), and the enter/atOutermost pair merged
   into one `enter(): bool` — two more distinct callees gone.
6. `TransactionHooks` drifted over the ceiling twice (22.75, then
   21.39 after rector's renames) — the drain loop moved to
   `HookDrain` and the scope accessors merged, and the family's last
   finding closed at 19.44.

The final map: the lane's biggest orchestration class is the
`Repository` gateway at 18.50, every fold under 20 — the same shape
the Redis delivery family and the #665 core recorded.

## KDD-DO6: the mutation round — 46 escapes answered, 12 documented

The first scoped run answered 921 mutants with 46 escapes; the settle
took three rounds and left **0 escapes over 918 killed + 3
timeout-detected**, with **12 documented equivalents** in the
infection ledger:

- **The killers** (thirty-four mutants): the scripted connection's
  verb stream now records fetches beside executes, so the CREATE-TABLE
  statements and the single-row selects pin **verbatim** (the Concat
  rewrites and the `limit(1)` boundaries die on exact-SQL assertions);
  the lock machine's no-op guards assert **no statement issued** (the
  ReturnRemovals die); the name corridor's byte boundary, the
  single-attempt and equal-cap policies, the fractional backoff
  (37.5 → 38, 56.25 → 56: round, neither floor nor ceil), the
  300-draw jitter corridor (both edges of a one-millisecond jitter —
  the negotiation mutant, the zero-arm exclusion and the below-initial
  dig all die), the non-numeric-string TTL steal (the engine's own
  `(float)` coercion folds garbage to zero), the mid-replay recording
  refusal, the re-flush after a failed replay (the finally and the
  disarm die), the driver-code-alone taxonomy arms, the
  empty-string-state and slot-after-skip walks, and — the round's own
  lesson — **the strengthened nested-scope assertion**: the reference's
  test asserted the FIFO order after the outermost commit, which an
  inner-boundary drain satisfies too; asserting that NO hook flushes
  at an inner scope boundary kills the `committed()` return-removal
  the weaker test could never see.
- **The documented equivalents** (twelve, each with the in-file essay
  the closed exclusion set demands): the two analyser-only `hrtime()`
  narrowing casts in `HookDrain::run` (the `TelemetryClock` doctrine —
  int always at runtime); the age comparison's operand cast in
  `MigrationLockAge` (**line-scoped** through
  `ignoreSourceCodeByRegex`, because the decode fold's own cast one
  method down IS killable — the non-numeric-string test — and a
  method-scoped entry would have swallowed it: the round's
  no-hiding-killable-mutants discipline, verified by diffing the
  killed lists between the ledgered and ledger-free runs); the TTL
  spelling cast (the KDD-DB9 concat/rector intersection); the name
  corridor's `mb_strlen('8bit')` (the `SqlIdentifier::quote` twin);
  the backoff's exponent cast (both casts of that line verified by
  running the complete suite under each manually applied removal);
  the jitter gate's zero boundary (`random_int(0, 0)` adds exactly
  zero — the negotiation mutant beside it dies); the drain's and the
  two arm spellings' empty-queue early returns (the fall-through
  answers the same zero through the caller's own check); and the
  blocking wait's ±1 microsecond-per-millisecond (the scheduler's own
  slack dwarfs it — the `usleep` removal itself dies on the 50 ms
  floor).
- **The three timeout-detections** are the replay loop's own mutants
  (`UnitOfWorkReplay`): removing the return or flipping the
  catch-arm's condition sends the retry loop around forever, and the
  timeout IS the kill.

## KDD-DO7: the covers-filter doctrine, replayed with its own lesson

Every test names every fold it executes (the SonarCloud new-code
condition reads the Clover the `CoversClass` metadata filters) — and
the round's new lesson: **an interface is not a valid coverage
target**. `MigrationInterface::class` and
`TransactionManagerInterface::class` in the covers blocks made PHPUnit
refuse the run outright ("not a valid target for code coverage"), so
the attribution names the implementing class and the concrete folds
the tests execute — thirty-four `CoversClass` attributes across the
nine test files, and the lane's aggregate answered 100% on every new
fold with the corridor's one uncovered `continue` line closed by the
sparse-slot battery.

## KDD-DO8: what this slice deliberately does not carry

The named follow-ups, each its own slice: the **configuration knob
block** (the `database` family over `ConnectionConfig::fromArray`, the
`FrameworkDefaults` registration, the per-purpose connections the
ROADMAP spells) — rule 17's config integration lands with the first
live reader, the same census-moment doctrine every opt-in family
answered, and with it the psalm baseline's five new
"consumer-arrives-with-the-wiring" entries leave the ledger; the
**unbounded-statement audit emission** the reference's adapter carried
over optional meter/logger seams (the wiring slice's question, the
`SqlQuery::unbounded` flag already travelling); the **condition-object
API** the where-family's exotic forms return with; and the reference's
v2.30.0 `PdoJobQueue` family — no Job lane exists in this tree. The
error-system integration IS here: the family's failures are the lane's
typed hierarchy over the framework root, and the boundary renders
them — nothing hand-built anywhere in the family.
