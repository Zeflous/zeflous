# The Database Core — KDD

**Scope:** the persistence lane's port side and its first adapter, the
ROADMAP's own "Database Layer Adapter" opened the way the adapters lane
records it ("the view engine and database adapters remain not started,
each over its own port in its own slice"): the `ConnectionInterface`
port with its nested-transaction and isolation semantics, the value
vocabulary the queries travel as (`SqlQuery`, `SqlExpression`,
`IsolationLevel`), the query builder over the identifier grammar, and
the PDO answer (`PdoConnection` + `ConnectionConfig`) under
`src/Zef/Framework/Adapters/Database/`, deptrac gaining the `Database`
layer (`[Exception]`, the lane-local family posture) and `Adapters +=
Database`, branch `feat/database-core`.

**Attribution:** the reference repository's own database core
(`reference/zeflous-framework`, its v2.18.0 "Database Core: query
builder, PDO adapter, migrations" and the v2.22.x orchestration that
followed) -- the port signatures, the builder's refusal messages, the
config's validation matrix and the PDO adapter's SAVEPOINT walk are the
reference's, adapted to this tree's disciplines below; the orchestration
half of the reference family (TransactionManager, UnitOfWork, Migrator,
Repository) is the NEXT slice's port, deliberately not mixed into this
one.

**Verified locally before the PR:** `ci:static` EXIT-0 (6275 tests /
15030 assertions; smoke 2760B/20000 iterations; workflow concurrency 23;
roadmap:gate pass; audit 0 advisories) · coverage 95.78% against the 90%
floor · psalm EXIT-0 over a regenerated baseline (the lane awaits its
composition slice, so the port's verbs and the adapter's entries ride
the same "consumer arrives with the wiring" ledger every opt-in lane
before it has carried) · deptrac 0 violations over the two widenings ·
archeology 0 error-level findings after SEVEN settle rounds (25 → 24 →
13 → 8 → 6 → 3 → 2 → 0) that KDD-DB6 records in full · scoped infection
over both new directories with the full suite as the killer, MSI 100/100
(124 mutants in the lane, 15 in the adapter, 0 escapes) after the
mutation round KDD-DB7 records · bench subjects for the builder's three
shapes (select 21.6μs, insert 6.7μs, update 8.1μs per build).

## The map

| Unit | Question it owns |
|---|---|
| `ConnectionInterface` | the outbound SQL port: execute/fetch verbs, lastInsertId, the nested-transaction primitives with savepoint semantics, isolation honoured only at the outermost level, `transaction()` rolling back on any throwable |
| `DatabaseException` + `QueryException` / `ConnectionException` / `TransactionException` | the lane's typed family over the framework root -- the `ConfigException` posture (a lane with a failure story owns one family), never a raw driver type leaking |
| `SqlQuery` | the immutable statement record: non-empty SQL, positional params that must be a list, the `unbounded` flag a confirmed WHERE-less write carries |
| `SqlExpression` | the ONLY raw-SQL escape hatch -- greppable, never quoted, never parameterised |
| `IsolationLevel` | the four SQL tokens `SET TRANSACTION ISOLATION LEVEL` spells |
| `SqlIdentifier` | the quoting grammar: strict `[A-Za-z_][A-Za-z0-9_]{0,63}`, at most one dot in a path, the BYTE ceiling (the ceiling's own rector story under KDD-DB5) |
| `SqlColumn` | the column-list spelling: `col`, `table.col`, `path AS alias` (anchored both sides), or a raw expression verbatim |
| `QueryBuilder` | the fluent facade: the verb surface, one delegation per verb over the folds below |
| `QueryPhase` | the phase vocabulary and its three guards -- select-side verbs refused on write queries, write types never mixed, WHERE refused on an insert |
| `QueryColumns` / `QueryAggregate` / `QueryPaging` / `QueryJoin` / `QueryOrder` | the select-side renderings and their closed vocabularies |
| `QueryValues` / `QueryPredicate` | the bound-value answer set and the predicate grammar answering one `QueryFragment` each |
| `QueryWhereState` / `QueryWhere` | the fragment list with its AND/OR joining, and the verb fold recording predicates onto it |
| `QueryHaving` | the aggregate-filter state, its params kept SEPARATE so the compiled statement binds in textual order (the reference's call-order binding KDD-DB2 records) |
| `QueryInsertRows` / `QueryUpdatePairs` | the write payloads' shape validation |
| `QuerySnapshot` / `QueryBuild` / `QuerySelectAssembly` / `QueryInsertAssembly` / `QueryUpdateAssembly` | the collected state at build time, the phase dispatch, and the three statement assemblers |
| `ConnectionConfig` | the validated configuration VO, a thin composition over its reading folds; the DSN delegated to `ConnectionDsn` |
| `ConnectionFields` / `ConnectionHost` / `ConnectionPort` / `ConnectionOptions` / `ConnectionOptionKeys` | the configuration map's strict readings, each refusal naming its field |
| `PdoConnection` | the adapter's port verbs, one delegation each over its folds |
| `PdoHandle` / `PdoConnect` | the lazy handle: no socket touched for configuration errors, the driver attributes armed once |
| `PdoStatementRun` / `PdoRows` / `PdoIdentity` | the statement's prepare/execute mapping, the FETCH_ASSOC answer's narrowing (the engine contract enforced, not assumed), the last-insert identity fold |
| `PdoTransactions` / `PdoIsolation` / `PdoTransactionRun` | the savepoint machine (MAX_NESTING 16, `PDO::inTransaction()` never consulted), the isolation-request rules, the `transaction()` closure's four-step orchestration |

## KDD-DB1: the port-first split the lane's own docblock promised

The reference family is one flat namespace; this tree answers the same
promise the event-durable lane made: the PORT and its vocabulary live in
the lane (`src/Zef/Framework/Database/`, config-blind, `[Exception]` the
only dep), the ADAPTER lives under `Adapters/Database/` behind the
one-way widening (`Adapters += Database`) -- the hexagonal direction the
deptrac comment records for every adapter before it.

## KDD-DB2: the having params' textual order -- an honest divergence

The reference bound every value into ONE param list appended at CALL
time, so a `having()` spelled before a `where()` bound its value before
the WHERE's -- a mismatched-position bug the single list could not see.
`QueryHaving` keeps its own params and `QuerySelectAssembly` binds
`[...where, ...having]`: every statement binds in the order its SQL
spells, whatever order the caller spelled the verbs. The reference's
semantics are preserved everywhere they were correct; this is the one
place they were not, and the divergence is the fix.

## KDD-DB3: the lean verb surface (the roadmap's own word)

The facade ships the core verbs -- table, select, distinct, count, join,
groupBy, having, orderBy, limit, offset, the where family, insertRows,
update, delete, allowUnbounded, build/toSql/getBindings. The sugar the
reference carried -- `selectRaw`, `aggregate` beyond COUNT, `leftJoin`,
single-row `insert`, `whereColumn`, `whereNested` -- is expressible
through the SqlExpression escape hatch, the join type parameter, or the
one-row list (`insertRows([[$row]])`), and whereNested's group form
returns with the condition-object API the Domain-Driven direction
eventually wants (a named follow-up, not a loss: the reference's tests
for the sugar became this tree's tests for the escape hatch that
replaces it). The ROADMAP's own "query builder sederhana" is the
mandate; KDD-DB6 is the ceiling that enforced it.

## KDD-DB4: the engine contracts are enforced, never assumed

Two places where a driver could break a documented contract are refused
loudly instead of trusted: `PdoRows::narrow()` halts on a non-array row
or a non-string column key under FETCH_ASSOC (the `DeliveryRecordValues`
walk's own discipline over its decoder's answer set), and
`PdoStatementRun::prepare()` refuses the falsy spelling the engine
signature still allows even though ERRMODE_EXCEPTION throws instead.

## KDD-DB5: the byte ceiling's rector story

`SqlIdentifier`'s 64-byte ceiling counts BYTES (`strlen`) -- the
identifier grammar is ASCII by construction, but a multibyte identifier
of 33 two-byte characters sits at 66 bytes and must meet the ceiling's
own message, not the grammar's. An automated migration pass rewrote the
check to `mb_strlen` (characters) -- silently widening the ceiling for
multibyte input -- and the boundary test (`got 66`) pins the byte
spelling back. The lesson joins the MBString ledger's existing class:
every length ceiling over an ASCII-by-construction grammar is a BYTE
ceiling, and the mutant that flips the spelling is killed by a
multibyte boundary that lands between the two counts.

## KDD-DB6: seven settle rounds -- the ceiling's own curriculum

The archeology gate reddened the first spelling 25 times. The rounds,
each recorded because each taught a different fold:

1. The reference's one-class builder (730 lines) reddened at difficulty
   50.28: the fluent facade itself is the anti-shape.
2. Folding the guards and renderings out halved it -- and taught that
   the metric is `D = (unique operators / 2) × (operands / unique
   operands)`: operator VARIETY and operand REPETITION, not method
   count (a 19-method class with no branches answers CC 2).
3. `$this` repetition is the fluent API's tax -- every `return $this`
   is an operand occurrence -- so the facade's verbs became pure
   two-line delegations and the guards moved into `QueryPhase`.
4. Named function calls are DISTINCT OPERATORS: `sprintf` replacing a
   concat RAISED one fold's difficulty (the concat kind already served
   three messages, the sprintf kind was new). The reverse lesson -- a
   fold with many distinct builtins (`is_string`, `preg_match`,
   `array_merge`...) is a fold waiting to redden.
5. The FIRST-CLASS CALLABLE (`$pdo->commit(...)`) added the spread kind;
   the closure it buys (phpstan's `method.dynamicName` satisfied without
   a suppression) costs one archeology kind -- paid for by the
   `=== MAX_NESTING` ceiling spelling the ±1 walk proves equivalent.
6. Constructors are exempt from the parameter ceiling (a 13-prop
   snapshot rides the `ServerRequest` precedent), which is what lets
   `QuerySnapshot` hand the assemblers the whole collected state.
7. The circular-dependency rule the container namespaces lane already
   recorded fired again: the driver vocabulary moved to the reading fold
   that consumes it (`ConnectionFields::DRIVERS`), because a const
   belongs to the declaration that references it.

The final map: the lane's biggest class is the facade at difficulty
17.20, every fold under 20, the same shape the Redis delivery family
recorded ("every fold under the 20 ceiling, the seams the ceiling forced
out").

## KDD-DB7: the mutation round

First run: 19 escapes over 84.92% MSI. The kills:

- FOURTEEN phase-guard escapes -- every verb whose guard no test
  violated. The two parameterised provider suites (`provideSelectSideVerbs`,
  `provideWhereFamilyVerbs`) pin EVERY verb against both write phases,
  and the two cascading verbs (count rides select's refusal, offset
  rides limit's when chained) taught the provider's honest shape: one
  verb per case, standalone.
- The constructor coalesce -- dead weight once `whereNested` left (its
  only consumer was the shared identifier), so the parameter left with
  it. A defaulted constructor parameter with no observable consumer is
  a mutant the codebase pays for twice.
- The alias regex's caret (leading-garbage input), the byte ceiling's
  MBString flip (the 33×2-byte boundary), and the DX hint's two concat
  operands (one exact-message assertion) -- three grammar lessons the
  reference's own KillRound files anticipated, replayed for this tree's
  spellings.

## KDD-DB8: what this slice deliberately does not carry

The named follow-ups, each its own slice: the orchestration family
(TransactionManagerInterface + TransactionManager, UnitOfWork,
UnitOfWorkRetryPolicy, Repository, MigrationInterface, Migrator -- the
reference's v2.22.x), the unbounded-statement audit emission the
reference's PdoConnection carried over optional meter/logger seams (the
wiring slice's question, the same census-moment doctrine every opt-in
family answered), the configuration knob block and FrameworkDefaults
registration (rule-17's config integration lands with the first live
reader), and the condition-object API the where-family's exotic forms
(whereNested, whereColumn) return with. The error-system integration
IS here: the lane's failures are the typed family over the root, and
the boundary renders them -- nothing hand-built anywhere in the lane.
