# Database schema reflection — the design record

**Status:** a design record, landed before the code. The implementation
arrives in this slice's own pull request over this record, refining it
where the verdicts below name their open edges; the KDD numbering
arrives with the implementation, not here. The record exists so the
reference reading, the unit sketch and the decisions to carry are
written down before the first line of source — the lane's own
documentation discipline, applied to the lane itself.

## The slice in one paragraph

The database lane's read-only introspection family: what tables exist,
what columns each carries, what indexes and foreign keys guard them —
answered from the live socket through the port's own fetch verbs. The
reference answers this question as the schema manager composed OVER
the connection (Doctrine DBAL's `AbstractSchemaManager`, Cycle ORM's
schema providers behind the same seam); the tree's answer keeps that
split: the reflection is a SERVICE over the connection, never a
widening of the port — `ConnectionInterface` stays the data-and-
transaction vocabulary it is, and a `database.schema` service identity
composes over the shared connection the wiring already answers.

## The question it owns

"Driver, what is the shape of what I own?" — the deploy-time and
operator-time question the lane's remaining slices consume: the
type-mapping slice's reflected map (the declared types feeding the
per-column converters), the migrations scaffolding verb's diff half
(live shape against declared history), and the operator's own
inspection. Nobody asks it at request time: the answers are cached by
the consumer or read by a human, so the fold owes no hot-path posture
beyond the port's own fetch cost.

## The units sketch

| Unit | Question it owns |
|---|---|
| `SchemaReflection` | the orchestrating fold: the dialect's questions asked through `fetchAll`, the answered rows folded into the schema value objects |
| `SchemaDialect` (adapter side) | the per-driver SQL: SQLite's `PRAGMA table_info` / `index_list` / `index_info` / `foreign_key_list` family; MySQL's and PostgreSQL's `information_schema` spellings — the three drivers the DSN grammar already accepts |
| `TableName` | the grammar fold over the identifier grammar the builder already spells |
| `TableSchema` / `ColumnSchema` / `IndexSchema` | the value objects: column name, declared type, nullable, default, the PK position; index name, columns, uniqueness; the foreign key's own triple |

## The decisions to carry

- **Read-only, forever.** No DDL verbs land here — the migrations
  family owns DDL, and even the scaffolding verb's diff half only
  READS. A reflection fold that can also alter is a footgun the lane
  refuses to own.
- **The port is not widened.** The reflection composes over
  `ConnectionInterface::fetchAll()` as a service identity — the
  Doctrine split (connection vs schema manager), arrived at the wiring
  seam: a `database.schema` registration beside `database.transactions`,
  over the shared lazy connection. No knob of its own: a census family
  needs a config face, a service answers an identity (the
  census-moment doctrine).
- **The dialect is adapter knowledge.** The PRAGMA vs
  `information_schema` split lives with the adapter folds; the
  Database-layer folds stay pure over answered rows — the same split
  the query-log family landed (orchestration engine-side, PDO facts
  adapter-side).
- **The declared type travels as the driver spells it.** No
  cross-driver type unification here: `INTEGER` vs `int(11)` vs
  `SERIAL` is the TYPE-MAPPING slice's question; this slice's value
  objects carry the driver's own spelling, honestly.
- **Landing order is a dependency, not a preference.** The type-mapping
  slice's reflected map and the scaffolding verb's diff half both
  consume this slice — the lane lands reflection FIRST.

## What the implementation must answer

- Real-socket pins over SQLite in-memory: create a table with the
  grammar the builder spells, reflect, assert the column/index/foreign-
  key facts — the suite never trusts a fold it cannot pin against the
  real driver.
- The two non-SQLite dialects carry their own honesty question: the CI
  suite reaches only SQLite, so the mysql/pgsql dialects either land
  with their own fixture posture (a named follow-up) or land
  spell-only with the gap recorded in the KDD — the implementation
  decides, the record requires the decision to be written down.
- The full battery: `ci:static` core green, PHPStan max clean, Psalm
  over the ledger, deptrac 0 new directions, archeology under the
  ceiling, scoped infection MSI 100/100 with 0 escapes, the roadmap
  row at landing.

## What this slice deliberately does not carry

View, trigger and sequence introspection (no consumer has asked);
statistics (row counts are data, not shape); any DDL; any caching
(the consumer owns the cache); a CLI inspector verb (a possible named
consumer, its own decision when an operator asks).
