<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container;

/**
 * A branch over a scoped leaf: the request-scope test battery's holder
 * for a dependency resolved mid-factory (the transitive-capture shape).
 */
final readonly class ScopedBranch
{
    public function __construct(
        public object $leaf,
    ) {
    }
}
