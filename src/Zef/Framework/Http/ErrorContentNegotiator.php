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
        $html = $qualities['text/html'] ?? $qualities['text/*'] ?? $qualities['*/*'] ?? 0.0;
        $json = max(
            $qualities['application/problem+json'] ?? 0.0,
            $qualities['application/json'] ?? 0.0,
            $qualities['application/*'] ?? 0.0,
            $qualities['*/*'] ?? 0.0,
        );

        return $html > 0.0 && $html > $json;
    }
}
