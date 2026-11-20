<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Decodes one structured body by its media type into the redacted,
 * replayable shape: the {@see ReplayBodyLane} judgment names the lane,
 * and this class owns the decodes alone (the #801 round's archeology
 * answer -- the routing judgment its own unit so the decoder's own
 * difficulty stays under the bar the pair census pushed it past).
 */
final class ReplayBodyDecoder
{
    /**
     * @return array{string, bool} sanitized body and whether it can be replayed
     */
    public static function decode(string $mediaType, string $body): array
    {
        return match (ReplayBodyLane::of($mediaType)) {
            'json' => self::json($body),
            'form' => self::form($body),
            null => ['', false],
        };
    }

    /** @return array{string, bool} */
    private static function json(string $body): array
    {
        $decoded = json_decode($body, true, 32, \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            return ['', false];
        }

        return [
            json_encode(
                ReplayPayloadSanitizer::sanitize($decoded),
                \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
            ),
            true,
        ];
    }

    /** @return array{string, bool} */
    private static function form(string $body): array
    {
        // The same pair bound the url-encoded strategy holds (issue
        // #801's finding 3): a captured ampersand storm is censused
        // before any decode allocates, the over-bound capture answering
        // not-replayable -- the failure lane this family exists to
        // serve never throwing over a body it merely records.
        if (FormBodyParser::pairCount($body) > FormBodyParser::PAIR_LIMIT) {
            return ['', false];
        }

        parse_str($body, $decoded);
        $safe = ReplayPayloadSanitizer::sanitize($decoded);

        return [http_build_query(\is_array($safe) ? $safe : []), true];
    }
}
