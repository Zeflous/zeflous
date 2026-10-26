<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The poison-pill answer (KDD-EA7): the book of listeners whose
 * deliveries kept dying -- a listener whose attributed dead
 * letters reached the threshold -- and whom every FUTURE defer
 * therefore skips. Quarantine is containment of future admission,
 * never retroactive destruction: already-admitted entries still
 * deliver, because a quarantined listener's queued work may yet
 * succeed and silently dropping admitted work would be a
 * retraction this vocabulary refuses.
 *
 * The book never lifts a quarantine on its own -- the evidence
 * that earned it stays in the dead-letter book, and restoring a
 * listener is the host's own judgment, spelled by constructing a
 * fresh regime.
 */
final class ListenerQuarantine
{
    /** @var list<EventListener> */
    private array $quarantined = [];

    /**
     * Quarantines a listener -- the drain fold's act when the
     * listener's attributed dead letters reached the threshold.
     */
    public function quarantine(EventListener $eventListener): void
    {
        if ($this->isQuarantined($eventListener)) {
            return;
        }

        $this->quarantined[] = $eventListener;
    }

    /**
     * Whether the listener is quarantined -- the admission filter
     * every future defer reads.
     */
    public function isQuarantined(EventListener $eventListener): bool
    {
        return \in_array($eventListener, $this->quarantined, true);
    }

    /**
     * How many listeners the book holds.
     */
    public function count(): int
    {
        return \count($this->quarantined);
    }
}
