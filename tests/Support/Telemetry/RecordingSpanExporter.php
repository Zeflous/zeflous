<?php

declare(strict_types=1);

namespace Zef\Test\Support\Telemetry;

use Override;
use RuntimeException;
use Zef\Framework\Telemetry\SpanData;
use Zef\Framework\Telemetry\SpanExporterInterface;

/**
 * Recording span exporter double: the exporter port's observation
 * surface -- every delivered batch and every delivered span lands
 * in a list the test reads, the call counter pins the delivery
 * discipline (attempts counted whether they succeed or not), and
 * the two failure switches turn the sink into the throwing
 * exporter the best-effort drain tests need.
 */
final class RecordingSpanExporter implements SpanExporterInterface
{
    public int $exports = 0;

    public int $shutdowns = 0;

    public bool $throwOnExport = false;

    public bool $throwOnShutdown = false;

    /** @var list<list<SpanData>> the batches delivered, in delivery order */
    public array $batches = [];

    #[Override]
    public function export(array $spans): void
    {
        ++$this->exports;

        if ($this->throwOnExport) {
            throw new RuntimeException('the delivery lane failed');
        }

        $this->batches[] = $spans;
    }

    #[Override]
    public function shutdown(): void
    {
        ++$this->shutdowns;

        if ($this->throwOnShutdown) {
            throw new RuntimeException('the sink failed to close');
        }
    }

    /**
     * The spans delivered so far, flattened across batches, in
     * delivery order.
     *
     * @return list<SpanData>
     */
    public function spans(): array
    {
        if ($this->batches === []) {
            return [];
        }

        return array_merge(...$this->batches);
    }
}
