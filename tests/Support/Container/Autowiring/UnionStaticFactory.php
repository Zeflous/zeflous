<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Test\Support\Container\Greeter;

/** Fixture factory: the static form with a union return type -- no single class to gather. */
final class UnionStaticFactory
{
    public static function make(bool $asFile = false): FileLogger|Greeter
    {
        return $asFile ? new FileLogger('union') : new Greeter('union');
    }
}
