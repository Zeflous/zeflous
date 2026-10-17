<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: an interface-typed variadic -- the declared implementations gather. */
final class LoggerVariadicHouse
{
    /** @var list<LoggerInterface> */
    public array $loggers;

    public function __construct(
        LoggerInterface ...$loggers,
    ) {
        $this->loggers = array_values($loggers);
    }
}
