<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * What one relay pass answered with: how many outbox rows the
 * pass relayed, how many delivery entries their defers admitted,
 * and how many pool listeners the quarantine filter skipped --
 * the transactional arm's own relay report, the twin
 * {@see DrainOutcome} spells for the drain. The three counts are
 * separate on purpose: a pass that relayed three rows admits a
 * DIFFERENT number of entries (one per surviving pool listener
 * per row), and a green relay count can still hide skipped
 * listeners -- the same silent-non-delivery honesty
 * {@see DeferralOutcome} carries, spelled at the pass's own
 * level.
 */
final readonly class OutboxRelayOutcome
{
    public function __construct(
        private int $relayed,
        private int $admitted,
        private int $skippedQuarantined,
    ) {
    }

    /**
     * How many outbox rows the pass relayed -- the rows whose
     * delivery entries landed and whose outbox rows the pass
     * removed.
     */
    public function relayed(): int
    {
        return $this->relayed;
    }

    /**
     * How many delivery entries the pass's defers admitted into the
     * durable store -- every surviving pool listener of every
     * relayed row.
     */
    public function admitted(): int
    {
        return $this->admitted;
    }

    /**
     * How many pool listeners the quarantine filter skipped across
     * the pass's defers -- the listeners the durable regime has
     * already given up on, named here so a silent non-delivery can
     * never hide in a green relay count.
     */
    public function skippedQuarantined(): int
    {
        return $this->skippedQuarantined;
    }
}
