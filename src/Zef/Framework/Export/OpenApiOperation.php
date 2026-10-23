<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * One OpenAPI operation: the route-and-method pair the paths object
 * carries -- one entry of the table's declaration rendered at one of
 * the HTTP methods it answers.
 *
 * Every field is the table's own truth, translated -- never guessed:
 *
 * - `operationId` is the route's reverse-routing name, and only when
 *   the entry answers exactly one method (the specification demands
 *   document-wide uniqueness, a multi-method route has one name for
 *   many operations, and the honest answer is to omit rather than to
 *   invent a per-method spelling);
 * - `deprecated` is the route's own deprecation declaration
 *   ({@see OpenApiDeprecation}), the document's flag for the tools
 *   and the router's own dates for the operators;
 * - `parameters` are the pattern's path variables with their declared
 *   constraints ({@see OpenApiParameters});
 * - `responses` is the one response the specification requires every
 *   operation to carry ({@see OpenApiResponses}), its description
 *   telling the reader the truth: the table declares the method, not
 *   the response's schema;
 * - the enrichment ({@see OpenApiOperationEnrichment}) and the
 *   `x-zef-*` extensions ({@see OpenApiOperationExtensions}) carry
 *   what the table and its declarations know and the specification
 *   has no field for -- verbatim, the engine's own spellings.
 *
 * @internal consumed through {@see OpenApiPaths}
 */
final readonly class OpenApiOperation
{
    /**
     * The operation object for one route at one of its methods.
     *
     * @param CompiledRoute $compiledRoute the route entry being rendered
     *
     * @return array<string, mixed> the OpenAPI Operation Object
     *
     * @throws ExportException when the route's enrichment declaration refuses
     */
    public static function fromRoute(CompiledRoute $compiledRoute): array
    {
        $enrichment = OpenApiOperationEnrichment::read($compiledRoute);

        $operation = [];

        if ($enrichment['summary'] !== null) {
            $operation['summary'] = $enrichment['summary'];
        }

        if ($enrichment['description'] !== null) {
            $operation['description'] = $enrichment['description'];
        }

        if ($enrichment['tags'] !== []) {
            $operation['tags'] = $enrichment['tags'];
        }

        if ($compiledRoute->name() !== null && \count($compiledRoute->methods()) === 1) {
            $operation['operationId'] = $compiledRoute->name();
        }

        $parameters = OpenApiParameters::fromRoute($compiledRoute);
        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        $operation['responses'] = OpenApiResponses::for($compiledRoute);

        foreach (OpenApiDeprecation::rendered($compiledRoute->deprecation()) as $field => $value) {
            $operation[$field] = $value;
        }

        foreach (OpenApiOperationExtensions::for($compiledRoute) as $field => $value) {
            $operation[$field] = $value;
        }

        return $operation;
    }
}
