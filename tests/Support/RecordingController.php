<?php

declare(strict_types=1);

namespace Zef\Test\Support;

/**
 * Deterministic controller double for `Class@method` handler resolution:
 * records the parameter maps its `show()` receives so dispatch tests can
 * assert what reached the handler, and counts the calls.
 */
final class RecordingController
{
    /** @var list<array<string, string>> */
    public array $received = [];

    public int $calls = 0;

    /**
     * @param array<string, string> $parameters
     */
    public function show(array $parameters): string
    {
        ++$this->calls;
        $this->received[] = $parameters;

        return 'shown:' . ($parameters['id'] ?? 'none');
    }
}
