<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * What one defer answered with: the event's name, how many pool
 * listeners were admitted as pending deliveries, and how many
 * pool listeners were skipped because they are quarantined --
 * the deferred arm's own admission report, the twin
 * {@see DispatchOutcome} never needed (a synchronous dispatch
 * admits nothing; it delivers).
 */
final readonly class DeferralOutcome
{
    public function __construct(
        private EventName $eventName,
        private int $admitted,
        private int $skippedQuarantined,
    ) {
    }

    /**
     * The name the defer was addressed by.
     */
    public function name(): EventName
    {
        return $this->eventName;
    }

    /**
     * How many pool listeners became pending deliveries -- the
     * pool snapshot's size after the quarantine filter, zero
     * included.
     */
    public function admitted(): int
    {
        return $this->admitted;
    }

    /**
     * How many pool listeners the quarantine filter skipped --
     * listeners this regime has already given up on, named here so
     * a silent non-delivery can never hide in a green count.
     */
    public function skippedQuarantined(): int
    {
        return $this->skippedQuarantined;
    }
}
