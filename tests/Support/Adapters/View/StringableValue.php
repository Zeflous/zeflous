<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\View;

use Override;
use Stringable;

/**
 * A plain Stringable carrying a fixed text -- the escape fold's
 * Stringable arm, spelled as a named fixture because the toolchain
 * disagrees with itself over anonymous-class spellings.
 *
 * @internal
 */
final readonly class StringableValue implements Stringable
{
    public function __construct(
        private string $text,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->text;
    }
}
