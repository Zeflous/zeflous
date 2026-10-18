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

        $qualities = [];
        foreach (explode(',', strtolower($accept)) as $part) {
            $segments = array_map('trim', explode(';', $part));
            $mediaType = array_shift($segments);
            if (!is_string($mediaType) || $mediaType === '') {
                continue;
            }

            $quality = 1.0;
            foreach ($segments as $segment) {
                if (str_starts_with($segment, 'q=')) {
                    $parsed = (float) substr($segment, 2);
                    $quality = $parsed >= 0.0 && $parsed <= 1.0 ? $parsed : 0.0;
                }
            }

            $qualities[$mediaType] = max($qualities[$mediaType] ?? 0.0, $quality);
        }

        $html = $qualities['text/html'] ?? 0.0;
        $json = max(
            $qualities['application/problem+json'] ?? 0.0,
            $qualities['application/json'] ?? 0.0,
            $qualities['application/*'] ?? 0.0,
            $qualities['*/*'] ?? 0.0,
        );

        return $html > 0.0 && $html > $json;
    }
}
