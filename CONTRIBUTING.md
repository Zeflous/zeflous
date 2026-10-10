# Contributing

Thanks for taking the time to contribute. This document describes the rules a
change must satisfy before it can land on `main`. They are enforced by CI, not
by convention alone — a pull request that breaks one of them cannot be merged.

## Table of contents

- [Getting started](#getting-started)
- [Mandatory rules](#mandatory-rules)
- [Pull requests](#pull-requests)
- [PR title convention (PR Validator gate)](#pr-title-convention-pr-validator-gate)
- [Commit messages](#commit-messages)
- [Quality gates](#quality-gates)
- [Roadmap status](#roadmap-status)
- [Changelog](#changelog)
- [Pull-request checklist](#pull-request-checklist)

## Getting started

```bash
git clone https://github.com/Zeflous/zeflous.git
cd zeflous
composer install
```

Run the full local pipeline before opening a pull request:

```bash
composer ci:strict
```

`composer ci:strict` is the repository's single source of truth for the strict
pipeline. A green `CI Strict` check on your pull request means exactly the same
set of gates you just ran locally.

`ci:strict` is composed of four independent lanes, each of which also runs as
its own CI job so a slow lane cannot block the others:

| Lane | Script | CI job |
| --- | --- | --- |
| Static analysis, unit tests, supply-chain audit | `composer ci:static` | `CI Static` |
| Line-coverage gate | `composer ci:coverage` | `CI Coverage` |
| Mutation testing (Infection, MSI 100) | `composer ci:infection` | `CI Infection` |
| Micro-benchmarks | `composer ci:bench` | `CI Bench` |

The `CI Strict` job is a thin aggregator that `needs` all four lanes, so the
required status-check context is unchanged. It runs with `if: always()` and
explicitly inspects each lane's result, so it **fails** (never silently skips)
whenever a lane is red — a skipped required check would otherwise count as
satisfied and let a red pull request merge.

## Mandatory rules

These rules are **non-negotiable**. They are part of the repository contract and
are enforced by CI and by review. A change that violates any of them must not be
merged. When in doubt, ask before you build — not after.

### 1. Zero Composer / zero vendor at production runtime

ZEF ships **zero Composer runtime dependencies**. The framework must run on a
bare PHP 8.4 install with no `vendor/` directory present.

- `composer.json` → `require` **MUST** be exactly `{ "php": "^8.4" }`. Nothing
  else may be added to the production `require` block.
- `composer.lock` → the production `packages` array **MUST** stay empty (`[]`).
  Only `packages-dev` may be populated.
- Anything normally obtained from a third-party library — YAML/TOML/XML parsers,
  polyfills, helper packages — **MUST** be implemented as an **in-repo shim**
  under `src/`, never pulled from Packagist.
- Dev-only dependencies (PHPStan, Psalm, Infection, Deptrac, PhpCodeArcheology,
  PHP-CS-Fixer, …) are allowed, but they **MUST NOT** leak into the runtime
  `src/` tree. `src/` may depend only on PHP itself and the repository's own
  code.
- Enforced by the [anti-regression zero-dependency gate](#16-anti-regression-zero-dependency-gate).

### 2. Roadmap order — Configuration System first, then Container

The build order is fixed and must not be reordered:

1. **Configuration System** (foundation) — **first**.
2. **Container** — only **after** the Configuration foundation is complete.

Do not start Container work while the Configuration foundation is unfinished.
Container depends on configuration (global scalar parameters, environment
variables, parameter providers, configuration merging, semantic configuration),
so building it first would force rework. See
[`ROADMAP.md`](ROADMAP.md) and [`ROADMAP-STATUS.md`](ROADMAP-STATUS.md).

### 3. Configuration System design style

The Configuration System follows the API and structure of
[`phlak/config`](https://github.com/PHLAK/Config), adapted to ZEF:

- **Immutable** — unlike `phlak/config` (which is mutable), ZEF's `Config` is
  immutable: every mutation returns a new instance (`with*`), matching the
  `final readonly` style of `Kernel` and `Container`.
- **Zero-dependency** — no third-party parser; see rule 1. Formats that would
  normally need a library (YAML/TOML/XML) are either handled natively, shimmed
  in-repo, or dropped from the initial scope.
- **Plus three capabilities `phlak/config` does not have:** schema validation,
  environment layering, and a compiled cache.

### 4. Edge-case and corner-case awareness

Code logic **MUST** be written with edge cases and corner cases in mind, not
just the happy path. Before a change is considered done, reason explicitly about
— and cover with tests — the boundary conditions of every branch:

- empty / null / missing input, and zero-length collections;
- single-element and maximum-size inputs;
- off-by-one boundaries (first, last, one-past-the-end);
- duplicate, unordered and repeated keys;
- malformed, truncated or hostile input (wrong type, bad encoding, oversized);
- concurrent or re-entrant use where the code is shared.

A branch that cannot be reached, or a case that is silently swallowed, is a
defect — not a simplification.

### 5. Enterprise Coding Standard

All code **MUST** follow the repository's Enterprise Coding Standard. In
practice this means:

- PHP 8.4 with `declare(strict_types=1);` in every file;
- PSR-4 autoloading, PSR-12 formatting (enforced by PHP-CS-Fixer and
  PHP_CodeSniffer + Slevomat);
- `final` classes and `readonly` properties by default; explicit, narrow types
  on every parameter, return value and property;
- no `mixed` where a precise type is expressible; no suppressed errors;
- small, single-responsibility units with clear names;
- no dead code, no commented-out code, no debug leftovers.

### 6. SonarCloud-clean logic (no new ruleKey exclusions)

Code logic **MUST NOT** introduce new SonarCloud issues. Fix the underlying
cause rather than silencing the rule:

- do **not** add a new `ruleKey` exclusion, ignore pattern, or quality-profile
  override to make a finding disappear;
- do **not** mark a new issue as "won't fix" / "false positive" to pass the
  gate;
- if a rule genuinely cannot be satisfied, raise it for review **before**
  merging — never widen the exclusion set unilaterally.

The existing exclusion set is frozen; it may only shrink, never grow.

### 7. Comprehensive and cohesive logic

Logic **MUST** be both **comprehensive** (it handles the full problem, not a
partial slice) and **cohesive** (each unit does one thing, and everything it
does belongs together):

- no half-implemented features, no `TODO`-as-implementation;
- no god-classes or grab-bag helpers that mix unrelated concerns;
- related behaviour lives together; unrelated behaviour is split apart;
- public surface is minimal and intentional.

### 8. Re-harden before pushing

Before a pull request is pushed, the author **MUST** re-harden the change:

- re-read the diff as an adversary — input validation, bounds, error paths,
  resource limits, and failure modes;
- confirm no secret, token or credential is committed;
- confirm no new runtime dependency slipped in (see rule 1);
- confirm the change still passes the full strict pipeline locally.

Hardening is a deliberate pass, not an assumption that "it works".

### 9. Unit-test coverage above 90 %

Unit-test line coverage **MUST** be **greater than 90 %** (the CI gate enforces
≥ 90 %; aim above it). New logic ships with its tests in the same pull request:

- every new branch and every new edge case has a test;
- tests assert behaviour, not implementation details;
- no test is skipped, weakened or deleted to make the gate pass.

### 10. Watch CI gates to green, then let auto-merge run

After pushing, the author **MUST** watch the CI gates until **all** of them are
green. Do not merge by hand and do not force a merge while a check is red or
pending:

- fix the failure and push again until every required check is green;
- once every required check is green, **let auto-merge run on its own** — it is
  armed automatically and will merge without manual intervention;
- never bypass a red or pending check.

### 11. Every conversation must be solved

Every review conversation on a pull request **MUST** be resolved before merge.
No thread may be left open, unanswered or "resolved" without an actual fix or an
explicit, justified agreement. A pull request with an unresolved conversation is
not ready to merge.

### 12. Re-audit after merge

After a pull request is merged, the change **MUST** be re-audited on `main`:

- confirm the merged result matches what was reviewed (no surprise commits);
- confirm the gates are still green on `main`;
- confirm no regression was introduced downstream;
- record the outcome so the next change starts from a verified baseline.

### 13. Keep the roadmap documents current after implementation

Every completed implementation **MUST** update **both** roadmap documents in the
same pull request that ships the change. They are the repository's public record
of what has actually been done and where it stands; letting them drift makes
both unreliable.

- **`ROADMAP-HISTORY.md`** — the **append-only** chronological history. Add a new
  row for the merged pull request (work item, PR number, merge date, merge
  commit SHA) under the roadmap area it advances, refresh the **Summary** counts
  and the **Last updated** line. Never rewrite or delete an existing row — the
  file only grows.
- **`ROADMAP-STATUS.md`** — the living "roadmap meter". Set the status
  (Implemented / Partial / Not started) of every affected area to match the
  **current** code, update its one-line evidence pointer (file / class /
  workflow) and the **Last updated** line. This file mirrors the present state,
  not history, so it is edited in place.

An implementation is **not** complete until both documents reflect it.

### 14. Mandatory quality gates

Every change **MUST** pass the full strict pipeline. The gates are:

| Gate | Threshold |
| --- | --- |
| Line coverage | **≥ 90 %** |
| Infection mutation score | **MSI 100** (and covered MSI 100) |
| PHPStan | **max level** + baseline ratchet (no new errors) |
| Psalm | **level 1** against the frozen baseline |
| Deptrac | architecture boundaries respected |
| `composer audit` | **strict** — fail on any advisory or non-allow-listed abandoned package |
| `CI Strict` | green (aggregates `CI Static`, `CI Coverage`, `CI Infection`, `CI Bench`) |

Do not weaken a gate, add a rule exclusion, or lower a threshold to make a
change pass. Fix the underlying issue instead.

### 15. TASK CONTINUATION MODE

When revising existing work, **copy to a new version before editing** — never
overwrite the previous version in place. Project directories use the `_vN`
suffix (`name/` → `name_v2/` → `name_v3/`); generated media get their next
version from the generating tool. The previous version stays intact and
read-only. This keeps every delivered revision reproducible and reviewable.

### 16. Anti-regression zero-dependency gate

A CI gate **MUST** assert the zero-dependency invariant on every pull request:

- `composer.json` → `require` equals `{ "php": "^8.4" }`; and
- `composer.lock` → `packages` equals `[]`.

The gate fails the build if either assertion is violated, so a runtime
dependency can never be introduced silently. This is the enforcement arm of
[rule 1](#1-zero-composer--zero-vendor-at-production-runtime).

## Pull requests

1. Branch from the latest `main`.
2. Keep the change focused; one logical change per pull request.
3. Make sure `composer ci:strict` passes locally.
4. Open the pull request against `main` with a **Conventional-Commit title**
   (see below).
5. Let CI run. Auto-merge is armed automatically once every required check is
   green — do not merge by hand.

## PR title convention (PR Validator gate)

Every pull request title **must** be a valid
[Conventional Commits](https://www.conventionalcommits.org/) header:

```
<type>(<scope>): <subject>
```

- `<type>` — **required**, one of the allowed types below.
- `(<scope>)` — optional; a short noun describing the affected area, e.g.
  `api`, `ci`, `changelog`.
- `!` — optional; marks a breaking change, e.g. `feat(api)!: drop v1`.
- `: <subject>` — **required**; a short, imperative description.

The type is matched case-insensitively (`Feat:` is accepted), but the canonical
form is lower-case.

### Allowed types

| Type | Changelog category |
| --- | --- |
| `feat` | Features |
| `fix` | Bug Fixes |
| `docs` | Documentation |
| `chore` | Maintenance |
| `refactor` | Maintenance |
| `perf` | Maintenance |
| `test` | Tests |
| `ci` | Maintenance |
| `build` | Maintenance |
| `style` | Maintenance |
| `revert` | Maintenance |
| `security` | Security |

A title whose type is not in this list (for example `wip:`, `update:`) is
rejected, because the changelog generator cannot categorise it.

### Examples

Valid:

```
feat(api): add pagination to the list endpoint
fix(router): handle an empty path
docs(contributing): document the PR Validator gate
security: harden the token comparison
chore(deps): bump phpstan to 2.3.0
```

Invalid:

```
update stuff
Add pagination
wip: still working on it
feat add pagination
```

### How the gate works

The **PR Validator** workflow
([`.github/workflows/pr-validator.yml`](.github/workflows/pr-validator.yml))
runs on every `pull_request` event (`opened`, `edited`, `synchronize`,
`reopened`) and fails the `PR Validator` check when the title does not match the
convention. Because `PR Validator` is a **required status check** on `main`, a
pull request with an invalid title **cannot be merged**.

If you fix the title, the `edited` event re-runs the gate automatically — no new
commit is needed.

### Single source of truth

The accepted types and the header regex are **not** duplicated in the workflow.
Both the gate and the changelog generator import the same module:

```
scripts/changelog/conventional.py
```

That module defines `CONVENTIONAL_CATEGORY`, `CONVENTIONAL_RE`, `ALLOWED_TYPES`
and `validate_title()`. Adding a type there changes the gate and the changelog
together, so the two can never disagree about what a valid title is.

## Commit messages

Commit messages follow the same Conventional-Commit convention. The changelog
generator falls back to the commit subject when a pull request has no
categorising label, so a well-formed commit subject keeps the release notes
accurate.

## Quality gates

The following checks run on every pull request. `CI Strict` and `PR Validator`
are **required** — the rest are advisory signals.

| Check | What it enforces |
| --- | --- |
| `CI Strict` | Aggregator: green only when `CI Static`, `CI Coverage`, `CI Infection` and `CI Bench` are all green. It runs with `if: always()` and fails (never skips) if any lane did not succeed. |
| `CI Static` | `composer ci:static` — PHPStan (max level) + ratchet, Deptrac, PHP-CS-Fixer, PHP_CodeSniffer + Slevomat, Rector, PHPUnit, the persistent-worker smoke test and the strict supply-chain audit — plus Psalm (level 1 + baseline), Progpilot (static security analysis) and PhpCodeArcheology (architecture & maintainability gate). |
| `CI Coverage` | `composer ci:coverage` — the 90% line-coverage gate. |
| `CI Infection` | `composer ci:infection` — Infection mutation testing (MSI 100) and the mutation-score floor. |
| `CI Bench` | `composer ci:bench` — PHPBench. |
| `PR Validator` | The pull-request title is a valid Conventional-Commit header. |
| `PhpCodeArcheology SARIF` | The architecture & maintainability report is published to GitHub Code Scanning and the gate passes (no error-level architecture finding). |
| `SonarCloud Code Analysis` | The server-side quality gate. |
| `Dependency Review` | Supply-chain review of dependency changes. |
| `API Documentation Check` | The generated API documentation is up to date. |
| `PHPBench` | Benchmark regression check. |
| `Changelog generator tests` | Unit tests for the changelog generator. |

Do not weaken a gate, add a rule exclusion, or lower a threshold to make a
change pass. Fix the underlying issue instead.

### PhpCodeArcheology (architecture & maintainability)

[PhpCodeArcheology](https://github.com/PhpCodeArcheology/PhpCodeArcheology) is
the repository's **non-server-side** counterpart to SonarCloud. Where PHPStan
and Psalm check type safety and bugs, PhpCodeArcheology measures **architecture
and maintainability** — 60+ metrics (cyclomatic/cognitive complexity,
maintainability index, LCOM, coupling, instability, Halstead), git churn
hotspots, a 0–100 health score and a technical-debt score.

It is installed as a Composer dev-dependency
(`php-code-archeology/php-code-archeology`, pinned `^2.11`) and runs inside the
`CI Strict` lane as two steps:

```bash
composer archeology       # writes SARIF + Markdown reports to build/archeology/
composer archeology:gate  # normalises SARIF paths and fails on any error-level finding
```

- **Strict configuration** lives in
  [`php-codearch-config.yaml`](php-codearch-config.yaml): the metric thresholds
  are pinned to the tool's tightest defaults so a future default change cannot
  silently loosen the gate.
- **The gate reads the SARIF `level` field** (via
  [`scripts/ci/archeology-gate.php`](scripts/ci/archeology-gate.php)) and fails
  on any **error-level** architecture finding — GodClass, SecuritySmell,
  DependencyCycle and "Difficulty is too high". It deliberately does **not**
  use the tool's own `--fail-on=error`, which counts the relative
  "effort/MI/LCOM more than 30% above/below average" findings as errors: those
  are a distribution artefact (any codebase has entities above 1.3× the mean by
  construction), not defects, and gating on them would be un-actionable. The
  same script rewrites the tool's absolute SARIF paths to repository-relative
  URIs, which GitHub Code Scanning requires.
- **The gate is deterministic** and depends only on the source tree. It is
  deliberately **not** a committed baseline: the tool's problem identity is
  `crc32(<absolute path>)`, so a baseline file is not portable between a
  developer's checkout and the CI runner and would report spurious "new
  problems" on every run.
- **Reports** (SARIF + Markdown) are uploaded as the
  `phpcodearcheology-report` build artifact on every run. The dedicated
  [`archeology.yml`](.github/workflows/archeology.yml) lane additionally
  publishes the SARIF to **GitHub Code Scanning** (Security → Code scanning
  alerts) under the `PhpCodeArcheology SARIF` status check, which is a
  **required** check on `main`.
- The tool's bundled **MCP server is intentionally not configured or used** —
  this gate is a plain CLI analysis step in CI.

## Roadmap status

The roadmap is tracked by **two** documents, and both are updated whenever an
implementation completes — see
[rule 13](#13-keep-the-roadmap-documents-current-after-implementation).

- [`ROADMAP-HISTORY.md`](ROADMAP-HISTORY.md) is the **append-only** chronological
  record — a changelog mapped onto the roadmap, one row per merged pull request,
  and never rewritten.
- [`ROADMAP-STATUS.md`](ROADMAP-STATUS.md) is the living "roadmap meter": for
  every area described in [`ROADMAP.md`](ROADMAP.md) it records the current
  status (Implemented / Partial / Not started) with a one-line evidence pointer,
  plus the cross-cutting quality gates. It mirrors the present state and is
  edited in place.

Update both whenever a roadmap area gains code — see the "How to update this
file" section at the bottom of each document.

## Changelog

The changelog is generated automatically from merged pull requests — you do not
edit it by hand. Only pull requests that are **actually merged into `main`**
appear in it; a pull request that is closed without merging, a draft, or a pull
request merged into a non-default branch is excluded.

The generator lives in `scripts/changelog/generate.py` and is driven by
[`.github/workflows/changelog.yml`](.github/workflows/changelog.yml) on `push`
to `main` and on `workflow_dispatch`.

## Pull-request checklist

Copy this into your pull-request description and tick every box before you ask
for review. A pull request that cannot tick a box is not ready to merge.

```markdown
- [ ] Branch is up to date with `main`; one logical change only.
- [ ] `composer ci:strict` passes locally (CI Strict, Coverage, Infection, Bench).
- [ ] PR title is a valid Conventional-Commit header (`<type>(<scope>): <subject>`).
- [ ] **Zero-dependency:** `composer.json` `require` is still `{ "php": "^8.4" }`.
- [ ] **Zero-dependency:** `composer.lock` production `packages` is still `[]`.
- [ ] No third-party runtime dependency leaked into `src/`; new formats use an in-repo shim.
- [ ] **Roadmap order:** Configuration System work precedes Container work.
- [ ] **Config design:** immutable (`with*`), zero-dependency, with schema validation / env layering / compiled cache where applicable.
- [ ] **Quality gates:** coverage ≥ 90 %, Infection MSI 100, PHPStan max + ratchet, Psalm L1, Deptrac, `composer audit` strict — all green.
- [ ] Edge cases and corner cases are handled and covered by tests.
- [ ] Logic follows the Enterprise Coding Standard and is comprehensive and cohesive.
- [ ] No new SonarCloud issue introduced; no new `ruleKey` exclusion added.
- [ ] Logic re-hardened before push.
- [ ] Unit-test coverage is above 90 %.
- [ ] CI gates watched to green; auto-merge left to run on its own.
- [ ] All review conversations resolved.
- [ ] Post-merge re-audit planned.
- [ ] No gate weakened, no rule exclusion added, no threshold lowered.
- [ ] **Roadmap docs:** `ROADMAP-HISTORY.md` appended (row: work item, PR, merge date, merge commit) with Summary counts refreshed.
- [ ] **Roadmap docs:** `ROADMAP-STATUS.md` reflects the current status + evidence pointer for every affected area.
- [ ] Changelog left to the generator (not edited by hand).
```

### Notes for agents

- **TASK CONTINUATION MODE:** copy to a new version before editing; never
  overwrite a delivered version.
