<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

/**
 * The host's own schedule for the worker drain loop: when the
 * next drain pass runs. KDD-DU12's deliberately-absent
 * drain-scheduling policy, spelled as the port it always was --
 * "the host owns time (EA4 carried over) and therefore owns the
 * schedule", so the loop owns the discipline and THIS port owns
 * the clock: the deployment's glue answers it with whatever its
 * runtime means by idle (the RoadRunner idle callback's tick, the
 * Swoole timer's interval, a CLI loop's sleep), and the loop never
 * learns which.
 *
 * The answer is the drain's own time parameter (KDD-EA4): a plain
 * integer "now" handed one pass at a time, never a clock the lane
 * could grow on. Null ends the schedule itself -- "no further
 * idle moments exist" -- which is one of the loop's two exits (the
 * sentinel is the other); a schedule that ends while entries stay
 * pending leaves them exactly where the at-least-once protocol
 * keeps them, waiting for the next process.
 *
 * A schedule that blocks between answers carries its own honest
 * obligation: it must wake when the host stops. The loop checks
 * the sentinel before every ask and after every ask, but it cannot
 * reach inside a blocked ask -- the schedule that parks the worker
 * in its runtime's wait must observe the same stop the sentinel
 * spells (the host holds both, which is exactly why both are the
 * host's). The ask itself is impure by declaration: it is the
 * host's own hook, free to block, read a clock, observe its
 * runtime, or arm the very sentinel the loop hands around.
 *
 * @api host applications and worker-runtime glue implement this
 *      port from their idle path and hand it to
 *      {@see WorkerDrainLoop::run()}
 */
interface WorkerDrainSchedule
{
    /**
     * The now the next drain pass runs at -- the host's own time,
     * handed one pass at a time -- or null when the schedule
     * itself is done and no further pass should be asked for.
     *
     * @phpstan-impure the ask is the host's own hook: it may block
     *                 in a runtime's idle wait, read a clock,
     *                 observe a shutdown, or arm the sentinel
     *                 itself -- no caller may assume an ask leaves
     *                 any state it read untouched
     */
    public function nextNow(): ?int;
}
