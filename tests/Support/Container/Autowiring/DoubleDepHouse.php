<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: two parameters naming the same unborn class -- one birth, two edges. */
final class DoubleDepHouse
{
    public function __construct(
        public SimpleDep $first,
        public SimpleDep $second,
    ) {
    }
}
