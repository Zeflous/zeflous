<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Telemetry\BatchRetryLane;
use Zef\Framework\Telemetry\BatchSpanProcessor;
use Zef\Framework\Telemetry\OtlpExporterException;
use Zef\Framework\Telemetry\RetryBackoffPolicy;
use Zef\Framework\Telemetry\SpanBatchQueue;
use Zef\Framework\Telemetry\SpanContext;
use Zef\Framework\Telemetry\SpanData;
use Zef\Test\Support\Telemetry\FlakySpanExporter;
use Zef\Test\Support\Telemetry\RecordingSpanExporter;

/**
 * The bounded queue and its drain, on their own terms: the bounds'
 * own refusals, the queue cap's silent drop, the batch splice's
 * exact slicing, the one-attempt delivery that swallows the
 * exporter's failures and never takes the process down with it,
 * the retry lane the export family lands on the same seam -- the
 * transient failure's earned backoff, the permanent rejection's
 * single attempt, the exhausted lane's lost batch -- observed
 * through the sleeper seam the wait hides behind, and the
 * shutdown's ordering -- drain, close, refuse, exactly once.
 *
 * @internal
 */
#[CoversClass(BatchSpanProcessor::class)]
#[CoversClass(BatchRetryLane::class)]
#[CoversClass(SpanBatchQueue::class)]
final class BatchSpanProcessorTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const string SPAN_ID = '00f067aa0ba902b7';

    private SpanContext $spanContext;

    private RecordingSpanExporter $recordingSpanExporter;

    #[Override]
    protected function setUp(): void
    {
        $this->spanContext = new SpanContext(self::TRACE_ID, self::SPAN_ID);
        $this->recordingSpanExporter = new RecordingSpanExporter();
    }

    public function testTheBoundsAreRefusedBelowOne(): void
    {
        try {
            new BatchSpanProcessor($this->recordingSpanExporter, 0, 1);
            self::fail('A zero queue bound must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('maxQueueSize must be >= 1.', $invalidArgumentException->getMessage());
        }

        try {
            new BatchSpanProcessor($this->recordingSpanExporter, 1, 0);
            self::fail('A zero batch size must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('batchSize must be >= 1.', $invalidArgumentException->getMessage());
        }
    }

    public function testAQueueBoundOfOneIsTheValidFloor(): void
    {
        $batchSpanProcessor = new BatchSpanProcessor($this->recordingSpanExporter, 1, 1);

        $this->queue($batchSpanProcessor, ['a', 'b']);
        $batchSpanProcessor->flush();

        self::assertSame(
            [['a']],
            $this->namesByBatch(),
            'the floor constructs, queues one span and delivers it alone -- the bound drops the second',
        );
    }

    public function testTheDefaultBoundsAreTheConstructorsOwnContract(): void
    {
        $batchSpanProcessor = new BatchSpanProcessor($this->recordingSpanExporter);

        $this->queue($batchSpanProcessor, array_map(
            static fn (int $index): string => 'span-' . $index,
            range(1, 2049),
        ));
        $batchSpanProcessor->flush();

        self::assertCount(
            2048,
            $this->recordingSpanExporter->spans(),
            'the default queue bound holds 2048 completed spans',
        );
        self::assertCount(
            8,
            $this->recordingSpanExporter->batches,
            'the default batch size slices the bound into eight batches',
        );
        self::assertCount(256, $this->recordingSpanExporter->batches[0], 'each default batch carries 256 spans');
    }

    public function testTheFlushSplicesTheQueueIntoBatches(): void
    {
        $batchSpanProcessor = new BatchSpanProcessor($this->recordingSpanExporter, 2048, 2);

        $this->queue($batchSpanProcessor, ['a', 'b', 'c', 'd', 'e']);
        $batchSpanProcessor->flush();

        self::assertSame([2, 2, 1], $this->batchSizes(), 'five spans slice into batches of two, two and one');
        self::assertSame(['a', 'b', 'c', 'd', 'e'], $this->names(), 'delivery order is queue order');

        $batchSpanProcessor->flush();

        self::assertSame(
            3,
            $this->recordingSpanExporter->exports,
            'the queue drains fully -- a second flush delivers nothing',
        );
    }

    public function testAnEmptyQueueFlushDeliversNothing(): void
    {
        $batchSpanProcessor = new BatchSpanProcessor($this->recordingSpanExporter);

        $batchSpanProcessor->flush();

        self::assertSame(0, $this->recordingSpanExporter->exports);
        self::assertSame([], $this->recordingSpanExporter->batches);
    }

    public function testTheQueueCapDropsBeyondTheBound(): void
    {
        $batchSpanProcessor = new BatchSpanProcessor($this->recordingSpanExporter, 2, 2);

        $this->queue($batchSpanProcessor, ['a', 'b', 'c']);
        $batchSpanProcessor->flush();

        self::assertSame(['a', 'b'], $this->names(), 'the third span beyond the bound is dropped, never queued');
    }

    public function testAFailedDeliveryLosesItsBatchAndTheDrainContinues(): void
    {
        $batchSpanProcessor = new BatchSpanProcessor($this->recordingSpanExporter, 2048, 2);
        $this->recordingSpanExporter->throwOnExport = true;

        $this->queue($batchSpanProcessor, ['a', 'b', 'c']);
        $batchSpanProcessor->flush();

        self::assertSame(2, $this->recordingSpanExporter->exports, 'both batches were attempted');
        self::assertSame(0, $this->recordingSpanExporter->shutdowns, 'no shutdown happened');
        $this->recordingSpanExporter->throwOnExport = false;

        $this->queue($batchSpanProcessor, ['d']);
        $batchSpanProcessor->flush();

        self::assertSame(['d'], $this->names(), 'the drain is healthy after the failure');
    }

    public function testATransientFailureEarnsTheRetriesThePolicyDeclares(): void
    {
        // The lane's own exception is the transient failure the
        // retries exist for, and the waits between the attempts are
        // the policy's own curve -- the initial delay, its doubling,
        // and the cap the doubling clamps at -- observed through the
        // sleeper seam, never a blocking call the suite would wait
        // on for real.
        $sleeps = [];
        $flakySpanExporter = new FlakySpanExporter([
            new OtlpExporterException('OTLP exporter transient rejection.'),
            new OtlpExporterException('OTLP exporter transient rejection.'),
            new OtlpExporterException('OTLP exporter transient rejection.'),
        ]);
        $batchSpanProcessor = new BatchSpanProcessor(
            $flakySpanExporter,
            2048,
            2,
            new RetryBackoffPolicy(3, 5, 20),
            static function (int $milliseconds) use (&$sleeps): void {
                $sleeps[] = $milliseconds;
            },
        );

        $this->queue($batchSpanProcessor, ['a']);
        $batchSpanProcessor->flush();

        self::assertSame(4, $flakySpanExporter->exports, 'one attempt plus the three declared retries');
        self::assertSame(['a'], $this->flakyNames($flakySpanExporter), 'the fourth attempt delivered the batch');
        self::assertSame([5, 10, 20], $sleeps, 'the backoff doubles from the initial delay and clamps at the cap');
    }

    public function testThePermanentRejectionIsNeverRetried(): void
    {
        // The plain InvalidArgumentException is the permanent
        // rejection -- an answer no retry addresses -- so the batch
        // earns exactly one attempt and no wait, whatever the policy
        // declares.
        $sleeps = [];
        $flakySpanExporter = new FlakySpanExporter([
            new InvalidArgumentException('OTLP exporter permanent rejection.'),
        ]);
        $batchSpanProcessor = new BatchSpanProcessor(
            $flakySpanExporter,
            2048,
            2,
            new RetryBackoffPolicy(3, 5, 20),
            static function (int $milliseconds) use (&$sleeps): void {
                $sleeps[] = $milliseconds;
            },
        );

        $this->queue($batchSpanProcessor, ['a']);
        $batchSpanProcessor->flush();

        self::assertSame(1, $flakySpanExporter->exports, 'the permanent rejection earns no retry');
        self::assertSame([], $flakySpanExporter->delivered, 'the batch is lost');
        self::assertSame([], $sleeps, 'no wait ever happens');
    }

    public function testTheExhaustedLaneLosesTheBatchAndTheDrainContinues(): void
    {
        // Any other throwable is transient too -- the runtime's own
        // socket failure rides the same lane -- and a batch whose
        // retries run out is lost while the drain walks on to the
        // next batch, which the recovered sink delivers.
        $sleeps = [];
        $flakySpanExporter = new FlakySpanExporter([
            new RuntimeException('connection reset'),
            new RuntimeException('connection reset'),
            new RuntimeException('connection reset'),
        ]);
        $batchSpanProcessor = new BatchSpanProcessor(
            $flakySpanExporter,
            2048,
            1,
            new RetryBackoffPolicy(2, 5, 40),
            static function (int $milliseconds) use (&$sleeps): void {
                $sleeps[] = $milliseconds;
            },
        );

        $this->queue($batchSpanProcessor, ['a', 'b']);
        $batchSpanProcessor->flush();

        self::assertSame(
            4,
            $flakySpanExporter->exports,
            'the first batch spent its three attempts, the second delivered',
        );
        self::assertSame(['b'], $this->flakyNames($flakySpanExporter), 'only the second batch was delivered');
        self::assertSame([5, 10], $sleeps, 'the exhausted batch waited the policy-declared backoff first');
    }

    public function testTheZeroRetryPolicyIsTheOneAttemptPosture(): void
    {
        // The shipped default, spelled as the explicit policy the
        // composition seam wires: a zero-retry declaration earns one
        // attempt and never a wait -- the posture the core shipped
        // before the lane arrived, unchanged.
        $sleeps = [];
        $flakySpanExporter = new FlakySpanExporter([
            new OtlpExporterException('OTLP exporter transient rejection.'),
        ]);
        $batchSpanProcessor = new BatchSpanProcessor(
            $flakySpanExporter,
            2048,
            2,
            new RetryBackoffPolicy(0, 5, 40),
            static function (int $milliseconds) use (&$sleeps): void {
                $sleeps[] = $milliseconds;
            },
        );

        $this->queue($batchSpanProcessor, ['a']);
        $batchSpanProcessor->flush();

        self::assertSame(1, $flakySpanExporter->exports, 'the zero-retry posture attempts once');
        self::assertSame([], $flakySpanExporter->delivered, 'the failed batch is lost');
        self::assertSame([], $sleeps, 'no wait ever happens');
    }

    public function testTheShutdownDrainsClosesAndRefuses(): void
    {
        $batchSpanProcessor = new BatchSpanProcessor($this->recordingSpanExporter, 2048, 2);

        $this->queue($batchSpanProcessor, ['a', 'b', 'c']);
        $batchSpanProcessor->shutdown();

        self::assertSame([['a', 'b'], ['c']], $this->namesByBatch(), 'the shutdown drains what remains');
        self::assertSame(1, $this->recordingSpanExporter->shutdowns);

        $this->queue($batchSpanProcessor, ['late']);

        self::assertSame(2, $this->recordingSpanExporter->exports, 'a closed processor refuses every later enqueue');

        $batchSpanProcessor->flush();

        self::assertSame(2, $this->recordingSpanExporter->exports, 'a post-shutdown flush delivers nothing new');
    }

    public function testTheShutdownIsIdempotent(): void
    {
        $batchSpanProcessor = new BatchSpanProcessor($this->recordingSpanExporter);

        $batchSpanProcessor->shutdown();
        $batchSpanProcessor->shutdown();

        self::assertSame(1, $this->recordingSpanExporter->shutdowns, 'the sink closes exactly once');
    }

    public function testAFailingSinkShutdownTakesNothingWithIt(): void
    {
        $batchSpanProcessor = new BatchSpanProcessor($this->recordingSpanExporter);
        $this->recordingSpanExporter->throwOnShutdown = true;

        $this->queue($batchSpanProcessor, ['a']);
        $batchSpanProcessor->shutdown();

        self::assertSame(['a'], $this->names(), 'the drain completed before the sink closed');
        self::assertSame(1, $this->recordingSpanExporter->shutdowns);
    }

    public function testTheDeliveredRecordsRideWhole(): void
    {
        $batchSpanProcessor = new BatchSpanProcessor($this->recordingSpanExporter);
        $record = $this->record('a');

        $batchSpanProcessor->onEnd($record);
        $batchSpanProcessor->flush();

        self::assertSame($record, $this->recordingSpanExporter->spans()[0]);
    }

    /**
     * Queues one record per name.
     *
     * @param list<string> $names
     */
    private function queue(BatchSpanProcessor $batchSpanProcessor, array $names): void
    {
        foreach ($names as $name) {
            $batchSpanProcessor->onEnd($this->record($name));
        }
    }

    /**
     * One completed-span record with the given name.
     */
    private function record(string $name): SpanData
    {
        return new SpanData(
            $name,
            $this->spanContext,
            null,
            1,
            2,
            3,
            4,
            'UNSET',
            null,
            [],
            [],
        );
    }

    /**
     * The delivered spans' names, flattened.
     *
     * @return list<string>
     */
    private function names(): array
    {
        return array_map(
            static fn (SpanData $spanData): string => $spanData->name,
            $this->recordingSpanExporter->spans(),
        );
    }

    /**
     * The delivered spans' names, batch by batch.
     *
     * @return list<list<string>>
     */
    private function namesByBatch(): array
    {
        return array_map(
            static fn (array $batch): array => array_map(
                static fn (SpanData $spanData): string => $spanData->name,
                $batch,
            ),
            $this->recordingSpanExporter->batches,
        );
    }

    /**
     * The delivered batches' sizes.
     *
     * @return list<int>
     */
    private function batchSizes(): array
    {
        return array_map(\count(...), $this->recordingSpanExporter->batches);
    }

    /**
     * The flaky double's delivered spans' names, flattened across
     * the batches its successful attempts carried.
     *
     * @return list<string>
     */
    private function flakyNames(FlakySpanExporter $flakySpanExporter): array
    {
        return array_map(
            static fn (SpanData $spanData): string => $spanData->name,
            array_merge([], ...$flakySpanExporter->delivered),
        );
    }
}
