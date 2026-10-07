<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use RuntimeException;
use Zef\Framework\Version;

final class VersionBench
{
    #[Revs(10000)]
    public function benchCurrent(): void
    {
        $version = Version::current();

        if ($version === '') {
            throw new RuntimeException('Version::current() must not be empty.');
        }
    }
}
