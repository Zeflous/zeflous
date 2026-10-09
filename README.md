# Zeflous

Zeflous is a PHP framework built around a hexagonal architecture and a
RoadRunner runtime. The repository ships the framework source, its test suite,
and the full CI pipeline that guards every change.

- [`ROADMAP.md`](ROADMAP.md) — the design and the areas the framework is built from.
- [`ROADMAP-STATUS.md`](ROADMAP-STATUS.md) — the living "roadmap meter" (Implemented / Partial / Not started per area).
- [`ROADMAP-HISTORY.md`](ROADMAP-HISTORY.md) — the work already done, grouped by roadmap area.
- [`CONTRIBUTING.md`](CONTRIBUTING.md) — how to contribute, the PR-title convention, and the full gate reference.

## Quality gates

Every pull request runs the checks below. `CI Strict`, `PR Validator` and
`PhpCodeArcheology SARIF` are **required** on `main` — a change cannot merge
until they are green. The rest are advisory signals.

| Check | What it enforces |
| --- | --- |
| `CI Strict` | Aggregator: green only when `CI Static`, `CI Coverage`, `CI Infection` and `CI Bench` are all green. |
| `CI Static` | `composer ci:static` — PHPStan (max level) + ratchet, Deptrac, PHP-CS-Fixer, PHP_CodeSniffer + Slevomat, Rector, PHPUnit, the persistent-worker smoke test and the strict supply-chain audit — plus Psalm (level 1 + baseline), Progpilot and PhpCodeArcheology. |
| `CI Coverage` | `composer ci:coverage` — the 90% line-coverage gate. |
| `CI Infection` | `composer ci:infection` — Infection mutation testing (MSI 100) and the mutation-score floor. |
| `CI Bench` | `composer ci:bench` — PHPBench. |
| `PR Validator` | The pull-request title is a valid Conventional-Commit header. |
| `PhpCodeArcheology SARIF` | The architecture & maintainability report is published to GitHub Code Scanning and the gate passes (no error-level architecture finding). |
| `SonarCloud Code Analysis` | The server-side quality gate. |
| `Dependency Review` | Supply-chain review of dependency changes. |
| `SBOM` | CycloneDX SBOM generation with a keyless (OIDC) attestation. |
| `API Documentation Check` | The generated API documentation builds. |
| `PHPBench` | Benchmark regression check. |
| `Changelog generator tests` | Unit tests for the changelog generator. |

Do not weaken a gate, add a rule exclusion, or lower a threshold to make a
change pass — fix the underlying issue instead. See
[`CONTRIBUTING.md`](CONTRIBUTING.md#quality-gates) for the full reference,
including the PhpCodeArcheology configuration.
