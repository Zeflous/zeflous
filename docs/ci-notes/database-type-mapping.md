# Database data-type mapping with JSON support — the design record

**Status:** a design record, landed before the code. The implementation
arrives in this slice's own pull request over this record, refining it
where the verdicts below name their open edges; the KDD numbering
arrives with the implementation, not here.

## The slice in one paragraph

The value vocabulary's widening: `DateTimeImmutable` and JSON values
cross the statement boundary as first-class citizens over a per-table,
opt-in type map — while the honest default stays exactly what it is
(`PdoRows`' raw narrowing, `null|bool|float|int|string`), unchanged for
every deployment that declares nothing. The reference's three answers
(Dctrine DBAL's Type system with its `convertToPHPValue` /
`convertToDatabaseValue` pair, Cycle ORM's typecast handlers, Laravel's
casts) all apply the mapping at a seam SEPARATE from the raw
connection; the tree follows the same split: the map rides the
gateway, the port stays raw.

## The question it owns

"Column, what shape do you want your bytes in?" — the fidelity gap the
lane's audit recorded as the deepest: a timestamp that arrives as a
string and leaves as a string round-trips only by luck of spelling; a
JSON column that arrives as an encoded string forces every consumer to
own the decode. The map is the one place the spelling converts, in
both directions, deterministically.

## The units sketch

| Unit | Question it owns |
|---|---|
| `TypeMap` | the per-table immutable map: column name → converter |
| `DateTimeType` | `DateTimeImmutable` in, the driver's canonical datetime spelling out; the string in, the immutable object out |
| `JsonType` | the array / decodable value in, the encoded string out (under `JSON_THROW_ON_ERROR`, the tree's own encode lineage); the string in, the decoded value out |
| the typed gateway seam (working name `TypedTableGateway`) | `TableGateway` carrying the map: writes encode at query build, reads decode after the narrowing |

## The decisions to carry

- **Opt-in per table, never a global mode.** A deployment spells the
  map for the tables whose fidelity it wants; every other table keeps
  the raw narrowing. No flag flips the whole connection's vocabulary —
  the same honest-default posture the query-log block carries.
- **The port stays raw.** The narrowing `PdoRows` answers today is the
  port's own contract; the mapping rides the gateway seam above it
  (writes: the encoded spelling lands in the `SqlQuery` params — the
  builder and the adapter untouched; reads: decode AFTER the
  narrowing, per the map). Zero adapter changes is the design's own
  test of the seam's placement.
- **`DateTimeImmutable`, never the mutable.** The tree's immutable
  idiom, non-negotiable at a value boundary.
- **Decimals travel as strings.** The reference's own default posture
  (`Doctrine\DBAL\Types` maps decimal to string): a float would lie
  about precision, and a silent lie at the boundary is the exact
  failure class this slice exists to close.
- **The reflected entry is the second consumer of schema reflection.**
  The map can be hand-declared OR composed from the reflection
  slice's declared types — the dependency the landing order records:
  reflection first, mapping second.
- **OPEN — the timezone posture.** The candidates: UTC-canonical at
  the boundary (the reference's own default) versus the application
  clock's zone. The recommendation carried in this record is
  UTC-canonical with the conversion owned by the converter, because a
  boundary that stores "whatever zone the writer was in" is a
  cross-timezone lie; the implementation confirms or overrules with
  the reasoning written down.

## What the implementation must answer

- Round-trip pins: `DateTimeImmutable` → statement → read → the equal
  instant; an array with unicode keys → encoded column → decoded
  equal; a malformed JSON cell → the named refusal, never a silent
  null.
- The null-cell edge (a nullable JSON column answers NULL before it
  answers a string) and the default-spelling edge (a column default
  the map does not cover).
- The full battery, archeology under the ceiling, scoped infection
  MSI 100/100, the roadmap row at landing.

## What this slice deliberately does not carry

Enum support (a named follow-up — the vocabulary question is its own);
binary blob fidelity (named follow-up); entity hydration (the
`Repository` family stays row-shaped — an ORM is a different lane);
a per-connection or global map (the per-table posture above);
immutable-date mutators beyond the constructor idioms.
