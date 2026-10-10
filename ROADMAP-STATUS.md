# ROADMAP Status

A living "roadmap meter": for every area described in [`ROADMAP.md`](ROADMAP.md),
this file records what is actually implemented in the repository today, with a
one-line evidence pointer (file / class / workflow). It is the single place to
see how far the code has moved against the roadmap.

**Legend**

| Status | Meaning |
|--------|---------|
| ✅ Implemented | The area is present and exercised by the test suite / CI. |
| 🟡 Partial | A working slice exists; the rest of the area is still open. |
| ⬜ Not started | No code yet. |

**Last updated:** 2026-10-08 · `main` @ `ffc0cdd04c`

---

## Container (`ROADMAP.md` → "CONTAINER")

| # | Area | Status | Evidence |
|---|------|--------|----------|
| 1 | Registration & service definition | 🟡 Partial | `src/Zef/Framework/Container/Container.php` (`set()`), `ServiceStore.php` — closure/factory registration only; array definitions, aliases, scalar params, env values, parameter providers not yet present. |
| 2 | Autowiring | ⬜ Not started | No reflection-based resolution yet. |
| 3 | Autoconfiguration (attributes) | ⬜ Not started | — |
| 4 | Injector (property/method injection) | ⬜ Not started | — |
| 5 | Scope & lifecycle | 🟡 Partial | `ServiceStore::resolve()` memoises instances (singleton/shared); factory/prototype, per-request scope, lazy services not yet present. |
| 6 | Tags & grouping | ⬜ Not started | — |
| 7 | Decorator & proxy | ⬜ Not started | — |
| 8 | Compiler passes & compilation | ⬜ Not started | — |
| 9 | Architectural extensibility | ⬜ Not started | — |
| 10 | Standardisation & integration | 🟡 Partial | PSR-11 surface via `src/Contracts/Psr/Container/ContainerInterface.php`; `Container implements ContainerInterface`. |
| 11 | Performance | ⬜ Not started | — |
| 12 | Debugging & developer experience | ⬜ Not started | — |

## Router (Radix Tree)

| Area | Status | Evidence |
|------|--------|----------|
| Core engine, advanced routing, performance, DX, ecosystem | ⬜ Not started | No router code yet. |

## Configuration System + DSL + Radix Tree

| Area | Status | Evidence |
|------|--------|----------|
| Configuration layer, DSL layer, radix tree layer | ⬜ Not started | — |

## Middleware Pipeline

| Area | Status | Evidence |
|------|--------|----------|
| Core architecture, execution engine, security, container integration, DX, performance | ⬜ Not started | — |

## HTTP Layer

| Area | Status | Evidence |
|------|--------|----------|
| Core message, security & hardening, parsing/formatting, observability | 🟡 Partial | `src/Zef/Framework/Message/Message.php` — the immutable message envelope exists; the HTTP request/response layer itself is not started. |

## HTTP Layer — "Zero-Library Shim"

| Area | Status | Evidence |
|------|--------|----------|
| Shim infrastructure, runtime adapter, security, performance | 🟡 Partial | `src/Contracts/Psr/Container/*` is the first zero-library PSR shim; the HTTP shim is not started. |

## Event Source System

| Area | Status | Evidence |
|------|--------|----------|
| Core event source, enterprise features | ⬜ Not started | — |

## Error Handling System

| Area | Status | Evidence |
|------|--------|----------|
| Standardisation, handler chain, observability, DX | 🟡 Partial | `src/Zef/Framework/Exception/ZefException.php`, `InvariantViolationException.php` — the exception root exists; the handler chain is not started. |

## Module Integration System

| Area | Status | Evidence |
|------|--------|----------|
| Core module engine, extension points, isolation, DX | 🟡 Partial | `src/Zef/Framework/Contracts/ServiceProviderInterface.php` — the provider contract exists; the module engine is not started. |

## Adapters Layer

| Area | Status | Evidence |
|------|--------|----------|
| View engine, database, adapters roadmap | ⬜ Not started | — |

## Version rules

| Area | Status | Evidence |
|------|--------|----------|
| Versioning policy | ✅ Implemented | `src/Zef/Framework/Version.php` (`Version::current()`), `tests/VersionTest.php`. |

---

## Cross-cutting: quality gates & CI (not in `ROADMAP.md`, tracked here)

| Item | Status | Evidence |
|------|--------|----------|
| Strict local pipeline | ✅ Implemented | `composer ci:strict` → `ci:static` + `ci:coverage` + `ci:infection` + `ci:bench` (`composer.json`). |
| CI lane (split workflows) | ✅ Implemented | Heavy lanes split into their own workflows: `.github/workflows/ci-static.yml`, `ci-coverage.yml`, `ci-infection.yml`, `ci-bench.yml` — each with its own status check, so one lane can be retried/re-run alone. `.github/workflows/ci-strict.yml` is the aggregator that keeps the required `CI Strict` check (it polls the four lane checks and fails unless all are green). |
| PR title gate | ✅ Implemented | `.github/workflows/pr-validator.yml` (job `PR Validator`), taxonomy in `scripts/changelog/conventional.py`. |
| Architecture gate | ✅ Implemented | `php-codearch-config.yaml`, `scripts/ci/archeology-gate.php`, `.github/workflows/archeology.yml` (job `PhpCodeArcheology SARIF`). |
| Static analysis | ✅ Implemented | PHPStan max + ratchet, Psalm level 1 + baseline, Progpilot (PHAR, SHA-256 pinned). |
| Supply chain | ✅ Implemented | `composer audit` strict gate, SBOM workflows, dependency review. |
| Zero-dependency gate | ✅ Implemented | `composer zero-deps` (part of `composer ci:static`) → `src/Zef/Framework/Tooling/ZeroDependencyGate.php` asserts `composer.json` `require === {php: ^8.4}` and `composer.lock` `packages === []`. |

---

## How to update this file

1. When a roadmap area gains code, change its **Status** and add the concrete
   **Evidence** pointer (file, class, or workflow) — never mark something
   Implemented without a pointer a reviewer can open.
2. Keep the **Last updated** line current: date + the `main` commit you checked.
3. Add a row to the cross-cutting table when a new gate/tool lands.
4. Do not restate the roadmap's prose here — link to `ROADMAP.md` and record
   only status + evidence.
