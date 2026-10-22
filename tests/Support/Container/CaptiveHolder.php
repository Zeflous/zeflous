<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container;

/**
 * A holder that captures one resolved dependency -- the request-scope
 * battery's captive shape.
 */
final readonly class CaptiveHolder
{
    public function __construct(
        public object $leaf,
    ) {
    }
}
