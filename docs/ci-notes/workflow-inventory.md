# CI note: the workflow inventory — all twenty-three lanes, one table (2026-10-23)

Date: 2026-10-23
Status: shipped (this pull request)
Scope: this note; linked from `CONTRIBUTING.md` (Quality gates)

The round-3 documentation-alignment audit found that seven of the repository's
automation lanes are invisible to `CONTRIBUTING.md` — the document names the
gates and links three workflow files, but a contributor reading it cannot
discover that the repository also auto-assigns issues, drafts releases,
attests SBOMs, or renders proof HTML. This note is the complete inventory:
one line per workflow under `.github/workflows/`, its check-run context where
it creates one, and whether anything requires that context.

## Required means required *somewhere*

The merge-blocking set was the **union of two live protection layers** until
2026-10-23 (issue #561, finding V-1), when the owner consolidated the two
into one: the classic branch-protection rule on `main` is gone
(`GET /branches/main/protection` answers 404 "Branch not protected") and the
active `main` ruleset (id 24654862) alone now requires `CI Strict`,
`PR Validator` and `PhpCodeArcheology SARIF`. `CI Ruleset Lint` audits the
required set — reading the rulesets endpoint with the owner's PAT first (the
only credential that reaches the administration-scoped endpoint in practice;
workflow token and anonymous reads answer 403), resolving the ruleset on the
fields the LIST endpoint actually returns (`target` + `enforcement`, the
branch targeting confirmed on the DETAIL read — the LIST response carries no
`conditions` even with `?includes=conditions`), and reading the classic
layer, whenever one exists, as a first-class verdict: `read`, `absent` (the
consolidated today), or `unread` — a privilege gap that degrades to a weekly
warning rather than a red lane, and a returning layer simply joins the union
again.

## The inventory

| Workflow | Lane (what it does) | Check context | Required? |
| --- | --- | --- | --- |
| `ci-static.yml` | Strict lane: `composer ci:static` — PHPStan + ratchet, Deptrac, PHP-CS-Fixer, PHP_CodeSniffer + Slevomat, Rector, PHPUnit, persistent-worker smoke, zero-deps, workflow-concurrency, roadmap gate, strict supply-chain audit; plus Psalm, Progpilot and PhpCodeArcheology steps | `CI Static` | yes — classic layer |
| `ci-coverage.yml` | Strict lane: the 90 % line-coverage gate; publishes the `coverage-reports` artifact | `CI Coverage` | no (aggregated by `CI Strict`) |
| `ci-infection.yml` | Strict lane: Infection mutation testing, MSI 100 (60-minute timeout) | `CI Infection` | no (aggregated by `CI Strict`) |
| `ci-bench.yml` | Strict lane: PHPBench micro-benchmarks | `CI Bench` | yes — classic layer |
| `ci-strict.yml` | The event-driven aggregator: creates/updates the required `CI Strict` check-run from the four lanes' check-runs; the `merge_group` polling path owns a group head's verdict | `CI Strict` (Checks API) + `CI Strict Aggregator` (the job) | yes — both layers (ruleset: the check; classic: the job context) |
| `pr-validator.yml` | PR title Conventional-Commit gate, single-sourced from `scripts/changelog/conventional.py` | `PR Validator` | yes — both layers |
| `archeology.yml` | Publishes the PhpCodeArcheology SARIF to GitHub Code Scanning; curates non-error findings out of the upload | `PhpCodeArcheology SARIF` | yes — both layers |
| `sonarcloud.yml` | SonarCloud scanner lane (resurrected 2026-10-26 on the new `zefl` org, project `zefl_zeflous`, gate "ZEF"): credential guard, coverage bridge, server-side quality-gate wait | `SonarCloud analysis` | no (advisory — the resurrection deliberately did not re-add the ruleset's `code_quality` rule) |
| `dependency-review.yml` | Supply-chain diff of dependency changes on pull requests | `Dependency Review` | no (advisory) |
| `docs-check.yml` | Doctum API-docs build + per-type documentation-drift assertion | `API Documentation Check` | no (advisory) |
| `phpbench.yml` | Post-merge PHPBench regression run (fail-closed when no benchmarks found) | `PHPBench` | no (advisory) |
| `changelog-tests.yml` | pytest suite for the changelog generator with a 90 % coverage floor | `Changelog generator tests` | no (advisory) |
| `ci-ruleset-lint.yml` | Weekly guard: every required context (the ruleset, plus the classic layer whenever one exists) must have a check-run on each of the last ten merged PR heads; reads PAT-first, verdicts an absent classic layer as absent | `CI Ruleset Lint` | no (advisory — guards the required set) |
| `ci-main-watchdog.yml` | Scheduled sweep (every 20 min): replays any main lane missing from the current main HEAD, with a recursion guard | `Verify main HEAD validation` | no (repair lane) |
| `auto-merge.yml` | The sweep: queue-aware branch sync, `action_required` run approvals, early auto-merge arming | `Auto Merge` | no (automation) |
| `changelog.yml` | Regenerates the per-version changelog + JSON API after a validated merge on `main`; opens a self-merging PR | `Update changelog` | no (automation) |
| `changelog-official.yml` | github-changelog-generator (Ruby, pinned 1.18.0) snapshot of unreleased changes → self-merging PR | `Official changelog snapshot` | no (automation) |
| `auto-assign.yml` | Assigns newly opened issues and pull requests to the owner | `run` (job id) | no (automation) |
| `release-drafter.yml` | Maintains the draft GitHub release from merged pull requests | `Update release draft` | no (automation) |
| `sbom.yml` | SBOM branch-attestation lane on push to `main` (calls the reusable generator) | `SBOM (branch attestation)` | no (automation) |
| `sbom-generate.yml` | Reusable workflow: CycloneDX SBOM + keyless OIDC attestation | `Generate SBOM` | no (automation) |
| `stale-prs-updater.yml` | Triage sweep: labels open pull requests `needs-manual-fix` / `gate-failed` | `Triage open pull requests` | no (automation) |
| `proof-html.yml` | Renders/validates repository HTML (README and friends) via `anishathalye/proof-html` | `build` (job id) | no (automation) |

## Notes

- **Required-through-both-layers contexts** (`CI Strict`, `PR Validator`,
  `PhpCodeArcheology SARIF`) are named by the ruleset and the classic rule
  alike; the table's "both layers" marks that redundancy.
- **`CI Strict Aggregator`** is the aggregator job's own check-run context —
  the job that *writes* the `CI Strict` check also reports itself. The
  sha-mismatch guard in `ci-strict.yml` (this pull request) keeps an
  event-driven pass that aggregates another pull request's head from exiting
  red on `main`'s tip, where the context is required through the classic
  layer.
- The `Sbom`/`stale`/`proof` lanes run on schedules or pushes and never
  report on pull-request heads; they are invisible to the merge gate by
  design.
- The autolabeler inside `.github/release-drafter.yml` carries its own family
  of Conventional-header regexes (six of the twelve types). It assigns
  labels only — the gate and the changelog generator stay single-sourced in
  `scripts/changelog/conventional.py` — so it is decoration, not enforcement,
  and is tracked as deferred drift on #561.
