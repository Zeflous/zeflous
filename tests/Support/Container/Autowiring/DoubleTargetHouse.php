<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Framework\Container\Autowiring\Target;

/** Fixture: a doubled #[Target] -- an ambiguity to surface, not a precedence to guess. */
final class DoubleTargetHouse
{
    public function __construct(
        #[Target(FileLogger::class)]
        #[Target(FileLogger::class)] // @phpstan-ignore-line
        public LoggerInterface $logger,
    ) {
    }
}
