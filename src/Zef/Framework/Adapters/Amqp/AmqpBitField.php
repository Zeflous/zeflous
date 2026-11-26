<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Amqp;

use InvalidArgumentException;

/**
 * The AMQP boolean bit field's own packing: the flags the protocol
 * declares packed MSB-first into a single octet -- every method the
 * family spells carries at most eight flags, so one octet is always
 * the whole field. Split from the wire writer the same ceiling
 * every slice's folds answer.
 */
final class AmqpBitField
{
    /**
     * One bit field's own octet.
     *
     * @param bool ...$flags the flags in the protocol's own declaration order
     *
     * @throws InvalidArgumentException when more than eight flags
     *                                  arrive -- no method the family spells carries them
     */
    public function octet(bool ...$flags): string
    {
        if (\count($flags) > 8) {
            throw new InvalidArgumentException(
                'An AMQP bit field packs at most eight flags, got '
                . \count($flags) . '.',
            );
        }

        $octet = 0;

        foreach (array_values($flags) as $index => $flag) {
            if ($flag) {
                $octet |= 1 << (7 - $index);
            }
        }

        return \chr($octet);
    }
}
