# The CLI Entrypoint Joins the Analysers' Scope — KDD

**Scope:** the widening that closes issue #718's analyser half: the CLI
entrypoint `bin/zef` — 169 lines after the #717 verb refactor landed it
there — enters the scope of every static-analysis gate that reads
first-party code: PHPStan (`phpstan.neon.dist` `paths`), Psalm
(`psalm.xml` `projectFiles`), Rector (`rector.php` `withPaths`),
php-cs-fixer (`.php-cs-fixer.php` finder), PHP_CodeSniffer
(`phpcs.xml.dist` `<file>`), and the syntax-lint corpus
(`LintReport::collectPhpFiles()`'s new named-entrypoint arm). The
duplication and mess corpora follow in their own slice (the issue's own
(b) split, `phpcpd`/`phpmd` over `bin/`), so every gate that can read
the entrypoint does.

**Verified locally before the PR:** `ci:static` EXIT-0 · psalm EXIT-0
(0 errors, 12 baseline entries retired) · phpstan `[OK] No errors` ·
`rector:check [OK]` · `format:check` 0 of 2167 files · `phpcs` green ·
scoped infection over the two touched gate classes MSI 100/100.

## KDD-CA1 — Finding 1's mechanism, gate by gate: the extension-less no-op

The issue's Finding 1 measured one mechanism: every scope config in this
repository selects files by directory walk + extension filter, and
`bin/zef` carries no extension, so a directory entry is a **silent
no-op** — the gate reports green over a corpus that never contained the
file. The fix is the same everywhere: name the file explicitly.

| Gate | The no-op spelling | The answering spelling |
|---|---|---|
| PHPStan | `paths: [- bin]` | `- bin/zef` (explicit file entry) |
| Psalm | `<directory name="bin"/>` | `<file name="bin/zef"/>` |
| Rector | `withPaths([__DIR__.'/bin'])` | `__DIR__.'/bin/zef'` |
| php-cs-fixer | `in([..., '/bin'])` (name filter drops it) | `append([__DIR__.'/bin/zef'])` |
| PHP_CodeSniffer | `<file>bin</file>` (directory + `extensions=php`) | `<file>bin/zef</file>` (an explicitly named file is sniffed as-is, measured) |
| Syntax lint | the `bin` corpus directory + `getExtension() === 'php'` | a new named-entrypoint arm in `LintReport` |

Every entry carries the essay beside it in the config that owns it; the
lint arm's directory roster keeps `bin` (any future `*.php` file under
it joins the corpus), while the one file the repository executes by name
rides by its own path.

## KDD-CA2 — the findings the widening actually met (not the issue's stale census)

The issue's pre-implementation measurements (15 phpstan / 101 psalm
findings) were taken against the 1,066-line pre-refactor entrypoint at
`7ad0d89f`. Re-measured against the refactored 169-line file the honest
census was:

- **PHPStan: 0 errors.** The verb refactor already left the entrypoint
  max-level clean.
- **Psalm: 11 errors, one class.** Every verb call site passed
  `array_slice($argv, 2)` to a `list<string>` parameter — psalm's
  `array_slice` stub returns `array<array-key, mixed>` (it loses
  list-ness; phpstan's own stub keeps it, which is why only psalm
  flagged this). The honest fix is one narrowing at the top:
  `$arguments = array_slice($argv, 2)` under `@var list<string>` — a
  **true runtime property** (PHP's CLI contract spells `$argv` as the
  script name then arguments, all strings; a slice with
  `preserve_keys=false` over a list answers a list), stated once
  instead of eleven times. The per-branch slices the #717 refactor kept
  were plumbing, not validation, so hoisting them changes no behaviour —
  and kills a repetition the duplication gate would have named.
- **Rector: 2 rules.** `dirname(__DIR__) . '/x'` → `__DIR__ . '/../x'`
  (equivalent strings) and the `!== null` check → the exclusive-type
  `instanceof CommandOutcome` spelling. Both applied, both equivalent.
- **php-cs-fixer / phpcbf: mechanical formatting.** use-imports for the
  eleven verb references, early-exit restructure inside the autoloader
  closure, the escape-simplified string keys, the end-of-file newline.

## KDD-CA3 — the baseline shrink the widening paid for

`bin/zef` entering `projectFiles` made it the **in-project consumer**
the verb classes' baseline entries were waiting for: psalm's
`findUnusedBaselineEntry` retired twelve entries the same run — the
eleven verb classes' `UnusedClass` pins (each rode the
"bin/zef-outside-projectFiles precedent" its landing recorded) and
`CommandOutcome`'s three `PossiblyUnusedProperty` pins (the entrypoint
reads all three: `stderr`, `stdout`, `exitCode`). The baseline is a
`-` twelve entries smaller than main; nothing was added.

## KDD-CA4 — the one exclusion the widening earned, and the one it did NOT take

`PSR1.Files.SideEffects` flags the entrypoint for declaring
`requireFrameworkAutoloader()` and executing the dispatch in one file.
That duality is the job description: **a loader cannot be autoloaded**
(chicken-and-egg), and the vendor-first strategy it owns must run before
any other file loads, so the entrypoint is the only home the bootstrap
grammar admits; PSR-1's own text targets library files, and a bootstrap
script is the canonical exception. The exclusion carries the essay in
`phpcs.xml.dist` — one rule, one file, no reasonable alternative, the
S1066 MeterCount shape.

The temptation NOT taken: excluding `ReferenceUsedNamesOnly` for the
`\STDERR`/`\STDOUT` spellings. Instead the two fixers were **harmonised**:
`native_constant_invocation` gains `scope: 'namespaced'` — the exact
posture its function twin already carries — so the namespace-less
entrypoint keeps the bare `STDERR` spelling Slevomat demands there
(a leading backslash in a file without a namespace is the error its own
sniff names), and the two tools agree instead of ping-ponging the
spelling. No src/tests behaviour changes: namespaced files rewrite
identically under both scopes.

## KDD-CA5 — the gates that stay out, and why that is not a no-op

Two gates do not read `bin/zef`, deliberately:

- **Deptrac** guards namespace-layer boundaries collected by
  `classNameRegex`; the entrypoint declares one **function** and no
  classes, so there is no layer token to collect — a `./bin` path entry
  would be the same silent no-op Finding 1 names, wearing a different
  hat. Its dependencies (the eleven verbs, `CommandOutcome`) are all
  inside the `Tooling` layer the config already guards from every other
  entry.
- **PhpCodeArcheology** measures class-level difficulty/effort; a
  function-only procedural file has no node its grammar can score.

Both exclusions are scope-of-grammar facts, not scope-of-config choices;
this note is their record.

## KDD-CA6 — the regression pins

The widening is pinned three ways: `LintReportTest` collects the named
extension-less entrypoint (and refuses a missing one, fail-loud like a
missing directory); `GateRunnerTest` asserts the lint arm actually
lints `bin/zef` and refuses a vanished entrypoint; and every config
entry carries its essay beside the line that owns it — so the next
reader sees the no-op trap before they re-trip it.
