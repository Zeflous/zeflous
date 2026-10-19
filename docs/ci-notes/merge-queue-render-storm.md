# CI note: the merge-queue self-re-render storm (2026-10-19)

Date: 2026-10-19
Status: fixed (this pull request); the maintainer re-enables the queue from the
ruleset side when ready — no settings change is required for the fix to hold.
Scope: `.github/workflows/ci-strict.yml`, `.github/workflows/auto-merge.yml`,
the `main` ruleset's merge queue (read-only evidence; its configuration is
unchanged and remains valid).

This note records the post-mortem of the symptom the maintainer reported as
"when the merge queue is active and every CI gate has passed, the merge stays
blocked; disable the queue and merges work again". Every claim below was
measured against live GitHub data (workflow runs, check-runs, ruleset version
history, sweep logs), not inferred from the workflow files alone.

## The symptom, measured

- 2026-10-19 00:00-02:15 UTC: the queue entries for PRs #449 and #457 were
  re-synthesised at least fourteen times (queue refs
  `gh-readonly-queue/main/pr-449-...` / `pr-457-...`, each re-render = a NEW
  group head sha and a full restart of all eight lane runs on it).
- During that window: `main` did not advance, no pull-request head branch was
  pushed (zero `synchronize` events on either PR), no entry was added by a
  human — the only writers touching the queued pull requests were the Auto
  Merge sweep's `gh pr merge --auto` calls (measured no-ops: no
  `auto_merge_enabled` timeline events after 22:21:52Z) and the CI Strict
  aggregator's check-run writes.
- The abandoned synthesis `27b0e6548c` (built 02:11:55) reached **all green on
  every check** — `CI Strict`, `PR Validator`, `PhpCodeArcheology SARIF`,
  `SonarCloud analysis`, both SonarCloud contexts, all four lanes — at
  02:24:10 and was still never merged: the queue had already re-rendered past
  it at 02:15:15. A green-but-superseded run keeps its `success` conclusion in
  the UI, which is exactly why the queue *looked* permanently green while
  being permanently blocked.
