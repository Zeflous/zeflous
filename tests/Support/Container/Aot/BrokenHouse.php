<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

use RuntimeException;

/**
 * Fixture service whose construction always fails: the warmup lane's
 * fail-closed surface, and the artifact lane's proof that a broken
 * definition halts whichever boot constructs it.
 */
final readonly class BrokenHouse
{
    public function __construct()
    {
        throw new RuntimeException('the broken house cannot be built');
    }
}
