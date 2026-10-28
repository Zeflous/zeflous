<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Redis;

use InvalidArgumentException;

/**
 * The RESP request grammar's writing half: one command -- its verb
 * and its arguments -- as the bytes the wire carries, every argument
 * length-prefixed so the payload is binary-safe by construction (a
 * value that itself carries the protocol's own line ending is legal
 * on the wire, the length is the boundary). An integer argument is
 * the wire's own number spelling -- the scores and timeouts the
 * Redis vocabulary spells as decimal text -- cast at this single
 * boundary so every call site hands the type it already holds.
 */
final readonly class RespCommandEncoder
{
    /**
     * Encodes one command as its RESP request bytes.
     *
     * @param list<int|string> $arguments the verb first, its arguments after
     *
     * @throws InvalidArgumentException when the command carries no
     *                                  argument at all -- the wire's own grammar refuses to send
     *                                  nothing
     */
    public function encode(array $arguments): string
    {
        if ($arguments === []) {
            throw new InvalidArgumentException(
                'A Redis command must carry at least its own verb.',
            );
        }

        $bytes = '*' . \count($arguments) . "\r\n";

        foreach ($arguments as $argument) {
            $spelling = (string) $argument;

            $bytes .= '$' . mb_strlen($spelling, '8bit') . "\r\n" . $spelling . "\r\n";
        }

        return $bytes;
    }
}
