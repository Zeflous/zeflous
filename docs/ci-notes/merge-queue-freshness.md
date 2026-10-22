# CI note: merge-queue pin freshness — judging the group head, the recorded landing, and the coverage-bridge race (2026-10-23)

Date: 2026-10-23
Status: shipped (this pull request)
Scope: `src/Zef/Framework/Tooling/RoadmapPinCommitCollector.php`, `RoadmapPinFreshness.php`, `RoadmapPinState.php`, `RoadmapHistoryRows.php`, `RoadmapDocGate.php`, `.github/workflows/sonarcloud.yml`, `CONTRIBUTING.md` (rules 13 and the pin-enforcement paragraph)

This note records the mechanism change that retires the recurring red-`main`
incident class of 2026-10-22, and the second, independent red source the same
day exposed in the SonarCloud coverage bridge.

## The incident class, named precisely

Twice on 2026-10-22 `main` went red while every merge had been performed by the
automation (queue merges, auto-merge armed, zero manual merges):

1. **CI Static, main-lane, run 39801758948 (15:06Z):** `ROADMAP-STATUS.md` pin
   `6f8fecf` stale — the substantive `docs(contributing)` change (#537) had
   merged through the queue *without* its record, and the healing pull request
   (#541) could only chase it. The red window between the two merges is the
   observable defect.
2. **SonarCloud, main-lane, job 119904052956 (15:27Z):** `CI Coverage did not
   complete successfully within 20 minutes for 8dce4624` — a bridge race, not a
   coverage failure (see the third section).

The first failure is not a flake and not a code regression: it is the design of
the freshness rule itself. `RoadmapPinCommitCollector::onMain()` recognised only
the push lane (`GITHUB_REF === refs/heads/main`) and the local `main` checkout.
The **merge queue lane** — the lane that *decides what lands on `main`* — ran
the gate but skipped the pin judgment entirely: `GITHUB_REF` on `merge_group` is
`refs/heads/gh-readonly-queue/main/pr-<N>-<sha>`, so the collector fell through
to the local branch check, read the queue branch name, and answered "not main".
The queue therefore certified group heads whose pin was stale, `main` advanced
to them, and the push lane reported the defect one lane too late to prevent it.

Healing pull requests patch the *result* of each occurrence. The mechanism fix
moves the judgment to where it can still refuse the merge.

## The mechanism: judge the group head, accept the recorded landing

Two changes compose, both fail closed.

**1. The collector recognises the queue lane** (`gh-readonly-queue/main/*`,
recognised only with an empty `GITHUB_BASE_REF`, so a pull-request lane can
never be captured by it). The group head is the commit the queue is about to
make `main`'s tip — empirically the very commit that lands: the queue merge
commits observed on `main` (#532 `d873d71`, #525 `f40faec`, #533 `6f8fecf`)
are two-parent merges of (main tip, pull-request head) whose subject is
`<pull request title> (#<number>)`. Judging the walk there means an
unrecorded substantive merge **fails the group's own checks and never reaches
`main`** — the failure surfaces on the pull request's queue entry, where the
automation can act on it, instead of on `main` after the damage.

**2. The walk accepts the recorded landing.** Judging the queue alone would
block every substantive pull request forever — pre-merge, no pin can name the
landing commit that does not exist yet. The closure is the landing's own
shape: the merge machinery stamps every landing commit with the pull request's
title and a trailing `(#<number>)`, and `ROADMAP-HISTORY.md` rows carry exactly
that number. A walk subject is now accepted when it is (a) the subject of a
MERGE commit of the walk — gathered by a second `git log --merges` pass, so a
plain commit spelled with the suffix cannot piggyback — and (b) its number
carries a history row **in the tree under validation**. The ledger is read by
the gate from the report's `ROADMAP-HISTORY.md` alone (`RoadmapHistoryRows::
pullRequests()`; numberless rows contribute nothing — 0 is not a pull-request
number).

The resulting contract (now rule 13 in `CONTRIBUTING.md`): **a substantive
pull request carries its own record** — the history row and the pin refresh
ride in the pull request itself, pinning to its own last substantive commit —
and the queue merges it with the row already present, so the walk from the
refreshed pin sees only bookkeeping plus the recorded landing. `main` goes
green **at the moment of the merge**, with no window. The pull request that
ships this change follows the contract itself and is its first demonstration:
its row rides in its own branch, its pin names its own code commit, and both
the group's checks and the post-merge push lane pass on the first try.

### Squash-method caveat (recorded deliberately)

The recorded-merge acceptance keys on merge commits (`--merges`), because the
queue's landing method today is the merge commit. A future switch to the
squash method would leave every substantive landing single-parent and refused
by this rule — an intentional, visible failure that forces the rule to be
revisited consciously at the moment the method changes, rather than letting
the acceptance silently widen to any suffixed subject.

## The second red source: the coverage-bridge newest-run race

The SonarCloud bridge (`.github/workflows/sonarcloud.yml`) polls
`actions/workflows/ci-coverage.yml/runs?head_sha=<sha>&per_page=1` — the
NEWEST run only. But every queue merge produces **two** CI Coverage runs for
the same head sha: the merge_group run (the queue's validation) and the push
run (main advancing to that very commit). On 2026-10-22 the merge_group run
for `8dce4624` had succeeded at 15:07:35Z, but the push run — created one
second after the merge — stayed "queued" in the API for 43 minutes (a GitHub
status propagation lag; the platform's own job log showed completion at
15:07:35Z, the run's `completed_at` read 15:50:40Z). The bridge locked onto
the push run, waited out its 20-minute deadline, and failed closed on
evidence that had existed all along.

The bridge now accepts **any** completed successful run for the sha — the
newest successful one wins — because the sha pins the content: an artifact
produced for that head sha is valid coverage evidence regardless of which
event produced it. It still fails closed when no run for the sha completes
successfully within the bound. The polling window, the artifact retry, and
the Clover validation are unchanged.

## Why this is a mechanism change and not another healing dance

The 2026-10-22 sessions healed each occurrence (pin-only pull requests #523,
#524, #534, #541) — correct, but each healing leaves the *generator* of the
incident untouched: the next substantive merge without its record reddens
`main` again, forever. The judgement of the ordering belongs where the
ordering is decided. With the queue judging the walk and the recorded landing
accepted, the automation's own rhythm — open pull request, arm auto-merge,
queue, merge — now *requires* the record to ride along, and `main` cannot
receive an unaccounted substantive commit through any automation path. The
user's framing was the design brief: changing the result without changing the
mechanism guarantees recurrence; this changes the mechanism.

## Evidence anchors

- Incident A: CI Static main-lane failure, run 39801758948, `8dce4624`, 15:06Z
  ("pin `6f8fecf` is stale: 2 commit(s) ... starting with
  `docs(contributing) (#537)`"); healed by #541 at the result level only.
- Incident B: SonarCloud job 119904052956, `8dce4624`, 15:27Z (bridge
  deadline); merge_group coverage run succeeded 15:07:35Z; run
  `completed_at` 15:50:40Z (43-minute lag).
- Landing shapes measured from `main`: `d873d71` (#532), `f40faec` (#525),
  `6f8fecf` (#533) — two parents, subject `<title> (#<number>)`.
- Queue ref shape: `refs/heads/gh-readonly-queue/main/pr-<N>-<sha>`
  (documented in `.github/workflows/ci-static.yml`, verified by the
  per-entry Sonar branch derivation).
