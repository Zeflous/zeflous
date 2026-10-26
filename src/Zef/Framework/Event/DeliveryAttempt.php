<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use Throwable;

/**
 * The attempt boundary: one queued delivery, one listener call, the
 * failure answered instead of thrown. This is the fold the drain
 * pass's own difficulty ceiling forced out -- attempting a delivery
 * and steering the pass are two questions, two seams -- and it is
 * where the per-delivery failure regime (KDD-EA2) physically lives:
 * the catch wraps ONE entry's listener call, never the pass.
 */
final class DeliveryAttempt
{
    /**
     * Runs one delivery's listener call, answering the failure it
     * threw -- or null when the listener took the event cleanly.
     */
    public function run(DeferredDelivery $deferredDelivery): ?Throwable
    {
        try {
            $deferredDelivery->listener()->handle($deferredDelivery->event());
        } catch (Throwable $throwable) {
            return $throwable;
        }

        return null;
    }
}
