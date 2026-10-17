<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: a typed variadic over a birthable concrete -- a birth must not join it. */
final class SimpleDepVariadicHouse
{
    /** @var list<SimpleDep> */
    public array $deps;

    public function __construct(
        SimpleDep ...$deps,
    ) {
        $this->deps = array_values($deps);
    }
}
