<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use LogicException;

/**
 * What the failure regime decided about one failed delivery: either
 * the retry entry it becomes (with its backoff ready-at already
 * answered), or the dead letter it earned -- and, when the letter
 * earns it, the quarantine verdict for the listener that failed. The
 * two shapes are pinned by the two named constructors; a verdict is
 * read through the predicate the drain spells, never guessed at.
 */
final readonly class FailureVerdict
{
    private function __construct(
        private ?DeferredDelivery $deferredDelivery,
        private ?DeadLetter $deadLetter,
        private bool $quarantines,
    ) {
    }

    /**
     * The verdict of a delivery that still has attempts left: the
     * retried entry, due no earlier than the backoff answered.
     */
    public static function retry(DeferredDelivery $deferredDelivery): self
    {
        return new self($deferredDelivery, null, false);
    }

    /**
     * The verdict of a delivery that spent its last attempt: the dead
     * letter it earned, and whether the listener's attribution just
     * reached the quarantine threshold.
     */
    public static function death(DeadLetter $deadLetter, bool $quarantines): self
    {
        return new self(null, $deadLetter, $quarantines);
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

    /**
     * Whether the listener's attributed dead letters just reached the
     * quarantine threshold -- the drain quarantines the listener.
     */
    public function quarantines(): bool
    {
        return $this->quarantines;
    }
}
