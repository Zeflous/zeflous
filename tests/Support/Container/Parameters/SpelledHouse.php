<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/** A consumer whose #[Value] parameter an explicit spelling overrides. */
final readonly class SpelledHouse
{
    public function __construct(
        #[Value('db.host')]
        public string $host,
    ) {
    }
}
