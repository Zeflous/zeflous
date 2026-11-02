# Database migrations CLI — the operator lane's runner, the discovery fold, and the ledger's first live caller

Slice: `feat(database): the migrations command family` — KDD-DW7's first
named follow-up (`docs/ci-notes/database-wiring.md`): "a CLI migration
runner (`bin/zef migrations:run`-shaped) -- the wiring answers the runner
as a service identity, and the command that walks it is the operator
lane's own future slice, landing with its own KDD (it is also the first
live CALLER of the Migrator verbs the ledger now carries)".

## The slice in one paragraph

Three verbs over one boot: `bin/zef migrations:status <config-directory>
[<migrations-directory>] [--json]` (the dry-run answer -- applied,
pending, declared), `migrations:run` (applies every pending migration),
and `migrations:rollback [--steps=<n>]` (rolls back the newest applied).
The one seam they share is `MigrationsBoot` (tests/Support/Tooling): the
host's configuration directory boots through the same
`ApplicationBootstrapper` the runtime rides (never a parallel parse, the
analysis CLIs' own doctrine), the runner answers from the container's own
`database.migrator` service identity the framework profile registers
(the wiring slice's per-purpose connection), and the operator's
migrations directory joins through the discovery fold
(`MigrationFiles::discover` over the one-file `MigrationFile::declaration`
fold) -- every declaration registered before the first question, so the
verbs answer over the whole declared set, never a partial registration.
The presentation family renders the plain data those questions answer:
`MigrationsStatusText`/`MigrationsStatusJson` (the counts on the header,
one record per version, the honest `(undeclared)`/null spelling for an
applied version whose declaration has since left the directory) and
`MigrationsRunText` (the verb, the honest singular, the empty answer
spelled too). The CLI grammar is guarded the analysis commands' own way:
usage defects exit 2, operational refusals exit 1 naming what an operator
needs, the answer exits 0.

## KDD-DM1: the scope — three verbs, one boot, the runner's own surface

KDD-DW7 named "`bin/zef migrations:run`-shaped"; the slice ships three
verbs because the Migrator's own public surface already spelled them --
`plan()` is status, `migrate()` is run, `rollback()` is rollback -- and a
family that answers only the mutation verbs would leave the operator
asking "what IS applied?" through a connection of their own composition.
The three verbs share one boot so they answer over the same declared
world: a deployment whose status answers two pending migrations runs
exactly those two, and the run's answer names exactly the versions the
status promised. The boot delegates to the runner's own verbs (the thin
`applied()`/`plan()`/`migrate()`/`rollback()` delegations) rather than
reaching around them, which is what makes the CLI the runner's first LIVE
caller -- the psalm ledger's promise, not a courtesy.

## KDD-DM2: the discovery fold — host content, and the seam ownership the duplicate guard forces

The migrations directory is HOST content (the tree ships no migrations
of its own), so the fold owns only the directory's grammar and invents
nothing: a directory that does not exist or is not one refuses naming
it; a file whose return is not a migration instance refuses naming the
file; a version declared by two files refuses naming BOTH. The duplicate
guard is the fold's own -- the runner's `MigrationRegistration` would
refuse the second registration too, but without the file names an
operator needs to find the offending pair; the guard lives where the
file names are. The declaration's OWN corridor (the 14-digit version
stamp, the name's byte ceiling) deliberately does NOT: a bad version
stamp refuses at the register verb's own words ("Migration version
'short' must be a 14-digit timestamp"), exactly where the orchestration
slice pinned it -- pinned here too, as a test. The scan is flat and
alphabetical, dot-files invisible (the staging doctrine), no ordering
contract beyond the walk's own determinism, because the runner sorts by
VERSION, never by file name. Including a migration file executes host
code; that is what a migration file IS -- the host's own throw inside
the file surfaces unchanged (pinned), the way every host-code seam
behaves.

## KDD-DM3: the sibling default — the migrations directory's convention

The migrations directory defaults to the configuration directory's
sibling `migrations/` (`app/configs` -> `app/migrations`), spelled in
the usage text, overridable by the explicit positional argument. No
environment override joins the storage root's `ZEF_STORAGE_ROOT`: the
storage root names RUNTIME-GENERATED content whose address genuinely
varies per environment, while the migrations directory is SOURCE content
that travels with the codebase -- a deployment that needs a different
directory spells it once in the command (or the pipeline that calls the
command), and a knob for it would be a config key whose reader is an
argv parser. A missing default directory is the discovery fold's own
verdict (exit 1, the directory named), never a usage defect: the
operator spelled a real configuration directory, and the tree that is
not there is the fact they need to see -- the `storage:map` precedent's
distinction, replayed.

## KDD-DM4: the ledger dance — four leave, nine arrive

The wiring slice's KDD-DW5 ended with the ledger carrying "Migrator's
runner verbs ... their live callers being the host's code". The host
arrived: `register` (from the boot's discovery walk), `plan`, `migrate`
and `rollback` (from the boot's delegations) each gain their first
caller inside the analysed tree, and their four `PossiblyUnusedMethod`
entries LEAVE the baseline. What arrives is the CLI family's own
surface: six `PossiblyUnusedMethod` entries for `MigrationsBoot`'s own
verbs and three `UnusedClass` entries for the renderers -- their
consumer is `bin/zef`, outside psalm's projectFiles, the same posture
every inspection renderer before them carries (the route inspector's
precedent). The debt moved again, and moved DOWN: from the runner's
verbs (a whole capability the tree could not reach) to one command
family's own surface (consumed by the one file every CLI consumer lives
in). The `--json`-on-status-only decision keeps the JSON renderer's
surface one verb wide: the mutations' machine-readable lane is the EXIT
CODE plus the status document a pipeline reads after -- a run that also
answered JSON would answer it over a body the exit code already told.

## KDD-DM5: the settle round — the one-file fold, and the guard psalm found

The discovery fold's first spelling sat at difficulty 21.27 over the 20
ceiling -- the directory grammar and the one-file judgement in one verb
(the class-difficulty-is-aggregate-volume lesson replayed). The split:
`MigrationFile::declaration()` owns the one-file question (the
regular-file guard, the include, the instance refusal) and the directory
walk keeps the duplicate-version guard -- 18.00 and 11.00 under the
ceiling, the `DatabaseConnectionKnobs` extraction's own shape. The
`include $file` psalm cannot resolve met the `PhpFileLoader` doctrine
-- the `is_file()` guard narrows the path -- and the guard is load-
bearing, not ceremonial: a glob `*.php` match can be a DIRECTORY wearing
the extension's name, and the refusal naming that entry is its own test.
The two lessons are one: the static analyser's complaint and the
difficulty ceiling both pointed at the same seam, and the answer to both
was the same fold -- not an ignore, not a baseline entry, a question
given its own unit.

## KDD-DM6: the mutation round — two kills, never ignores

Two escapes answered with tests. The `UnwrapFinally` on the boot's
cleanup: an AOT-ENABLED boot (a `database.php` plus an `aot.php`
declaring `enabled => true`) dumps the throwaway container artifact the
boot itself wired, and the artifact never outlives the boot -- glob
compared before and after, the analysis boots' own discipline spelled
for the migrations boot (the killed mutant's process leaves the file
behind, which is exactly what the glob sees). The `ArrayOneItem` on the
JSON records fold: a status document whose applied section carries TWO
records, both asserted -- a one-record test cannot see a records list
truncated to its first element. Scoped over the family: 77 mutants
killed, MSI 100/100, 0 escapes, 0 new ignores.

## KDD-DM7: what this slice deliberately does not carry

The named follow-ups, each its own slice: a **scaffolding verb**
(`migrations:generate`-shaped, the reference's none-existent answer and
the tree's own operator-lane question -- a command that WRITES a host
migration file is a different posture than every analysis verb the
bin carries, and it lands with its own KDD if a deployment asks);
**`--json` on the mutation verbs** (DM4's reasoning); a **migrations
directory knob** (DM3's reasoning -- the day a deployment asks, the
census entry arrives the way every opt-in family's did); and the
reference's `ConfigMigrator` family -- its "migrations" are CONFIG-FILE
migrations (the DSL's own versioned schema walk), a different lane that
would ride the Configuration System's own area, never this one.

## KDD-DM8: the numeric-string key truth

The 14-digit version stamp is an integer-like string, and PHP converts
integer-like strings to INT array keys on every write path -- the
discovered map the boot answers is INT-keyed at runtime whatever the
PHPDoc says (`array<string, MigrationInterface>`, the spelling the whole
src family already carries and psalm/phpstan accept). The renderers'
`$names[$version]` lookups ride PHP's own string-offset conversion (a
string offset into an int-keyed array finds the key), so the honest
`(undeclared)` fallback never fires for a declared version -- pinned by
the e2e runs. The TEST side is where the truth bites: a literal
`['20261102120000' => 'create users']` in a test is typed by phpstan as
`array<int, string>` (the same conversion, modelled for literals), and
an `assertSame` against a PHPDoc'd `array<string, string>` return is
flagged "always false" -- the honest spelling is the one the runtime
itself uses: expected maps built through `array_combine` (typed
generically, int-keyed at runtime like the real map) and row lists built
through `foreach` concatenation, never literal-keyed map comparisons.
The lesson generalises: when a domain vocabulary is integer-like
strings, pin it through the shapes the runtime produces, not the shapes
the literal looks like.

## Verification

The full local battery: `ci:static` core green (6698 tests / 16233
assertions, smoke 2760B budget 20000, concurrency 23 workflows, roadmap
gate, audit 0, duplication and mess clean), PHPStan max clean, Psalm
EXIT-0 over the regenerated ledger (four entries out, nine in), deptrac
0 violations over the one widening (`Tooling += Database`, the storage
and HTTP inspection commands' own read-side pattern), archeology 0
error-level findings after the fold split (18.00 and 11.00 under the 20
ceiling), scoped infection over the family MSI 100/100 (77 killed, 0
escapes, 0 new ignores), coverage with the new folds at 100% lines and
the tree at 98.54% against the 90% floor, and the end-to-end CLI walked
by hand over a self-contained fixture: all three verbs over a real
SQLite socket through the wiring (the tables landed in apply order),
the durable record answered by a second boot, the rollback re-appliable,
the honest `(undeclared)` rendering after a declaration file was
removed, the sibling default answered without the positional argument,
and every guarded exit verified -- usage defects 2 (missing configuration
directory, unknown flag, `--steps=0`, `--json` on a mutation verb, no
arguments), operational refusals 1 (the missing migrations directory
named, the silent profile's own refusal at the host's first use), the
answer 0.
