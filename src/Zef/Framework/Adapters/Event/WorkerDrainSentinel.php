<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

/**
 * The worker drain loop's shutdown sentinel: the one-way stop
 * signal the host's own shutdown path arms and the loop checks
 * between passes -- KDD-EA7's own reading ("a shutdown sentinel
 * for the worker loop -- belongs to the worker-adapter lane,
 * which owns the loop"), spelled as the vocabulary the deployment
 * shares between its stop hook and its drain loop.
 *
 * The signal is ARM-ONLY on purpose. A stop that could un-stop is
 * a race the vocabulary refuses: once the host's shutdown path has
 * said stop, every later reader must read the same answer, so
 * there is no disarm verb and no reset -- a host that wants a
 * fresh loop arms a fresh sentinel. Arming twice is the same stop
 * (idempotent), exactly as settling a settled entry is a no-op in
 * the store's own protocol.
 *
 * The stop costs nothing by declaration: the store's at-least-once
 * protocol (KDD-DU1) owns the survival, so an armed sentinel
 * leaves every un-drained entry exactly where it was -- pending,
 * waiting for the next process's pass. A stop that lost work would
 * make the signal a liability no host would dare pull.
 *
 * @api host applications and worker-runtime glue arm this signal
 *      from their shutdown path and hand it to
 *      {@see WorkerDrainLoop}, whose passes it ends
 */
final class WorkerDrainSentinel
{
    private bool $armed = false;

    /**
     * Arms the stop signal -- the one-way verb the host's shutdown
     * path calls (the SIGTERM handler, the runtime's stop callback,
     * the CLI's interrupt). Idempotent: arming an armed sentinel is
     * the same stop.
     */
    public function arm(): void
    {
        $this->armed = true;
    }

    /**
     * Whether the host has told the loop to stop -- the answer the
     * loop reads between passes, never inside one (KDD-DW2: a pass
     * in flight completes; the per-delivery isolation and the
     * store's settlement ordering own everything a pass touches).
     * The answer is mutable state a host's shutdown path -- or the
     * schedule it was asked through -- may change between any two
     * reads, which is why the loop asks again after every ask.
     */
    public function isArmed(): bool
    {
        return $this->armed;
    }
}
