<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/** One parameter cannot carry the same attribute twice. */
final readonly class DoubledValueHouse
{
    public function __construct(
        #[Value('db.host')]
        #[Value('db.host')] // @phpstan-ignore-line
        public string $host,
    ) {
    }
}
