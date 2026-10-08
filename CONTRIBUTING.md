# Contributing

Thanks for taking the time to contribute. This document describes the rules a
change must satisfy before it can land on `main`. They are enforced by CI, not
by convention alone — a pull request that breaks one of them cannot be merged.

## Table of contents

- [Getting started](#getting-started)
- [Pull requests](#pull-requests)
- [PR title convention (PR Validator gate)](#pr-title-convention-pr-validator-gate)
- [Commit messages](#commit-messages)
- [Quality gates](#quality-gates)
- [Changelog](#changelog)

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
| `CI Strict` | `composer ci:strict` — PHPStan (max level) + ratchet, Deptrac, PHP-CS-Fixer, PHP_CodeSniffer + Slevomat, Rector, PHPUnit, the 90% coverage gate, Infection (MSI 100), the strict supply-chain audit, the persistent-worker smoke test and PHPBench — plus Psalm (level 1 + baseline), Progpilot (static security analysis) and PhpCodeArcheology (architecture & maintainability gate). |
| `PR Validator` | The pull-request title is a valid Conventional-Commit header. |
| `PhpCodeArcheology SARIF` | The architecture & maintainability report is published to GitHub Code Scanning and the gate passes (no error-level architecture finding). |
| `SonarCloud Code Analysis` | The server-side quality gate. |
| `CodeQL` | Code scanning. |
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

## Changelog

The changelog is generated automatically from merged pull requests — you do not
edit it by hand. Only pull requests that are **actually merged into `main`**
appear in it; a pull request that is closed without merging, a draft, or a pull
request merged into a non-default branch is excluded.

The generator lives in `scripts/changelog/generate.py` and is driven by
[`.github/workflows/changelog.yml`](.github/workflows/changelog.yml) on `push`
to `main` and on `workflow_dispatch`.
