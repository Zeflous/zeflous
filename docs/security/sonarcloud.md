# SonarCloud gate — retired 2026-10-26, resurrected the same day

The SonarCloud analysis lane lived from 2026-10-07 until PR #622 retired it
(merged 09:24:47Z), and was brought **back the same day** on a freshly
provisioned account: the owner values the cloud analyzer's contribution to
code quality — the PR decoration, the web UI's trending, the server-side
quality gate over the whole tree — and provisioned a new SonarCloud account
rather than let the lane go. This document is the **resurrection record**:
what is back, what is deliberately NOT back, the new account's coordinates,
the account-side steps that remain, and the doctrine that survived both the
retirement and the return.

## 1. The new account (the live values)

| Setting | Value |
| --- | --- |
| Organization key | `zefl` (a different SonarCloud tenant than the retired `zeflous` — the old org's history and gate configuration did not carry over) |
| Project key | `zefl_zeflous` |
| Project name | ZEF Framework |
| Quality gate | **ZEF** (the owner's chosen gate, selected project-side) |
| `SONAR_TOKEN` | Organization-level Actions secret, visibility **all** (the same shape the retired lane verified with the 2026-10-10 `probe/secret-visibility` investigation); refreshed 2026-10-26T10:23:18Z, an hour after the retirement merged |

Values reported by the owner on 2026-10-26. Re-verify any time:

```
GET https://sonarcloud.io/api/organizations/search?organizations=zefl
GET https://sonarcloud.io/api/components/show?component=zefl_zeflous
```

`sonar-project.properties` carries `sonar.projectKey=zefl_zeflous` /
`sonar.organization=zefl`; the scanner workflow
(`.github/workflows/sonarcloud.yml`) reads the rest from that file. If the
project is ever recreated under a different key, update both values in the
same change.

## 2. What is back — and what deliberately is not

**Back** (rebuilt from the retired revision's proven machinery):

- `.github/workflows/sonarcloud.yml` — the credential guard (fail-closed vs
  green-with-notice), the coverage bridge (polls the CI Coverage lane's run
  for the same commit, downloads its `coverage-reports` artifact), the
  analysis-scope diagnostic, the validated manual-run `pr_number` arguments,
  and the full-SHA-pinned actions (checkout v7.0.1, scanner action v8.2.2).
- `sonar-project.properties` — the exclusion allow-list scope doctrine, the
  coverage/cpd exclusions, and the earned issue exclusions
  (`php:S1066` on `MeterCount.php`, `php:S4790` on `PostmanCollection.php`,
  `php:S1142` on `OpenApiUiMiddleware.php` — the three carried over from the
  first life unchanged — plus `php:S5332` on `WorkerUri.php`, the resurrected
  lane's first addition: the worker URI join's scheme literal is the rule's
  own false-positive class, the S4790 posture — the literal is the inbound
  request's own self-description, never a communication endpoint, and the
  forwarded-proto decision the rule wants made is the trusted-proxy lane's,
  never an extraction default).
- The CI Coverage lane's `coverage-reports` artifact upload (clover + JUnit),
  and the main-validation watchdog's dispatch entry for `sonarcloud` — it is
  the LAST lane in the dispatch list so its coverage bridge can find the CI
  Coverage artifact.

**Deliberately NOT back** (each one existed only to serve the
merge-blocking role, which the resurrection does not resume):

- The ruleset's `code_quality` rule. That rule reads SonarCloud analysis and
  blocks merges without one — it is the failure mode the retirement
  discovered (and the one account-side step the original removal recipe
  missed; PR #622 removed it via API before queueing). Re-adding it would
  hand every future merge to the new account's plan horizon: a lapsed or
  rate-limited analyzer must never deadlock the queue. The lane is
  **advisory**; the required set stays exactly `CI Strict`, `PR Validator`,
  `PhpCodeArcheology SARIF`. Making the lane blocking again is one ruleset
  API call — deliberately not taken.
- The `merge_group` trigger and the per-entry `merge-queue/pr-N` SonarCloud
  branch resolution. The watchdog's post-merge `main` dispatch keeps `main`
  analysed at its final sha instead — the same metrics, no queue latency, no
  short-lived branches in SonarCloud.
- The changelog lanes' dispatch wires (`changelog.yml` /
  `changelog-official.yml` running this workflow with a `pr_number` for
  their bot-authored update PRs). Those existed to feed the `code_quality`
  rule an analysis on PRs whose `pull_request` event never fires; the rule
  is gone, so the wires stay gone. The `pr_number` input remains for
  MANUAL runs.
- The coverage bridge's report-regeneration step and its two composer-script
  indirections (`coverage:ci`, `ci:coverage:reports`). The coverage GATE
  itself writes `build/clover.xml` AND `build/junit.xml` in one suite run,
  so the bridge consumes the gate's own artifact — the leaner shape the
  retirement's investigation paid for. A second full suite run per commit
  stays retired.

## 3. Account-side steps still owed (the resurrection checklist)

Done by the owner, 2026-10-26: account created, organization `zefl`,
project `zefl_zeflous` (public), quality gate "ZEF" selected,
`SONAR_TOKEN` refreshed in the organization secrets (visibility all).

Still to verify on the SonarCloud account side — the first analyses will
tell:

1. **The OSS plan.** The Free plan's language set has **no PHP analyzer**:
   the scanner preprocesses files but indexes none of them and the run ends
   with an effectively empty code model (measured 2026-10-07 on the old
   account — the setup checklist's hardest-won lesson). A first analysis
   that reports ~0 indexed PHP files is this symptom, not a repo-side bug.
2. **Automatic Analysis disabled** (Analysis Method). CI-based analysis and
   automatic analysis conflict; with both enabled the scanner errors out.
3. **The ALM binding + the SonarQube Cloud GitHub App** granted access to
   `Zeflous/zeflous` — *the step that turns decoration on* (Administration
   → ALM Integrations → GitHub; Organization settings → GitHub Apps →
   SonarQube Cloud → Configure). Without it the analysis and the gate
   verdict still run, but GitHub PRs carry no `sonarqubecloud[bot]` comment
   and no `SonarCloud Code Analysis` check-run. A SonarCloud organization
   can only decorate pull requests of GitHub repos owned by the account/org
   it is bound to — the new org `zefl` must be bound to the GitHub side
   that owns this repository for decoration to appear at all.

## 4. Doctrine that outlived the retirement (and now serves the return)

- **The `MeterCount::advanced()` nested-guard spelling is load-bearing.**
  The lane's one issue-level exclusion (`php:S1066`) exists because merging
  the nested `is_int` guards into one `&&`-joined condition mints an
  **escaping mutation-testing mutant** (the mixed-operand case answers the
  identical float under both spellings, so no test can distinguish them,
  breaking the MSI-100 contract). The exclusion is back with the analyzer,
  and the *code shape* must stay regardless: do not "simplify" those guards;
  the mutation gate (`infection.json5`, the MeterCount cast records) still
  owns the reasoning.
- **Exclusions are gate-conflict answers, never style preferences.** The
  lane's whole exclusion history holds four issue-level entries — three
  documenting a mechanical conflict between two gates, and one (the S5332
  worker-URI entry) carrying the S4790 false-positive-class argument: the
  rule's own documentation does not cover the shape it flagged. That bar is the
  repository's standing doctrine for ANY analyzer, local or cloud (see
  CONTRIBUTING.md, "Analyzer-clean logic"): an exclusion must name the two
  gates that conflict and prove the conflict is mechanical, or it does not
  land.
- **The blocking verdicts stay local.** The retirement was honest because
  the duplication and mess detectors (`composer qa:duplication`, `composer
  qa:mess` inside `composer ci:static`, PR #616) own the copy-paste and
  code-smell verdicts in the battery that demonstrably blocks: the
  first-push reds on the most recent non-changelog slices (#610 `CI Static`,
  #613 `CI Infection`) were local-gate mirrors while `SonarCloud analysis`
  was green both times. The resurrection adds the cloud signal BACK on top
  of that stack — it does not move any blocking verdict into the cloud.

## 5. The scope doctrine, for the record

The analyzer sees an explicit allow-list (everything that is not
first-party source excluded: `vendor/**`, `build/**`, `.github/**`,
`scripts/**`, `stubs/**`, docs and manifest files), with `tests/**`,
`benchmarks/**` and `scripts/**` additionally outside the coverage
denominator, and the vendored PSR shim outside the copy-paste detector —
the same boundary the local `DuplicationGate` draws in its own docblock.
The boundary is fail-closed: a new top-level directory enters the scope
only when someone decides to remove its pattern from
`sonar-project.properties`. The scanner step's "Analysis scope (diagnostic)"
prints the analysed file counts so scope drift is visible, never silent.

The lane's first-life spelling also carried `sonar.sources=src` and
`sonar.tests=tests`. The scanner's analysis-scope UX ignores both keys and
warned about them on every run ("The following properties are configured but
have no effect: sonar.sources, sonar.tests"), so they were removed on
2026-10-26, hours into the resurrected lane's life: the exclusion allow-list
above is the only scope control the scanner actually honours, and the
intent those keys documented now lives in this record instead of in dead
configuration. The removal changes nothing about what is analysed — the
whole-project scope was measured as early as run 37652159694 (2026-10-07,
the first life), which is exactly how `vendor/` reached the first PR's
24.6%-coverage artefact before the allow-list existed.

## 6. Original setup checklist (preserved from the first life)

1. Sign in at <https://sonarcloud.io> with the GitHub account that owns this
   repository.
2. **Put the organization on the OSS plan** (free for open-source
   organizations). The Free plan's language set has **no PHP analyzer**: the
   scanner preprocesses files but indexes none of them and the run ends with
   an effectively empty code model. The OSS plan unlocks PHP, unlimited
   branches and pull-request analysis, at zero cost for public repositories.
3. **Create the project manually** (`Create project -> Manually`), pick the
   organization, and set the project key. For the current account:
   organization **`zefl`**, project key **`zefl_zeflous`**. Choose a
   **public** project.
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
6. **Create a token** and save it as the **`SONAR_TOKEN`** secret. Any token
   whose SonarCloud identity is a member of the analysis organization works.
   The current shape: an organization-level Actions secret with visibility
   **all** (this repository reads it without carrying a repository-level
   secret).
7. **Verify**: a correct configuration produces, on the PR: a
   `sonarqubecloud[bot]` comment ("Quality Gate Passed/Failed"), a
   `SonarCloud Code Analysis` check-run (GitHub App `sonarqubecloud`), and
   the PR in the SonarCloud UI with its `qualityGateStatus`.

The scanner action pinned, in the current workflow:
`SonarSource/sonarqube-scan-action` at the full commit SHA
`ba9859eae8dd6bd29e412f25ddbbef3d032000f4` (tag v8.2.2), with
`-Dsonar.projectBaseDir=.` and the exclusions allow-list (section 5).

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

Conclusion at the time (applies to the new account identically): the
workflow and the analysis are correct; decoration is delivered by the
**SonarQube Cloud GitHub App** and is gated by that app being granted access
to this repository — and by the new organization being bound to the GitHub
side that owns it.
