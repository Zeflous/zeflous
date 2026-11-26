<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

/**
 * One held delivery the broker answered a get with: the delivery
 * tag the ack echoes (the channel-scoped identity the claim holds),
 * the redelivered flag the broker's own requeue history spells (the
 * at-least-once duplicate's own mark, spelled by the engine, never
 * consulted by the claim -- the record the body carries is the
 * authority), and the body bytes the content frames reassembled.
 */
final readonly class AmqpDelivery
{
    public function __construct(
        private int $deliveryTag,
        private bool $redelivered,
        private string $body,
    ) {
    }

    /**
     * The held delivery's own tag -- the channel-scoped identity the
     * ack echoes.
     */
    public function deliveryTag(): int
    {
        return $this->deliveryTag;
    }

    /**
     * Whether the broker requeued this message before the hold.
     */
    public function redelivered(): bool
    {
        return $this->redelivered;
    }

    /**
     * The body bytes the content frames reassembled -- the delivery
     * record the Event lane's own decoder spells.
     */
    public function body(): string
    {
        return $this->body;
    }
}
