<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The method-set half of a route declaration: the verbs a route answers,
 * validated and upper-cased once at the declaration site.
 *
 * A declared set must carry at least one method and every spelling must
 * be a non-empty string; each is upper-cased here (any spelling, unicode
 * included) so the Standard Route Schema entry carries the canonical
 * form. De-duplication and ordering stay the engine's policy -- the DSL
 * declares, {@see CompiledRoute::fromArray()} normalises.
 *
 * @internal
 */
final class RouteDslMethods
{
    /**
     * Validates a declared method set and upper-cases each spelling.
     *
     * @param list<mixed> $methods the declared HTTP methods, any spelling
     *
     * @return list<string>
     */
    public static function upperCased(array $methods, string $path): array
    {
        if ($methods === []) {
            throw new ConfigException(
                \sprintf('The route "%s" must declare at least one HTTP method.', $path),
            );
        }

        $upperCased = [];

        foreach ($methods as $method) {
            if (!\is_string($method) || $method === '') {
                throw new ConfigException(
                    \sprintf('The route "%s" must not declare an empty HTTP method.', $path),
                );
            }

            $upperCased[] = mb_strtoupper($method);
        }

        return $upperCased;
    }
}
