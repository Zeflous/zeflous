<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\Span;
use Zef\Framework\Telemetry\SpanContext;
use Zef\Test\Support\Telemetry\RecordingEnd;
use Zef\Test\Support\Telemetry\SteppingTelemetryClock;

/**
 * The live span's lifecycle, on its own terms: the timeline the
 * injected clock freezes (the monotonic start, the wall start, every
 * event's wall timestamp, the monotonic end), the recording contract
 * (verbatim attributes, ignored writes once ended), the status
 * vocabulary's fold and refusal, and the one handoff -- the frozen
 * record, with its derived wall end, delivered exactly once.
 *
 * @internal
 */
#[CoversClass(Span::class)]
final class SpanTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string SPAN_ID = '00f067aa0ba902b7';

    private SteppingTelemetryClock $steppingTelemetryClock;

    private RecordingEnd $recordingEnd;

    #[Override]
    protected function setUp(): void
    {
        $this->steppingTelemetryClock = new SteppingTelemetryClock(
            monotonicEpoch: 1_000,
            monotonicStep: 5_000,
            wallEpoch: 1_000_000_000,
            wallStep: 1_000,
        );
        $this->recordingEnd = new RecordingEnd();
    }

    public function testASpanTakesItsWholeTimelineFromTheClock(): void
    {
        $span = $this->span('request');

        self::assertFalse($span->isEnded());
        self::assertSame(
            1,
            $this->steppingTelemetryClock->monotonicReadCount(),
            'the open takes one monotonic reading',
        );
        self::assertSame(1, $this->steppingTelemetryClock->wallReadCount(), 'the open takes one wall reading');

        $span->end();

        $record = $this->recordingEnd->records[0];
        self::assertSame(1_000, $record->startNs, "the monotonic start is the clock's first reading");
        self::assertSame(1_000_000_000, $record->startUnixNano, "the wall start is the clock's first wall reading");
        self::assertSame(6_000, $record->endNs, "the monotonic end is the clock's second reading");
        self::assertSame(1_000_005_000, $record->endUnixNano, 'the wall end is derived from the monotonic delta');
        self::assertSame(2, $this->steppingTelemetryClock->monotonicReadCount());
        self::assertSame(1, $this->steppingTelemetryClock->wallReadCount());
    }

    public function testAnExplicitEndReadingOverridesTheClock(): void
    {
        $span = $this->span('request');
        $span->end(9_000);

        $record = $this->recordingEnd->records[0];
        self::assertSame(9_000, $record->endNs);
        self::assertSame(1_000_008_000, $record->endUnixNano);
        self::assertSame(
            1,
            $this->steppingTelemetryClock->monotonicReadCount(),
            'an explicit end never reads the clock',
        );
    }

    public function testABrokenClockEndClampsToTheStart(): void
    {
        $span = $this->span('request');
        $span->end(500);

        $record = $this->recordingEnd->records[0];
        self::assertSame(1_000, $record->endNs, 'the end clamps to the start');
        self::assertSame(1_000_000_000, $record->endUnixNano, 'the derived wall end stays at the wall start');
        self::assertSame(0.0, $record->durationSeconds());
    }

    public function testTheEndHandsTheRecordOverExactlyOnce(): void
    {
        $span = $this->span('request');

        $span->end();
        $span->end(9_000);
        $span->end();

        self::assertCount(1, $this->recordingEnd->records);
        self::assertSame(6_000, $this->recordingEnd->records[0]->endNs, 'the first close wins');
        self::assertTrue($span->isEnded());
    }

    public function testOpeningAttributesAreRecordedVerbatim(): void
    {
        $span = $this->span('request', [
            'http.method' => 'GET',
            'attempts' => 2,
            'nested' => ['db', 'cache'],
        ]);

        $span->end();

        self::assertSame(
            ['http.method' => 'GET', 'attempts' => 2, 'nested' => ['db', 'cache']],
            $this->recordingEnd->records[0]->attributes,
            'the recording is verbatim until the hygiene lane lands',
        );
    }

    public function testAnEmptyOpeningKeyIsNotARecording(): void
    {
        $span = $this->span('request', ['' => 'void']);

        $span->end();

        self::assertSame([], $this->recordingEnd->records[0]->attributes);
    }

    public function testTheRecordingChainsReturnTheSpan(): void
    {
        $span = $this->span('request');

        self::assertSame($span, $span->setAttribute('http.route', '/toko'));
        self::assertSame($span, $span->setAttributes(['retries' => 1, '' => 'ignored']));
        self::assertSame($span, $span->addEvent('cache.miss', ['key' => 'p:2']));
        self::assertSame($span, $span->setStatus('error', 'upstream 503'));

        $span->end();

        $record = $this->recordingEnd->records[0];
        self::assertSame('/toko', $record->attributes['http.route']);
        self::assertSame(1, $record->attributes['retries']);
        self::assertArrayNotHasKey('', $record->attributes);
        self::assertSame('ERROR', $record->status);
        self::assertSame('upstream 503', $record->statusDescription);
    }

    public function testEventsCarryTheWallClockTimestampAndVerbatimAttributes(): void
    {
        $span = $this->span('request');
        $span->addEvent('first');
        $span->addEvent('second', ['attempts' => 2]);

        $span->end();

        $events = $this->recordingEnd->records[0]->events;
        self::assertCount(2, $events);
        self::assertSame('first', $events[0]['name']);
        self::assertSame(1_000_001_000, $events[0]['time_unix_nano'], 'the first event is the second wall reading');
        self::assertSame([], $events[0]['attributes']);
        self::assertSame('second', $events[1]['name']);
        self::assertSame(1_000_002_000, $events[1]['time_unix_nano']);
        self::assertSame(['attempts' => 2], $events[1]['attributes']);
        self::assertSame(
            3,
            $this->steppingTelemetryClock->wallReadCount(),
            'the open, the two events: three wall reads',
        );
    }

    public function testAnEmptyEventNameIsNotARecording(): void
    {
        $span = $this->span('request');

        $span->addEvent('', ['ignored' => true]);

        $span->end();

        self::assertSame([], $this->recordingEnd->records[0]->events);
        self::assertSame(1, $this->steppingTelemetryClock->wallReadCount(), 'an ignored event never reads the clock');
    }

    public function testTheStatusFoldsCaseAndKeepsTheDescription(): void
    {
        $span = $this->span('request');

        $span->setStatus('ok');
        $span->end();

        $record = $this->recordingEnd->records[0];
        self::assertSame('OK', $record->status);
        self::assertNull($record->statusDescription);
    }

    public function testAnUnknownStatusIsRefusedWithTheKnownList(): void
    {
        $span = $this->span('request');

        try {
            $span->setStatus('BOGUS');
            self::fail('An unknown status must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'Invalid span status: BOGUS (known statuses: UNSET, OK, ERROR).',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testWritesAfterTheEndAreIgnored(): void
    {
        $span = $this->span('request');
        $span->setAttribute('http.method', 'GET');
        $span->addEvent('before-end');
        $span->setStatus('OK');
        $span->end();

        $wallReadsAtClose = $this->steppingTelemetryClock->wallReadCount();

        $span->setAttribute('late', 'write');
        $span->setAttributes(['late-map' => 'write']);
        $span->addEvent('late-event');
        $span->setStatus('ERROR', 'too late');

        $record = $this->recordingEnd->records[0];
        self::assertSame(['http.method' => 'GET'], $record->attributes);
        self::assertCount(1, $record->events);
        self::assertSame('before-end', $record->events[0]['name']);
        self::assertSame('OK', $record->status);
        self::assertNull($record->statusDescription);
        self::assertSame(
            $wallReadsAtClose,
            $this->steppingTelemetryClock->wallReadCount(),
            'a late event never reads the clock',
        );
    }

    public function testTheContextAndParentRideWhole(): void
    {
        $context = new SpanContext(self::TRACE_ID, self::SPAN_ID);
        $parent = new SpanContext(self::TRACE_ID, '1111111111111111');

        $span = new Span('request', $context, $parent, $this->steppingTelemetryClock, $this->recordingEnd->closure());
        $span->end();

        self::assertSame($context, $span->getContext());
        self::assertSame($context, $this->recordingEnd->records[0]->context);
        self::assertSame($parent, $this->recordingEnd->records[0]->parent);
    }

    /**
     * One span over the test's frozen clock and recording end.
     *
     * @param array<string,mixed> $attributes
     */
    private function span(string $name, array $attributes = []): Span
    {
        return new Span(
            $name,
            new SpanContext(self::TRACE_ID, self::SPAN_ID),
            null,
            $this->steppingTelemetryClock,
            $this->recordingEnd->closure(),
            $attributes,
        );
    }
}
