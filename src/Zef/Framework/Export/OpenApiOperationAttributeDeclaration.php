<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use ReflectionMethod;
use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The reflected method's declaration: the attribute instances the
 * reflection reads, judged into the one enrichment shape the
 * operation fold merges -- one declaration accepted, none answered
 * as the honest absence, and two refused as the ambiguity they are.
 *
 * The fold exists so {@see OpenApiOperationAttributeReader} can stay
 * a single-question class under the architecture gate's difficulty
 * ceiling: the reader owns the handler grammar and the reflection
 * guard, this fold owns what the reflection found.
 *
 * @internal consumed through {@see OpenApiOperationAttributeReader}
 */
final readonly class OpenApiOperationAttributeDeclaration
{
    /**
     * The declaration a reflected method carries, or null when it
     * carries none.
     *
     * @param string $class  the handler's class half (reflectable by the reader's guard)
     * @param string $method the handler's method half (reflectable by the reader's guard)
     *
     * @return null|array{summary: null|non-empty-string, description: null|non-empty-string,
     *                     tags: list<non-empty-string>}
     *
     * @throws ExportException when the method carries the attribute more than once,
     *                         or the attribute's fields fail the prose grammar
     */
    public static function read(string $class, string $method, CompiledRoute $compiledRoute): ?array
    {
        if (!class_exists($class)) {
            return null;
        }

        $reflectionMethod = new ReflectionMethod($class, $method);

        $attributes = $reflectionMethod->getAttributes(OpenApiOperationAttribute::class);

        if ($attributes === []) {
            return null;
        }

        if (\count($attributes) > 1) {
            throw new ExportException(
                \sprintf(
                    'The route "%s" handler carries the OpenAPI operation attribute more than once.',
                    $compiledRoute->path()->pattern(),
                ),
            );
        }

        $openApiOperationAttribute = $attributes[0]->newInstance();

        return self::enrichment($openApiOperationAttribute, $compiledRoute);
    }

    /**
     * The declared attribute's own enrichment, judged by the prose
     * grammar the two spellings share.
     *
     * @return array{summary: null|non-empty-string, description: null|non-empty-string,
     *                tags: list<non-empty-string>}
     *
     * @throws ExportException when the attribute's fields fail the prose grammar
     */
    private static function enrichment(
        OpenApiOperationAttribute $openApiOperationAttribute,
        CompiledRoute $compiledRoute,
    ): array {
        return [
            'summary' => OpenApiOperationProse::optional(
                $openApiOperationAttribute->summary,
                'summary',
                $compiledRoute,
            ),
            'description' => OpenApiOperationProse::optional(
                $openApiOperationAttribute->description,
                'description',
                $compiledRoute,
            ),
            'tags' => OpenApiOperationProse::tags($openApiOperationAttribute->tags, $compiledRoute),
        ];
    }
}
