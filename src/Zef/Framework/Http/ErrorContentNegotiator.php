<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/** Resolves whether a request explicitly prefers HTML over JSON problem details. */
final class ErrorContentNegotiator
{
    public static function prefersHtml(string $accept): bool
    {
        if ($accept === '') {
            return false;
        }

        $qualities = AcceptHeaderParser::qualities($accept);
        $html = self::qualityFor($qualities, 'text/html', 'text/*');
        $json = self::qualityFor($qualities, 'application/problem+json', 'application/json', 'application/*');

        return $html > 0.0 && $html > $json;
    }

    /** @param array<string, float> $qualities */
    private static function qualityFor(array $qualities, string ...$mediaTypes): float
    {
        foreach ([...$mediaTypes, '*/*'] as $mediaType) {
            if (array_key_exists($mediaType, $qualities)) {
                return $qualities[$mediaType];
            }
        }

        return 0.0;
    }
}
