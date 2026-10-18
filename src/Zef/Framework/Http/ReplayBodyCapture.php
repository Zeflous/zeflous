<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/** Captures only structured, redacted request bodies that can be replayed safely. */
final class ReplayBodyCapture
{
    /**
     * @return array{string, bool} Sanitized body and whether it can be replayed.
     */
    public static function capture(ServerRequestInterface $request): array
    {
        $body = ReplayBodyReader::read($request);
        if ($body === null) {
            return ['', false];
        }

        if ($body === '') {
            return ['', true];
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        $mediaType = trim(explode(';', $contentType, 2)[0]);

        try {
            if (self::isJsonMediaType($mediaType)) {
                return self::json($body);
            }

            if ($mediaType === 'application/x-www-form-urlencoded') {
                return self::form($body);
            }
        } catch (JsonException|Throwable) {
            return ['', false];
        }

        return ['', false];
    }

    private static function isJsonMediaType(string $mediaType): bool
    {
        return $mediaType === 'application/json'
            || (str_starts_with($mediaType, 'application/') && str_ends_with($mediaType, '+json'));
    }

    /** @return array{string, bool} */
    private static function json(string $body): array
    {
        $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        $safe = ReplayPayloadSanitizer::sanitize($decoded);

        return [json_encode($safe, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), true];
    }

    /** @return array{string, bool} */
    private static function form(string $body): array
    {
        parse_str($body, $decoded);
        $safe = ReplayPayloadSanitizer::sanitize($decoded);

        return [http_build_query(is_array($safe) ? $safe : []), true];
    }
}
