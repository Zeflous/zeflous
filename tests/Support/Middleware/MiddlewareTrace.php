<?php

declare(strict_types=1);

namespace Zef\Test\Support\Middleware;

/**
 * Shared order collector for pipeline tests: every recording middleware
 * appends its enter/exit marks here, so a test can evidence the onion
 * order a pipeline actually executed (outer -> inner -> terminal -> inner
 * -> outer) as one flat, asserted sequence.
 */
final class MiddlewareTrace
{
    /** @var list<string> */
    private array $marks = [];

    public function enter(string $name): void
    {
        $this->marks[] = $name . ':enter';
    }

    public function exit(string $name): void
    {
        $this->marks[] = $name . ':exit';
    }

    /**
     * @return list<string>
     */
    public function marks(): array
    {
        return $this->marks;
    }
}
