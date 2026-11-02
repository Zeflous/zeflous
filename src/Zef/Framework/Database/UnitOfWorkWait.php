<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The unit's default wait: the engine's own microsecond sleep over the
 * millisecond backoff — the one place the unit blocks, behind the
 * sleeper seam the suite observes.
 *
 * @internal
 */
final class UnitOfWorkWait
{
    /**
     * Blocks for the given backoff, in milliseconds.
     */
    public static function block(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}
