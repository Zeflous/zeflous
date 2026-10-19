<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/** The product the fixture factory target answers. */
final readonly class FactoryProduct
{
    public function __construct(
        public Window $window,
        public string $frame,
    ) {
    }
}
