<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The method name of one call entry, proven: a non-empty string, and
 * an existing method of the class whenever parse time knows it (the
 * boot discipline -- a definition that survives boot never fails at
 * instantiation through a missing method).
 */
final readonly class MethodCallNames
{
    /**
     * The entry's method name, proven on the class when parse time
     * knows it.
     *
     * @param null|class-string $class
     */
    public static function proven(string $id, ?string $class, mixed $method): string
    {
        if (!\is_string($method) || $method === '') {
            throw MethodCallRefusals::methodNotAName($id, $method);
        }

        if ($class !== null && !method_exists($class, $method)) {
            throw MethodCallRefusals::methodMissing($id, $class, $method);
        }

        return $method;
    }
}
