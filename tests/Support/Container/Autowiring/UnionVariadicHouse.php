<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Test\Support\Container\Greeter;

/** Fixture: a union-typed variadic -- the collection names no single type. */
final class UnionVariadicHouse
{
    /** @var list<Greeter|int> */
    public array $values;

    public function __construct(
        Greeter|int ...$values,
    ) {
        $this->values = array_values($values);
    }
}
