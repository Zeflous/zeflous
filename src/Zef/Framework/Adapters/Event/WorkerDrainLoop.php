<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use Zef\Framework\Event\DurableEventDispatcher;

/**
 * The worker-adapter drain loop: the persistent worker's idle
 * drain and its shutdown sentinel -- KDD-DU12's first named
 * follow-up, KDD-EA1's own named consumer ("the persistent
 * worker's idle loop is the named future consumer -- the
 * worker-adapter lane"). The loop OWNS the discipline: one drain
 * pass per now the host's schedule hands, the sentinel checked
 * between passes, the stop answered honestly. The loop never owns
 * the clock: no sleep, no cadence, no "when should the host call
 * drain" policy -- the schedule port carries the host's time, and
 * a deployment hands it from whatever its runtime means by idle.
 *
 * The arm the loop drives is the durable facade the host asked
 * its dispatcher for ({@see DurableEventDispatcher}, answered by
 * {@see \Zef\Framework\Event\EventDispatcher::durableDelivery()}),
 * composed one-way exactly as KDD-DU11 spells: the loop adds no
 * drain arithmetic of its own -- the pass is the facade's own
 * {@see DurableEventDispatcher::drain()} verbatim, the KDD-EA9
 * one-pass doctrine composed, never duplicated. A web host that
 * only wants to drain after the response still calls that verb
 * itself (KDD-EA1's other consumer); this loop is the spelling for
 * the process whose idle time IS the delivery schedule.
 *
 * Each {@see self::run()} is its own discipline: the counting
 * starts at zero, the sentinel is read as it stands (a loop run
 * against an armed sentinel drains nothing), and two runs over one
 * loop are two honest answers, never one accumulated one.
 *
 * @api host applications and worker-runtime glue compose this loop
 *      over their durable dispatcher and run it from their idle
 *      path
 */
final readonly class WorkerDrainLoop
{
    public function __construct(
        private DurableEventDispatcher $durableEventDispatcher,
        private WorkerDrainSentinel $workerDrainSentinel,
    ) {
    }

    /**
     * Runs the idle drain: asks the host's schedule for the next
     * pass's now and drains one pass at it, again and again, until
     * the schedule ends its own handing or the sentinel is armed.
     *
     * The sentinel is read at the TOP of every iteration -- the
     * boundary between passes (an armed loop never blocks on a
     * schedule it will not use, and a stop that arrives anywhere
     * else is observed at the very next boundary). A now the
     * schedule hands is one pass of the host's own authority: the
     * loop drains it and observes any arm that landed meanwhile at
     * the next boundary, so at most ONE pass runs past an arm --
     * bounded, and safe, because the store's at-least-once protocol
     * owns everything a pass touches. A pass in flight is never
     * interrupted: it completes under its own per-delivery
     * isolation, exactly as the store's settlement ordering owns
     * its windows.
     *
     * The schedule's null ending and the sentinel can tell the
     * truth about one exit together -- a stop the schedule absorbed
     * into its own ending (the idle wait that woke to a shutdown
     * and had nothing more to hand) -- and the exit's cause is read
     * from the sentinel's own state at that moment: an armed
     * sentinel is the host's stop however it surfaced, and a quiet
     * sentinel beside a null answer is the schedule completing the
     * host's idling.
     */
    public function run(WorkerDrainSchedule $workerDrainSchedule): WorkerDrainOutcome
    {
        $passes = 0;
        $lastOutcome = null;

        while (!$this->workerDrainSentinel->isArmed()) {
            $now = $workerDrainSchedule->nextNow();

            if ($now === null) {
                return new WorkerDrainOutcome($passes, $this->workerDrainSentinel->isArmed(), $lastOutcome);
            }

            $lastOutcome = $this->durableEventDispatcher->drain($now);
            ++$passes;
        }

        return new WorkerDrainOutcome($passes, true, $lastOutcome);
    }
}
