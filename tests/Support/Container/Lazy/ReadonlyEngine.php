<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Lazy;

/**
 * Fixture readonly class: a readonly class carries a native lazy proxy
 * as honestly as a plain one does (the proxy forwards every member to
 * the real instance), so the lazy lane accepts it.
 */
final readonly class ReadonlyEngine
{
    public function __construct(
        public int $thrust,
    ) {
    }

    public function thrust(): int
    {
        return $this->thrust;
    }
}
