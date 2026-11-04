# Database migrations scaffolding — the design record

**Status:** a design record, landed before the code. The implementation
arrives in this slice's own pull request over this record, refining it
where the verdicts below name their open edges; the KDD numbering
arrives with the implementation, not here. The slice is KDD-DW7's own
named follow-up ("a CLI migration runner" landed as the command family
#680; the scaffolding verb is the recorded next).

## The slice in one paragraph

TWO verbs, honestly split before any code exists:
`migrations:generate <name>` — the TEMPLATE verb (a new migration file
from the house template, timestamped, written into the host's
migrations directory; NO database touched) — and `migrations:diff` —
the REFLECTED half (live schema against declared history, the
scaffolded migration answered from the difference), which lands only
after the schema-reflection slice and only with its scratch-database
question answered. The split is the record's own honesty: the template
verb has zero dependencies and lands first; the diff verb is a
consumer of reflection and lands second, over its own refined record.

## The facts the design rests on

`MigrationFiles` scans the host's directory flat and alphabetically
(the glob skips dot-files), `MigrationFile` is the one-file fold, and
`MigrationInterface` is the up/down contract; the directory is HOST
content — the tree ships no migrations of its own. The generate verb
writes into the directory the caller spells, the same boundary the run
verb already reads through; the file's name must sort where the scan
reads it (the timestamp prefix the existing file grammar carries).

## The units sketch

| Unit | Question it owns |
|---|---|
| the template fold | the exact class shape `MigrationInterface` demands, spelled from the house template with the migration's own name and today's timestamp |
| the writing verb (CLI) | `bin/zef migrations:generate <config-directory> [<migrations-directory>] <name>` — argv/env boundary per the `route:*` and `storage:map` precedents; exit 0 = file created (the path printed), 1 = refusal (the directory does not exist / is not a directory / is not writable — a named refusal, a verdict, never a usage defect), 2 = usage |
| the renderer pair | the answer's text and JSON spellings in the test-support tooling lane, the `MigrationsStatus*` precedent beside them |

## The decisions to carry

- **The template is the tree's own.** The reference's generate shape
  (Laravel's `make:migration` lineage, Doctrine's
  `migrations:generate`) arrives as the house spelling: a final class,
  the interface's two verbs, no scaffolding magic beyond the names.
- **No knob.** The directory is an argument, the run verb's own shape
  — the census family gains nothing (a generate knob with no config
  face is a dead key).
- **The refusal table is the contract.** A migrations directory that
  cannot hold the file answers exit 1 with the refusal naming the
  defect (the storage lane's own refusal grammar), because an operator
  scripting the verb needs the verdict, not a traceback.
- **The diff verb's honesty is pre-recorded.** Diffing a live MySQL
  schema against a SQLite replay is a lie — the diff verb lands only
  for the driver its scratch database can spell (an in-memory SQLite
  scratch for a SQLite live schema), or replays against the SAME
  driver; the implementation writes the choice down, and the record
  refuses to promise cross-driver diffing the tree cannot honestly
  answer.

## What the implementation must answer

- The template pin: the generated file's exact content (the class
  shape, the name, the timestamp) asserted byte-for-byte against the
  interface's contract — a template that drifts from the contract is
  a footgun printed on every generate.
- The name grammar pins (the identifier grammar, the sort position in
  the flat-alphabetical scan) and the exit-code table.
- The overwrite refusal: a name colliding with an existing file
  answers the named refusal, never a silent clobber.
- The full battery, archeology under the ceiling, scoped infection
  MSI 100/100, the roadmap row at landing.

## What this slice deliberately does not carry

The diff verb itself (the second half, behind schema reflection and
the scratch question — its own refined record when it lands);
interactive prompting (the CLI's argv/env doctrine has no TTY
expectations); template variants (up-only, dummy, test fixtures —
named follow-ups if an operator asks); any DDL execution (the run and
rollback verbs own execution, forever).
