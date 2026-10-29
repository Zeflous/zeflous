<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use LogicException;

/**
 * What the failure regime decided about one failed delivery: either
 * the retry entry it becomes (with its backoff ready-at already
 * answered), or the dead letter it earned. The two shapes are pinned
 * by the two named constructors; a verdict is read through the
 * predicate the drain spells, never guessed at.
 *
 * The verdict deliberately carries no quarantine verdict: the
 * crossing is the BOOK's own read, taken after the letter is
 * appended (or attributed) -- a judgment that guessed at state it
 * had not yet written would count a letter the book may already
 * hold, and the at-least-once re-drain of a crashed pass would
 * cross the threshold one death early. The judgment owns the
 * arithmetic; the book owns the count.
 */
final readonly class FailureVerdict
{
    private function __construct(
        private ?DeferredDelivery $deferredDelivery,
        private ?DeadLetter $deadLetter,
    ) {
    }

    /**
     * The verdict of a delivery that still has attempts left: the
     * retried entry, due no earlier than the backoff answered.
     */
    public static function retry(DeferredDelivery $deferredDelivery): self
    {
        return new self($deferredDelivery, null);
    }

    /**
     * The verdict of a delivery that spent its last attempt: the dead
     * letter it earned.
     */
    public static function death(DeadLetter $deadLetter): self
    {
        return new self(null, $deadLetter);
    }

    /**
     * Whether the verdict is a retry -- the drain requeues the entry.
     */
    public function isRetry(): bool
    {
        return $this->deferredDelivery instanceof DeferredDelivery;
    }

    /**
     * The retried entry -- meaningful only under {@see isRetry()}.
     */
    public function retryEntry(): DeferredDelivery
    {
        return $this->deferredDelivery ?? throw new LogicException(
            'The verdict is not a retry; read it through isRetry().',
        );
    }

    /**
     * The dead letter -- meaningful only under a death verdict.
     */
    public function deadLetter(): DeadLetter
    {
        return $this->deadLetter ?? throw new LogicException(
            'The verdict is not a death; read it through isRetry().',
        );
    }
}
