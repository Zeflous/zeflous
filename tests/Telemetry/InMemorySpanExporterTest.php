<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\InMemorySpanExporter;
use Zef\Framework\Telemetry\SpanContext;
use Zef\Framework\Telemetry\SpanData;

/**
 * The in-process sink, on its own terms: the delivery order, the
 * read-only observation surface, the reset that clears it, and the
 * no-op close (the memory the spans sit in is the sink's whole
 * state).
 *
 * @internal
 */
#[CoversClass(InMemorySpanExporter::class)]
final class InMemorySpanExporterTest extends TestCase
{
    public function testDeliveriesLandInDeliveryOrder(): void
    {
        $inMemorySpanExporter = new InMemorySpanExporter();

        $inMemorySpanExporter->export([$this->record('a'), $this->record('b')]);
        $inMemorySpanExporter->export([$this->record('c')]);

        $spans = $inMemorySpanExporter->spans();
        self::assertCount(3, $spans);
        self::assertSame('a', $spans[0]->name);
        self::assertSame('b', $spans[1]->name);
        self::assertSame('c', $spans[2]->name);
    }

    public function testTheRecordsRideWhole(): void
    {
        $inMemorySpanExporter = new InMemorySpanExporter();
        $record = $this->record('a');

        $inMemorySpanExporter->export([$record]);

        self::assertSame($record, $inMemorySpanExporter->spans()[0]);
    }

    public function testAnEmptyBatchDeliversNothing(): void
    {
        $inMemorySpanExporter = new InMemorySpanExporter();

        $inMemorySpanExporter->export([]);

        self::assertSame([], $inMemorySpanExporter->spans());
    }

    public function testTheResetClearsTheCollectedRecord(): void
    {
        $inMemorySpanExporter = new InMemorySpanExporter();
        $inMemorySpanExporter->export([$this->record('a')]);

        $inMemorySpanExporter->reset();

        self::assertSame([], $inMemorySpanExporter->spans());
    }

    public function testTheCloseIsANoOp(): void
    {
        $inMemorySpanExporter = new InMemorySpanExporter();
        $inMemorySpanExporter->export([$this->record('a')]);

        $inMemorySpanExporter->shutdown();

        self::assertCount(1, $inMemorySpanExporter->spans(), 'the close leaves the collected record standing');
    }

    /**
     * One completed-span record with the given name.
     */
    private function record(string $name): SpanData
    {
        $spanContext = new SpanContext('4bf92f3577b34da6a3ce929d0e0e4736', '00f067aa0ba902b7');

        return new SpanData($name, $spanContext, null, 1, 2, 3, 4, 'UNSET', null, [], []);
    }
}
