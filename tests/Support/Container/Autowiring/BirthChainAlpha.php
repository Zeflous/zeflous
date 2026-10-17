<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: one half of a birth chain that closes onto itself. */
final class BirthChainAlpha
{
    public function __construct(
        public BirthChainBeta $beta,
    ) {
    }
}
