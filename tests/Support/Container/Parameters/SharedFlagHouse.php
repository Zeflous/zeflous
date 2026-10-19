<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/** One of two consumers sharing one address (the memo reuse arm). */
final readonly class SharedFlagHouse
{
    public function __construct(
        #[Value('shared.flag')]
        public bool $flag,
    ) {
    }
}
