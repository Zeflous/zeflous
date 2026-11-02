<?php

declare(strict_types=1);

namespace Zef\Test\Support\Telemetry;

use Override;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The recording meter double: the counter/observation surface as an
 * append-only tape, what the unbounded-statement audit emission's
 * own unit reads -- every increment and observe recorded as the
 * triple it arrived as, so a test asserts the series, the value and
 * the dimensions the emission spelled, never a rendered exposition
 * format.
 */
final class RecordingMeter implements MeterInterface
{
    /** @var list<array{name: string, value: float|int, attributes: array<string, mixed>}> */
    public array $increments = [];

    /** @var list<array{name: string, value: float, attributes: array<string, mixed>}> */
    public array $observations = [];

    #[Override]
    public function increment(string $name, float|int $value = 1, array $attributes = []): void
    {
        $this->increments[] = ['name' => $name, 'value' => $value, 'attributes' => $attributes];
    }

    #[Override]
    public function observe(string $name, float $value, array $attributes = []): void
    {
        $this->observations[] = ['name' => $name, 'value' => $value, 'attributes' => $attributes];
    }

    #[Override]
    public function snapshot(): array
    {
        return [];
    }
}
