<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP frame, the wire's own unit of carriage: its type (1 the
 * method, 2 the content header, 3 the content body, 4 the
 * heartbeat), the channel it rides, and the payload bytes the
 * frame's own size prefix bounded. Immutable by construction -- the
 * reader answers one whole frame at a time, the writer carries one
 * whole frame at a time, and the end byte the protocol demands
 * after every payload is validated at the read and spelled at the
 * write, never carried here.
 */
final readonly class AmqpFrame
{
    public const int TYPE_METHOD = 1;

    public const int TYPE_CONTENT_HEADER = 2;

    public const int TYPE_CONTENT_BODY = 3;

    public const int TYPE_HEARTBEAT = 4;

    /**
     * @param int    $type    the frame's type, one the protocol spells
     * @param int    $channel the channel the frame rides
     * @param string $payload the frame's own payload bytes
     */
    public function __construct(
        private int $type,
        private int $channel,
        private string $payload,
    ) {
    }

    /**
     * The frame's type -- the method, content header, content body
     * or heartbeat spelling.
     */
    public function type(): int
    {
        return $this->type;
    }

    /**
     * The channel the frame rides.
     */
    public function channel(): int
    {
        return $this->channel;
    }

    /**
     * The frame's own payload bytes.
     */
    public function payload(): string
    {
        return $this->payload;
    }

    /**
     * The method identity this frame carries -- a method frame's
     * own class and method ids, answered for every judgment the
     * await discipline spells.
     */
    public function method(): AmqpMethod
    {
        return AmqpMethod::ofFrame(
            AmqpUnpack::integer('n', mb_substr($this->payload, 0, 2, '8bit')),
            AmqpUnpack::integer('n', mb_substr($this->payload, 2, 2, '8bit')),
        );
    }

    /**
     * The argument bytes this frame carries -- a method frame's own
     * payload past the class and method ids.
     */
    public function arguments(): string
    {
        return mb_substr($this->payload, 4, null, '8bit');
    }
}
