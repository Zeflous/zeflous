<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/** A consumer whose scalar parameters arrive from the parameters map. */
final readonly class ConnectionHouse
{
    public function __construct(
        #[Value('db.host')]
        public string $host,
        #[Value('db.port')]
        public int $port,
    ) {
    }
}
