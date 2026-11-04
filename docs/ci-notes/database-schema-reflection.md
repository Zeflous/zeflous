# Database schema reflection — the read-only introspection family over the connection port

Slice: `feat(database): the schema reflection family over the connection port` — the
read-only introspection question the lane's remaining slices consume: what
tables exist, what columns each carries, what indexes and foreign keys guard
them, answered from the live socket through the port's own fetch verbs. This
document arrived as the design record BEFORE the code (the lane's own
documentation discipline, applied to the lane itself) and now carries the
landed verdicts the implementation refined it with.

## The slice in one paragraph

Twelve new units over the Doctrine schema-manager split: `TableName` (the
grammar fold over the identifier grammar the builder already spells, a
single validated segment refused at construction), the four value objects
(`ColumnSchema`, `IndexSchema`, `ForeignKeySchema`, `TableSchema` — pure over
the constructor arguments the adapter folds narrow, carrying the driver's
own spellings), the dialect fold `SchemaDialect` (the per-driver SQL —
SQLite's PRAGMA family and the two `information_schema` spellings, every
selected cell aliased to the canonical key its row fold reads), the
narrowing folds `SchemaCells` (the string/integer identity cells) and
`SchemaFlags` (the verdict vocabulary), the three row folds `SchemaColumns`,
`SchemaIndexes`, `SchemaForeignKeys` (each owning both driver shapes behind
one `fold()`), and the orchestrating fold `SchemaReflection` (the
`PdoConnection` lineage over the read side: every question asked through
`ConnectionInterface::fetchAll()`, one delegation each). The wiring answers
the service identity (`DatabaseWiring::schema()`), the profile registers it
(`database.schema`, lazy, over the shared connection and the config), and
the declaration fold answers the driver word (`defaultDriver()`) — the port
itself stays the data-and-transaction vocabulary it always was, widened by
nothing.

## KDD-SR1: the service-over-port split, arrived at the wiring seam

