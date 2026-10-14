<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container;

/**
 * Fixture service with a constructor that takes the argument shapes the
 * declarative definition grammar exercises: a plain string, a plain int
 * and an injected collaborator (the resolved reference).
 */
final readonly class Greeter
{
    public function __construct(
        public string $salutation,
        public int $verbosity = 1,
    ) {
    }

    public function greet(string $name): string
    {
        return $this->salutation . ' ' . $name . '!';
    }
}
