# CI note: main-merge validation gap and the two-layer repair (2026-10-10)

## Symptom

On the `main` branch's recent commits, only DeepSource appeared to be running.
Fifteen workflows declare `on: push: branches: [main]`, yet the two most recent
merge commits had no GitHub Actions runs at all:

| Commit | PR | merged_by | push-lane runs | check-runs |
|--------|----|-----------|----------------|------------|
| `61e80e9` | #134 | `mbetixz` (human PAT) | 15 workflows ran | 18 |
| `5943777` | #140 | `github-actions[bot]` | **0** | 0 |
| `5df97d2` | #139 (HEAD) | `github-actions[bot]` | **0** | 2 (both scheduled sweeps) |

DeepSource stayed visible because it is a GitHub App that receives webhook
events directly, outside GitHub Actions -- it is immune to the mechanism that
silenced everything else. The repository's HEAD had never been validated by
`CI Strict`, SonarCloud, archeology, the coverage gate, or any other lane.

## Root cause

Two GitHub behaviours compose into the gap:

1. **The recursion guard.** Events created by the built-in `GITHUB_TOKEN`
   (push, pull_request, workflow_run -- everything except `workflow_dispatch`
   and `repository_dispatch`) do not trigger new workflow runs. A merge
   attributed to `github-actions[bot]` therefore lands on `main` with zero
   `on: push` runs.
2. **Auto-merge attribution.** GitHub attributes a native auto-merge to the
   identity that created the pull request (measured: `merged_by == PR author`
   on all six PRs #134/#136/#137/#138/#139/#140; the arming token does not
   change it -- the Auto Merge sweep arms with the `ZEF_TOKEN` PAT, and those
   PRs still merged as the bot).

The changelog lanes (`changelog.yml`, `changelog-official.yml`) created their
update pull requests with the built-in `GITHUB_TOKEN`, so every changelog PR
was a bot-created PR, merged as `github-actions[bot]`, producing merge commits
that fire no events. The lanes already worked around the SAME guard on the
update *branch* (they dispatch the required lanes explicitly), but nothing
replayed the *main* side after the merge.

A live diagnostic probe (branch `probe/secret-visibility`, since deleted)
confirmed the org-level secret `ZEF_TOKEN` resolves inside Actions runs
(length 40, classic PAT, authenticates as `mbetixz`) -- so the fix could build
on the existing secret rather than new infrastructure.

## Repair (two layers)

**Layer 1 -- keep the events firing (root cause).** The changelog lanes now
push the update branch, create the pull request, and arm auto-merge with
`secrets.ZEF_TOKEN || secrets.GITHUB_TOKEN` (PAT first, built-in token as the
fallback). A PAT-created PR merges as a human actor, the
push events fire, and every main lane runs exactly as it does for a human
merge; the `action_required` approval dance disappears too. The explicit lane
dispatches run ONLY in built-in mode, so the natural runs are not duplicated.
The push output is scrubbed of the token before it reaches the console.

**Layer 2 -- guarantee the invariant (defence in depth).** A new scheduled
workflow, `ci-main-watchdog.yml`, sweeps three times an hour and verifies that
every lane a human push would trigger (`ci-static`, `ci-coverage`,
`ci-infection`, `ci-bench`, `ci-strict`, `archaeology`, `docs-check`,
`phpbench`, `proof-html`, `sbom`, `release-drafter`, `sonarcloud` -- in
dependency order, SonarCloud last so its coverage bridge can find the CI
Coverage artifact) has a run for the current `main` HEAD. A missing lane is
dispatched on `main` via `gh workflow run --ref main` -- `workflow_dispatch`
being the one event type the recursion guard does not swallow, which is what
makes it the correct repair channel. A red lane is reported (step summary +
`::warning::`) but never auto-retried, so a genuinely red main cannot burn a
lane set every sweep; a cancelled run produced no verdict and is replayed on
the next sweep.

Steady state costs one short API-only job per sweep: when merges fire push
events normally, every lane already has its run and nothing is dispatched.
The watchdog also covers every OTHER way the invariant can break -- a future
bot-merged PR from any lane, a revoked or expired PAT (it was already
refreshed once), or a dropped webhook.

`auto-merge.yml` additionally reports its active credential mode (PAT vs
built-in, never the value) in every sweep's log and step summary, so a silent
PAT expiry is visible instead of indistinguishable from a healthy sweep.

## Verification checklist

- [x] Root cause measured, not inferred: run lists queried per exact `head_sha`.
- [x] Secret resolution probed live (length + identity only; branch deleted).
- [x] jq state machine unit-checked against green / live / red / empty inputs.
- [x] Embedded shell passes `bash -n`; YAML parses with the repo's PyYAML.
- [x] Concurrency declarations satisfy `WorkflowConcurrencyPolicy` (constant
      group with `cancel-in-progress: false` is allowed for non-cancelling
      sweeps; the policy's commit-keyed requirement applies to cancelling
      workflows and to the `ci-strict.yml` aggregator, which is unchanged).
- [x] No `ruleKey` exclusions added anywhere; no new SonarCloud findings by
      construction (YAML + shell + docs only; no PHP source touched).
