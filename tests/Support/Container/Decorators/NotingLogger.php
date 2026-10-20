<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Decorators;

use Override;

/**
 * A decorated target that also carries a method-call injection: the
 * `calls` lane and the decoration lane over one declaration, the
 * re-homed base keeping the calls the declaration spelled.
 */
final class NotingLogger implements LineLogger
{
    /** @var list<string> */
    private array $notes = [];

    public function __construct(
        private readonly string $path = 'app.log',
    ) {
    }

    #[Override]
    public function line(string $text): string
    {
        return 'file(' . $this->path . '):' . $text;
    }

    public function note(string $text): void
    {
        $this->notes[] = $text;
    }

    /**
     * @return list<string>
     */
    public function notes(): array
    {
        return $this->notes;
    }
}
