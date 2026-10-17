<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: a birthable class whose whole plan its own defaults satisfy. */
final class SimpleDep
{
    public function __construct(
        public string $name = 'simple',
    ) {
    }
}
