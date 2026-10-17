<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: a scalar variadic -- an unplanned collection omits to empty. */
final class VariadicHouse
{
    /** @var list<string> */
    public array $notes;

    public function __construct(
        string ...$notes,
    ) {
        $this->notes = array_values($notes);
    }
}
