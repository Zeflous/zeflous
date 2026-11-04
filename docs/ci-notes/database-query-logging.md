# Database query logging — the per-query record, the duration observation, the slow verdict

Slice: `feat(database): per-query logging with slow-query detection` — the
lane's not-yet-ledger item the wiring slice's own KDD-DW7 promised would
arrive "the day a deployment asks, with its census entry". The ask is the
roadmap's own named slice: one record per executed statement, one duration
observation against the shared meter, and the slow verdict's WARN record
and counter the statements that strictly exceed the configured bar add on
top.

## The slice in one paragraph

Nine new units over the slow-request family's own doctrines: `QueryLogPolicy`
(the family's pure knob set — the arm, the bar, and the bar's own
`exceededBy()` arithmetic), `QueryLogBounds` (the corridor both construction
paths must hold), `QueryLogException` (the family's typed refusal leaf, the
migration lane's own TTL-corridor posture), `PdoQueryOperation` (the
operation word folded from the statement's own head, the five-word
vocabulary), `PdoQuerySignals` (the emission fold: the two records and the
two meter signals one statement answers), `PdoQueryLog` (the measured window
the armed connection holds — the clock seam, the opening and closing
instants, the contained emission), `PdoQueryLogArming` (the family's arming
fold: the bracket a declaration's policy arms over the shared sinks, or the
honest void), `PdoQueryRun` (the data verbs' orchestration — the statement
run inside the window, the audit emission after it, the
`PdoTransactionRun` lineage over the two data verbs), and
`DatabaseQueryLogKnobs` (the `database.query_log` block's own strict reading
into the policy, the `DatabaseConnectionKnobs` lineage one level down). The
knob block joins the family's census (`DatabaseKnobs`, family-additively),
the declaration carries the policy the block spells, and `DatabaseWiring`
composes the bracket over the same shared meter and the same ONE structured
log address the unbounded-statement audit already rides — every record the
family writes lands in the one sink.

## KDD-QL1: the throw path records, the window measures statement work only, and the observation is never worth the answer

The window brackets the statement's OWN work only: the opening reading
before it, the emission in the `finally` seam the throw path shares (a
statement that answered slowly records, and a statement that threw slowly
records and then propagates its own exception untouched — the hung
statement that ends in a `QueryException` after thirty seconds is the
pathology the family most exists for; the slow-request family's own KDD-SR1
doctrine, transferred to statements), and the unbounded-statement audit
emission AFTER the window — an emission is never statement work, and a
duration that measured its own record would lie about the statement. Because
the emission runs in a finally, a failing sink could MASK the statement's
own exception — the one failure mode the family must never introduce — so
the whole observation (the closing clock read included) is contained
best-effort the way the router lane's own telemetry is (`RouteMatchTelemetry`:
"a meter that fails is contained, never fatal, because the measurement is
never worth the match's own answer" — here, the query's). The seeding pins
both edges: a throwing logger never converts a successful statement into a
failure, and never masks a failing statement's own exception. The opening
read stays OUTSIDE the containment, the slow-request middleware's own shape:
an operator who wires a clock seam that throws owns the failure they wired,
and the wrong-VALUES seam is contained by geometry instead (KDD-QL2).

## KDD-QL2: strictly-exceeds, integer nanoseconds, the tenth-of-the-bar default

The verdict is the policy's own judgment, never a call site's re-derivation:
`exceededBy(int $elapsedNs)` holds the millisecond knob translated to its
nanosecond spelling once where the knob lives, the comparison strict (a span
that exactly MEETS the bar is not slow — pinned at the exact boundary, one
nanosecond on either side), and a negative span exceeds no positive bar —
a broken clock seam fabricates nothing (the backwards-clock test: every
span answers -0.5ms, the statements succeed, no slow verdict ever fires).
The shipped default bar is 100ms — a tenth of the slow-request family's own
one-second weight, the round number where one statement starts spending a
slice the request's own slow bar cannot afford. The corridor is the
slow-request family's own (1..3600000): the floor refuses the zero bar that
would WARN on literally every statement, the ceiling the hour-deep bar
beyond every lane's own pathology recording.

