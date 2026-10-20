<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

/**
 * The replacement's own shape: the wrapper a {@see
 * ReplacingResolvedListener} answers -- an object that carries the
 * instance it replaced, the one observable difference between the
 * constructed and the recorded answer.
 */
final readonly class ReplacedService
{
    public function __construct(
        public mixed $inner,
    ) {
    }
}
