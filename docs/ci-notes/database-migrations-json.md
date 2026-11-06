# Database migrations JSON answers — the decision record

**Status:** the design record landed first (draft #708, codeless, so
the implementation arrived against a written contract); the KDD
numbering below is the implementation's refinement of that record,
arrived with the code — the ledger's smallest named follow-up
("--json on the mutation verbs"), landing with zero dependencies
exactly as the record promised. The record's own honest posture
stands: this slice REVERSES a recorded decision — the CLI slice's
KDD-DM4 reasoning ("the mutations' machine-readable lane is the exit
code plus the status document a pipeline reads after") and the
scaffolding slice's KDD-MS7 sentence ("the mutation verbs keep
refusing it") were both scoped to their landings; the reversal was
itself WRITTEN before it was coded, the ledger's own discipline.

## The slice in one paragraph

The mutation verbs gain their JSON twins: `bin/zef migrations:run
--json` and `migrations:rollback --json` answer the machine-readable
payload beside the text every operator reads — `MigrationsRunJson`
and `MigrationsRollbackJson` (tests/Support/Tooling), two renderer
classes over the inspection family's one encoding spelling, each one
verb and one key, wired by the `--json` flag the CLI's migrations
branch now accepts on all four family verbs. The exit codes are
UNCHANGED — the JSON is the payload, never the verdict.

## KDD-MJ1: the verdict reversal, carried honestly

The CLI slice refused this flag with a real argument: the exit code
IS the machine-readable lane, and a pipeline could re-ask status
after every run. The design record's question refined it: "Script,
what just happened?" — a pipeline forced to re-ask status composes
two commands to learn one fact, and the run's own answer already
carries that fact (the versions, the names). The reversal's cost is
one flag branch; its gain is the drift question closed at the source:
the status document a pipeline reads after and the run's own answer
can never disagree, because they no longer both exist. The exit-code
table stays the contract BOTH spellings share — pinned: a second run
touching nothing answers exit 0 with the empty applied list, the
same verdict the text spelling answers (`storage:map`'s own
discipline: the shape is a flag, the verdict is not).

## KDD-MJ2: the payload is the text renderer's own facts

No enrichment, no second bookkeeping — the twins take the SAME
inputs the text renderer takes (`array<string, string> $names`,
`list<string> $versions`) and render the same facts: one record per
version, `{'version', 'name'}` — the status document's own record
shape, `null` for a version whose declaration has since left the
directory (the honest spelling the text renderer carries as
`(undeclared)`). The verb's own word travels as the document's one
key: `applied` for the run, `rolledBack` for the rollback — the
camelCase spelling the inspection family's multiword keys carry
(`failedStage`, `sharedPrefix`). The text headline's count does NOT
travel: the count is derivable from the list (a script counts), and
the design record's own enumeration — the versions, the names, the
verb's word — is the contract; a `count` key would be the enrichment
the record refused.

## KDD-MJ3: the encoder is the shared one

`RouteInspectionJson::encode` rides again — pretty-printed for the
operator's terminal, forward slashes unescaped, throw-on-error so an
encoding failure surfaces as the CLI's own refusal. A per-verb
encoder would spell the same JSON differently, the exact
fragmentation the renderer-pair discipline exists to prevent; the
twins join the family (`route:list`, `storage:map`, `migrations:status`,
`migrations:generate`) over the one spelling.

## KDD-MJ4: two twins, one text — the units as the record sketched

The design record's unit sketch named two classes, and the
implementation honours it: `MigrationsRunJson::applied()` and
`MigrationsRollbackJson::rolledBack()`, each `final readonly` with
its own private records fold (the status renderer's own shape — a
ten-line walk, `null` for the undeclared key). The TEXT renderer
stays ONE class with two verbs (`MigrationsRunText::applied()` /
`rolledBack()` — its landed shape untouched): the asymmetry is the
record's own, and the duplication it buys is the independence the
family's renderers already carry (each unit answers its own question
alone). The records fold's `?? null` arm is the mutation round's own
question — answered below.

## KDD-MJ5: the flag grammar — one boundary, four verbs, unchanged

`--json` joins the family's accepted set: status, run, rollback and
generate all own it now (one `in_array` the CLI branch spells).
`--steps=` stays the rollback verb's alone — `--steps=1` on the run
verb still refuses with the usage, exit 2, the status precedent's own
grammar ("a flag the verb does not own refuses"); unknown flags
refuse the same way. The usage block spells `[--json]` on both
mutation rows beside their own flags (`[<migrations-directory>]
[--steps=<n>] [--json]`), and the flag composes unchanged with the
explicit migrations directory (pinned at the subprocess level). The
JSON documents carry the trailing newline every JSON answer in the
tree carries; the text renderers' own trailing newline is untouched.

## KDD-MJ6: what this slice deliberately does not carry

Replayed from the record, unchanged: `--json` on verbs with no
machine-readable answer to carry (the boundary doctrine — a flag
that changes nothing is a dead flag); a streaming or evented shape
(the verbs answer once, at completion — the progress question is a
TTY question the CLI doctrine already answers with text); any schema
for the JSON beyond the pins (the pins ARE the schema — a formal
schema document would be a second source of truth the pair could
drift from).

## Verification

- `MigrationsBootTest` — nineteen tests over the boot and the whole
  presentation family: the four new twin pins are (1) the run's JSON
  and text twins answering the SAME facts over the real socket (the
  runner's own `migrate()` answer, the boot's own declared names,
  each JSON record asserted equal to `{'version', 'name'}` and each
  text row asserted to spell the same version and name — the drift
  question closed at the fold), (2) the honest empty (`applied: []`,
  never a truncated document), (3) the rollback twin over `steps=2`
  answering EXACTLY two records, newest-first, names travelling (a
  third declared migration joined the fixture for the pin), and (4)
  the undeclared rollback naming its version `null`, never a guess.
- `MigrationsMutationCommandTest` — eight subprocess tests over
  `bin/zef` itself, the family's first mutation-verb CLI pins: the
  text baseline byte-exact (headline, three rows, stdout alone),
  the JSON answer carrying the three applied versions in order with
  their declared names (the design record's own payload pin), the
  second run's honest empty with the SAME exit code 0 (the
  shape-is-not-the-verdict pin), `--steps=2 --json` answering
  exactly two (the record's own steps pin), the text and JSON
  rollbacks over the same operation answering the same facts, the
  flag-not-owned usage refusal (exit 2, stderr, the run row's
  usage line), the usage block spelling both mutation rows with
  `[--json]`, and the missing migrations directory refusing exit 1
  with the named verdict on stderr and nothing on stdout.
- Scoped mutation round over the two renderer files: **10 mutants,
  10 killed, MSI 100/100, zero escapes, zero new ignores** — the
  records fold's `?? null` arm (the `Coalesce` mutator's `?:`
  rewrite) is killed by the undeclared-version pins under the
  suite's `failOnWarning` posture: the mutated arm reads a missing
  array key as a warning, and warnings fail the suite. The two
  renderer classes enter the psalm baseline as the renderer
  family's own `UnusedClass` position (their consumer is `bin/zef`,
  outside psalm's projectFiles).
- Full battery on the branch head: 7234 tests / 17668 assertions;
  phpstan 0 errors; psalm 0 errors; deptrac 0 violations; phpcs and
  php-cs-fixer clean; rector clean; duplication and mess gates
  green; archeology 0 error-level findings; coverage gate 98.59%
  (required 90); zero-deps, lint (2161 files), smoke (2760 bytes /
  2,000,000 budget), workflow-concurrency (23 workflows) and the
  roadmap gate green.
