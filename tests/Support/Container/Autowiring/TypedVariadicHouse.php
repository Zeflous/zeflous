<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Test\Support\Container\Greeter;

/** Fixture: a typed variadic -- the collection arm is the tags lane's to come. */
final class TypedVariadicHouse
{
    /** @var list<Greeter> */
    public array $greeters;

    public function __construct(
        Greeter ...$greeters,
    ) {
        $this->greeters = array_values($greeters);
    }
}
