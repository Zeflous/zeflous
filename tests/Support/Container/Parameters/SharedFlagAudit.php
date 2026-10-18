<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/** The other consumer sharing one address (the memo reuse arm). */
final readonly class SharedFlagAudit
{
    public function __construct(
        #[Value('shared.flag')]
        public bool $flag,
    ) {
    }
}
