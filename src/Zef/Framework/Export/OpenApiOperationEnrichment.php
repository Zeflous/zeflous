<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The operation's enrichment: the documentation a route carries
 * beyond the table's structural truth, read from the two places a
 * declaration can live -- the handler method's own attribute
 * ({@see OpenApiOperationAttributeReader}, reflected at export time
 * over the `Class@method` form the engine's own resolver grammar
 * spells), or the route's `meta['openapi']` map
 * ({@see OpenApiOperationMetaReader}, the table-side spelling, the
 * only one a service-id handler can carry).
 *
 * THE TWO SPELLINGS ARE MUTUALLY EXCLUSIVE, fail-closed: a route whose
 * handler declares the attribute AND whose meta carries the map has
 * two declarations for one operation, and the export refuses the
 * ambiguity with the route's own identity -- the same refusal the
 * table's own duplicate declarations receive.
 *
 * @internal consumed through {@see OpenApiOperation}
 */
final readonly class OpenApiOperationEnrichment
{
    /**
     * The enrichment one route carries, in the shape the operation fold
     * merges: null fields mean "not declared" and an empty tag list
     * means "no tags".
     *
     * @param CompiledRoute $compiledRoute the route whose enrichment is read
     *
     * @return array{summary: null|non-empty-string, description: null|non-empty-string, tags: list<non-empty-string>}
     *
     * @throws ExportException when both spellings are present, or either carries a wrong shape
     */
    public static function read(CompiledRoute $compiledRoute): array
    {
        $fromAttribute = OpenApiOperationAttributeReader::read($compiledRoute);
        $fromMeta = OpenApiOperationMetaReader::read($compiledRoute);

        if ($fromAttribute !== null && $fromMeta !== null) {
            throw new ExportException(
                \sprintf(
                    'The route "%s" declares its OpenAPI enrichment twice: the handler method carries'
                    . ' the attribute and the route meta carries the openapi map -- declare one.',
                    $compiledRoute->path()->pattern(),
                ),
            );
        }

        $enrichment = $fromAttribute ?? $fromMeta;

        return $enrichment ?? ['summary' => null, 'description' => null, 'tags' => []];
    }
}
