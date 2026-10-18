<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/** A union parameter cannot carry #[Value] (a value names no single type). */
final readonly class UnionValueHouse
{
    public function __construct(
        #[Value('app.either')]
        public int|string $either,
    ) {
    }
}
