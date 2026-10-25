<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\MessageInterface;

/**
 * The wire-dump family's header fold: a PSR-7 message's header fields
 * as the wire itself spells them.
 *
 * One `Name: value` line per field VALUE, in the message's own
 * declaration order -- a repeated field answers one line per value,
 * exactly the octets a serializer would emit -- never the joined
 * `Name: v1, v2` spelling the header-line accessor answers, because a
 * raw dump's one job is to show what is actually on the wire (the
 * audit family is the validator; this family is the witness).
 *
 * @internal
 */
final class HttpWireHeaders
{
    /**
     * The message's header lines, one per field value, declaration order.
     *
     * @return list<string>
     */
    public static function lines(MessageInterface $message): array
    {
        $lines = [];

        foreach ($message->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $lines[] = $name . ': ' . $value;
            }
        }

        return $lines;
    }
}
