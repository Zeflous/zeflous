<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aliasing;

use Zef\Test\Support\Container\Greeter;

/**
 * A class-shaped decorator over a Greeter target: the alias-ride
 * battery's wrapper, whose construction is the observable the chain's
 * lifetime rides (a transient chain rebuilds whole, a request chain
 * rides one scope).
 */
final readonly class SalutingGreeter
{
    public function __construct(
        public Greeter $inner,
    ) {
    }

    public function salute(string $name): string
    {
        return $this->inner->greet($name);
    }
}
