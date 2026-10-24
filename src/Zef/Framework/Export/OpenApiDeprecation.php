<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

/**
 * The deprecation declaration's OpenAPI rendering: the one question
 * the operation fold asks of a deprecated route -- how does the
 * router's own deprecation ({@see \Zef\Framework\Config\Routing\CompiledRoute::deprecation()},
 * the RFC 9745 instant, the RFC 8594 sunset, the documentation link)
 * spell in the document's fields?
 *
 * The answer is the pair the operation merges: the specification's
 * own `deprecated: true` flag for the tools, and the `x-zef-deprecation`
 * extension carrying the router's own instants as ISO-8601 UTC -- the
 * dates the dispatch lane's `Deprecation`/`Sunset` headers actually
 * answer with, rendered in the one spelling both worlds read.
 *
 * @internal consumed through {@see OpenApiOperation}
 */
final readonly class OpenApiDeprecation
{
    /**
     * The deprecation's operation fields, or an empty map for a route
     * that carries no deprecation declaration.
     *
     * @param null|array{date: int, sunset: null|int, link: null|non-empty-string} $deprecation
     *
     * @return array<string, mixed> the `deprecated` flag and the `x-zef-deprecation` extension
     */
    public static function rendered(?array $deprecation): array
    {
        if ($deprecation === null) {
            return [];
        }

        $extension = ['date' => gmdate('Y-m-d\TH:i:s\Z', $deprecation['date'])];

        if ($deprecation['sunset'] !== null) {
            $extension['sunset'] = gmdate('Y-m-d\TH:i:s\Z', $deprecation['sunset']);
        }

        if ($deprecation['link'] !== null) {
            $extension['link'] = $deprecation['link'];
        }

        return [
            'deprecated' => true,
            'x-zef-deprecation' => $extension,
        ];
    }
}
