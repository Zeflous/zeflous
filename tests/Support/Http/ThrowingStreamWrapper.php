<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use RuntimeException;

/**
 * Deterministic stream wrapper whose open fails by throwing: an exception
 * raised inside a userland wrapper's stream_open propagates through the
 * engine's fopen() unchanged (verified against the PHP engine), which is
 * the path that must still restore a scoped error handler.
 */
final class ThrowingStreamWrapper
{
    /** @var null|resource */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        throw new RuntimeException('The wrapper refused to open ' . $path . '.');
    }
}
