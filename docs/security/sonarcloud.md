# SonarCloud gate — retired (2026-10-26)

The SonarCloud analysis lane was **retired** by the QA-gates follow-up slice.
The workflow (`.github/workflows/sonarcloud.yml`), the root configuration
(`sonar-project.properties`), and every dispatch wire that kept the lane alive
are gone from the tree. This document survives as the **retirement record**:
why the lane was removed, exactly what the removal changed, what has to happen
on the GitHub/SonarCloud account side for the retirement to be complete, and
the two pieces of doctrine that outlive the analyzer. The original setup
checklist and investigation notes are preserved below in case the lane is
ever resurrected.

---

## 1. Why it was retired

The decision is the one `docs/ci-notes/qa-gates.md` (KDD-QA8) recorded as "a
user decision, one-slice job when it comes". The facts that made it safe:

- **SonarCloud was never in the merge-blocking set.** Since the #568 ruleset
  consolidation the required checks are exactly `CI Strict`, `PR Validator`
  and `PhpCodeArcheology SARIF`; the SonarCloud job's own verdict ran green
  or red without ever gating a merge through those contexts.
- **The signals it uniquely owned went local first.** The duplication and
  mess detectors (`composer qa:duplication`, `composer qa:mess` inside
  `composer ci:static`, PR #616) own the copy-paste and code-smell verdicts
  in the battery that demonstrably blocks: the first-push reds on the most
  recent non-changelog slices (#610 `CI Static`, #613 `CI Infection`) were
  local-gate mirrors while `SonarCloud analysis` was green both times.
- **The trial was the trigger, not the reason.** The account's OSS/trial
  horizon is what prompted the retirement now; the structural justification
  above is what makes the retirement honest rather than a down-scoping.

## 2. What the retirement changed (repository side)

| Removed | Where |
| --- | --- |
| The scanner workflow | `.github/workflows/sonarcloud.yml` (deleted) |
| The analysis configuration | `sonar-project.properties` (deleted; its scope doctrine is summarised in section 5 below) |
| The changelog lanes' dispatch step | `changelog.yml` / `changelog-official.yml` no longer `gh workflow run sonarcloud.yml` for their update-branch PRs |
| The main-validation watchdog lane | `ci-main-watchdog.yml`'s dispatch list drops `sonarcloud` (it was ordered last so its coverage bridge could find the `CI Coverage` artifact) |
| The workflow inventory row | `docs/ci-notes/workflow-inventory.md` |
| The check table + prose | `README.md`, `CONTRIBUTING.md` (the "SonarCloud-clean logic" section is rewritten as the local-gate doctrine it already was in practice) |

Merge blocking is unchanged: the required-check set, the merge queue's
ALLGREEN grouping and every local gate are untouched.

## 3. Account-side teardown (GitHub + SonarCloud)

The retirement is complete only when the account side matches the tree.

1. **Remove the `code_quality` rule from the `main` ruleset** (Repository
   settings → Rules → Rulesets → `main`). This is the one step the original
   KDD-QA8 recipe did not name, discovered while scoping the removal: the
   ruleset carried a `code_quality` rule (severity: all) that reads
   SonarCloud's code-quality reporting — without an analysis to read it
   **blocks the merge** (the same failure mode the changelog lanes'
   `workflow_dispatch` + `pr_number` wiring existed to prevent). Retiring the
   workflow without retiring this rule deadlocks every subsequent pull
   request, changelog PRs included.
2. **Delete the `SONAR_TOKEN` repository secret** (optional cleanup; nothing
   reads it once the workflow is gone).
3. **SonarCloud account side** (optional): delete the `zef_zeflous` project
   and revoke the SonarQube Cloud GitHub App's access to `Zeflous/zeflous`
   — Administration → ALM Integrations → GitHub, and the org's GitHub Apps
   configuration. Keeping them is harmless; they simply receive no further
   analyses.

## 4. Doctrine that outlives the lane

- **The `MeterCount::advanced()` nested-guard spelling is load-bearing.**
  The lane's one issue-level exclusion (`php:S1066`) existed because merging
  the nested `is_int` guards into one `&&`-joined condition mints an
  **escaping mutation-testing mutant** (the mixed-operand case answers the
  identical float under both spellings, so no test can distinguish them,
  breaking the MSI-100 contract). The exclusion is gone with the analyzer,
  but the *code shape* must stay: do not "simplify" those guards; the
  mutation gate (`infection.json5`, the MeterCount cast records) still owns
  the reasoning.
- **Exclusions were gate-conflict answers, never style preferences.** The
  lane's whole exclusion history held exactly one issue-level entry, each
  time documenting a mechanical conflict between two gates. That bar is the
  repository's standing doctrine for ANY analyzer, local or cloud: an
  exclusion must name the two gates that conflict and prove the conflict is
  mechanical, or it does not land.

## 5. The scope doctrine, for the record

The analyzer saw an explicit allow-list (everything that is not first-party
source excluded: `vendor/**`, `build/**`, `.github/**`, `scripts/**`,
`stubs/**`, docs and manifest files), with `tests/**`, `benchmarks/**` and
`scripts/**` additionally outside the coverage denominator, and the vendored
PSR shim outside the copy-paste detector — the same boundary the local
`DuplicationGate` draws in its own docblock. The local gates inherited that
boundary as their own doctrine when the lane retired.

---

## 6. Original setup checklist (preserved for resurrection)

1. Sign in at <https://sonarcloud.io> with the GitHub account that owns this
   repository.
2. **Put the organization on the OSS plan** (free for open-source
   organizations). The Free plan's language set has **no PHP analyzer**: the
   scanner preprocesses files but indexes none of them and the run ends with
   an effectively empty code model. The OSS plan unlocks PHP, unlimited
   branches and pull-request analysis, at zero cost for public repositories.
3. **Create the project manually** (`Create project -> Manually`), pick the
   organization `zeflous`, and set the project key to **`zef_zeflous`**.
   Choose a **public** project.
4. **Bind GitHub (ALM integration)** — *the step that turns decoration on.*
   Administration → ALM Integrations → GitHub, install/configure the
   **SonarQube Cloud GitHub App** so it is granted access to
   `Zeflous/zeflous` (Organization settings → GitHub Apps → SonarQube Cloud
   → Configure; "All repositories" or the selected list including this one).
   *Diagnostic*: if misconfigured, the analysis still runs and the gate is
   still evaluated, but GitHub PRs carry **no** `sonarqubecloud[bot]`
   comment and **no** `SonarCloud Code Analysis` check-run.
5. **Disable Automatic Analysis** (Analysis Method): CI-based analysis and
   automatic analysis conflict.
6. **Create a token** and save it as the repository secret
   **`SONAR_TOKEN`**. Any token whose SonarCloud identity is a member of the
   organization **`zeflous`** works.
7. **Verify**: a correct configuration produces, on the PR: a
   `sonarqubecloud[bot]` comment ("Quality Gate Passed/Failed"), a
   `SonarCloud Code Analysis` check-run (GitHub App `sonarqubecloud`), and
   the PR in the SonarCloud UI with its `qualityGateStatus`.

The scanner action to pin, if resurrected: `SonarSource/sonarqube-scan-action`
at the full commit SHA of tag `v8.2.2`, with `-Dsonar.projectBaseDir=.` and
the exclusions allow-list restored (section 5).

## 7. Chain of evidence for the decoration investigation (2026-10-07)

| Fact | Source |
| --- | --- |
| Analysis runs, gate passes on PRs | run 37652159694 log: `QUALITY GATE STATUS: PASSED ...&pullRequest=2` |
| Scanner detects the PR context and the ALM binding | run log: `Detected project binding: BOUND`, `Auto-configuring pull request 2` |
| SonarCloud knows the GitHub PR URL | `GET /api/project_pull_requests/list?project=zef_zeflous` -> `"url":"https://api.github.com/repos/Zeflous/zeflous/pulls/2"` |
| No decoration on the repo | PR #1/#2 have 0 issue comments and no `commented` timeline event |
| No app check-run on this repo | `commits/<head>/check-runs` -> app `github-actions` only |
| The app check-run DOES exist on the reference repo | `Zeflous/zef-framework` -> `SonarCloud Code Analysis` / app `sonarqubecloud` |
| Decoration is an app-side feature | `docs/security` of the reference + `sonar-project.properties` comment: "a SonarCloud organization can only decorate pull requests of GitHub repos owned by the account/org it is bound to" |

Conclusion at the time: the workflow and the analysis were already correct;
decoration is delivered by the **SonarQube Cloud GitHub App** and is gated by
that app being granted access to this repository.
