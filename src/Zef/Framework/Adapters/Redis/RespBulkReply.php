<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Redis;

use InvalidArgumentException;

/**
 * The RESP bulk reply's own decode: the null spelling, or the
 * measured payload with its own trailer verified -- a bulk is
 * complete only when its whole declared length has arrived AND the
 * two bytes after it are the protocol's own line ending, the two
 * halves the grammar refuses to guess past. The length line is this
 * fold's whole input; the measured read is the wire's.
 */
final readonly class RespBulkReply
{
    public function __construct(
        private RespReader $respReader,
    ) {
    }

    /**
     * Decodes the bulk reply its length line spells.
     *
     * @param string $payload the length line's own payload (`-1` the
     *                        null spelling, otherwise the declared byte count)
     *
     * @throws InvalidArgumentException when the length is not a
     *                                  length the grammar admits, or the measured trailer is
     *                                  not the protocol's own line ending
     */
    public function decode(string $payload): ?string
    {
        if ($payload === '-1') {
            return null;
        }

        if (preg_match('/^-?\d+$/', $payload) !== 1) {
            throw new InvalidArgumentException(
                "The Redis reply of type '\$' is not a decimal integer: '{$payload}'.",
            );
        }

        $length = (int) $payload;

        if ($length < 0) {
            throw new InvalidArgumentException(
                "The Redis bulk reply's length '{$payload}' is not a length the grammar admits.",
            );
        }

        $run = $this->respReader->readExact($length + 2);

        if (mb_substr($run, $length, null, '8bit') !== "\r\n") {
            throw new InvalidArgumentException(
                "The Redis bulk reply does not end with the protocol's own line ending.",
            );
        }

        return mb_substr($run, 0, $length, '8bit');
    }
}
