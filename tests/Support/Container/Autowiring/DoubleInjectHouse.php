<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Framework\Container\Autowiring\Inject;

/** Fixture: a doubled #[Inject] -- an ambiguity to surface, not a precedence to guess. */
final class DoubleInjectHouse
{
    public function __construct(
        #[Inject('app.a')]
        #[Inject('app.b')] // @phpstan-ignore-line
        public string $clock,
    ) {
    }
}
