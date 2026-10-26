<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The durable drain's settlement fold: how every answered entry
 * leaves the store -- the write-the-answer-then-settle-the-
 * question ordering in ONE seam, so a crash between the two costs
 * a duplicate, never a loss (the at-least-once declaration the
 * store's own port spells). A delivered entry settles alone; a
 * retried entry is requeued under its fresh identity BEFORE the
 * spent one settles; a dead-lettered entry's story is recorded
 * BEFORE the spent one settles. The fold owns the ORDERING only
 * -- which failure a spent entry became is the judgment's own
 * question, and the books (the dead-letter book, the quarantine)
 * stay the judgment's own state.
 */
final readonly class DurableSettlement
{
    public function __construct(private DeferredDeliveryStore $deferredDeliveryStore)
    {
    }

    /**
     * Finishes the store's business with a delivered entry.
     */
    public function settle(DeliveryEnvelope $deliveryEnvelope): void
    {
        $this->deferredDeliveryStore->settle($deliveryEnvelope);
    }

    /**
     * Finishes the spent entry by requeueing the retry that
     * replaced it -- the retry FIRST, the settle second: the
     * crash window between them costs a duplicate redelivery of
     * the spent entry, never the loss of the retry.
     */
    public function retry(DeliveryEnvelope $spent, DeliveryEnvelope $retried): void
    {
        $this->deferredDeliveryStore->requeue($retried);
        $this->deferredDeliveryStore->settle($spent);
    }

    /**
     * Finishes the spent entry by recording its whole story where
     * an operator can read it -- the story FIRST, the settle
     * second: the crash window between them costs the story being
     * re-judged on the next pass, never the entry vanishing
     * unrecorded.
     */
    public function dead(
        DeliveryEnvelope $deliveryEnvelope,
        string $failureClass,
        string $failureMessage,
        int $deadAt,
    ): void {
        $this->deferredDeliveryStore->deadLetter($deliveryEnvelope, $failureClass, $failureMessage, $deadAt);
        $this->deferredDeliveryStore->settle($deliveryEnvelope);
    }
}