- The storm ended only by luck of timing: the maintainer's 02:04:28Z ruleset
  re-save (identical parameters — a delete/re-add of the `merge_queue` rule)
  restarted the queue; the 02:05:18 synthesis survived to certification
  (#449 merged 02:17:39, #457 02:25:56) because the next amplifier events
  landed after the entries had certified. The queue was switched off at
  03:49:48Z; PRs #458/#459/#460 then merged queue-less within the hour.

## Root cause 1 — the event-driven aggregator wrote check-runs on merge-group heads

The aggregator's `workflow_run` trigger fired for EVERY lane completion,
including lanes running on merge-group refs. A lane run on a merge group
reports its head branch as `gh-readonly-queue/main/pr-N-...`, and the
event-driven aggregator resolved that run's `head_sha` — the group head — and
wrote the required `CI Strict` check-run on it from OUTSIDE the `merge_group`
path, while CI Infection (the slowest lane, ~9-10 minutes) was still running.

The measured correlation, three for three in the final storm phase:

| Fast lane completes on the group | Aggregator write (group head) | Queue re-render |
| --- | --- | --- |
| 02:07:52 (CI Static, synthesis 7be40f3720) | ~02:08:15 | **02:08:38** (new head 20d9a5ae80) |
| 02:11:04 (CI Static, synthesis 20d9a5ae80) | ~02:11:25 | **02:11:55** (new head 27b0e6548c) |
| 02:14:16 (CI Static, synthesis 27b0e6548c) | ~02:14:45 | **02:14:58/02:15:15** (new head 959685bfe3 — the survivor) |

Each re-render superseded every in-flight lane run, restarting the ~10-minute
certification clock. The storm period at its peak was ~3 minutes — below the
certification time by a factor of three — so the group could never certify.
The queue-side mechanism that maps "required-check activity on a group head"
to "re-synthesise the group" is GitHub-internal and not directly observable;
what IS observable is that (a) the writes and the re-renders alternate with a
constant 40-60 second offset, (b) nothing else in the repository wrote to the
group heads, and (c) the one synthesis whose fast-lane completion did not
produce a re-render (02:17:46, on the survivor) is the one where the
below-entry had just certified and merged (02:17:39), taking the batch past
its rebuild window.

**Fix (`ci-strict.yml`):** the `workflow_run` trigger now carries
`branches-ignore: ['gh-readonly-queue/**']`, so event-driven firings for
merge-group lane completions never start. The `merge_group` polling run is the
sole owner of a group head's verdict and writes it exactly once — the final
conclusion, well inside the ruleset's 90-minute check-response timeout. An
in-script fail-safe guard re-checks `workflow_run.head_branch` and exits
without a write if a filter regression ever lets one through. A second,
orthogonal guard never RE-OPENS a concluded verdict on any head: a
still-pending aggregation pass can no longer flip a `completed` check-run back
to `in_progress` (the regression shape the storm was made of), while a pass
carrying a definite conclusion still refreshes it — a re-run lane that went
red must be able to turn the recorded verdict red, and a fixed one green.

## Root cause 2 — the event cascade amplified the storm into a 1-2 minute beat

Every beat of the storm fanned out through `workflow_run` chains that were all
designed for other purposes:

```
merge-group lane completes
  -> CI Strict aggregator run (default-branch context, head_branch = main)
     completes
       -> changelog lanes fire (their `branches: [main]` filter matches the
          AGGREGATOR run's branch, not the merge group's) and their job guard
          correctly skips them -- but a skipped run is still "completed"
          -> Auto Merge sweep fires (trigger: changelog lanes completed,
             skipped completions included)
             -> the sweep processes the queued pull requests (no-op arms,
                suspended syncs -- pure noise, but a full runner each)
```

The filter bypass is structural: a `workflow_run`-triggered run executes in
the context of the default branch, so its `head_branch` is always `main` —
downstream `branches: [main]` filters cannot see that the event originated on
a merge group. The changelog lanes' own job guards were already correct; the
cascade they could not stop was the sweep trigger.

**Fix (`auto-merge.yml`):** a `workflow_run` firing whose changelog run
concluded `skipped` now skips the sweep job outright — a skipped changelog run
means its guard found no validated `main` merge to react to, so no repository
state changed. Real changelog completions (success or failure) still sweep,
and the cron (7,27,47) plus the pull-request triggers keep the arming
coverage. Additionally the arm step never re-arms a pull request whose
`autoMergeRequest` is already set: an armed PR is GitHub's to finish (it queues
itself the moment its checks pass), and every redundant write against a queued
PR is one more chance to reset the queue's batch window. The 2026-10-18 fix
(#456) had already suspended the branch sync while the queue is active; the
arm was the sweep's last remaining touch on a queued PR.

## Why the queue itself was NOT misconfigured

The live ruleset version during the storm (history version 53738544, and its
identical predecessors) read: `merge_method: MERGE`, batch 1-5 entries,
`min_entries_to_merge_wait_minutes: 5`, `grouping_strategy: ALLGREEN`,
`check_response_timeout_minutes: 90` — a sane configuration whose 90-minute
timeout the storm never reached (entries re-rendered long before it). The
storm was repo-side event noise, not queue settings; the maintainer's five
on/off toggles (18:58Z, 19:43Z, 20:17Z, 22:18Z, 23:54Z ruleset versions) each
released the stuck entries exactly as disabling the queue always did, which is
why the queue looked like the culprit. No ruleset change ships with this fix;
the queue can simply be re-enabled.

## Verification checklist

- [x] Every timeline claim read from the live API: merge-group runs
      (`GET /actions/runs?event=merge_group`), check-runs on the abandoned
      (`27b0e6548c`) and surviving (`959685bfe3`) synthesis heads, the Auto
      Merge sweep logs (runs 39188515635 and 39189068495 — "branch sync
      suspended", "Auto-merge armed" against queued PRs), the PR timelines
      (`auto_merge_enabled` events), and the ruleset version history.
- [x] The corrupted-`branches:` red herring disproven by hexdump: the
      changelog filters on `main` are the clean bytes `branches: [main]`; the
      `ain]` reading was a terminal rendering artefact of the literal `[m`
      sequence, recorded here so nobody re-investigates it.
- [x] Changed YAML parses with PyYAML; the aggregator's embedded script passes
      `node --check`; the changed shell blocks pass `bash -n`.
- [x] No PHP source changed; no gate weakened — the strict pipeline's verdict
      logic is untouched (the aggregator still fails whenever a lane is not
      green); two self-interactions are removed, one event chain is damped.
- [ ] Post-merge (rule 12): re-enable the merge queue, queue one pull request,
      and confirm the entry certifies on its FIRST synthesis (one group head,
      one `CI Strict` write, merge within ~CI Infection + 2 minutes).
