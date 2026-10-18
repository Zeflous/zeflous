<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/**
 * A consumer whose #[Value] address is missing while its constructor
 * default exists: the attribute is the binding intent, so the default
 * is not consulted and the boot refuses with the way out.
 */
final readonly class DefaultedHouse
{
    public function __construct(
        #[Value('missing.default')]
        public string $name = 'builtin',
    ) {
    }
}
