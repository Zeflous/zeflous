<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Zef\Framework\Container\Autowiring\Target;

/** Fixture: the #[Target] attribute binds the interface-typed parameter. */
final class TargetedHouse
{
    public function __construct(
        #[Target(FileLogger::class)]
        public LoggerInterface $logger,
    ) {
    }
}
