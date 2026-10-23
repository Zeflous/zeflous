<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The enrichment's attribute spelling: the handler method's own
 * {@see OpenApiOperationAttribute}, reflected at export time over the
 * `Class@method` form the engine's own resolver grammar spells.
 *
 * A handler the reflection cannot read -- not a loadable class, or no
 * such method on it -- carries no attribute enrichment, silently and
 * by design: whether a service id resolves is the dispatcher's own
 * judgment at dispatch time ({@see \Zef\Framework\Config\Routing\RouteHandlerResolver}),
 * not a judgment the exporter re-makes. A handler that IS reflectable
 * but declares the attribute twice refuses the export with the
 * route's own identity -- one declaration per operation, the same
 * refusal the table's own duplicate declarations receive.
 *
 * @internal consumed through {@see OpenApiOperationEnrichment}
 */
final readonly class OpenApiOperationAttributeReader
{
    /** The handler separator the engine's own resolver grammar spells. */
    private const string HANDLER_SEPARATOR = '@';

    /**
     * The attribute's declaration for one route, or null when the
     * handler carries none the reflection can read.
     *
     * @param CompiledRoute $compiledRoute the route whose handler is reflected
     *
     * @return null|array{summary: null|non-empty-string, description: null|non-empty-string,
     *                     tags: list<non-empty-string>}
     *
     * @throws ExportException when the handler carries the attribute more than once,
     *                         or the attribute's fields fail the prose grammar
     */
    public static function read(CompiledRoute $compiledRoute): ?array
    {
        $handler = $compiledRoute->handler();
        $parts = explode(self::HANDLER_SEPARATOR, $handler);

        if (\count($parts) !== 2 || !self::reflectable($parts)) {
            return null;
        }

        return OpenApiOperationAttributeDeclaration::read($parts[0], $parts[1], $compiledRoute);
    }

    /**
     * Whether the handler's pair names a class and a method the
     * reflection can read -- the guard that keeps the reader away
     * from handlers it must never reflect.
     *
     * @param list<string> $parts the handler's `Class@method` pair
     */
    private static function reflectable(array $parts): bool
    {
        return class_exists($parts[0]) && method_exists($parts[0], $parts[1]);
    }
}
