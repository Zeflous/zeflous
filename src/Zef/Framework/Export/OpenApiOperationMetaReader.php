<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The enrichment's meta spelling: the route's `meta['openapi']` map
 * -- the table-side declaration, the only enrichment a service-id
 * handler can carry (a handler the reflection cannot read has no
 * method to decorate).
 *
 * The map's shape is judged when read, fail-closed: the value must
 * be a map, its keys the three the family owns (an unknown key is a
 * caller's invention the export refuses to ship), and every field
 * the prose grammar accepts. The route's own identity rides every
 * refusal -- the same posture every declaration in the table already
 * receives.
 *
 * @internal consumed through {@see OpenApiOperationEnrichment}
 */
final readonly class OpenApiOperationMetaReader
{
    /** The meta key the enrichment's table-side spelling lives under. */
    private const string META_KEY = 'openapi';

    /** The keys the family owns -- nothing else ships. */
    private const array OWNED_KEYS = ['summary', 'description', 'tags'];

    /**
     * The meta map's declaration for one route, or null when the meta
     * carries no openapi key.
     *
     * @param CompiledRoute $compiledRoute the route whose meta is read
     *
     * @return null|array{summary: null|non-empty-string, description: null|non-empty-string,
     *                     tags: list<non-empty-string>}
     *
     * @throws ExportException when the meta value is not a map, carries an unknown key,
     *                         or a field fails the prose grammar
     */
    public static function read(CompiledRoute $compiledRoute): ?array
    {
        $meta = $compiledRoute->meta();

        if (!\array_key_exists(self::META_KEY, $meta)) {
            return null;
        }

        $declared = $meta[self::META_KEY];

        if (!\is_array($declared)) {
            throw new ExportException(
                \sprintf(
                    'The route "%s" meta openapi must be a map, %s given.',
                    $compiledRoute->path()->pattern(),
                    get_debug_type($declared),
                ),
            );
        }

        foreach (array_keys($declared) as $key) {
            if (!\in_array($key, self::OWNED_KEYS, true)) {
                throw new ExportException(
                    \sprintf(
                        'The route "%s" meta openapi carries the unknown key "%s".',
                        $compiledRoute->path()->pattern(),
                        (string) $key,
                    ),
                );
            }
        }

        return [
            'summary' => OpenApiOperationProse::optional($declared['summary'] ?? null, 'summary', $compiledRoute),
            'description' => OpenApiOperationProse::optional(
                $declared['description'] ?? null,
                'description',
                $compiledRoute,
            ),
            'tags' => OpenApiOperationProse::tags($declared['tags'] ?? [], $compiledRoute),
        ];
    }
}
