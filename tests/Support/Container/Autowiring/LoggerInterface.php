<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: an interface a planned parameter may ask for a binding of. */
interface LoggerInterface
{
    public function log(string $message): string;
}
