<?php

declare(strict_types=1);

namespace Zef\Test\Support\Telemetry;

use Override;
use Throwable;
use Zef\Framework\Telemetry\SpanData;
use Zef\Framework\Telemetry\SpanExporterInterface;

/**
 * Flaky span exporter double: the retry lane's observation surface
 * -- a fixed sequence of failures the drain must answer, then the
 * delivery succeeds. Each entry in the sequence is thrown in order
 * (a transient failure as any {@see Throwable}, a permanent one as
 * the plain {@see \InvalidArgumentException} the lane refuses to
 * retry), and every call past the sequence's end delivers.
 *
 * The call counter and the batch log are the lane's two
 * observables: how many attempts a batch earned, and which batches
 * the successful attempts carried.
 *
 * @internal
 */
final class FlakySpanExporter implements SpanExporterInterface
{
    public int $exports = 0;

    /** @var list<list<SpanData>> the batches the successful attempts delivered */
    public array $delivered = [];

    /**
     * @param list<Throwable> $failures the failure sequence, thrown in
     *                                  order before the first delivery
     */
    public function __construct(
        private readonly array $failures = [],
    ) {
    }

    #[Override]
    public function export(array $spans): void
    {
        $failureIndex = $this->exports;

        ++$this->exports;

        if ($failureIndex < \count($this->failures)) {
            throw $this->failures[$failureIndex];
        }

        $this->delivered[] = $spans;
    }

    #[Override]
    public function shutdown(): void
    {
        // The double owns no process and no artefact: the shutdown
        // contract the exporter interface spells is the empty one,
        // and the suite's drain never reads it for observation.
    }
}
