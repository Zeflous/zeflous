# CI note: merge-gate audit — dead required checks, spoofable contexts, the aggregator stall (2026-10-18)

Date: 2026-10-18
Status: partial (the code-side fixes shipped as pull requests; the ruleset-side findings were filed as issues — findings 1+2 were applied 2026-10-18 with the ruleset fix evidenced on #420 and the docs half in PR #423, and finding 3's posture was decided 2026-10-18: merge queue — the queue-aware workflow half ships as a pull request, the ruleset half adds the `merge_queue` rule and drops the app check from the required set, evidenced on #421)
Scope: `.github/workflows/ci-strict.yml`, `.github/workflows/sonarcloud.yml`, `.github/workflows/auto-merge.yml`, the `main` ruleset (id 24654862)

This note records the second hardening audit: **can a pull request merge while a
required check has never reported?** The answer was probed against live data, not
inferred from the workflow files, and every claim below carries its measurement.

## Findings (ordered by severity)

### 1. Three of the eight required checks never report — HIGH (settings-side)

The `main` ruleset (id 24654862) requires eight status-check contexts. Measured on
PR #406 (head `1a833ae073db445dbe95694de79f353d91942ef8`, merged 2026-10-17T19:12:51Z):

| Required context (ruleset) | Check-runs on the head sha | Verdict |
|---|---|---|
| `CI Strict` | 1 (GitHub Actions, success) | enforces |
| `PR Validator` | 1 (GitHub Actions, success) | enforces |
| `PhpCodeArcheology SARIF` | 1 (GitHub Actions, success) | enforces |
| `SonarCloud Code Analysis` | 1 (SonarQubeCloud, success) | enforces |
| `SonarCloud analysis` | 1 (GitHub Actions, success) | enforces |
| `DeepSource: PHP` | **0** | dead requirement |
| `DeepSource: Python` | **0** | dead requirement |
| `DeepSource: Secrets` | **0** | dead requirement |

The head sha carries 15 check-runs in total (all four strict lanes, the aggregator
job, PR Validator, archeology, both Sonar contexts, advisory lanes) — and none from
DeepSource. The same probe on the `main` push HEAD (`541cc26e`, mid-validation at
probe time: 16 check-runs, lanes `in_progress`) shows **0 DeepSource check-runs
there too**: the integration is not reporting anywhere, on any event, for any ref.

GitHub only blocks a merge on a required check it considers *expected*. A check
context that has never reported for the head is never expected, so it never blocks:
the ruleset advertises eight gates and five enforce. This is the measured instance
of the symptom that prompted the audit ("a job that never got its check still let
the merge through").

### 2. The required set drifted from the documented contract — same issue

Three documents disagree with the live ruleset, and with each other:

| Source | Claims as required on `main` |
|---|---|
| `CONTRIBUTING.md` (Quality gates) | `CI Strict`, `PR Validator` — "the rest are advisory signals" |
| `README.md` (Quality gates) | `CI Strict`, `PR Validator`, `PhpCodeArcheology SARIF` |
| live ruleset 24654862 | all eight, including the three DeepSource contexts and both Sonar contexts |

A contributor reading either document would believe SonarCloud is advisory; the
ruleset blocks on it. The fix belongs with the ruleset change (the file issue), and
whichever required set is settled should be restated identically in both documents.

### 3. Required checks are produced by the PR's own workflow version — HIGH (settings-side; posture decided 2026-10-18: merge queue)

A `pull_request`-triggered workflow executes the workflow file **from the pull
request's head**, not from `main`. The four required contexts produced by such
workflows (`CI Static`/`CI Coverage`/`CI Infection`/`CI Bench` through the
aggregator, `PR Validator`, `PhpCodeArcheology SARIF`, `SonarCloud analysis`) can
all be made "green" by a pull request that edits the corresponding workflow file —
the check-runs are created by the PR's own code. `ci-strict.yml`'s `pull_request`
(bootstrap) path additionally holds `checks: write` from the PR context.

The composition that makes this a real merge path rather than a curiosity:

1. the ruleset requires **zero approving reviews**
   (`required_approving_review_count: 0`, `require_code_owner_review: false` —
   despite `.github/CODEOWNERS` existing precisely to make that flag meaningful);
2. `auto-merge.yml` arms native auto-merge on every open pull request;
3. `auto-merge.yml` **auto-approves** every waiting (`action_required`) run of every
   same-repository pull request, erasing the first-run approval checkpoint that is
   GitHub's last human gate on workflow-modifying code.

Fork pull requests cannot exploit this (their `GITHUB_TOKEN` is read-only, so the
check-runs cannot be written); the path requires write access, which today is the
owner alone. The exposure grows silently the first time a collaborator is added.
`docs/ci-ruleset-verify-{1,2,3}.md` record that review requirements were tried and
backed out — they stall native auto-merge for a solo maintainer (a PR author cannot
count as their own approver). The issue filed from this audit lays out the honest
options (accept + document / code-owner review with a trusted second approver /
merge queue with `merge_group` triggers added to the lanes) instead of silently
keeping the strictest-looking, weakest-enforcing configuration.

**Resolution (2026-10-18):** the maintainer chose the merge queue (option 3). It is
the one posture that keeps the solo-maintainer flow (no second approver needed)
while killing the spoof class outright: the queue runs the required checks on the
combined merge-group ref using the **default branch's** workflow files, so a pull
request's own workflow version can never certify itself. The queue-aware half is
this change — `merge_group` triggers on the aggregator, the four lanes, PR
Validator (which re-resolves the queued PR's title from the merge-group branch
name and re-validates it, fail closed), archeology (gate runs on the group; the
SARIF upload runs on the group head too — see the drill paragraph below) and the SonarCloud lane
(short-lived branch `merge-queue`, secrets injectable so the guard holds it to
the push-to-main standard). The settings half, applied with the ruleset change
evidenced on #421: the `merge_queue` rule itself, and the `SonarCloud Code
Analysis` app check moving OFF the required set — the SonarCloud GitHub App does
not report on merge groups (product management statement, May 2024,
community.sonarsource.com/t/112436), so keeping it required would stall every
queue entry; the blocking server-side verdict travels with the workflow check
`SonarCloud analysis`, which waits on the same gate. One platform caveat is
recorded for the drill: GitHub has had bugs where the queue read the
`pull_request`-context check instead of waiting for the `merge_group` one
(reverted Feb 2025, community thread 151100) — the first queued entries should
be watched for exactly that.

**Drill outcome (2026-10-18, first entries):** the queue itself behaved —
entries waited for `merge_group`-context check-runs on the cumulative group
heads (four entries, one ALLGREEN group, no symptom of the platform quirk
above; PR Validator's queue-path title resolution green on every head). The
drill instead caught a bug in this very change: the archeology lane's SARIF
upload was skipped on `merge_group`, but the ruleset's `code_scanning` rule
evaluates the merge group head at merge time, so every entry was dequeued
seconds after its checks went green — the merge was blocked by a rule, not by
a check (entries #445 and #450 removed 25 seconds and 2 minutes after their
respective evaluation points, all checks success). The fix — uploading the
curated SARIF on the queue path — could not ride the broken queue itself
(the queue runs the default branch's workflow files, so a queue-path fix is
the one change the queue cannot validate), and was landed through a
temporary ruleset window with the `merge_queue` rule disabled; the rule was
re-enabled the moment the fix reached `main`. The same drill caught a second
queue-path defect the same evening: the SonarCloud scan pinned ONE shared
branch name (`merge-queue`) for every entry, and a group of N entries runs N
concurrent scans — after a group rebuild started two entries' scans within
seconds of each other, their server-side compute-engine tasks raced and the
loser died with "CE Task finished abnormally with status: FAILED", turning a
required check red while the winner went green on the very same branch. The
branch is now named per entry (`merge-queue/pr-<N>`, derived from the queue
ref, fail closed on an unparsable ref), so concurrent entries can never
collide and retries re-analyse their own branch in place.

### 4. The aggregator can stall the required check `pending` forever — MEDIUM (fixed here)

`ci-strict.yml`'s fallback path (the `pull_request`/`push`/`workflow_dispatch`
triggers — the bootstrap case, and the safety net for a dropped `workflow_run`
event) waits for the four lanes with a bounded loop and then computes:

```js
const conclusion = state.anyFailed ? "failure" : (state.allDone ? "success" : null);
const status = conclusion ? "completed" : "in_progress";
```

Two defects compose:

- the deadline (45 minutes) is **shorter than the slowest lane's own timeout**
  (`ci-infection.yml`: 60 minutes), so a lane that merely runs long is still
  unresolved when the loop gives up;
- expiry leaves the check-run **`in_progress`** — an eternal pending, not a verdict.

The stall is fail-closed (nothing merges), but it is fail-*silent*: the pull request
sits `blocked` with no red check to act on, indefinitely. The precise precondition:
the final lane completion must (a) arrive after the fallback deadline, and (b) fire
no `workflow_run` event that reaches this workflow (dropped events are best-effort
and have no retry). A lane that concludes `cancelled` does NOT stall — the
aggregator already treats any non-success conclusion as a hard failure.

The fix shipped in this lane: the deadline moves **above** the slowest lane's
timeout (70 minutes) with the job budget above it (75), and fallback expiry now
**concludes `timed_out`** — a visible, red, actionable verdict — instead of
`in_progress` forever. The event-driven path is unchanged: a single pass with
lanes still pending stays `in_progress` there by design, because each lane
completion re-fires the aggregator.

### 5. The required `SonarCloud analysis` check is fail-open on missing credentials — MEDIUM (fixed here)

`sonarcloud.yml`'s documented contract (docs/security/sonarcloud.md §2): when the
`SONAR_TOKEN` secret is unavailable the job reports **green with a notice**. That
was the right call when the check was advisory — an unconfigured repository must
not have its queue blocked by a gate it cannot run. But the check is now in the
ruleset's **required** set: as a required check, green-without-analysis means an
expired or deleted `SONAR_TOKEN` silently disables the SonarCloud gate for
same-repository pull requests and `main` pushes — exactly the context where the
secret is always injectable and its absence is a misconfiguration, not a withheld
credential.

The fix shipped in this lane narrows the soft path to the two contexts where GitHub
actually withholds secrets (a fork head, a `dependabot[bot]`/`renovate[bot]`
author) and fails closed everywhere else. docs/security/sonarcloud.md §2 is
amended in the same change, so the documented contract and the behaviour cannot
drift apart again.

### 6. The auto-approve sweep erases the first-run approval checkpoint — MEDIUM (fixed here)

`auto-merge.yml` approves every waiting run of every same-repository pull request
on every sweep. That behaviour was built for the changelog bot's pull requests
(their runs are left `action_required` by the recursion guard — PRs #31/#49), but
as written it also auto-approves the runs of a pull request that **modifies
workflow files**, whose first-run approval is the one moment a human reviews the
automation itself. Combined with finding 3 (zero required reviews), that is the
unreviewed-execution path. The fix shipped in this lane: the sweep reads the PR's
changed-file list and leaves workflow-modifying pull requests for a maintainer;
bot-authored changelog pull requests (which never touch workflows) keep their
automatic approval, so the original stall does not return.

### 7. The auto-merge sweep's branch sync fights the merge queue — HIGH (fixed here)

`auto-merge.yml`'s step 1 called the REST `update-branch` endpoint for every
open pull request on every sweep — a rule written before the merge queue
existed, when syncing a behind branch satisfied the "branches up to date"
policy and re-triggered CI. With the queue live the same call inverts into
sabotage: the queue validates an entry on a group head built from live
`main`, so an armed pull request's branch running behind `main` is not a
defect — while ANY new commit pushed to that branch (including the merge
commit the sync itself pushes) removes the entry from the queue, disarms
auto-merge, and restarts the entire check cycle.

The evening the queue went live, `main` moved with every changelog
regeneration, and the sweep pushed a sync merge commit onto armed pull
requests' branches minutes after each movement. Entry #453's branch received
three of them in 32 minutes — `4d62759` (22:23:27Z, three minutes after #448
merged), `bf41ec4` (22:41:41Z, four minutes after #452), `a658b2d`
(22:55:22Z, two minutes after #454) — each authored by the PAT identity
through the REST endpoint (committer `GitHub <noreply@github.com>`), each
discarding a running check cycle; #449 sat open for over four hours in the
same churn. At 22:18:28Z the maintainer switched the `merge_queue` rule off,
and the stuck pull requests released at once: #448 (22:20:20Z), #452
(22:37:58Z), #454 (22:53:46Z) and #453 (23:11:01Z) all merged within 53
minutes of the toggle.

That release exposed the other half of the defect's shape: with the queue
off, the sync is LOAD-BEARING — the ruleset's strict up-to-date policy
(`strict_required_status_checks_policy: true`) leaves a behind branch
unmergeable, and each post-toggle merge followed a sync or a fresh changelog
regeneration (#452, for example, merged sixteen minutes after the sync
commit `cff898b` landed on its branch). "Never sync" would therefore starve
the queue-off world exactly as "always sync" starved the queue-on world.

The fix shipped in this lane is a MODE SWITCH, not a ban: once per sweep the
lane reads the repository rulesets, and while any active branch ruleset runs
a `merge_queue` rule the branch sync is suspended outright (the queue owns
up-to-dateness at the group head); the moment no queue is active the sweep
syncs exactly as it always did. The ruleset read fails closed — an
unreadable state suspends the sync for that sweep, because the cost of a
wrongly-synced queued entry is a lost queue position while the cost of one
skipped sweep is a short delay.

## A disproven hypothesis, recorded to prevent re-investigation

The auto-approve filter selects runs with `.conclusion == "action_required"`
(`auto-merge.yml`, the `workflow_run`-driven sweep's jq program). The GitHub
Actions documentation describes waiting runs as `status: "action_required"` with a
null conclusion, which would make that filter a silent no-op. Probed live
(`GET /repos/Zeflous/zeflous/actions/runs?status=action_required`, 2026-10-18):
11 waiting runs, every one reported as `status: "completed"` **with**
`conclusion: "action_required"`. The filter matches real waiting runs on this
repository; no change is needed, and anyone auditing that jq program against the
REST documentation alone would otherwise "fix" a working filter.

## Verification checklist

- [x] Every ruleset claim read from the live API (`GET /repos/Zeflous/zeflous/rulesets/24654862`), not from a cached copy.
- [x] PR #406 head check-runs enumerated (15 total; DeepSource absent) — the merge that prompted the audit, measured on its own head sha.
- [x] `main` push HEAD check-runs enumerated (16 total; DeepSource absent there too).
- [x] The `action_required` field-semantics probe (11 runs) — the disproven hypothesis above.
- [x] Changed YAML parses with PyYAML; the aggregator's embedded script passes `node --check`; the changed shell blocks pass `bash -n`.
- [x] PR titles validated against `scripts/changelog/conventional.py` (the PR Validator taxonomy).
- [x] No PHP source changed; no SonarCloud `ruleKey` exclusions added; no gate weakened — two gates are tightened (4: pending→timed_out verdict; 5: fail-open→fail-closed) and one checkpoint restored (6).
- [ ] Post-merge: re-audit on `main` per CONTRIBUTING rule 12 — confirm the aggregator concludes `timed_out` (not `in_progress`) on a stuck-lane drill, and the Sonar guard fails a deliberately un-credentialed same-repo run.
- [ ] Post-merge (sweep guard, finding 7): with the queue active, an armed entry must survive a `main` movement — no dequeue, no merge commit pushed onto its head branch — and merge from its next green group head; with the queue off, a behind armed entry must still receive its sync.

## Follow-up remediation — 2026-10-22

The follow-up change hardens the three remaining workflow findings without
rewriting the existing merge-group aggregator, whose queue-owned single-write
path is intentionally protected against the 2026-10-19 re-render storm:

- **SonarCloud coverage bridge (F-01):** the required `SonarCloud analysis`
  check no longer sets `sonar.coverage.exclusions=**/*` when coverage data is
  missing. It now fails closed if the matching `CI Coverage` run does not
  complete successfully, the artifact is absent/expired or cannot be
  downloaded/unpacked, or the Clover report is invalid.
- **Ruleset lint sampling (F-02):** the weekly lint paginates the closed-PR
  collection, filters merged PRs, and sorts by `merged_at` descending before
  selecting the latest ten. The API's `updated` ordering and first-page limit
  can no longer silently select an older sample.
- **Explicit job budgets (F-03):** `Auto Assign` has a five-minute timeout and
  `Proof HTML` has a ten-minute timeout, bounding runner consumption.
- **Required versus advisory checks:** `CONTRIBUTING.md` now states that the
  active ruleset defines the merge-blocking contexts. A red advisory check is
  not evidence that a required gate was bypassed; a required check must not be
  overridden by a green PR-head result when its merge-group result is red or
  missing.

The existing `CI Strict` merge-group ownership, bounded polling, and terminal
failure behavior are preserved. These changes are configuration/doc changes;
they do not alter PHP runtime code, lower thresholds, or add SonarCloud
`ruleKey` exclusions. Local `composer ci:strict` and live merge-group
verification remain required before the PR is ready to merge.

