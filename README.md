# Zeflous

[![CI Strict](https://img.shields.io/github/check-runs/Zeflous/zeflous/main?label=CI%20Strict)](https://github.com/Zeflous/zeflous/actions?query=branch%3Amain)

Zeflous is a PHP framework built around a hexagonal architecture and a
RoadRunner runtime. The repository ships the framework source, its test suite,
and the full CI pipeline that guards every change.

- [`ROADMAP.md`](ROADMAP.md) — the design and the areas the framework is built from.
- [`ROADMAP-STATUS.md`](ROADMAP-STATUS.md) — the living "roadmap meter" (Implemented / Partial / Not started per area).
- [`ROADMAP-HISTORY.md`](ROADMAP-HISTORY.md) — the work already done, grouped by roadmap area.
- [`CONTRIBUTING.md`](CONTRIBUTING.md) — how to contribute, the PR-title convention, and the full gate reference.

## Quality gates

Every pull request runs the checks below, and every merge into `main` is
validated through a **merge queue**: once the required checks are green on the
pull request, GitHub re-runs them on the combined merge-group ref using the
workflow files from `main` itself, so what gets certified is the merged
result — not the pull request's own workflow version. `CI Strict`, `PR
Validator` and `PhpCodeArcheology SARIF` are
**required** on `main` — a change cannot merge until they are green on both
the pull request and its merge group. The rest are advisory signals.

| Check | What it enforces |
| --- | --- |
| `CI Strict` | Aggregator: green only when `CI Static`, `CI Coverage`, `CI Infection` and `CI Bench` are all green. |
| `CI Static` | `composer ci:static` — PHPStan (max level) + ratchet, Deptrac, PHP-CS-Fixer, PHP_CodeSniffer + Slevomat, Rector, PHPUnit, the persistent-worker smoke test and the strict supply-chain audit — plus Psalm (level 1 + baseline), Progpilot and PhpCodeArcheology. |
| `CI Coverage` | `composer ci:coverage` — the 90% line-coverage gate. |
| `CI Infection` | `composer ci:infection` — Infection mutation testing (MSI 100) and the mutation-score floor. |
| `CI Bench` | `composer ci:bench` — PHPBench. |
| `PR Validator` | The pull-request title is a valid Conventional-Commit header. |
| `PhpCodeArcheology SARIF` | The architecture & maintainability report is published to GitHub Code Scanning and the gate passes (no error-level architecture finding). |
| `SonarCloud analysis` | The cloud analyzer's scanner lane (advisory — resurrected 2026-10-26 on the new `zefl` org; bridges the CI Coverage artifact and waits for the server-side "ZEF" quality gate); fail-closed when uncredentialed in a context that must have credentials — see [`docs/security/sonarcloud.md`](docs/security/sonarcloud.md). |
| `SonarCloud Code Analysis` | The SonarCloud GitHub App's own check on pull requests — advisory decoration that appears once the app is granted the repository; mirrors the same server-side verdict `SonarCloud analysis` waits for. |
| `Dependency Review` | Supply-chain review of dependency changes. |
| `SBOM` | CycloneDX SBOM generation with a keyless (OIDC) attestation. |
| `API Documentation Check` | The generated API documentation builds. |
| `PHPBench` | Benchmark regression check. |
| `Changelog generator tests` | Unit tests for the changelog generator. |
| `CI Ruleset Lint` | Weekly guard: every required status-check context in the live `main` ruleset must have a check-run on each of the last ten merged pull-request heads (the dead-required-check failure mode of the merge-gate audit). |

Do not weaken a gate, add a rule exclusion, or lower a threshold to make a
change pass — fix the underlying issue instead. See
[`CONTRIBUTING.md`](CONTRIBUTING.md#quality-gates) for the full reference,
including the PhpCodeArcheology configuration.
