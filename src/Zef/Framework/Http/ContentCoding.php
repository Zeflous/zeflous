<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The content-coding vocabulary the compression family speaks: the
 * preference order the negotiator walks, and the zlib window each
 * coding's wrapper asks for -- the one place either spelling lives,
 * so no collaborator re-spells it.
 *
 * The preference order is the roadmap's own (`br` > `gzip` >
 * `deflate`): Brotli's density wins over gzip's universality when
 * both sides can speak it. The window argument is the wrapper the
 * zlib engine writes around the deflate stream it produces --
 * `gzip` (RFC 1952) asks for window 15 with the gzip-format bit
 * (31), `deflate` in HTTP means the zlib wrapper (RFC 1950), which
 * asks for window 15 bare. That `deflate`-means-zlib reading is the
 * RFC 9110 definition, not the historic client confusion that
 * expected a raw stream -- the confusion is a client bug the
 * standard explicitly closed.
 *
 * The vocabulary deliberately carries no `compress` (the LZW coding
 * no engine wants), no `zstd` (no PHP core seam), and no `identity`:
 * identity is the absence of a coding, and its grammar belongs to
 * the negotiator's client-side reading ({@see AcceptEncodingQualities}),
 * not to the producer's table.
 */
final class ContentCoding
{
    /**
     * The codings the family prefers, best first.
     */
    private const array PREFERENCE = ['br', 'gzip', 'deflate'];

    private const array WINDOWS = [
        'gzip' => 31,
        'deflate' => 15,
    ];

    /**
     * The preference order over the codings the runtime can produce.
     *
     * @param list<string> $capabilities the producible codings
     *
     * @return list<string> the preferences that are producible, best first
     */
    public static function preferences(array $capabilities): array
    {
        $preferred = [];

        foreach (self::PREFERENCE as $coding) {
            if (!\in_array($coding, $capabilities, true)) {
                continue;
            }

            $preferred[] = $coding;
        }

        return $preferred;
    }

    /**
     * The zlib window a coding's wrapper asks for.
     *
     * @param string $coding the negotiated coding
     *
     * @throws HttpException when the coding is not one the vocabulary
     *                       spells a window for
     */
    public static function window(string $coding): int
    {
        return self::WINDOWS[$coding] ?? throw new HttpException(
            "The content coding '{$coding}' has no zlib window.",
        );
    }
}
