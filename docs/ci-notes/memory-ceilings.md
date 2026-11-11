# CI note: explicit memory ceilings on the three parallel gates — exit 137 is a budget, not a defect (2026-11-08)

Date: 2026-11-08
Status: shipped (this pull request)
Scope: `composer.json` (`mutation`), `rector.php` (`withParallel`/`withMemoryLimit`), `tests/Support/Tooling/GateRunner.php` (the mess arm's command), `tests/Tooling/GateRunnerTest.php` (the pinned command's assertion), `CONTRIBUTING.md` (the quality-gates resource paragraph)
Origin: issue #720 — "parallel gates have no memory ceiling → OOM (exit 137) in constrained environments"

## The failure mode, named precisely

Three gates spawn parallel workers by the host's **CPU core count**, with no
memory budget anywhere in the spelling:

```text
# composer.json -- the mutation lane
"mutation": "... vendor/bin/infection --threads=max ..."
# rector.php -- no parallel stanza at all: Rector's default is a worker per core
# tests/Support/Tooling/GateRunner.php -- the mess lane
'... vendor/bin/phpmd analyze src --format=json --ruleset=phpmd.xml ...'
```

The result on the audit environment — a 2 GiB cgroup (`memory.max` =
2147483648) on a many-core host — is not a gate verdict but a kernel kill:

```text
$ cat /sys/fs/cgroup/memory.max
2147483648

# Infection — 19,278 mutants, three attempts (#720's field evidence)
47 threads -> exit 137 @ mutant 1800
 6 threads -> exit 137 @ mutant 2650
 3 threads -> stable, 1.37 GB / 2.0 GB

# Rector — default parallel
$ composer rector:check
Killed                                     # exit 137, no diagnostic
# Rector — one path at a time (serial)
$ php vendor/bin/rector process src --dry-run --no-progress-bar
[OK] Rector is done!                       # exit 0

# PHPMD — default (a worker per core for parsing)
$ composer qa:mess
The mess detector did not complete a normal run (exit 137)
# PHPMD — pinned pool
$ php vendor/bin/phpmd analyze src --format=json --ruleset=phpmd.xml --threads=1
{"version":"3.0.0","files":[]}             # exit 0, no violation
```

Exit 137 never mentions memory, so the failure is easy to misdiagnose as a
code defect — the exact inversion of what a gate is for. And because
CONTRIBUTING requires `composer ci:strict` to pass locally before push, the
gates stopped being locally reproducible on any small-cgroup machine: the
verdict could only come from CI, weakening the pre-push principle itself.

## The ceilings, spelled where the defaults were implicit

| Gate | Before | After | Why the number |
| --- | --- | --- | --- |
| `composer mutation` | `--threads=max` | `--threads=3` | The issue's own ladder: 3 threads measured 1.37 GB stable on the 2 GiB cgroup; 6 and 47 died. Three is also within a standard CI runner's effective pool, so the lane's wall time does not regress. |
| `composer rector:check` | Rector default (worker per core) | `withParallel(null, 2)` + `withMemoryLimit('2G')` | The pool is pinned so the worker count is environment-independent, and the per-process ceiling is the diagnostic: a genuine overshoot becomes PHP's "Allowed memory size exhausted" with a stack trace instead of the kernel's silent kill. The ceiling itself is 2G because the runner's real per-worker need has crossed 1G (the 2026-11-11 merge-queue evidence below); a 1G pin was a blocker the tree could not out-run. A 2 GiB cgroup host pins the pool to 1 — serial was #720's only field-certified shape, and two ~1G-plus working sets exceed that budget. |
| `composer qa:mess` | phpmd default (worker per core) | `--threads=1` | The pinned spelling the issue measured green. The tree parses in seconds single-threaded, so the ceiling costs nothing the gate would notice. |

Each ceiling lives in the same place the command itself is spelled — the
composer script, the Rector config, the gate's command builder — so anyone
editing the gate reads the budget and its reason together. The mess arm's
command is pinned by `GateRunnerTest::testMessGatePassesACleanRun`, so a
regression back to the unbounded spelling fails a test rather than a machine.

## Reading a 137 after this change

`Killed` / exit 137 on a gate now means one of two things, both actionable:

1. **The environment's cgroup is below the documented budget** — the ceilings
   assume ≥ 2 GiB; raise the environment, not the gate.
2. **A genuine runaway** — and with Rector's per-process floor the message is
   explicit rather than silent; Infection and phpmd reports still land
   because their pools fit the budget.

Either way the number is evidence about the *environment*, never again a
phantom defect in the tree. A larger host that wants more throughput can
raise the three numbers locally — the ceilings are minimums for
reproducibility, not maximums for performance.

## 2026-11-11 addendum: the 1G ceiling was the merge blocker

`fix(ci): raise the rector memory ceiling to 2G` records the day the
1G pin stopped being a diagnostic floor and became the queue's blocker:

- **The evidence.** The merge-queue group run for the then-open #809 died
  in `rector:check` twice (09:17 and 14:05 on 2026-11-11), each time with
  `Child process error: PHP Fatal error: Allowed memory size of
  1073741824 bytes exhausted (tried to allocate 2097152 bytes)` — a worker
  refused a 2 MiB allocation on top of a full 1G, the pin's own ceiling.
  The pull request's own `CI Static` run had already needed three attempts
  to pass on the same tree; the group runs failed both times they were
  built. A kicked-out queue entry also disarms GitHub's auto-merge, so the
  pull request could not land while the pin held.
- **The reading.** The 2026-11-08 sizing assumed "two ~1G-capped processes
  fit the 2 GiB budget" — a budget statement about the *audit* cgroup,
  mistaken for a measurement of the worker's real need. The runner's real
  per-worker working set is now just over 1G (the refused allocation was
  2 MiB), so the 1G cap fired on a healthy tree; the diagnostic worked
  exactly as designed and told the truth the sizing had not measured.
- **The fix.** `withMemoryLimit('2G')`: double the real need measured on
  the runner, the ceiling the tree can out-run again. The pool stays at 2
  — two workers each with headroom fit any standard runner — while a
  2 GiB cgroup host pins the pool to 1 locally (`withParallel(null, 1)`),
  the serial shape #720's own field evidence certified. The ceilings stay
  minimums for reproducibility, never maximums for performance.
