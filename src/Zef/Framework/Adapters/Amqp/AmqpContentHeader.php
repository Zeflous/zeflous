<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP content header codec: the frame that announces every
 * message's body -- the class it belongs to, the weight the grammar
 * reserves (always zero), the body size the body frames must
 * reassemble to, and the property list the flags index. The family
 * writes exactly one property (the delivery mode: every record the
 * store publishes is persistent, the durability the port promises),
 * and reads exactly one field (the body size -- the properties the
 * broker answered ride unread inside the frame's own bounds, the
 * record the body carries being the only content the store reads).
 */
final class AmqpContentHeader
{
    /** The basic class the delivery records ride. */
    private const int CLASS_BASIC = 60;

    /** The delivery-mode property's own flag -- the fourth of the fifteen. */
    private const int FLAG_DELIVERY_MODE = 0x1000;

    /** The persistent delivery mode -- every record the store publishes. */
    private const int DELIVERY_MODE_PERSISTENT = 2;

    /**
     * The content header one published record announces itself with
     * -- the basic class, a zero weight, the body size, and the one
     * property the family spells: the persistent delivery mode.
     *
     * @param int $bodySize the body's own byte count
     */
    public function encode(int $bodySize): string
    {
        return (new AmqpWireWriter())
            ->short(self::CLASS_BASIC)
            ->short(0)
            ->longlong($bodySize)
            ->short(self::FLAG_DELIVERY_MODE)
            ->octet(self::DELIVERY_MODE_PERSISTENT)
            ->bytes();
    }

    /**
     * The body size one answered content header announces -- the
     * count the body frames must reassemble to.
     *
     * @throws InvalidArgumentException when the header ends before the body size does
     */
    public function bodySizeOf(string $payload): int
    {
        if (mb_strlen($payload, '8bit') < 12) {
            throw new InvalidArgumentException(
                'The content header is refused: it ends before the body size'
                . ' does.',
            );
        }

        return AmqpUnpack::integer('J', mb_substr($payload, 4, 8, '8bit'));
    }
}
