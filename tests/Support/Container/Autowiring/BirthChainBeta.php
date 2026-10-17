<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: the other half of a birth chain that closes onto itself. */
final class BirthChainBeta
{
    public function __construct(
        public BirthChainAlpha $alpha,
    ) {
    }
}
