<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;
use Zef\Test\Support\Container\Greeter;

/** A class-typed parameter cannot carry #[Value] (a value is no instance). */
final readonly class ClassTypedValueHouse
{
    public function __construct(
        #[Value('app.greeter')]
        public Greeter $greeter,
    ) {
    }
}
