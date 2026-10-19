<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Decorators;

use Override;

/**
 * The class-shaped decorator: wraps the inner logger and answers with
 * its prefix around the inner's answer -- the extra constructor
 * argument (the prefix literal) proves a decorator's own arguments
 * travel alongside the inner reference.
 */
final readonly class PrefixedLogger implements LineLogger
{
    public function __construct(
        private LineLogger $lineLogger,
        private string $prefix,
    ) {
    }

    #[Override]
    public function line(string $text): string
    {
        return $this->prefix . '(' . $this->lineLogger->line($text) . ')';
    }
}
