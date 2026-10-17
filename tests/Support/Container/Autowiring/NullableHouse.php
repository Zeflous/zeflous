<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: a required nullable parameter -- null must be earned, not guessed. */
final class NullableHouse
{
    public function __construct(
        public ?LoggerInterface $logger,
    ) {
    }
}
