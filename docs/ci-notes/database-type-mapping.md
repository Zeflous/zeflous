# Database data-type mapping with JSON support — the design record, refined with the landed verdicts

Slice: `feat(database): the data-type mapping with JSON support` — the value
vocabulary's widening: `DateTimeImmutable` and JSON values crossing the
statement boundary as first-class citizens over a per-table, opt-in type
map, while the honest default stays exactly what it is (`PdoRows`' raw
narrowing, `null|bool|float|int|string`), unchanged for every deployment
that declares nothing. This document arrived as the design record BEFORE
the code (the lane's own documentation discipline, applied to the lane
itself) and now carries the landed verdicts the implementation refined it
with.

## The slice in one paragraph, as it landed

Nine units over the gateway seam: `TypeMappingException` (the family's
typed refusal leaf over the `DatabaseException` root, the query-log
corridor's own posture), `ColumnType` (the converter contract — the two
directions' verbs, the open extension a host's own converter implements),
`DateTimeType` and `JsonType` (the two built-in converters), `TypeMap`
(the per-table immutable value: the loudly-validated column-to-converter
vocabulary, its converters the public spelling the folds walk),
`TypeMapEncode` and `TypeMapDecode` (the two direction folds over the
map — the write direction encoding at query build, the read direction
decoding after the port's own narrowing), `ReflectedTypeMap` (schema
reflection's second consumer: the fold that answers the map a reflected
table's declared types spell), and `TypedTableGateway` (the gateway that
REQUIRES the map and overrides the five verbs it carries). The design
record's four-unit sketch refined into nine by the archeology ceiling's
own demand — the splits documented in KDD-TM2.

## KDD-TM1: the seam placement — the base pristine, the overrides carrying the map

The design record's working name said "TableGateway carrying the map",
and the landing answered it literally: `Repository` — the base every
gateway in the tree descends — stays EXACTLY the spelling main landed
(not one line, not one word of its docblock touched), and
`TypedTableGateway` carries the map through the five verbs it overrides:
`insert`/`update` encode their payloads at query build (the encoded
spelling lands in the `SqlQuery` params — the builder and the adapter
untouched), `find`/`findOneBy`/`findBy` decode after the port's own
narrowing. Every other verb (`count`, `exists`, `delete`, `qb`) is
inherited untouched — the map carries no opinion about them. The honest
default is therefore a DIFFERENT GATEWAY, never a mode: the same table
over the raw `TableGateway` answers the same encoded spellings the map
stored (`'2026-11-05 03:14:15'`, `'{"kunci":"nilai"}'`), and the same
`DateTimeImmutable` write value refuses loudly there in the builder's
own value grammar — the seam pin the suite carries over the one real
SQLite socket, both vocabularies, both refusal shapes. ZERO ADAPTER
CHANGES: the port, `PdoRows`, the `PdoConnection` lineage — all exactly
as the query-log family left them; the narrowing the port answers is
the narrowing the map decodes after, and that placement is the design's
own test of itself.

## KDD-TM2: the archeology settle — the split by question, at the class level

The first spelling put the optional map inside the base's constructor
with the encode/decode points inline: `Repository` measured 26.05 over
its own 18.75 landing baseline, and the map-with-application measured
26.00 — both over the 20 ceiling, and the curriculum's lesson measured
precisely: the difficulty is (unique operators / 2) x (operand
occurrences / unique operands), so every REPEATED identifier over the
same collaborators raises the ratio, and a base that already hosts nine
verbs has no headroom for five more call sites. The settle is the
schema family's own flag-fold lesson applied at the CLASS level: the
raw question stays where it always was (Repository untouched at 18.75),
the typed question moves to the override gateway (17.25), the map's
VALUE splits from its APPLICATION (`TypeMap` the validated vocabulary
at 11.50), and the application itself splits BY DIRECTION
(`TypeMapEncode::row()` at 10.86, `TypeMapDecode::row()`/`rows()` at
16.88 — the `RepositoryCriteria::apply` idiom, a static fold over the
subject it walks, one direction one fold). The intermediate spellings
measured the curriculum's lesson in reverse — a single
application-fold carrying both directions reached 25.45 with the shared
walk's closures, 24.00 with three straight loops — because the two
directions' loops REPEAT the same operand names ($column, $value,
$converters) and repetition is the ratio's fuel: splitting by direction
gives each fold its own small vocabulary, and every unit lands under
the ceiling with room.

