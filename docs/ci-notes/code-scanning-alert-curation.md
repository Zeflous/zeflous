# Code scanning alert curation — why the PhpCodeArcheology alert feed carries error-level findings only

Date: 2026-10-10
Status: implemented (PR "fix(ci): report only actionable findings in the code-scanning alert feed")
Scope: `.github/workflows/archeology.yml`, `scripts/ci/archeology-gate.php`, `tests/Scripts/ArcheologyGateTest.php`

## Symptom

GitHub's Security tab ("Code scanning alerts") showed **79 open alerts** on `main`, all from
PhpCodeArcheology, none of them actionable defects:

- 42 × `PCA/function/Effort-more-than-30-above-average-effort-*`
- 12 × `PCA/function/Maintainability-index-is-more-than-30-below-average-MI-*`
- 8 × `PCA/class/LCOM-is-more-than-30-above-average-LCOM-*`
- 7 × `PCA/class/Effort-*`, 7 × `PCA/file/Effort-*`
- 2 × `PCA/class/Maintainability-index-*`, 1 × `PCA/file/Maintainability-index-*`

Investigation (API: `GET /repos/Zeflous/zeflous/code-scanning/alerts?state=open`, plus the raw
SARIF behind the newest analysis) split those 79 alerts into three distinct problems.

## Root cause 1 — the tool double-emits every function/method finding

