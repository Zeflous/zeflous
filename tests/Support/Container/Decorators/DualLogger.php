<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Decorators;

use Override;

/**
 * A class-shaped decorator with two logger dependencies: the inner it
 * wraps and a second logger referenced by its own identifier -- the
 * fixture that proves a decorator may reference (and decorate through)
 * another declared service.
 */
final readonly class DualLogger implements LineLogger
{
    public function __construct(
        private LineLogger $inner,
        private LineLogger $other,
    ) {
    }

    #[Override]
    public function line(string $text): string
    {
        return 'dual(' . $this->inner->line($text) . '|' . $this->other->line($text) . ')';
    }

    public function other(): LineLogger
    {
        return $this->other;
    }
}
