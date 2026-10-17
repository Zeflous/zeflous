<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture factory: the static form whose return type is built-in -- no class to gather. */
final class BuiltinStaticFactory
{
    public static function make(): string
    {
        return 'made';
    }
}
