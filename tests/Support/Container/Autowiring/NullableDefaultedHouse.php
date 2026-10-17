<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: a nullable parameter whose null the constructor itself declares. */
final class NullableDefaultedHouse
{
    public function __construct(
        public ?LoggerInterface $logger = null,
    ) {
    }
}
