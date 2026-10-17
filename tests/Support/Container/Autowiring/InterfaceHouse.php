<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: an interface-typed parameter with no binding of its own. */
final class InterfaceHouse
{
    public function __construct(
        public LoggerInterface $logger,
    ) {
    }
}
