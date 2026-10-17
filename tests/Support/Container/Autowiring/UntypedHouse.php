<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: a required mixed-typed parameter -- nothing to derive a binding from. */
final class UntypedHouse
{
    public function __construct(
        public mixed $payload,
    ) {
    }
}
