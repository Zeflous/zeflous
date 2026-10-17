<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Countable;
use Stringable;

/** Fixture: an intersection-typed parameter -- no single binding. */
final class IntersectionHouse
{
    public function __construct(
        public Countable&Stringable $both,
    ) {
    }
}
