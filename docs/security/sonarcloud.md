# SonarCloud gate

Cloud-side quality analysis for this repository, run by
`.github/workflows/sonarcloud.yml` and configured by
`sonar-project.properties` at the repository root.

This document records **what is analysed, by what, which verdict blocks a pull
request, why pull-request decoration is an account-side setting, and the
one-time setup it needs.** It exists so those decisions are reviewable instead
of implicit in a workflow file.

---

## 1. Position in the pipeline

The analysis lane is deliberately separate from the local quality gates
(PHPStan max level, PHP_CodeSniffer + Slevomat, PHP-CS-Fixer, Rector, Deptrac,
Infection, PHPUnit; see `composer ci:strict`). The local gates prove structural
and behavioural correctness deterministically; this lane adds an independent
cloud reviewer whose findings do not overlap theirs, and a blocking
quality-gate verdict.

## 2. What this gate is

- **Cloud-side analysis** of the repository, scoped by the explicit exclusion
  allow-list in `sonar-project.properties` (section 4 below).
- **Blocking on the SonarCloud quality-gate verdict**: `sonar.qualitygate.wait=true`
  makes the scanner wait for the server-side computation; a red gate fails the
  job. The default "Sonar way" gate applies (new-code reliability, security,
  maintainability, coverage, duplications).
- **Pull-request decoration**: once the GitHub ALM binding is configured
  (section 3, step 4), SonarCloud posts inline comments and a quality summary
  on every pull request it analyses.
- **Always conclusive; green-with-notice when uncredentialed**: whenever the
  job cannot scan credentialed — `SONAR_TOKEN` not set yet, or a
  `pull_request` event where GitHub withholds repository secrets (a
  `dependabot[bot]` author, or a head from a fork) — the job is green **with a
  visible notice** (step summary + `::notice::`), never red and never silently
  green. The job name **`SonarCloud analysis`** is a status-check context; keep
  it stable if a branch protection rule pins it.

## 3. Setup checklist (one-time)

The workflow ships fully wired; only the SonarCloud account side is manual.

1. Sign in at <https://sonarcloud.io> with the GitHub account that owns this
   repository.
2. **Put the organization on the OSS plan** (free for open-source
   organizations). The Free plan's language set has **no PHP analyzer**: the
   scanner preprocesses files but indexes none of them and the run ends with an
   effectively empty code model. The OSS plan unlocks PHP, unlimited branches
   and pull-request analysis, at zero cost for public repositories.
3. **Create the project manually** (`Create project -> Manually`), pick the
   organization `zeflous`, and set the project key to **`zef_zeflous`** (or
   note the key SonarCloud assigns and align `sonar-project.properties` in the
   same change). Choose a **public** project.
4. **Bind GitHub (ALM integration)** — *this is the step that turns decoration
   on.* Go to **Administration -> ALM Integrations -> GitHub** and install /
   configure the **SonarQube Cloud GitHub App** so that it is granted access to
   **`Zeflous/zeflous`**. On GitHub this lives at
   **Organization settings -> GitHub Apps -> SonarQube Cloud -> Configure**,
   where the app's repository access must include `Zeflous/zeflous` (choose
   "All repositories" or add `Zeflous/zeflous` to the selected list). The
   app's installation on org `Zeflous` currently exists with
   `repository_selection: selected`.
   *Diagnostic*: if this is misconfigured, the analysis still runs and the gate
   is still evaluated, but GitHub PRs carry **no** `sonarqubecloud[bot]`
   comment and **no** `SonarCloud Code Analysis` check-run — the symptom that
   prompted this document.
5. **Disable Automatic Analysis** (Project Administration -> Analysis Method ->
   disable "Automatic Analysis"): CI-based analysis and automatic analysis
   conflict.
6. **Create a token** (My Account -> Security -> Generate Token) and save it as
   the repository secret **`SONAR_TOKEN`** (Settings -> Secrets and variables
   -> Actions). Any token whose SonarCloud identity is a member of the
   organization **`zeflous`** works. The optional **`ZEF_TOKEN`** secret is
   accepted for compatibility and falls back automatically when unset.
7. **Verify**: open a pull request. A correct configuration produces, on the
   PR: a **`sonarqubecloud[bot]` comment** titled "Quality Gate Passed/Failed",
   a **`SonarCloud Code Analysis`** check-run (GitHub App `sonarqubecloud`),
   and, in the SonarCloud UI, the PR under **Pull Requests** with its
   `qualityGateStatus`.

## 4. Analysis scope

SonarCloud's new analysis-scope UX **analyses the whole project and ignores
`sonar.sources` / `sonar.tests`** (measured on run 37652159694, 2026-10-07: the
scanner warns *"The following properties are configured but have no effect:
sonar.sources, sonar.tests"*). The authoritative control is therefore the
explicit **`sonar.exclusions` allow-list** in `sonar-project.properties`, which
excludes everything that is not first-party source:

| Excluded | Why |
| --- | --- |
| `vendor/**` | third-party dependencies (the vendored PSR shim included) |
| `build/**`, `.scannerwork/**`, `node_modules/**` | generated / tooling output |
| `.github/**`, `.git/**` and the `.*cache` files | not application code |
| `scripts/**`, `doctum.php`, `rector.php` | dev/CI tooling with its own local gates |
| `README.md`, `ROADMAP.md`, `composer.*`, `package.json`, `index.html` | non-code files |

`sonar.coverage.exclusions` additionally keeps non-instrumented trees out of
the coverage denominator (`tests/**`, `benchmarks/**`, `scripts/**`), and
`sonar.cpd.exclusions` keeps the vendored shim out of the copy-paste detector
while leaving first-party code fully visible to it.

**Diagnostic**: the workflow's "Analysis scope (diagnostic)" step prints the
tracked-file and `src/`-file counts on every run, so scope drift is visible
rather than silent.

## 5. Chain of evidence for the decoration investigation (2026-10-07)

| Fact | Source |
| --- | --- |
| Analysis runs, gate passes on PRs | run 37652159694 log: `QUALITY GATE STATUS: PASSED ...&pullRequest=2` |
| Scanner detects the PR context and the ALM binding | run log: `Detected project binding: BOUND`, `Auto-configuring pull request 2` |
| SonarCloud knows the GitHub PR URL | `GET /api/project_pull_requests/list?project=zef_zeflous` -> `"url":"https://api.github.com/repos/Zeflous/zeflous/pulls/2"` |
| No decoration on the repo | PR #1/#2 have 0 issue comments and no `commented` timeline event |
| No app check-run on this repo | `commits/<head>/check-runs` -> app `github-actions` only |
| The app check-run DOES exist on the reference repo | `Zeflous/zef-framework` -> `SonarCloud Code Analysis` / app `sonarqubecloud` |
| Decoration is an app-side feature | `docs/security` of the reference + `sonar-project.properties` comment: "a SonarCloud organization can only decorate pull requests of GitHub repos owned by the account/org it is bound to" |

Conclusion: the workflow and the analysis were already correct; decoration is
delivered by the **SonarQube Cloud GitHub App** and is gated by that app being
granted access to this repository. The workflow was nevertheless hardened for
scope parity with the reference (section 4) and switched to the same scanner
action (`SonarSource/sonarqube-scan-action`, pinned to the full commit SHA of
tag `v8.2.2`).
