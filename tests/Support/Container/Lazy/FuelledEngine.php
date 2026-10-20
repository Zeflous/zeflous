<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Lazy;

use Override;

/**
 * Fixture implementation behind the interface return type -- never
 * deferred to, only answered by the interface-returning fixture
 * factory.
 */
final class FuelledEngine implements EngineContract
{
    #[Override]
    public function thrust(): int
    {
        return 9;
    }
}