The reflection is a SERVICE over the connection, never a widening of the
port: `ConnectionInterface` is untouched (not one method, not one word of
its docblock), and every question the family asks travels through the
port's own `fetchAll()` verb as a plain `SqlQuery` — the Doctrine
schema-manager split (`AbstractSchemaManager` composed over the connection;
Cycle's schema providers behind the same seam). The identity lands beside
`database.transactions` in the profile's catalogue: `database.schema`,
factory `DatabaseWiring::schema()`, over the SHARED lazy connection the
`database.connection` identity answers (the registration hands the same
service in, so the reflection and the transactions ride the one socket),
with the config service handed in beside it because the fold derives the
dialect's driver word from the declaration the connection identity itself
answers — the reflection's dialect and the connection it composes over
therefore agree on the one driver the deployment spelled. The registration
defers behind a lazy proxy (the `database.connection` posture): the fold
can refuse (the silent knob), and a refusal that fires at the host's own
first ask is the never-at-the-boot discipline every wired default carries.
No knob of its own: a census family needs a config face, a service answers
an identity — the `database` knob block is untouched by this slice, its
census and its defaults exactly as the query-log family left them.

## KDD-SR2: the dialect is adapter knowledge, aliased at the spell

The PRAGMA vs `information_schema` split lives with the adapter folds, and
the Database-layer folds stay pure over the constructor arguments the
adapter narrows — the same split the query-log family landed. The dialect
owns BOTH the spell and the answer's key vocabulary: every selected cell of
the `information_schema` spells is aliased at the spell itself to the
canonical key its row fold reads (`name`, `declared_type`, `is_nullable`,
`dflt_value`, `pk_position` over the columns question; `name`,
`column_name`, `is_unique` over the indexes question; `name`,
`column_name`, `referenced_table_name`, `referenced_column_name` over the
foreign keys), so no fold downstream branches on a driver's native column
names — the fold branches on the driver word ONCE (the shape question) and
reads one canonical vocabulary everywhere. SQLite's PRAGMA family cannot
alias, so its row folds read the PRAGMA's own keys — the one shape
divergence the per-driver folds own. The table name interpolates into the
PRAGMA literals as a single-quoted string: the `TableName` grammar
(`[A-Za-z_][A-Za-z0-9_]{0,63}`, one segment) carries no quote, no dot, no
whitespace, so the interpolated literal can never become SQL syntax without
a second escaping layer. The index-columns question interpolates the INDEX
name — a name that arrives from the driver's own answer, not from the
caller — so the dialect validates it through the identifier grammar before
it interpolates (`SqlIdentifier::quote()`, the same one-grammar fold), and
a hostile answer refuses loudly instead of travelling into a spell.

## KDD-SR3: the driver's own spelling travels untouched — and the suite pins the real socket

The declared type travels as the driver spells it: `INTEGER` from the
PRAGMA, `int(11)` from MySQL's `COLUMN_TYPE`, the `information_schema`
`DATA_TYPE` word from PostgreSQL. Cross-driver unification is the
type-mapping slice's question, not this one's. The declared default
travels in the driver's own LITERAL spelling, pinned against the real
SQLite socket: `'20'` and `'1.5'` arrive as the strings the PRAGMA answers,
`NULL` stays the word an explicit `DEFAULT NULL` spells, `'hi'` stays
quoted, `datetime('now')` stays its own SQL — the suite never normalises
what the driver actually answered, because a fold that quietly parsed the
literal would answer a shape the socket never spelled. SQLite's own
quirks travel honestly too, pinned rather than edited: the `INTEGER
PRIMARY KEY` column answers `notnull 0` (the PRAGMA's own quirk), so the
reflected column answers nullable — the fold carries the driver's answer,
never the doctrine it should have had; the composite-key members answer
their own positions (1, 2) with the same honest notnull; the UNIQUE
constraint's implicit `sqlite_autoindex_orders_1` guard travels with the
declared indexes, unfiltered (a fold that silently dropped the driver's own
answers would answer a shape the socket never spelled); the foreign keys
travel in the PRAGMA's own answer order (the last-declared first) with
`name` null — SQLite's `foreign_key_list` answers no constraint name at
all (the `CONSTRAINT orders_user_fk` spelling names nothing the PRAGMA
carries), and the value carries null, never a fabricated `fk_0`. The
tables inventory answers the driver's own name order with the internal
`sqlite_sequence` excluded by the spell itself (the driver's own
bookkeeping, not the deployment's shape) — and the multi-column foreign
key pairs its columns in the answer's own `seq` order, pinned over the
real socket.

## KDD-SR4: the two non-SQLite dialects — spell-only, the gap recorded

The record demanded the decision in writing, and this is it: the MySQL and
PostgreSQL dialects land SPELL-ONLY in this slice. Their spells are pinned
as exact strings (both dialects, all four questions), their row folds are
pinned over hand-crafted rows in the documented `information_schema` shapes
(the aliased canonical keys the spells themselves promise — the fold
contract is OUR spell, so the pin is honest about what OUR fold reads),
and the live-socket verification is the named follow-up: CI's socket
reaches SQLite alone, so a MySQL/PostgreSQL `information_schema` answering
a shape the documentation did not spell (or a version drifting a column)
is a gap this slice records rather than a gap it hides. The deepest edge
of that gap is named too: the primary-key ordinal the two spells read from
`KEY_COLUMN_USAGE.ORDINAL_POSITION` (the position-within-the-constraint
the standard documents) and the PostgreSQL index walk's
`unnest(... WITH ORDINALITY)` ordering are documented-shape reads the CI
socket cannot confirm — the day a deployment asks, the live-socket
fixture posture (the redis mock-server lane's own shape) is the follow-up
that answers them.

## KDD-SR5: the miss refuses loudly

A table whose columns question answers EMPTY is a name the driver does
not know, and the reflection refuses with the name spelled (`The driver
answered no columns for table 'missing' -- the reflected name owns no
table.`) instead of answering a silent `TableSchema` of three empty lists.
The worst failure shape available is the silent one — an operator
believing they reflected something while the fold answered nothing — and
the connections map's own doctrine (the undeclared name with the way out
spelled) transfers one fold over. The empty answer is the miss because a
real table cannot own zero columns in any of the three dialects; the
refusal lives in the ORCHESTRATION (the driver-answer fact), never in
the value object (which stays pure over its arguments).

## KDD-SR6: the settle — three rounds, every question its own unit, and the arithmetic that split the cell fold

The first spelling carried the four cell narrowings in one `SchemaCells`
and the schema fold's driver derivation inline in the wiring: archeology
reddened both over the 20 ceiling (`SchemaCells` 37.14, `DatabaseWiring`
20.80 over its own 19.12 landing baseline), and the intermediate
spellings that centralised the refusal message and extracted a shared
`answered()` fetch measured the curriculum's own lesson in reverse —
27.04, then 30.50: every REPEATED identifier raises the ratio, and every
freshly-named one lowers it, but an extraction that ADDS operator kinds
(`in_array`, the self-call) while the repetition stays raises the
difficulty it was meant to shed. The house answer, the #665 curriculum
replayed until the gate went green, split by QUESTION: the verdict
vocabulary (the flag fold — MySQL's int expressions, the PRAGMA's zeros,
PostgreSQL's bools) is `SchemaFlags::verdict()` (6.50); the declared
default's narrowing lives with its ONE consumer as
`SchemaColumns::declaredDefault()`; the identity cells (names and
ordinals) stay `SchemaCells` (18.75); the driver word moved to the
declaration fold it answers for (`DatabaseDeclaration::defaultDriver()`,
the one-name lookup the connection fold composes, read there once); and
the wiring's three silent-declaration refusals share one spelling
(`undeclaredDefault()`, the identity and the way out as arguments) —
`DatabaseWiring` landed at 19.46, under its own baseline, its messages
byte-identical to the pins the wiring tests carry. The Psalm ledger
carries fifteen new `PossiblyUnusedProperty` entries over the four value
objects — the storage slice's own precedent (the renderer consumed only
by `bin/zef`): the value properties are consumed today by the suite's
pins alone, and the type-mapping slice (the reflected map's declared
types feeding the per-column converters) is the named consumer that will
read them from `src` — the baseline entries retire with that slice.

## KDD-SR7: what this slice deliberately does not carry

The record's non-goals stand as landed: view, trigger and sequence
introspection (no consumer has asked); statistics (row counts are data,
not shape); any DDL (read-only, forever — the migrations family owns DDL,
and even the scaffolding verb's diff half only READS); any caching (the
consumer owns the cache); a CLI inspector verb (a possible named
consumer, its own decision when an operator asks). Two further edges
named by the landing: the tables inventory does not filter by the
spell's exclusion beyond the driver's own internal tables (a host's own
`sqlite_*`-prefixed table IS reflected — the driver's answer, unedited),
and the reflection composes over the connection the registration hands
in, so a deployment composing the family by hand over their own
`ConnectionInterface` implementation spells the driver word themselves
(the constructor's two arguments travel together from the wiring).

## Verification

The full local battery after the settle: `ci:static` core green (7,107
tests / 17,280 assertions — forty-two new tests over the real SQLite
socket, the spell pins, the recorded-shape row folds, the value
invariants and the two catalogue pins the registration widened:
`ApplicationBootstrapperTest`'s profile array and the container
inventory's lazy marks, 3 → 4), PHPStan max clean, Psalm level 1 EXIT-0
(fifteen baseline entries, the storage precedent), deptrac 0 violations
(the adapter folds stay inside the Adapters lane's existing `Database`
dependency, the new folds all inside it), the duplication and mess gates
clean, archeology 0 error-level findings (every unit under the 20
ceiling), and the mutation story: every new and touched file at MSI
100/100 under the scoped per-file rounds (the single-file filter the
debugging slice learned to trust, run file by file: the twelve new
units, the wiring, the declaration and the profile), with the pin set
that answers the escapes the rounds surfaced — the real-socket pins
(the spellings, the defaults, the answer orders, the miss, the
`INTEGER PRIMARY KEY` quirk), the recorded-shape fold pins on BOTH
information_schema dialects (each match arm reached by its own fold),
the value-invariant refusals (the one-side-empty pair, the strict
numeric-spelling distinctness, the twice-answered column), and the
missing-cell refusal the `pk` fold carries — each living in the test
that declares the class it kills for (attribution-independent by
construction, the KDD-QL10 doctrine). The full-tree round runs on the
branch's CI as it does for every pull request. The wiring's lazy
proxy keeps the rule-17 posture: the
silent profile still boots without running the schema factory (the
lazy-mark pin the inventory report carries now answers 4), and the
boot-level pin answers the reflection over the one shared socket the
connection service rides.
