<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

/**
 * The broker's queue.declare-ok answer: the queue's own name
 * echoed (the broker's authority on what the declaration landed
 * as), and the two counts the declare answers -- the message count
 * the pending lane's bound and the books' per-identity counts read,
 * and the consumer count the family never consults but the grammar
 * always carries.
 */
final readonly class AmqpDeclareOk
{
    public function __construct(
        private string $queue,
        private int $messageCount,
        private int $consumerCount,
    ) {
    }

    /**
     * The queue's own name, echoed by the broker.
     */
    public function queue(): string
    {
        return $this->queue;
    }

    /**
     * How many ready messages the queue held at the declare -- the
     * count the capacity judgment and the walk bound read.
     */
    public function messageCount(): int
    {
        return $this->messageCount;
    }

    /**
     * How many consumers the queue carried at the declare.
     */
    public function consumerCount(): int
    {
        return $this->consumerCount;
    }
}
