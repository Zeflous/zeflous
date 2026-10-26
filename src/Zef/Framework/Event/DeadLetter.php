<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The record of one delivery that exhausted its attempts: the
 * event's name, the listener that could not take it, the attempts
 * spent, the failure's class and message, and the dead-at moment
 * -- the drain's own now (KDD-EA4: time is a parameter, so the
 * record carries the only "when" the regime ever knew).
 *
 * A dead letter is evidence, not a work order: nothing in this
 * layer retries a dead letter, redelivers it, or ages it out --
 * reading the book and acting on it is the host's own judgment.
 */
final readonly class DeadLetter
{
    public function __construct(
        private EventName $eventName,
        private EventListener $eventListener,
        private int $attempts,
        private string $failureClass,
        private string $failureMessage,
        private int $deadAt,
    ) {
    }

    /**
     * The name of the event whose delivery died.
     */
    public function eventName(): EventName
    {
        return $this->eventName;
    }

    /**
     * The listener the delivery was addressed to -- the object
     * identity the book attributes dead letters by.
     */
    public function eventListener(): EventListener
    {
        return $this->eventListener;
    }

    /**
     * How many attempts the delivery spent before dying.
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * The failure's own class name -- the exception the last
     * attempt threw.
     */
    public function failureClass(): string
    {
        return $this->failureClass;
    }

    /**
     * The failure's message, verbatim.
     */
    public function failureMessage(): string
    {
        return $this->failureMessage;
    }

    /**
     * When the letter was written -- the drain's now.
     */
    public function deadAt(): int
    {
        return $this->deadAt;
    }
}
