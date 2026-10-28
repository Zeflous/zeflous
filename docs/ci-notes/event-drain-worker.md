# Event Drain Worker — The Idle Drain, The Schedule Port, The Shutdown Sentinel

KDD-DW1..DW7 for `src/Zef/Framework/Adapters/Event/` — the
Adapters lane's second slice over the Event arm, KDD-DU12's first
named follow-up ("the worker-adapter drain loop -- the persistent
worker's idle drain + the shutdown sentinel; owns the loop this
arm feeds") and KDD-EA1's own named consumer ("the persistent
worker's idle loop is the named future consumer -- the
worker-adapter lane"): the loop's discipline, the host's time as
a port, and the one-way stop signal, spelled so the deployment's
glue composes all three from whatever its runtime means by idle.
`feat/event-drain-worker`.

**Verified locally before the PR:** `ci:static` EXIT-0 (5858
tests / 14267 assertions; smoke 20000 at 2760 bytes; concurrency
23 workflows; audit 0 advisories) · psalm 0 errors (baseline
SHRINKS: `DurableEventDispatcher::drain()` gained its first src
consumer and left the baseline, the #298/#303 lineage continued)
· progpilot `[]` · scoped infection over the slice's three
mutable files, 14 mutants, MSI 100/100, 0 escapes · archeology 0
error-level findings (the loop at difficulty 11.00, the sentinel
2.00, the outcome 0.90 -- every fold under the 20 ceiling with
room to spare) · coverage 98.43% whole-tree with the new folds at
100% (12/12 lines the loop, 2/2 the sentinel, 4/4 the outcome).

## The map

| Unit | Question it owns |
|---|---|
| `WorkerDrainSentinel` | whether the host has told the loop to stop (the arm-once one-way signal) |
| `WorkerDrainSchedule` | when the next pass runs (the host's own time, handed one ask at a time) |
| `WorkerDrainLoop` | the loop's discipline: one pass per handed now, the stop observed at every boundary |
| `WorkerDrainOutcome` | what one whole run answered (the passes, the stop's cause, the last pass's own counts) |

Three of the four are new spellings; the fourth question -- what
one pass answers -- is the Event lane's own `DrainOutcome`,
carried whole and never re-derived, exactly as the loop composes
the facade's own drain verb rather than growing a second
arithmetic.

## KDD-DW1 — The loop is ours; the schedule is the host's

KDD-DU12's deliberately-absent drain-scheduling policy ("the host
owns time, EA4 carried over, and therefore owns the schedule"),
spelled as the port it always was: `WorkerDrainSchedule` owns
WHEN, one `nextNow(): ?int` per pass, answered by the deployment's
glue from whatever its runtime means by idle -- the RoadRunner
idle callback's tick, the Swoole timer's interval, a CLI loop's
sleep -- while the loop owns the discipline that runs between the
asks. The loop carries no clock, no sleep, no cadence: a
`run(schedule)` that invented its own ticking would be the policy
the ledger refused, wearing a port's clothes. Null ends the
schedule itself -- "no further idle moments exist" -- which is one
of the run's two exits and leaves every pending entry exactly
where the at-least-once protocol keeps it, waiting for the next
process. The ask is impure by declaration (`@phpstan-impure`): it
is the host's own hook, free to block, read a clock, observe a
runtime, or arm the very sentinel the loop hands around -- no
caller may assume an ask leaves any state it read untouched.

## KDD-DW2 — The sentinel is arm-once, observed between passes, and costs nothing

KDD-EA7's worker-adapter reading ("a shutdown sentinel for the
worker loop -- belongs to the worker-adapter lane, which owns the
loop"), spelled as the vocabulary the deployment shares between
its stop hook and its drain loop. Arm-only on purpose: a stop that
could un-stop is a race the vocabulary refuses, so there is no
disarm and no reset -- a host that wants a fresh loop arms a fresh
sentinel, and arming twice is the same stop, exactly as settling a
settled entry is a no-op in the store's own protocol. The stop
costs nothing by declaration: an armed sentinel leaves every
un-drained entry pending (DU1's at-least-once owns the survival),
so the signal is a liability no host must weigh before pulling --
and an armed loop never even asks the schedule (the armed-before-
run run answers zero passes with zero asks, pinned by test).

## KDD-DW3 — A boundary observation, honestly bounded

The sentinel is read at the TOP of every iteration -- the boundary
between passes -- and never mid-instruction: a signal handler can
arm the sentinel between ANY two statements of any spelling, so
"stop means stop" at instruction granularity is a pretension every
honest loop refuses. What the discipline guarantees instead is
bounded: at most ONE pass runs past an arm (the arm that lands
anywhere inside an iteration -- while the schedule was handing its
answer, or mid-pass inside a listener's delivery -- is observed at
the very next boundary, pinned by both windows' tests), a pass in
flight always completes under its own per-delivery isolation
(KDD-EA2, the mid-pass arming test observing both pool listeners
deliver), and the one pass that may run past an arm is the
schedule's own handed now -- the host's authority, one pass of it,
never the loop's. A now the schedule hands in good faith is
drained; the schedule that wants no such final pass hands null,
which is its own way to end the run.

## KDD-DW4 — The exit's cause is read from the sentinel's own state

The run's two exits are spelled as one question answered at the
exit: `$sentinel->isArmed()` read in the return itself. A quiet
sentinel beside a null answer is the schedule completing the
host's idling (`stoppedBySentinel() === false`); an armed sentinel
is the host's stop however it surfaced -- armed before the first
ask, armed while the schedule was handing a real now, or armed
inside the schedule's own null ending (the idle wait that woke to
a shutdown and had nothing more to hand: the schedule absorbed the
stop into its ending, and the run still names the sentinel's state
at that exit, pinned by the nullable-now test). The outcome
carries no third state, no "both" spelling: the sentinel's own
reading at the exit IS the honest answer, and the host that armed
it knows both facts without the ledger duplicating them.

## KDD-DW5 — The facade composed verbatim, never duplicated

The loop drives `DurableEventDispatcher` -- the facade
`EventDispatcher::durableDelivery()` answers, the one host-reachable
spelling of the durable arm (the engine it wraps is constructed
inside the composition, with the dispatcher's own event book, so
the facade is what every deployment actually holds). The loop adds
no drain arithmetic of its own: the pass is the facade's own
`drain(now)` verbatim, KDD-EA9's one-pass doctrine composed rather
than duplicated, and the outcome's `lastOutcome()` is that pass's
own `DrainOutcome` carried whole -- null when no pass ran, because
a run that drained nothing has no store reading and the outcome
says so instead of inventing one. The aggregate story -- every
pass's counts summed and timestamped -- is deliberately NOT here:
traceability over drain outcomes is the named follow-up KDD-EA12/
DU12 both spell, and the counts are its seam. A web host that only
wants to drain after the response still calls the facade's verb
itself (KDD-EA1's other consumer); this loop is the spelling for
the process whose idle time IS the delivery schedule. Each
`run()` is its own discipline -- counting from zero, the sentinel
read as it stands -- so two runs over one loop are two honest
answers, never one accumulated one.

## KDD-DW6 — The analyser's model and the mutable read

The first spelling re-read the sentinel after every ask as a guard
condition, and PHPStan refused it twice over: the after-ask guard
read "always false" (the analyser's sound model -- a pure property
read cannot change without a write it can see, and it cannot see
the schedule's side effect), while marking the read impure -- the
analyser's own hint -- tripped the opposite lint (`impureMethod.
pure`: marked impure, no side effects in the body). A minimal
reproduction confirmed the deadlock is the tool's model of
read-only mutable state, not this code's shape: impure calls on
OTHER objects do not invalidate a remembered pure read. The exit
is not an exclusion (the ruleset is closed) and not an annotation
the linter refutes, but the honest granularity KDD-DW3 spells
anyway: the sentinel read ONCE per iteration, at the boundary
where the doctrine always observed it, and the exit's cause read
from the sentinel in the return expression -- where the analyser
carries no remembered guard to contradict and no rule fires. The
restructure is the same lesson the difficulty ceiling taught the
durable slice: when two gates pull one spelling in opposite
directions, the seam moves -- the code re-spelled, never the gate
weakened. One baseline line SHRINKS beside it:
`DurableEventDispatcher::drain()` gained its first src consumer
and left `psalm-baseline.xml`, the #298/#303 lineage continued.

## KDD-DW7 — Named follow-ups (the honest scope ledger)

- **The cross-process bus answers** -- Redis/RabbitMQ/Kafka over
  the same `DeferredDeliveryStore` port (DU12's second bullet),
  with the claim semantics and the indexes the file adapter's
  honesty ledger names; a deployment's queue worker composes them
  under this same loop.
- **Durable quarantine and durable traceability** -- the
  process-local books re-spelled over the store; the drain
  outcome's five counts are the seam, and this slice's
  `lastOutcome()` hands the run's freshest reading to exactly that
  fold.
- **The `queue/` storage lane + the module `events` knob** -- the
  wired default and its configuration reader, landing with the
  module system's own slice (DU10's census-moment doctrine).
- **A drain-scheduling policy stays deliberately absent** (DU12's
  own closing bullet, carried verbatim): the schedule port is the
  placeholder-shaped answer -- the host's to fill, never the
  lane's to default.
