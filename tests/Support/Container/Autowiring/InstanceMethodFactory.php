<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Test\Support\Container\Greeter;

/** Fixture factory: the non-static method the static spelling cannot call. */
final class InstanceMethodFactory
{
    public function make(): Greeter
    {
        return new Greeter('instance');
    }
}
