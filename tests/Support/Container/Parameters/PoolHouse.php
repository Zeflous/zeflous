<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/** A consumer reading one whole-array parameter. */
final readonly class PoolHouse
{
    /** @param list<string> $replicas */
    public function __construct(
        #[Value('db.replicas')]
        public array $replicas,
    ) {
    }
}
