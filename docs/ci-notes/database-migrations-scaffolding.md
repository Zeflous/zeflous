# Database migrations scaffolding — the decision record

**Status:** the design record landed first (draft #707, codeless, so
the implementation arrived against a written contract); the KDD
numbering below is the implementation's refinement of that record,
arrived with the code — the record's own template verb, landing with
zero dependencies exactly as the split promised. The reflected half
(`migrations:diff`) stays behind the schema-reflection landing and
its scratch-database question, its own refined record when it lands.

## The slice in one paragraph

The TEMPLATE verb of the migrations family: `bin/zef
migrations:generate <config-directory> [<migrations-directory>]
<name> [--json]` — a new migration file printed from the house
template, timestamped, written into the directory the run verb
already reads through; NO database touched, NO runner booted, NO
application boot at all (an empty configuration directory answers
the scaffold — the honest posture, not a database-less coincidence).
The implementation is four folds and a dispatch row:
`MigrationTemplate` (src, the template's own entries + the version
boundary), `MigrationName` (src, the name grammar's value fold — the
archeology round's split, below), `MigrationsGenerate` (tooling, the
write seam + refusal table), `MigrationsGenerateText/Json` (the
answer's two spellings), and the `migrations:generate` branch
`bin/zef` now answers beside its three siblings — the sixteenth verb
the vocabulary pins.

## KDD-MS1: the verdict — a template verb with zero dependencies

The record's split, honoured: the generate verb touches NOTHING the
run verb needs — no `MigrationsBoot`, no container, no database
profile resolution, so a host that never spelled a connection
scaffolds its first migration anyway. The configuration directory
argument stays in the usage shape for one reason: the family's argv
boundary is ONE boundary (every migrations verb resolves it the
same way, exit 2 the same way), and an operator scripting the family
should not learn two grammars for one lane. The cost of carrying it
is a realpath + is_dir check the verb never uses beyond the
boundary's own honesty.

## KDD-MS2: the template pin — the contract, byte for byte

The generated file is the interface's own five verbs with the
migration's identity as literal answers: `version()` returning the
14-digit stamp, `name()` returning the canonical name, empty `up()`
and `down()` for the operator to own, `getLockTtl()` returning
`null` (the ordinary-migration doctrine — the runner's default TTL),
and the file's own return composing the class, the declaration
grammar `MigrationFile::declaration` includes. The tests pin BOTH
sides: the byte spelling (`assertFileEquals`-grade string pin) AND
the contract (the generated file INCLUDED, asserting
`MigrationInterface` + the three literal answers) — a template that
drifts from the contract is a footgun printed on every generate, so
the byte pin alone would only prove the drift consistent.

## KDD-MS3: the class name carries the version stamp — fatal-free by construction

The reference's generate lineage (Doctrine's `Version`-stamped class
names) arrives as the house spelling for a structural reason the
record left open: `MigrationFiles::discover` includes EVERY `*.php`
in a flat walk, and two house-generated migrations sharing a name
("create users" run twice) would declare the SAME class name — the
second include a fatal PHP cannot catch. The template's class name
is therefore the name's words capitalised WITH the 14-digit stamp
beside them (`CreateUsers20261106143000`): two migrations sharing a
name are two classes, the version uniqueness the file grammar
already sorts by made structural in the class name. The pin is the
double-include test: two same-named templates, two stamps, two
distinct classes, no fatal — the discovery walk's flat includes can
never meet a duplicate class name from the house template.

## KDD-MS4: the name grammar — one corridor, normalised separators

Lowercase words of letters and digits (a word may start with a
digit — `enable 2fa login` is an operator's natural spelling — but
the NAME starts with a letter, so a name never reads like a stamp),
single spaces or hyphens as separators, at most the registration
grammar's own 128-byte ceiling (the `MigrationRegistration` twin).
The separator is normalised, never invented: hyphens and spaces are
ONE migration — the same file name, the same content, the canonical
name carrying spaces (`MigrationInterface::name()`'s
human-readable spelling) and the file slug carrying hyphens
(`20261106143000-create-users.php`, the house file grammar the tests
already write). The corridor refuses as a CONSTRUCTION VALUE
(`InvalidArgumentException`, the CLI command name's own doctrine)
through every public entry — the usage defect, answered at the
boundary, never the migration phase's typed family.

## KDD-MS5: the refusal table is the contract

Each defect the write can meet answers its own NAMED verdict, exit
1, the lane's own grammar — never a traceback: a directory that
does not exist or is not one refuses with the discovery fold's OWN
message (the read side and the write side answering the same
verdict for the same directory); a directory that cannot hold the
file refuses naming it (`is not writable`); a name colliding with
an existing file refuses, never a silent clobber (the existing
file's bytes pinned to survive the refusal); and a version already
held by an existing file refuses naming the holder. Exit 0 answers
the created file's PATH printed (the design record's own
requirement); exit 2 answers the usage defects (missing name, a
name outside the grammar, an unknown flag, a missing configuration
directory). The CLI subprocess test exercises the whole table end
to end — the streams pinned too (answers on stdout, refusals on
stderr, usage on stderr).

## KDD-MS6: the held-version guard reads the FILE grammar only

The duplicate-version refusal the discovery walk owns
(`MigrationFiles` refusing a version two files both declare) is
PRE-EMPTED at the write for the house convention only: a generated
file always wears `<stamp>-<slug>.php`, so the guard globs
`<stamp>-*.php` and refuses naming the holder — the write verb
never prints a file the scan would refuse, an operator learning
the verdict from the command that created it, not the run that
would trip over it. A hand-written file whose name carries no stamp
stays OUTSIDE the guard (pinned): its duplicate version, if any,
remains the discovery walk's own refusal — loading host code
belongs to the read side, and the write side never reaches into a
file it did not print to ask what it declares.

## KDD-MS7: no knob; one argv boundary; the JSON the pipeline reads

The census family gains nothing (the record's own refusal: a
generate knob with no config face is a dead key) — the migrations
directory is an ARGUMENT, defaulting to the configuration
directory's sibling `migrations` exactly as the run verb resolves
it, so the write lands in the boundary the read already owns. The
`--json` flag rides the generate verb (it is an ANSWER verb, the
status verb's own posture — the mutation verbs `run`/`rollback`
keep refusing it): the created file's path, its version stamp and
its canonical name over the inspection family's one encoding
spelling, the stable shape a deployment pipeline scaffolds through.
The version stamp is the verb's own clock, UTC, `YmdHis` — the
14-digit stamp the runner's grammar demands, spelled the same
second the operator asked.

## KDD-MS8: what this slice deliberately does not carry

The diff verb itself (behind schema reflection and the scratch
question — its own refined record, and the record's pre-recorded
honesty stands: diffing a live MySQL schema against a SQLite replay
is a lie, so diff lands only for the driver its scratch can spell
or replays against the SAME driver); interactive prompting (the
CLI's argv/env doctrine has no TTY expectations); template variants
(up-only, dummy, test fixtures — named follow-ups if an operator
asks); any DDL execution (the run and rollback verbs own execution,
forever — the generated `up()`/`down()` are empty by the same
doctrine).

## Verification

- `MigrationTemplateTest` — ten tests: the byte-for-byte template
  pin, the contract pin (the generated file included, answering the
  interface + its three literals), the fatal-free double-include
  pin (two same-named migrations, two stamps, two classes), the
  file-name grammar and its sort position, the separator
  normalisation (one migration, whichever spelling), the name
  corridor's refusal table through every public entry (each verdict
  pinned as the FULL message, byte-exact — the concat-reordering
  mutants the ContainsString spelling would let walk), the version
  corridor's refusal table, and the grammar's own acceptance edges
  (digits inside words, the single word, the 128-byte boundary
  spelling, the class name's shape).
- `MigrationsGenerateTest` — eleven tests over the write seam and
  the renderer pair: the write landing the template's own bytes,
  the hyphen/trailing-slash normalisation, and the refusal table's
  every row (missing directory, a plain file wearing the path, the
  unwritable directory, the collision with the existing file's
  bytes surviving, the held version naming its holder, the
  unstamped hand-written file staying outside the guard), plus the
  text and JSON answers pinned by value.
- `MigrationsGenerateCommandTest` — twelve subprocess tests over
  `bin/zef` itself: the default sibling directory and the explicit
  one, the created file satisfying the interface contract end to
  end (include + answers), the JSON document's identity pins, the
  missing/unwritable directory refusals (exit 1, stderr, the named
  verdicts), the usage table (exit 2: the name outside the grammar,
  the missing name, the unknown flag, the missing configuration
  directory), the usage line's generate row, and the directory
  listing answering the sixteenth verb's own line.
- The vocabulary and the listing move together: `FrameworkVerbs`
  gains `migrations:generate` (the correspondence test's own pin,
  `bin/zef`'s branch and the vocabulary one surface), and the CLI
  directory pins ride the count (15 → 16 framework verbs) in
  `CliListTest` and the module integration test.
- Scoped mutation rounds over the five touched source files: the
  template + the name fold + the write + the renderer pair — after
  the restructure below, **52 mutants, 52 killed, MSI 100/100,
  zero escapes** (the first round's 75 carried the pre-split
  template's validation surface). Two lessons the first round's
  nine escapes taught, both paid: (1) the name-refusal message is
  pinned FULL (the ContainsString spelling let seven
  concat-reordering mutants walk), and (2) the template's own
  validation must not be masked by a nested re-validation — the
  corridor moved to the `MigrationName` value fold the entries
  compose after their own refusal, so each entry validates exactly
  once and the validation's removal is visible in that entry's own
  answer. One documented ignore joins the family: the corridor's
  byte ceiling counts BYTES over `mb_strlen($name, '8bit')` — the
  encoding that IS byte semantics, the mutator's strlen rewrite
  answering the identical count for every name the grammar can ever
  see — the `MigrationRegistration`/`SqlIdentifier` twins' own
  argument, replayed for the scaffolding verb's corridor (the
  fourth member of that doctrine family), scoped to the single
  method.
- The CI archeology round (the first red check on the branch):
  `MigrationTemplate` as one unit answered difficulty 25.70 (file)
  over the 20 ceiling — answered by the house's own fold split, the
  corridor becoming the `MigrationName` value fold: the template
  lands at difficulty 16.82 and the name fold at 10.67, both under
  the ceiling, the gate green (0 error-level findings). The value
  fold's shape also paid a coverage dividend the metadata filter
  demanded: the fold carries its own `#[CoversClass]` entry on the
  template's test (the #589 covers-filter lesson replayed — a unit
  the tests exercise only through another class's metadata collects
  nothing), both units answering 19/19 statements.
- The psalm ledger moves with the landing: the three tooling units
  enter the baseline as `UnusedClass` (their consumer is `bin/zef`,
  outside psalm's projectFiles — the renderer family's own
  position), the src-side template needs no entry.
- Full battery on the branch head: 7222 tests / 17624 assertions;
  phpstan 0 errors; psalm 0 errors; deptrac 0 violations (the
  template and the name fold sit inside the Database layer, the
  write inside the Tooling layer whose Database edge the migrations
  family already owns); duplication + mess gates green; archeology
  0 error-level findings (after the round above); coverage gate
  98.49% (required 90); zero-deps, smoke, workflow-concurrency and
  the roadmap gate green.