## KDD-TM3: the zone posture confirmed — UTC-canonical at the boundary

The design record carried the timezone edge as OPEN with a
recommendation; the implementation CONFIRMS the recommendation in
writing: the write direction converts the instant to UTC wall time
before it formats (`Y-m-d H:i:s`, the whole-second spelling the
driver's own CURRENT_TIMESTAMP answers), and the read direction anchors
the offset-free stored spelling AS UTC — because a boundary that stores
"whatever zone the writer was in" is a cross-timezone lie, and a reader
that guesses the server's zone is the same lie read back. The pin: a
Jakarta-zone instant (`+07:00`) round-trips the EQUAL instant, the
answer anchored in the UTC zone. A stored spelling that carries its own
offset still wins (the anchor applies only to offset-free spellings),
so a foreign writer's explicit zone travels honestly. Microseconds are
the DATETIME(6) question, a named follow-up: the canonical spelling
carries whole seconds, and a converter that silently kept sub-second
precision would answer a spelling the column's own default never
spells. The MUTABLE never crosses: `DateTimeImmutable` is
non-negotiable at a value boundary — and the tree's own toolchain
enforces the idiom harder than the boundary does (the fixer's
`date_time_immutable` rule rewrites every literal `DateTime` symbol,
constructor, static factory, even `DateTime::class`, into the
immutable), so the mutable-refusal pin holds its fixture through the
one seam the toolchain cannot rewrite: reflection over the class-name
string.

## KDD-TM4: the JSON converter — the encode lineage, and the affinity trap named

Both directions travel under `JSON_THROW_ON_ERROR`, the tree's own
encode lineage (`JsonBodyParser` on the read side at the same depth
ceiling, the log family's writers on the write side). The write
vocabulary is the row vocabulary's own — arrays and scalars, anything
else refusing with the type named: an OBJECT refuses because the read
direction answers arrays (never stdClass), so accepting one on write
would round-trip into a different shape than the caller handed in; a
resource or NAN refuses as a value JSON cannot spell, with the engine's
own cause carried in the refusal (code 0, the cause as `previous` —
the `JsonBodyParser` pin posture). The read direction decodes STRING
cells only, and the non-string refusal names the trap honestly: a bare
scalar cell is the SQLite NUMERIC-AFFINITY trap — a `JSON`-declared
column carries numeric affinity, so a stored `'123'` travels back as
int — and the honest guidance a deployment needs at the boundary, not
a silent cast answering a value the cell never spelled. The malformed
cell is the NAMED refusal, never a silent null, the spelling carried
in the message and the engine cause underneath it.

## KDD-TM5: the reflected entry — the closed vocabulary, and decimals never coerced

`ReflectedTypeMap::fromSchema()` reads a reflected `TableSchema`'s
declared types and answers the map they spell — schema reflection's
second consumer, the dependency order the landing recorded (reflection
first, mapping second). The matching vocabulary is CLOSED, and honestly
so: `DATETIME` and `TIMESTAMP` (the whole-second spellings) answer the
datetime converter, `JSON` answers the JSON converter, matched
case-insensitively as the driver spells them; every other spelling maps
to NO entry — `DATE` (the whole-second canonical would rewrite a
day-only cell), the precision spellings (`datetime(6)`), the multiword
PostgreSQL spellings the spell-only dialects answer, `JSONB`, and every
decimal spelling. DECIMALS NEVER COERCE: the reference's own default
posture (a float would lie about precision, and a silent lie at the
boundary is the exact failure class this family exists to close) — and
the socket's own honesty is pinned: SQLite's NUMERIC affinity answers a
`DECIMAL(10,2)` column a float (`'1.50'` stored travels back `1.5`),
and the map owns no opinion about it — the driver's own answer travels
untouched, no converter either way.

## KDD-TM6: the edges the record named — null cells, default spellings, raw criteria

The null cell answers NULL before it answers anything a converter could
decode, on BOTH sides: the null WRITE value passes the encode fold
untouched (a nullable column's NULL is the database's own answer, not
`json_encode(null)`'s literal 'null'), and the null CELL passes the
decode fold untouched — the folds' shared walk guards null before the
converter is ever asked. The default-spelling edge: a column DEFAULT
the map did not spell on the write (`DEFAULT CURRENT_TIMESTAMP`) still
answers the canonical spelling on the read, and the map decodes it
honestly — SQLite's own clock is UTC-canonical, the same posture the
boundary carries. Criteria values and condition trees travel RAW in
both directions: a comparison literal is the host's own driver-side
spelling, and a mapped PHP value handed to a criteria refuses loudly
in the builder's own value grammar (`QueryValues`), never silently
coerced — the one-row finder's criteria pin carries the string
spelling working exactly as spelled.

## KDD-TM7: the psalm ledger — two retirements, three arrivals, honest remainders

The reflection slice's row promised this family as the value objects'
named src consumer; the landing keeps the promise exactly as far as
the reads reach: `ReflectedTypeMap` reads `TableSchema::$columns` and
`ColumnSchema::$declaredType`, RETIRING those two baseline entries —
and the other thirteen remain honestly (the indexes, the foreign keys,
the key positions, the defaults, the nullability verdicts: their
consumer is still the suite's pins, and the slice that reads them is a
future schema-diff or inspector question, named here so the ledger
stays truthful). Three `MixedAssignment` entries ARRIVE on the
direction folds — the two walks' foreach values and the decode
assignment — the honest port-shaped rows (`array<string, mixed>` is
what the port's own docblock answers; the narrowing is a runtime fact
the adapter enforces, not a type the port carries), the same entries
the connection-knobs walk has carried since its own landing. Zero
`UnusedClass` entries: the family's nine units all carry `@api` — the
public vocabulary mark — because every one of them is a host's own
composition point (the converters implementable, the map
hand-declarable, the folds callable, the gateway constructible).

## KDD-TM8: the mutation story — 119 killed, one documented ignore

The scoped per-file rounds (the discipline the debugging slice learned
to trust) close at MSI 100/100: 119 mutants killed across the nine
units and their wiring, one ignore entry documented with the argument
that earned it. The kill shapes the rounds surfaced: the SQL-SPELL
pins over the scripted connection (`'SELECT * FROM "events" WHERE "id"
= ? LIMIT 1'` — the single-row limit literals the raw base kills the
same way, inherited here over the typed gateway's own overrides); the
CAUSE-CARRIER pins (`assertSame(0, $e->getCode())` plus the engine
cause as `previous` — the JsonBodyParser posture — killing the
exception-construction integers and the message-concat rewrites); the
WALK-CONTINUATION pins (an unmapped column and a null column IN FRONT
of the mapped one, so a `continue` rewritten to `break` visibly
starves the mapped column behind it); and the refusal-message tails
pinned in full (the affinity trap's closing clause included). The one
ignore: `ReflectedTypeMap::fromSchema`'s `mb_strtolower` — the Kelvin
argument inverted for a k-less vocabulary (datetime, timestamp, json
carry no k, and the Kelvin sign's landing on k is the only
single-character lower-case arrival in ASCII the Unicode map carries),
verified by running the database family suite under the manually
applied mutation — the complete suite passes, the real-socket round
trips included, so fold-divergent spellings miss every word under both
spellings and the fold answers no-entry either way.

## The honest limits, as they landed

Enum support (the vocabulary question is its own), binary blob
fidelity, entity hydration (the `Repository` family stays row-shaped —
an ORM is a different lane), a per-connection or global map, and
criteria-side encoding (the Condition walk is its own fold, the day a
consumer asks) — each a named non-goal. The reflected vocabulary's
out-of-word spellings (DATE, precision, multiword, JSONB) are named
follow-ups the same way. A config face for the map (a `database`
knob-block spelling of type words per table) is deliberately absent:
the map is CODE — the host's own gateway declaration spells it, with
hand-declared converters OR the reflected composition — because a knob
can only spell the built-in words while the map's whole openness is
the host's own `ColumnType` implementations; the census doctrine keeps
its own shape (no knob without a reader, and the reader here would be
a wiring fold the record never promised).
