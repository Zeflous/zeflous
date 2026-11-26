<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

/**
 * The broker's basic.get-ok answer: the held delivery's own
 * identity -- the delivery tag the ack echoes, the redelivered flag
 * the broker's own requeue history spells, the exchange and routing
 * key the message rode, and the message count the queue still held
 * behind it. The content the answer carries -- the header and body
 * frames that follow the method -- is the channel's own reading,
 * never this record's.
 */
final readonly class AmqpGetOk
{
    public function __construct(
        private int $deliveryTag,
        private bool $redelivered,
        private string $exchange,
        private string $routingKey,
        private int $messageCount,
    ) {
    }

    /**
     * The held delivery's own tag -- the channel-scoped identity
     * the ack echoes.
     */
    public function deliveryTag(): int
    {
        return $this->deliveryTag;
    }

    /**
     * Whether the broker requeued this message before -- the
     * at-least-once duplicate's own flag, spelled by the engine.
     */
    public function redelivered(): bool
    {
        return $this->redelivered;
    }

    /**
     * The exchange the message rode.
     */
    public function exchange(): string
    {
        return $this->exchange;
    }

    /**
     * The routing key the message rode.
     */
    public function routingKey(): string
    {
        return $this->routingKey;
    }

    /**
     * How many ready messages the queue still held behind this one.
     */
    public function messageCount(): int
    {
        return $this->messageCount;
    }
}
