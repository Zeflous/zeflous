<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

/**
 * The interception tests' shared observation channel: a log service
 * the fixtures record into -- registered under the container as an
 * ordinary service and referenced through the entries' argument
 * grammar, the one channel a knob-materialised interceptor or
 * listener has for observable state.
 */
final class InterceptionLog
{
    /** @var list<string> */
    private array $lines = [];

    public function record(string $line): void
    {
        $this->lines[] = $line;
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return $this->lines;
    }
}
