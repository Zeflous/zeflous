<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: the declared root whose planning walks into the circular birth chain. */
final class ChainRoot
{
    public function __construct(
        public BirthChainAlpha $alpha,
    ) {
    }
}
