# CI note: the coverage measurement contract — one sanctioned invocation, one answer (2026-11-08)

Date: 2026-11-08
Status: shipped (this pull request)
Scope: `CONTRIBUTING.md` (rule 9, the measurement paragraph), this note, `README.md` (the CI Coverage row's pointer)
Origin: issue #721 — "coverage measurement is invocation-sensitive (~9-point gap between manual and official command)"

## The trap, named precisely

Two invocations that look equivalent answer different numbers on the same
suite, the same checkout, the same PHP:

```text
# the "intuitive" spelling — no pcov.directory, no pcov.exclude,
# no zend.assertions
$ php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text
 Summary:
  Classes:   85.53% (1023/1196)
  Methods:   90.69% (2935/3236)
  Lines:     90.00% (16236/18039)

# the repository's official script (composer.json "coverage")
$ composer coverage
 Summary:
  Classes:   92.55% (1107/1196)
  Methods:   96.87% (3135/3236)
  Lines:     98.59% (17786/18039)
```

Both runs above were measured on `8bb7904b` (PHP 8.4.24, pcov 1.0.12) —
**8.59 points apart on lines**, 7.02 on classes, 6.18 on methods. The issue's
original field evidence (checkout `7ad0d89f`, PHP 8.4.27) measured 89.77 %
against 98.56 % — the gap is stable across the tree's growth, not an artifact
of one checkout.

The dangerous part is not the size of the gap but where the bare spelling
lands: **90.00 % exactly** on the current tree, and **below the 90 % floor**
on the checkout the issue was filed from. A developer measuring manually
after a harmless refactor sees either "sitting on the gate's floor" or
"under the gate's floor" and starts hunting a regression that does not
exist — while `composer ci:coverage` never went red at all.

## Why the numbers differ — the three pinned settings

The official script spells every knob the bare invocation leaves to default:

```json
"coverage": "@php -d pcov.directory=$PWD -d pcov.exclude=/vendor/ -d memory_limit=1G -d zend.assertions=1 vendor/bin/phpunit --coverage-text"
```

- **`pcov.directory=$PWD`** — restricts instrumentation to the repository
  tree. Without it pcov instruments by its own default scope, and the
  classes outside that scope drop out of the *recorded* numerator: the same
  suite, 84 fewer classes carrying coverage (1023 vs 1107) and 1550 fewer
  covered lines (16236 vs 17786), with the denominator unchanged — the
  signature of files loaded but not instrumented, not of code losing tests.
- **`pcov.exclude=/vendor/`** — keeps the vendored tree out of the record
  entirely; the denominator stays the repository's own 18,039 lines.
- **`zend.assertions=1`** — compiles the assertions in. Production and the
  bare CLI run with assertions compiled out, so every `assert()` site reads
  as an uncovered line under the bare spelling even though a test executes
  it; the official measurement counts what the suite actually exercises.
- **`memory_limit=1G`** — not a measurement knob at all: the full suite with
  instrumentation simply does not fit the default limit.

## The contract

1. `composer coverage` (local reading) and `composer ci:coverage` (the
   90 % gate) are the **only sanctioned measurements**. This is now rule 9's
   own paragraph in `CONTRIBUTING.md`.
2. A number produced by any other invocation is **unmeasured**, not a
   decline: triage the invocation before triaging the code.
3. Do not "fix" a manual reading by chasing the gap — reproduce it with the
   official script first; if the official number is green, the manual number
   was never evidence of anything.
