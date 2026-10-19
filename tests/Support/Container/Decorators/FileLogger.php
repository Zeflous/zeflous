<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Decorators;

use Override;

/**
 * The decorated target's own implementation: the chain's innermost
 * layer, a plain class shape with a literal argument.
 */
final readonly class FileLogger implements LineLogger
{
    public function __construct(
        private string $path = 'app.log',
    ) {
    }

    #[Override]
    public function line(string $text): string
    {
        return 'file(' . $this->path . '):' . $text;
    }
}
