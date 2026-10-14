<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container;

/**
 * Fixture service that consumes another service through its constructor --
 * the shape an `['service' => id]` argument reference resolves.
 */
final readonly class Announcer
{
    public function __construct(
        public Greeter $greeter,
        public string $channel,
    ) {
    }

    public function announce(string $name): string
    {
        return '[' . $this->channel . '] ' . $this->greeter->greet($name);
    }
}
