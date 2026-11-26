<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP wire's own binary reading: one big-endian integer
 * unpacked from the bytes a length check already bounded -- the
 * single place the engine's own {@see unpack()} answer is narrowed,
 * because the analyser cannot see what the grammar guarantees (a
 * format over the bytes the caller measured), and a refusal family
 * that never fires is still the honest spelling: the bytes are
 * bounded by construction, and the fold answers the integer the
 * format spelled.
 */
final class AmqpUnpack
{
    /**
     * One unpacked integer -- the format's first value.
     *
     * @param string $format the binary format the bytes carry
     * @param string $bytes  the bytes a length check already bounded
     *
     * @throws InvalidArgumentException when the engine refuses its
     *                                  own unpacking -- unreachable by construction, spelled
     *                                  because the narrowing is a promise, not a hope
     */
    public static function integer(string $format, string $bytes): int
    {
        $unpacked = unpack($format, $bytes);
        $value = $unpacked === false ? null : ($unpacked[1] ?? null);

        if (!\is_int($value)) {
            throw new InvalidArgumentException(
                'The AMQP wire bytes refused their own binary reading.',
            );
        }

        return $value;
    }
}
