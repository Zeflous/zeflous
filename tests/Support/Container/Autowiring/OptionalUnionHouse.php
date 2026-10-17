<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: an optional union-typed parameter -- its own default bakes. */
final class OptionalUnionHouse
{
    public function __construct(
        public int|string $id = 7,
    ) {
    }
}
