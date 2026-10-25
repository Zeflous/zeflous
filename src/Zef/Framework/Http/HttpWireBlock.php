<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\MessageInterface;

/**
 * The wire-dump family's shared block fold: one message's wire block
 * -- the start line, the header lines, the empty line that ends the
 * head, then the (bounded) body.
 *
 * The two spellings that share it ({@see HttpWireRequest} with its
 * request line, {@see HttpWireResponse} with its status line) differ
 * only in that first line, so the fold they both ride lives once, in
 * this class -- CRLF between every line of the head, a bare CRLF pair
 * closing it, the body following verbatim. A message with no header
 * fields answers its start line then the closing pair directly; a
 * message with no body answers its head and nothing after it.
 *
 * @internal
 */
final class HttpWireBlock
{
    /**
     * One message's wire block: start line, header lines, empty line,
     * body.
     *
     * @param non-empty-string $startLine the block's first line
     * @param int              $bodyBound the maximum body byte count rendered
     */
    public static function rendered(string $startLine, MessageInterface $message, int $bodyBound): string
    {
        $lines = HttpWireHeaders::lines($message);

        $head = $startLine;

        if ($lines !== []) {
            $head .= "\r\n" . implode("\r\n", $lines);
        }

        return $head . "\r\n\r\n" . HttpWireBody::rendered($message->getBody(), $bodyBound);
    }
}
