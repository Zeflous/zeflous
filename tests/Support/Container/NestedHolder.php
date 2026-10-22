<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container;

/**
 * A holder over a fellow singleton -- the nested-captor shape.
 */
final readonly class NestedHolder
{
    public function __construct(
        public object $inner,
    ) {
    }
}