## KDD-QL3: two records, fixed levels, no bound values

The per-query record is the family's primary deliverable — the reference's
SQL-logger shape (one record per executed statement, DEBUG level, the SQL
travelling in the context so the event stays greppable) — and the slow
verdict rides alongside it rather than replacing it: two questions, two
records (the per-query record says the statement ran and how long it took;
the slow record flags the bar it crossed, with the `threshold_ms` fact that
names the bar in force). Both levels are constants, not knobs — a knob
would fragment the one vocabulary every consumer of the records filters on
(KDD-SR8's doctrine). The bound VALUES are deliberately absent from every
record: the statement's text is grammar while the bound values are payload,
and the tree's privacy-by-default posture refuses payload in records — the
same refusal the slow-request family's record spells over request bodies
and query strings, and the same SQL-without-params shape the unbounded
audit already carries.

## KDD-QL4: the knob block — arm follows declaration, and the defaults map stays null

`database.query_log` is the family's own block inside the `database` map
(the census widened family-additively, the census message pins updated with
it), carrying `enabled` (default ON within a spelled block — spelling the
block IS the ask, the slow-request family's arm doctrine) and `threshold_ms`
(default 100). The ABSENT block is the silent posture: a null policy, no
emission object at all. The framework profile's own default deliberately
spells `'query_log' => null` rather than a disabled frame — a
disabled-frame default would MERGE with a host's own spelling (their
`threshold_ms` landing over the frame's `enabled: false`), silently
disarming a family the host believes they armed: exactly the
silently-mistyped-arm posture the strict readings exist to refuse. The
sub-map census (`enabled`, `threshold_ms`) and the strict readings
(native bool, native int — the family's own `readName` posture over
map-typed knobs) live in the declaration fold, because the Database layer
owns no configuration reading; the corridor asserts at the policy's
construction so BOTH paths halt identically, the config typo and the
hand-spelled nonsense value, the migration lane's own TTL-corridor posture
(`QueryLogException`, the family's typed leaf over the exception root).

## KDD-QL5: the honest void — no bracket, no clock read

The connection holds ONE nullable seam (`?PdoQueryLog`), and the family's
own arming fold (`PdoQueryLogArming::bracket()`) answers null whenever the
family is not armed — the absent block, the disarmed block — or carries no
reader at all (both sinks null: a measurement no consumer would ever see,
the timing family's "no observable difference between measured-and-
discarded and never-measured" discipline). The wiring hands the fold the
declaration's policy and the same shared meter and ONE log address the
unbounded-statement audit rides — one logger instance serving both
families' seams, the one-sink doctrine literally. A null bracket never
reads the clock (the orchestration's nullsafe opening `?->begin() ?? 0`
answers the inert instant the closing edge never reads), never allocates a
record, never touches the statement's own path: the unarmed connection's
verbs walk the same statement run they always walked (pinned: the unarmed
test answers its verbs with empty tapes on both sinks, while the audit
emission's own seams stay untouched — two families, two independent seams
over the same sinks).

## KDD-QL6: the meter signals — seconds, the dotted dimension, five words

The duration travels to the shared meter as `zef.db.query.duration_seconds`
— the base-unit spelling the router lane's own duration series established
(`zef.router.match.duration_seconds`, the OTLP convention over the
observability export lane's signals) — observed per statement with the
operation word as its only dimension (`db.statement.operation`, the dotted
key the telemetry lane's series carry). The slow verdict counts
`zef.db.slow_query.total`. The operation vocabulary is closed at five words
— select, insert, update, delete, other — folded from the statement's own
head the same way the unbounded audit's own operation fold reads its
statements (the prefix match over the upper-cased, left-trimmed head, no
boundary guard: no valid SQL statement begins with the letters of one
operation word while being another), so the dimension space stays bounded
exactly the way the meter's own contract demands. DDL, EXPLAIN and CTE
heads fold to `other` — honest, not guessed: a `WITH`-prefixed statement's
terminal verb is not the head's to spell.

## KDD-QL7: the clock seam is one closure, not the two-domain interface

The telemetry clock's own interface exists because a SPAN's whole timeline
needs two domains (the monotonic duration and the wall timestamp) in one
freezable place; a statement's duration needs ONE reading, and the
slow-request family's own measured-span shape is the single `Closure():int`
seam (`TimingClock::monotonicNs(...)`'s idiom). The default closure is the
telemetry engine's own system clock — `(new TelemetryClock())->monotonicNs(...)`
— because the adapter layer's deptrac widening already reaches Telemetry
(the meter contract's widening), so no new direction opens, and the engine
clock is reused rather than duplicated. The suite pins durations through
injected closures (the backwards-clock seam) and through the emission fold
directly (the exact-boundary pins), never by sleeping.

## KDD-QL8: the settle — four rounds, every question its own unit

The archeology round reddened three existing classes over the ceiling the
first spelling carried (`PdoConnection` 28.20, `DatabaseDeclaration` 29.67,
`DatabaseWiring` 29.35 against the 20 bar — the Halstead difficulty the
aggregate-volume lesson names: the duplicated bracket raised the operand
ratio, not any method's own complexity), and the intermediate spellings
that folded the new questions INTO the touched classes reddened the new
folds the same way (the lesson measured precisely: Halstead difficulty is
(η1/2) × (N2/η2) — every REPEATED identifier raises the ratio, every
freshly-named one lowers it, so the sixth method over the same five
collaborators costs more than the first). The house answer, the #665
curriculum replayed until the gate went green: every question its own
unit. The data verbs' orchestration — the window, the statement run, the
audit emission after it — is `PdoQueryRun` (the `PdoTransactionRun`
lineage; the verbs delegate to it in one line each, and `PdoConnection`
landed at 15.00, under its own main baseline); the arming question is
`PdoQueryLogArming` (10.50); the window itself is `PdoQueryLog` (13.13);
the block's strict reading is `DatabaseQueryLogKnobs` (16.19); and the
wiring shed its negation operator by answering the positive branch first
(19.12). The statement-pair fold the settle first extracted was folded
back into the orchestration when `PdoStatementRun` (15.42) showed the pair
wants no third spelling — `execute()` answering the statement it ran is
the whole pair.

## KDD-QL9: what this slice deliberately does not carry

Named follow-ups, each its own decision: **per-connection thresholds** (the
block is family-level — one posture per deployment; the day a deployment
asks for a chattier reporting connection than its OLTP one, the
per-connection spelling arrives with its own census entry); **the bound
values in records** (refused, KDD-QL3 — the day a deployment asks for a
redacted-param record, the redaction fold is its own slice, never a knob
that silently degrades the posture); **an EXPLAIN hook** on slow queries
(a query PLAN is the database's own answer to a different question, and a
query that just ran slow is the wrong moment to run another one); **the
transaction scope's own timing** (the transaction family's slow-hook guard
owns it — `HookDuration`/`HookDrain`, deliberately unknobbed by KDD-DW7);
**lastInsertId** (a driver identity read, not a statement — neither
reference logs it); and **per-verb timing splits** (prepare vs execute vs
fetch — a single window per statement, the one number an operator graphs;
the phase a failing statement died in is the typed exception's own fact).

## KDD-QL10: the first CI round -- twenty escapes, three roots, and one vocabulary lesson

The first full-tree infection run answered with twenty escapes the scoped
local round had reported clean (the local round's filter invocation only
honoured one file of the list -- the lesson KDD-DW8 already spelled, once
more: a green local battery is only the battery you RAN). Three roots,
each with its own answer:

**The attribution root (eight escapes).** The null-seam kills lived in the
wiring tests -- the meter-less connection's record path, the address-less
connection's signals path -- which do not declare `PdoQuerySignals` in
their covers lists, so the suite's `requireCoverageMetadata` filtered the
attribution out and infection never ran them for those mutants. The kills
now live where the class is declared: two direct emission tests handing
the fold a null meter (both records answer, both signals skipped) and a
null logger (both signals answer, no records) -- the kills are
attribution-independent by construction.

**The write-only root (four escapes, two documented equivalents).** The
`?? 0` opening instant's fallback is inert by construction: the coalesce
takes it only when the bracket is null, and the closing edge is then
null-skipped itself, so the fallback value never flows anywhere a test
could observe -- verified by running the whole database family (493
tests) under the manually applied ±1 mutations, green under both. The two
`ignoreSourceCodeByRegex` entries (one per integer mutator, the line-
scoped spelling that keeps every future integer literal in the two
orchestration methods under the mutators) carry this proof in
`infection.json5`. The coalesce SWAP on the same line stays under its
mutator and is killed: `0 ?? begin()` pins the opening at zero and the
measured span answers the uptime scale -- a magnitude the fetch test's
duration bounds now refuse (the execute test already refused it; the
first spelling's fetch test had no bounds, which is exactly why its swap
escaped).

**The discriminator root (eight escapes).** The boundary pin the first
spelling carried (`100*1000000+1 -> 100.0`) cannot separate a ±1 divisor
rewrite: both spellings round to the same two decimals at that distance
from the boundary. The discriminators are measured, not guessed:
100 254 999 ns answers 100.25 under `/1_000_000` but 100.26 under
`/999_999`; 100 255 001 ns answers 100.26 but 100.25 under `/1_000_001`;
150 512 345 ns answers 150.51 at two decimals but 150.5 / 150.512 /
150.0 under the precision and floor rewrites. Three emit calls, six
records (the query record AND the slow record -- the mutants live in both
context folds), each pinned to its exact float.

**The vocabulary lesson (one escape, killed -- a precedent's claim does
not transfer).** The unbounded-audit's `mb_strtoupper` ignore (KDD-DW6)
rests on the claim that "no non-ASCII character upper-cases into any of
its letters" -- true for UPDATE (U, P, D, A, T, E are unreachable from
the case map's non-ASCII exceptions), FALSE for SELECT: U+017F (the long
s) upper-cases into S, so `mb_strtoupper("\u{017F}ELECT 1")` answers
`SELECT 1` while `strtoupper` keeps the long s and the head folds to
`other`. The operation fold therefore KILLS the mutant with the long-s
head pin -- the ignore precedent is vocabulary-specific, and this fold's
five-word vocabulary is a different vocabulary. (The arming fold's
coalesce escape -- `($meter ?? $logger)` swapped -- was truth-table
equivalent under all four nullness combinations; the restructure to the
explicit `=== null && === null` spelling replaces it with a mutant the
arming table test kills through the reader-armed arms.)

## Verification

The full local battery after the CI round's answers landed: `ci:static`
core green (7,065 tests / 17,086 assertions, smoke 20,000 iterations at
2,760 bytes growth, concurrency 23 workflows, roadmap gate pin-fresh,
strict audit 0 advisories, duplication and mess clean), PHPStan max clean,
Psalm level 1 EXIT-0 with no new ledger entries, deptrac 0 violations (no
new direction), archeology 0 error-level findings (every unit under the 20
ceiling), and the mutation story in two rounds: the scoped round's one
escape (the sub-map census's `continue` → `break`, dying to the same
known-behind-known kill test KDD-DW6 recorded one level up) and the first
FULL-TREE CI round's twenty escapes — every one of them now answered
(KDD-QL10): twelve killed by the new deterministic pins, four dissolved by
the arming restructure and the fetch bounds, four carried as the two
line-scoped documented equivalents whose write-only construction the whole
database family running green under the manually applied mutations proves.
The end-to-end rule-17 path pinned twice: the fold level (declaration ->
wiring -> adapter -> real SQLite socket -> the tapes and the JSONL sink)
and the boot level unchanged (the silent profile still boots without
running the wiring's factories — the lazy proxies own that, untouched).
