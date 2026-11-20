<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/**
 * The closed set's bool arm (issue #800's finding 4): a bool-typed
 * parameter whose address carries a scalar of another family, the exact
 * shape the lane's own type judgment refuses.
 */
final class TypedFlagHouse
{
    public function __construct(
        #[Value('runtime.flag')]
        public bool $flag,
    ) {
    }
}
