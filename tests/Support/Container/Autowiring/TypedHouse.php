<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Test\Support\Container\Greeter;

/** Fixture: a typed parameter plus a defaulted scalar. */
final class TypedHouse
{
    public function __construct(
        public Greeter $greeter,
        public string $channel = 'default',
    ) {
    }
}
