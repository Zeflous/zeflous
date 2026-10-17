<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Test\Support\Container\Greeter;

/** Fixture factory: the static form whose return type names its product. */
final class GreeterFactory
{
    public static function make(): Greeter
    {
        return new Greeter('made');
    }
}
