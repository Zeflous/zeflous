<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/** Decodes one structured body by its media type into the redacted, replayable shape. */
final class ReplayBodyDecoder
{
    /**
     * @return array{string, bool} sanitized body and whether it can be replayed
     */
    public static function decode(string $mediaType, string $body): array
    {
        return match (true) {
            self::isJsonMediaType($mediaType) => self::json($body),
            $mediaType === 'application/x-www-form-urlencoded' => self::form($body),
            default => ['', false],
        };
    }

    private static function isJsonMediaType(string $mediaType): bool
    {
        return $mediaType === 'application/json'
            || (str_starts_with($mediaType, 'application/') && str_ends_with($mediaType, '+json'));
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
        parse_str($body, $decoded);
        $safe = ReplayPayloadSanitizer::sanitize($decoded);

        return [http_build_query(\is_array($safe) ? $safe : []), true];
    }
}
