<?php

declare(strict_types=1);

namespace Zef\Test\Support\Telemetry;

use Override;
use RuntimeException;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The throwing meter double: the counter surface as an always-failing
 * seam, what the best-effort emission containment's own unit reads --
 * every verb throws the same named failure, so a test asserts that a
 * meter which cannot record its observation never turns the caller's
 * own answer into a post-effect exception (the observation is never
 * worth the query's own answer).
 */
final class ThrowingMeter implements MeterInterface
{
    #[Override]
    public function increment(string $name, float|int $value = 1, array $attributes = []): void
    {
        throw new RuntimeException('the meter seam refused to record');
    }

    #[Override]
    public function observe(string $name, float $value, array $attributes = []): void
    {
        throw new RuntimeException('the meter seam refused to observe');
    }

    #[Override]
    public function snapshot(): array
    {
        throw new RuntimeException('the meter seam refused to snapshot');
    }
}
