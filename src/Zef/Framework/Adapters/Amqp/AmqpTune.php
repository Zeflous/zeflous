<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

/**
 * The broker's connection.tune answer: the three ceilings the
 * connection negotiates against its own -- the channel maximum, the
 * frame maximum, and the heartbeat the broker proposes (zero: the
 * broker proposes none; above zero: the broker proposes an
 * interval, and the client's own answer the connection spells).
 */
final readonly class AmqpTune
{
    public function __construct(
        private int $channelMax,
        private int $frameMax,
        private int $heartbeat,
    ) {
    }

    /**
     * The channel ceiling the broker answers -- zero spells the
     * protocol's own unlimited.
     */
    public function channelMax(): int
    {
        return $this->channelMax;
    }

    /**
     * The frame ceiling the broker answers -- zero spells the
     * protocol's own unlimited.
     */
    public function frameMax(): int
    {
        return $this->frameMax;
    }

    /**
     * The heartbeat the broker proposes.
     */
    public function heartbeat(): int
    {
        return $this->heartbeat;
    }
}
