<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use Override;
use Zef\Framework\Event\DeferredDeliveryStore;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DrainTrace;
use Zef\Framework\Event\ListenerIdentity;

/**
 * The in-memory stand-in for the durable store: the port's verbs
 * answered by arrays, with an ordered action ledger a test
 * reads to pin the PROTOCOL (the write-the-answer-then-settle-the-
 * question ordering, the read-only take, the books' write ordering)
 * independently of any adapter's file mechanics. The at-least-once
 * doctrine is spelled honestly here too: takeDue reads, settle
 * removes, an entry taken but not settled is re-taken, and an
 * attribution redelivered is the idempotent no-op its entry key
 * makes it.
 *
 * @internal
 */
final class InMemoryDeliveryStore implements DeferredDeliveryStore
{
    /** @var array<string, DeliveryEnvelope> the pending entries, keyed by entry id */
    private array $pending = [];

    /** @var array<string, string> the attributed deaths, keyed by entry id, valued by identity */
    private array $attributed = [];

    /** @var array<string, int> the quarantined identities, keyed by identity, valued by the quarantine moment */
    private array $quarantined = [];

    /** @var list<DrainTrace> the drain journal, in the order the passes completed */
    private array $drainTraces = [];

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

    #[Override]
    public function recordAttributedDeath(
        DeliveryEntryId $deliveryEntryId,
        ListenerIdentity $listenerIdentity,
        int $attributedAt,
    ): void {
        $this->attributed[$deliveryEntryId->spelling()] = $listenerIdentity->spelling();
        $this->actions[] = 'attributed:' . $deliveryEntryId->spelling()
            . ':' . $listenerIdentity->spelling() . ':' . $attributedAt;
    }

    #[Override]
    public function deadLettersAttributedTo(ListenerIdentity $listenerIdentity): int
    {
        $attributed = 0;

        foreach ($this->attributed as $identity) {
            if ($identity !== $listenerIdentity->spelling()) {
                continue;
            }

            ++$attributed;
        }

        return $attributed;
    }

    #[Override]
    public function quarantineListener(
        ListenerIdentity $listenerIdentity,
        int $quarantinedAt,
    ): void {
        $this->quarantined[$listenerIdentity->spelling()] = $quarantinedAt;
        $this->actions[] = 'quarantine:' . $listenerIdentity->spelling() . ':' . $quarantinedAt;
    }

    #[Override]
    public function isListenerQuarantined(ListenerIdentity $listenerIdentity): bool
    {
        return isset($this->quarantined[$listenerIdentity->spelling()]);
    }

    #[Override]
    public function recordDrainOutcome(DrainTrace $drainTrace): void
    {
        $this->drainTraces[] = $drainTrace;
        $this->actions[] = 'drain:' . $drainTrace->drainedAt()
            . ':' . $drainTrace->delivered()
            . '/' . $drainTrace->retried()
            . '/' . $drainTrace->deadLettered()
            . '/' . $drainTrace->quarantined()
            . '/' . $drainTrace->pending();
    }

    /**
     * The ordered action ledger -- the protocol trace a test pins
     * orderings against ("requeue BEFORE settle", "dead BEFORE
     * settle", "attributed BEFORE dead", "the drain trace LAST").
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

    /**
     * The attributed deaths, keyed by entry id and valued by the
     * listener's identity -- a test's direct inspection seam over
     * the durable attribution book.
     *
     * @return array<string, string>
     */
    public function attributed(): array
    {
        return $this->attributed;
    }

    /**
     * The quarantined identities, keyed by identity and valued by
     * the quarantine moment -- a test's direct inspection seam over
     * the durable quarantine book.
     *
     * @return array<string, int>
     */
    public function quarantined(): array
    {
        return $this->quarantined;
    }

    /**
     * The drain journal, in the order the passes completed -- a
     * test's direct inspection seam over the traceability records.
     *
     * @return list<DrainTrace>
     */
    public function drainTraces(): array
    {
        return $this->drainTraces;
    }
}
