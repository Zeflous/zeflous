<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

use Override;

/** Fixture: the concrete implementation a binding may point at. */
final class FileLogger implements LoggerInterface
{
    public function __construct(
        public string $path = 'default.log',
    ) {
    }

    #[Override]
    public function log(string $message): string
    {
        return $this->path . ':' . $message;
    }
}
