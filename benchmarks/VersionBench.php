<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Exception\InvariantViolationException;
use Zef\Framework\Version;

final class VersionBench
{
    #[Revs(10000)]
    public function benchCurrent(): void
    {
        $version = Version::current();

        if ($version === '') {
            throw new InvariantViolationException('Version::current() must not be empty.');
        }
    }
}
