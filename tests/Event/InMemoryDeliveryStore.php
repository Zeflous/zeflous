<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use Override;
use Zef\Framework\Event\DeferredDeliveryStore;
use Zef\Framework\Event\DeliveryEnvelope;

/**
 * The in-memory stand-in for the durable store: the port's six
 * verbs answered by arrays, with an ordered action ledger a test
 * reads to pin the PROTOCOL (the write-the-answer-then-settle-the-
 * question ordering, the read-only take) independently of any
 * adapter's file mechanics. The at-least-once doctrine is spelled
 * honestly here too: takeDue reads, settle removes, and an entry
 * taken but not settled is re-taken.
 *
 * @internal
 */
final class InMemoryDeliveryStore implements DeferredDeliveryStore
{
    /** @var array<string, DeliveryEnvelope> the pending entries, keyed by entry id */
    private array $pending = [];

    /** @var list<string> the ordered action ledger, the protocol's own trace */
    private array $actions = [];

    /**
     * @param int $capacity the admission bound (>= 1); retries are
     *                      never bound, exactly as the port spells
     */
    public function __construct(private readonly int $capacity = 64)
    {
    }

    #[Override]
    public function save(DeliveryEnvelope ...$envelopes): void
    {
        $demanded = \count($envelopes);

        if (\count($this->pending) + $demanded > $this->capacity) {
            throw new InvalidArgumentException(
                'The delivery store is full: capacity ' . $this->capacity
                . ', occupied ' . \count($this->pending) . ', demanded '
                . $demanded . ' -- drain before deferring more.',
            );
        }

        foreach ($envelopes as $envelope) {
            $this->pending[$envelope->entryId()->spelling()] = $envelope;
        }

        $this->actions[] = 'save:' . $demanded;
    }

    #[Override]
    public function requeue(DeliveryEnvelope $deliveryEnvelope): void
    {
        $this->pending[$deliveryEnvelope->entryId()->spelling()] = $deliveryEnvelope;
        $this->actions[] = 'requeue:' . $deliveryEnvelope->entryId()->spelling();
    }

    #[Override]
    public function takeDue(int $now): array
    {
        $due = [];

        foreach ($this->pending as $envelope) {
            if ($envelope->readyAt() > $now) {
                continue;
            }

            $due[] = $envelope;
        }

        usort(
            $due,
            static fn (DeliveryEnvelope $left, DeliveryEnvelope $right): int => [
                $left->readyAt(),
                $left->entryId()->spelling(),
            ] <=> [$right->readyAt(), $right->entryId()->spelling()],
        );

        return $due;
    }

    #[Override]
    public function settle(DeliveryEnvelope ...$envelopes): void
    {
        foreach ($envelopes as $envelope) {
            unset($this->pending[$envelope->entryId()->spelling()]);
            $this->actions[] = 'settle:' . $envelope->entryId()->spelling();
        }
    }

    #[Override]
    public function deadLetter(
        DeliveryEnvelope $deliveryEnvelope,
        string $failureClass,
        string $failureMessage,
        int $deadAt,
    ): void {
        $this->actions[] = 'dead:' . $deliveryEnvelope->entryId()->spelling()
            . ':' . $failureClass . ':' . $failureMessage . ':' . $deadAt;
    }

    #[Override]
    public function pendingCount(): int
    {
        return \count($this->pending);
    }

    /**
     * The ordered action ledger -- the protocol trace a test pins
     * orderings against ("requeue BEFORE settle", "dead BEFORE
     * settle").
     *
     * @return list<string>
     */
    public function actions(): array
    {
        return $this->actions;
    }

    /**
     * The pending entries, keyed by entry id -- a test's direct
     * inspection seam.
     *
     * @return array<string, DeliveryEnvelope>
     */
    public function pending(): array
    {
        return $this->pending;
    }
}
