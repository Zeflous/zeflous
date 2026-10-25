<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\StreamInterface;

/**
 * The wire-dump family's body fold: the message body as the wire
 * carries it, bounded for the channel that renders it.
 *
 * The bound is the caller's own policy (the CLI renderers carry the
 * family's default, a test may pass any width): a body within it is
 * answered verbatim -- raw bytes, control bytes included, because a
 * raw dump's one job is to be truthful -- and a body beyond it is
 * truncated at the bound with the honest notice of exactly how many
 * bytes were withheld, the bounded-channel doctrine the family shares
 * with the route debug summary's own clamp. Both measurements run
 * under the `'8bit'` encoding -- byte semantics, the unit the notice
 * names.
 *
 * @internal
 */
final class HttpWireBody
{
    /**
     * The family's default render bound: eight kibibytes, the widest a
     * terminal view stays honest at while a larger body still renders
     * its head.
     */
    public const int DEFAULT_BOUND = 8192;

    /**
     * The body's wire spelling, truncated at the bound when exceeded.
     *
     * @param int $bodyBound the maximum byte count rendered
     *
     * @throws HttpException when the bound is negative
     */
    public static function rendered(StreamInterface $stream, int $bodyBound): string
    {
        if ($bodyBound < 0) {
            throw new HttpException('The wire body bound must not be negative.');
        }

        $contents = (string) $stream;
        $length = mb_strlen($contents, '8bit');

        if ($length <= $bodyBound) {
            return $contents;
        }

        return mb_substr($contents, 0, $bodyBound, '8bit')
            . "\n[" . ($length - $bodyBound) . ' of ' . $length . " bytes truncated]\n";
    }
}
