<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Exception\InvariantViolationException;
use Zef\Framework\Telemetry\BatchSpanProcessor;
use Zef\Framework\Telemetry\InMemorySpanExporter;
use Zef\Framework\Telemetry\TelemetryClock;
use Zef\Framework\Telemetry\Tracer;

/**
 * The telemetry lane's span-lifecycle reference subject -- the
 * per-span costs the observability phase instruments for regression
 * watching (the CI Bench lane runs this file on every push).
 *
 * Not a victory claim. The honest cost structure these numbers pin:
 * an armed tracer's span lifecycle is the identity mint (two random
 * byte draws and their hex renders, the W3C grammar's validation),
 * the clock pair the span opens with, the attribute write, the end's
 * record assembly and its handoff to the processor's queue -- and
 * the void path's cost is the singleton read, nothing else, which
 * the disarmed subject pins as its own number.
 *
 * The bench mirrors `HttpHotPathBench`'s discipline: each method
 * guards its own invariant, so a broken lifecycle fails the lane
 * rather than silently reporting a number.
 */
final readonly class TelemetrySpanBench
{
    private Tracer $tracer;

    private Tracer $disarmedTracer;

    public function __construct()
    {
        $this->tracer = new Tracer(
            new BatchSpanProcessor(new InMemorySpanExporter()),
            new TelemetryClock(),
            true,
        );
        $this->disarmedTracer = new Tracer(
            new BatchSpanProcessor(new InMemorySpanExporter()),
            new TelemetryClock(),
            false,
        );
    }

    #[Revs(2000)]
    public function benchASpanLifecycleMintsRecordsAndHandsOver(): void
    {
        $span = $this->tracer->startSpan('bench.request', ['http.method' => 'GET']);
        $span->setAttribute('http.route', '/bench')
            ->addEvent('cache.miss', ['key' => 'p:2'])
            ->setStatus('OK')
        ;
        $span->end();

        $spanContext = $span->getContext();
        if (
            preg_match('/^[0-9a-f]{32}$/D', $spanContext->traceId) !== 1
            || preg_match('/^[0-9a-f]{16}$/D', $spanContext->spanId) !== 1
            || !$span->isEnded()
        ) {
            throw new InvariantViolationException('The lifecycle must mint a W3C identity and end.');
        }
    }

    #[Revs(5000)]
    public function benchADisarmedTracerAnswersTheVoidSpan(): void
    {
        $span = $this->disarmedTracer->startSpan('bench.request');

        if (
            !$span->isEnded()
            || $span->getContext()->isValid()
        ) {
            throw new InvariantViolationException('The void span must be the ended, invalid answer.');
        }
    }
}
