<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: a required union-typed parameter -- no single binding. */
final class UnionHouse
{
    public function __construct(
        public int|string $id,
    ) {
    }
}
