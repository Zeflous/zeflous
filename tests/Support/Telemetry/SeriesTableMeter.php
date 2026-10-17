<?php

declare(strict_types=1);

namespace Zef\Test\Support\Telemetry;

use LogicException;
use Override;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * Fixed-table {@see MeterInterface} test double: answers the exact
 * snapshot a render test pins, refusing the recording methods a
 * render never calls -- the double the exposition suite uses to hold
 * series shapes the counting engine cannot mint (a NaN sum, an
 * infinite sum, a table past the render's own ceiling).
 */
final readonly class SeriesTableMeter implements MeterInterface
{
    /**
     * @param array<string,array{count:float|int,sum:float,attributes:array<string,mixed>}> $snapshot
     */
    public function __construct(
        private array $snapshot,
    ) {
    }

    #[Override]
    public function increment(string $name, float|int $value = 1, array $attributes = []): void
    {
        throw new LogicException('The fixed-table meter does not record.');
    }

    #[Override]
    public function observe(string $name, float $value, array $attributes = []): void
    {
        throw new LogicException('The fixed-table meter does not record.');
    }

    #[Override]
    public function snapshot(): array
    {
        return $this->snapshot;
    }
}