phpcodearcheology v2.11.3 writes **two SARIF results** for each function/method-level finding
(same rule, same location, same message, distinct `correlationGuid`) and leaves
`partialFingerprints.primaryLocationLineHash` **empty**. GitHub cannot merge them, so it creates
**two alerts per finding**: 27 of the 79 alerts were exact duplicates of another alert (paired
alert numbers, e.g. #755/#756, #753/#754, …).

## Root cause 2 — function-level findings carry class-FQN pseudo-paths

Function/method-level results use the class FQN — `Zef\Framework\Tooling\GateRunner`, backslashes,
no `.php` — as the `artifactLocation.uri`. Such an alert points at a "file" that does not exist in
the repository: it cannot be opened, and its location anchors nowhere. (Class- and file-level
results carry real paths; only the function-level ones were broken.)

## Root cause 3 — the alerts are relative-threshold artefacts, not defects

Every remaining finding is a *"more than 30% above/below average"* warning. Those thresholds are
**recomputed from the codebase average on every analysis**, which has three consequences:

1. **Outliers exist by construction.** The average of a finite set is always below its maximum:
   any non-uniform codebase has entities above 1.3 × the mean. Measured on `main`: 95 methods
   average 439 effort while 29 of them sit at ~0 and the top 21 carry **94%** of the total.
2. **Fixing an outlier re-flags the next tier.** Removing a large entity lowers the average, so
   the threshold drops and previously-green entities cross it. Observed live: the same rule set
   reported **67 results on commit `148f8ee` and 79 on `61e80e9`** with no production-code change
   in between (the average shifted as classes were split in PR #137).
3. **A "zero warnings" state is unreachable in maintainable code.** It would require every
   method in the codebase to be within ±30% of the mean effort — i.e. every method the same size.

The repo's own gate has said exactly this since it was written (`scripts/ci/archeology-gate.php`
v1 docblock): the relative warnings are "a distribution artefact … rather than defects", and only
SARIF `level: error` results (GodClass, SecuritySmell, DependencyCycle, "Difficulty is too high",
…) are genuine architecture findings. The problem was that the **upload** still shipped the
warnings to the alert feed, which the branch ruleset (`code_quality`, severity all) then treats
as blocking findings — on pull requests a metric shift in touched code could stall a merge on a
finding that is not a defect.

### Evidence that the flagged code is not debt

Each flagged entity was read before deciding anything:

- `GateRunner` (effort 26,788, MI 86.9, CC 15): a 10-method, 89-LLOC dispatcher that delegates to
  the gate classes; every private helper is already single-purpose. Its Halstead effort is an
  artefact of aggregating a wide vocabulary, not of tangled logic.
- `MutationReport`, `CoverageReport`, `LintReport`, `DependencyAudit`: strict-parsing value
  objects (static factory + validated metrics + typed errors) — idiomatic PHP.
- `GateResult` (LCOM 4), `Message` (LCOM 3), `HealthReport` (LCOM 3): static-factory value
  objects. LCOM penalises static factories by design (they share no instance state); the metric
  does not distinguish that from real incohesion.

Refactoring clean, idiomatic code to appease a relative metric would make the code worse, and —
per consequence 2 above — would still not reach zero alerts.

## The fix

`scripts/ci/archeology-gate.php` now does five things in one pass (each unit-tested via a real
subprocess in `tests/Scripts/ArcheologyGateTest.php`):

1. **Normalise absolute paths** to repository-relative URIs (unchanged from v1).
2. **Rewrite FQN pseudo-URIs** through the PSR-4 layout (`Zef\…` → `src/Zef/….php`) — but only
   when the mapped file exists; unknown FQNs are left untouched (fail-closed, never fabricate a
   path).
3. **Collapse duplicate results** (same rule + location + message + level) into one finding.
4. **Backfill stable fingerprints** (`primaryLocationLineHash`) for every kept result, so future
   uploads update the same alert instead of forking it.
5. **Curate by severity**: only `level: error` results remain in the uploaded SARIF. An absent
   `level` is treated as `error` per the SARIF 2.1.0 default — unknown severity fails closed.
   The gate's exit-code behaviour is unchanged (non-zero only for error-level findings).

On the current `main` SARIF (79 results) the gate now reports:

```
normalised 79 SARIF location(s).
collapsed 27 duplicate result(s).
0 error-level finding(s) kept for the code-scanning upload; 52 relative-metric warning(s) excluded
```

The next analysis of `main` after this change therefore contains zero results, and GitHub
auto-closes the 79 open alerts as `fixed`.

## What stays visible, and where

Nothing is hidden — the alert channel simply stops carrying non-defects:

| Signal | Where it lives after this change |
| --- | --- |
| All 60+ metrics, per file/class/method | `phpcodearcheology` run (every push/PR) |
| Every warning-level finding (incl. the 52) | `build/archeology` artifact (Markdown + HTML + raw SARIF), uploaded by the same lane |
| Health score / grade (B, 88.9) | Tool summary + artifact |
| Refactoring priorities | Tool's `refactoring-roadmap.md` in the artifact |
| Error-level architecture defects | Code scanning alerts (unchanged — this is the only channel that blocks PRs) |

## Compliance notes

- **No ruleKey exclusions.** SonarCloud is a separate analyzer and is untouched; no analysis rule
  is disabled here either — the tool still runs every rule on every commit and every finding is
  still reported in the artifact. What changed is which severity deserves the *alert* channel,
  aligning the upload with the doctrine this gate has had since v1.
- **No production code was changed** to chase a relative metric, deliberately: see the evidence
  section above.
- **Coverage/MSI unchanged**: `scripts/**` is outside the PHPUnit coverage scope and outside
  Infection's mutation scope; the new tests exercise the gate as a subprocess exactly the way the
  workflow invokes it.

## Verification checklist

- [x] Local `composer ci:static` — all 12 gates green (197 tests incl. 10 new gate tests)
- [x] Local `composer archeology` + `archeology:gate` on `main`: 79 → 27 collapsed → 0 kept →
      exit 0; curated SARIF has `results: []` and remains valid SARIF 2.1.0
- [x] Error-level fixtures: kept, fingerprinted, gate exits 1; unknown FQN left as-is
- [x] After merge: code scanning alerts on `main` drop to 0 (all 79 auto-closed as `fixed`)
- [x] PR lane uploads the curated SARIF on the merge ref → no new warning-level alerts can block
      future PRs
