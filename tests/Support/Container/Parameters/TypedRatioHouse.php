<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

use Zef\Framework\Container\Autowiring\Value;

/**
 * The closed set's float arm (issue #800's finding 4): a float-typed
 * parameter whose address carries a scalar of another family, the exact
 * shape the lane's own type judgment refuses.
 */
final class TypedRatioHouse
{
    public function __construct(
        #[Value('db.ratio')]
        public float $ratio,
    ) {
    }
}
