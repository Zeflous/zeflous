<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\MeterCount;
use Zef\Framework\Telemetry\MeterDimensions;
use Zef\Framework\Telemetry\MeterEviction;
use Zef\Framework\Telemetry\MeterFold;
use Zef\Framework\Telemetry\MeterOverflow;
use Zef\Framework\Telemetry\MeterSlot;
use Zef\Framework\Telemetry\MeterTable;

/**
 * The metrics engine, on its own terms: the counter and the
 * observation series' accumulation, the composite identity two
 * recordings of the same dimensions share however the caller ordered
 * the map, the framework-owned names' dimension allow-lists (and the
 * bounded extras the error-shaping series carry), the lifecycle
 * vocabulary's closed fold, and the series ceiling's overflow bucket
 * -- the campaign's closing family's engine half.
 *
 * @internal
 */
#[CoversClass(CounterMeter::class)]
#[CoversClass(MeterSlot::class)]
#[CoversClass(MeterTable::class)]
#[CoversClass(MeterOverflow::class)]
#[CoversClass(MeterEviction::class)]
#[CoversClass(MeterFold::class)]
#[CoversClass(MeterDimensions::class)]
#[CoversClass(MeterCount::class)]
final class CounterMeterTest extends TestCase
{
    public function testTheCounterAccumulatesUnderOneName(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.custom.requests');
        $counterMeter->increment('zef.custom.requests');
        $counterMeter->increment('zef.custom.requests', 5);

        self::assertSame(
            [
                'count' => 7,
                'sum' => 7.0,
                'attributes' => [],
            ],
            $counterMeter->snapshot()[MeterSlot::key('zef.custom.requests', [])],
            'a pure counter keeps its count and sum in step',
        );
    }

    public function testTheObservationSeriesCountsAndSumsSeparately(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->observe('zef.custom.duration', 0.5);
        $counterMeter->observe('zef.custom.duration', 1.5);

        $series = $counterMeter->snapshot()[MeterSlot::key('zef.custom.duration', [])];
        self::assertSame(2, $series['count']);
        self::assertSame(2.0, $series['sum']);
    }

    /**
     * The monotonic export contract (issue #788, finding 5): the OTLP
     * payload declares `isMonotonic: true` and the Prometheus render
     * spells `# TYPE counter` -- a negative increment would make both
     * declarations lies, and Prometheus would read the drop as a
     * reset and answer `rate()` garbage over it. The counter refuses
     * it outright.
     */
    public function testTheNegativeIncrementIsRefused(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.custom.requests', 5);

        try {
            $counterMeter->increment('zef.custom.requests', -1);
            self::fail('A negative increment must be refused: the exported total is monotonic.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The counter refuses a negative increment: the exported total is monotonic.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(
            5,
            $counterMeter->snapshot()[MeterSlot::key('zef.custom.requests', [])]['count'],
            'the refused increment never landed',
        );
    }

    /**
     * The observation series is the honest sibling: a measurement
     * below zero is a reading the histogram-style series records
     * with its sign, never a rewound total.
     */
    public function testTheNegativeObservationStillRecords(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->observe('zef.custom.temperature', -7.5);

        $series = $counterMeter->snapshot()[MeterSlot::key('zef.custom.temperature', [])];
        self::assertSame(1, $series['count']);
        self::assertSame(-7.5, $series['sum']);
    }

    public function testTheFractionalAdvanceJoinsTheCountAsAFloat(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.custom.requests', 3);
        $counterMeter->increment('zef.custom.requests', 0.5);

        $series = $counterMeter->snapshot()[MeterSlot::key('zef.custom.requests', [])];
        self::assertSame(3.5, $series['count']);
        self::assertSame(3.5, $series['sum']);
    }

    public function testDistinctDimensionsAreDistinctSeries(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.custom.requests', 1, ['tenant' => 'alpha']);
        $counterMeter->increment('zef.custom.requests', 1, ['tenant' => 'beta']);
        $counterMeter->increment('zef.custom.requests', 2, ['tenant' => 'alpha']);

        $snapshot = $counterMeter->snapshot();
        self::assertCount(2, $snapshot, 'two dimensions, two series');
        self::assertSame(3, $snapshot[MeterSlot::key('zef.custom.requests', ['tenant' => 'alpha'])]['count']);
        self::assertSame(1, $snapshot[MeterSlot::key('zef.custom.requests', ['tenant' => 'beta'])]['count']);
    }

    public function testTheDimensionOrderDoesNotMintASeries(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.custom.requests', 1, ['tenant' => 'alpha', 'region' => 'eu']);
        $counterMeter->increment('zef.custom.requests', 1, ['region' => 'eu', 'tenant' => 'alpha']);

        self::assertCount(1, $counterMeter->snapshot(), 'the ordered map mints one identity');
    }

    public function testTheHttpSeriesKeepsOnlyItsTwoDimensions(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.http.requests', 1, [
            'http.request.method' => 'GET',
            'http.response.status_code' => 200,
            'url.path' => '/orders/92',
            'user.handle' => 'zoe',
        ]);

        $series = $counterMeter->snapshot()[MeterSlot::key('zef.http.requests', [
            'http.request.method' => 'GET',
            'http.response.status_code' => 200,
        ])];
        self::assertSame(
            ['http.request.method' => 'GET', 'http.response.status_code' => 200],
            $series['attributes'],
            'the high-entropy dimensions the request series refuses',
        );
    }

    public function testTheRouterSeriesKeepsItsMethodAndOutcomeSlices(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.router.matches.total', 1, [
            'http.request.method' => 'GET',
            'router.match.outcome' => 'not_found',
            'url.path' => '/orders/92',
            'http.response.status_code' => 404,
        ]);

        $series = $counterMeter->snapshot()[MeterSlot::key('zef.router.matches.total', [
            'http.request.method' => 'GET',
            'router.match.outcome' => 'not_found',
        ])];
        self::assertSame(
            ['http.request.method' => 'GET', 'router.match.outcome' => 'not_found'],
            $series['attributes'],
            'the router series keeps exactly the two slices it exists to be read by',
        );
    }

    public function testTheRouterDurationSeriesObservesUnderTheSameSlices(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->observe('zef.router.match.duration_seconds', 0.000005, [
            'http.request.method' => 'GET',
            'router.match.outcome' => 'found',
            'router.route.name' => 'orders.show',
        ]);

        $series = $counterMeter->snapshot()[MeterSlot::key('zef.router.match.duration_seconds', [
            'http.request.method' => 'GET',
            'router.match.outcome' => 'found',
        ])];
        self::assertSame(
            ['http.request.method' => 'GET', 'router.match.outcome' => 'found'],
            $series['attributes'],
        );
        self::assertSame(0.000005, $series['sum']);
    }

    public function testTheErrorsTotalKeepsTheSanitisedExceptionType(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.http.errors.total', 1, [
            'exception.type' => 'RuntimeException',
            'url.path' => '/orders/92',
        ]);

        $series = $counterMeter->snapshot()[MeterSlot::key('zef.http.errors.total', [
            'exception.type' => 'RuntimeException',
        ])];
        self::assertSame(['exception.type' => 'RuntimeException'], $series['attributes']);
    }

    public function testTheErrorsTotalBoundsTheExceptionType(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.http.errors.total', 1, ['exception.type' => str_repeat('A', 200)]);

        $series = array_values($counterMeter->snapshot())[0];
        $exceptionType = $series['attributes']['exception.type'];
        self::assertIsString($exceptionType);
        self::assertLessThanOrEqual(
            128,
            mb_strlen($exceptionType),
            'the exception type the errors total carries is bounded',
        );
        self::assertStringEndsWith('…', $exceptionType, 'the cut carries the ellipsis marker');
    }

    public function testTheResolveDurationKeepsTheBoundedServiceIdentity(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->observe('zef.container.resolve.duration_seconds', 0.001, [
            'zef.service.id' => 'app.orders.repository',
            'url.path' => '/orders/92',
        ]);

        $series = $counterMeter->snapshot()[MeterSlot::key('zef.container.resolve.duration_seconds', [
            'zef.service.id' => 'app.orders.repository',
        ])];
        self::assertSame(['zef.service.id' => 'app.orders.repository'], $series['attributes']);
    }

    public function testTheResolveDurationFoldsTheUnboundedServiceIdentity(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->observe('zef.container.resolve.duration_seconds', 0.001, [
            'zef.service.id' => 'not a service id!',
        ]);

        $series = $counterMeter->snapshot()[MeterSlot::key('zef.container.resolve.duration_seconds', [
            'zef.service.id' => '[other]',
        ])];
        self::assertSame('[other]', $series['attributes']['zef.service.id']);
    }

    public function testTheLifecycleVocabularyIsClosed(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.lifecycle.events.total', 1, ['event.name' => 'request.started']);
        $counterMeter->increment('zef.lifecycle.events.total', 1, ['event.name' => 'an unlisted event']);

        self::assertSame('request.started', $this->lifecycleEventName($counterMeter, 'request.started'));
        self::assertSame(
            'other',
            $this->lifecycleEventName($counterMeter, 'other'),
            "the unlisted name folds to the closed vocabulary's other",
        );
    }

    public function testTheLifecycleSeriesDropsItsForeignDimensions(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.lifecycle.events.total', 1, [
            'event.name' => 'worker.started',
            'worker.pid' => 4242,
        ]);

        $series = $counterMeter->snapshot()[MeterSlot::key('zef.lifecycle.events.total', [
            'event.name' => 'worker.started',
        ])];
        self::assertSame(['event.name' => 'worker.started'], $series['attributes']);
    }

    public function testTheLifecycleSeriesWithoutAnEventNameKeepsNoDimensions(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.lifecycle.events.total', 1, ['tenant' => 'alpha']);

        $series = $counterMeter->snapshot()[MeterSlot::key('zef.lifecycle.events.total', [])];
        self::assertSame([], $series['attributes'], 'no event name, no dimension to slice by');
        self::assertSame(1, $series['count']);
    }

    public function testTheCustomFamilyKeepsItsSanitisedDimensions(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app.cart.additions', 1, ['sku' => 'A-17', 'currency' => 'EUR']);

        $series = $counterMeter->snapshot()[MeterSlot::key('app.cart.additions', [
            'currency' => 'EUR',
            'sku' => 'A-17',
        ])];
        self::assertSame(['currency' => 'EUR', 'sku' => 'A-17'], $series['attributes'], 'the kept map is ordered');
    }

    public function testTheSensitiveDimensionsNeverBecomeSeries(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app.billing.charges', 1, ['amount' => 42, 'api_key' => 'whfb7szd']);

        $series = $counterMeter->snapshot()[MeterSlot::key('app.billing.charges', ['amount' => 42])];
        self::assertSame(['amount' => 42], $series['attributes'], 'the credential never becomes a dimension');
    }

    public function testTheFullTableCollapsesIntoTheOverflowBucket(): void
    {
        $counterMeter = new CounterMeter();

        for ($i = 0; $i < 1024; ++$i) {
            $counterMeter->increment('app.cardinality.probe', 1, ['ordinal' => $i]);
        }

        $counterMeter->increment('app.cardinality.probe', 1, ['ordinal' => 1024]);

        $snapshot = $counterMeter->snapshot();
        self::assertCount(1024, $snapshot, 'the ceiling holds, the overflow bucket included');
        self::assertSame(
            2,
            $snapshot[MeterSlot::key('app.cardinality.probe', ['zef.cardinality.bucket' => 'overflow'])]['count'],
            'the collapse folds its casualty onto the bucket: the evicted sibling rides along, the count still adds up',
        );
        self::assertArrayNotHasKey(
            MeterSlot::key('app.cardinality.probe', ['ordinal' => 1023]),
            $snapshot,
            'the newest casualty is retired to make the bucket room',
        );

        $totalCount = 0;

        foreach ($snapshot as $series) {
            $totalCount += $series['count'];
        }

        self::assertSame(
            1025,
            $totalCount,
            'every one of the 1025 increments is still counted after the collapse',
        );
    }

    public function testTheEstablishedSeriesStillCountsPastTheCeiling(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app.cardinality.keeper', 3);

        for ($i = 0; $i < 1024; ++$i) {
            $counterMeter->increment('app.cardinality.probe', 1, ['ordinal' => $i]);
        }

        $counterMeter->increment('app.cardinality.keeper', 4);

        self::assertSame(
            7,
            $counterMeter->snapshot()[MeterSlot::key('app.cardinality.keeper', [])]['count'],
            'a table at the ceiling still counts the series it already holds',
        );
    }

    public function testTheOverflowBucketIsNotEvictedForItsOwnSiblings(): void
    {
        $counterMeter = new CounterMeter();

        for ($i = 0; $i < 1024; ++$i) {
            $counterMeter->increment('app.cardinality.probe', 1, ['ordinal' => $i]);
        }

        $counterMeter->increment('app.cardinality.probe', 1, ['ordinal' => 1024]);
        $counterMeter->increment('app.cardinality.probe', 1, ['ordinal' => 1025]);

        $snapshot = $counterMeter->snapshot();
        self::assertSame(
            3,
            $snapshot[MeterSlot::key('app.cardinality.probe', ['zef.cardinality.bucket' => 'overflow'])]['count'],
            'the bucket absorbs its own later collapses: the folded casualty plus the two',
        );
        self::assertArrayHasKey(
            MeterSlot::key('app.cardinality.probe', ['ordinal' => 0]),
            $snapshot,
            'the eviction the first collapse paid is the only one',
        );
    }

    /**
     * The audit's live repro (issue #788, finding 1): a fresh identity
     * on metric B needs room at a full table whose newest series is
     * metric A's overflow bucket -- the first spelling dropped that
     * bucket outright, and A's collapsed count vanished while the
     * docblock promised the count still adds up. The eviction now
     * skips the buckets and folds its casualty onto its own metric's
     * bucket, so B's room is paid by A's newest non-bucket series and
     * A's bucket keeps its count.
     */
    public function testTheCrossMetricCollapseFoldsOntoTheCasualtysOwnBucket(): void
    {
        $counterMeter = new CounterMeter();

        for ($i = 0; $i < 1024; ++$i) {
            $counterMeter->increment('app.cardinality.alpha', 1, ['ordinal' => $i]);
        }

        $counterMeter->increment('app.cardinality.alpha', 1, ['ordinal' => 1024]);
        $counterMeter->increment('app.cardinality.beta', 1, ['ordinal' => 0]);

        $snapshot = $counterMeter->snapshot();
        self::assertSame(
            3,
            $snapshot[MeterSlot::key('app.cardinality.alpha', ['zef.cardinality.bucket' => 'overflow'])]['count'],
            "A's bucket rides through B's collapse: its folded sibling plus the two collapses it already held",
        );
        self::assertSame(
            1,
            $snapshot[MeterSlot::key('app.cardinality.beta', ['zef.cardinality.bucket' => 'overflow'])]['count'],
            "B's fresh identity answers its own overflow bucket",
        );
        self::assertArrayNotHasKey(
            MeterSlot::key('app.cardinality.alpha', ['ordinal' => 1022]),
            $snapshot,
            "the casualty B's room cost was A's newest non-bucket series, never A's bucket",
        );
    }

    /**
     * The pathological table the folding walk cannot answer for free:
     * every series already collapsed, one more distinct name arriving
     * -- the world of a thousand collapsed names the operator already
     * chose. The honest residual is the newest bucket dropped, its
     * count lost, spelled by the eviction's own bounded walk.
     */
    public function testTheAllBucketWorldDropsTheNewestBucketHonestly(): void
    {
        $counterMeter = new CounterMeter();

        for ($i = 0; $i < 1024; ++$i) {
            $counterMeter->increment("app.cardinality.family{$i}", 1);
        }

        $counterMeter->increment('app.cardinality.family0', 1, ['ordinal' => 0]);

        $bucketCount = 0;

        foreach ($counterMeter->snapshot() as $series) {
            if ($series['attributes'] !== ['zef.cardinality.bucket' => 'overflow']) {
                continue;
            }

            ++$bucketCount;
        }

        self::assertSame(
            1024,
            $bucketCount,
            "the cascade retires every series into its own metric's bucket, the count never lost on the way",
        );

        $counterMeter->increment('app.cardinality.freshfamily', 1);

        $snapshot = $counterMeter->snapshot();
        self::assertCount(1024, $snapshot, 'the ceiling holds through the all-bucket residual');
        self::assertSame(
            1,
            $snapshot[MeterSlot::key('app.cardinality.freshfamily', ['zef.cardinality.bucket' => 'overflow'])]['count'],
            'the fresh name answers its own bucket',
        );
    }

    public function testTheHttpSeriesKeepsTheDimensionsItActuallyCarries(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.http.requests', 1, ['http.response.status_code' => 200]);

        $series = array_values($counterMeter->snapshot())[0];
        self::assertSame(
            ['http.response.status_code' => 200],
            $series['attributes'],
            'the absent dimension stays absent -- the walk never stops at the first miss',
        );
    }

    public function testTheErrorsTotalRefusesTheNonStringExceptionType(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.http.errors.total', 1, ['exception.type' => 5]);

        $series = array_values($counterMeter->snapshot())[0];
        self::assertSame(
            [],
            $series['attributes'],
            'a non-string exception type is not a dimension the series keeps',
        );
    }

    public function testTheErrorsTotalTruncatesTheExceptionTypeExactly(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.http.errors.total', 1, ['exception.type' => str_repeat('A', 200)]);

        $series = array_values($counterMeter->snapshot())[0];
        self::assertSame(
            str_repeat('A', 125) . '…',
            $series['attributes']['exception.type'],
            'the bound the series carries is exactly its 128 bytes',
        );
    }

    public function testTheErrorsTotalKeepsEveryDimensionTogether(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef.http.errors.total', 1, [
            'http.request.method' => 'POST',
            'http.response.status_code' => 500,
            'exception.type' => 'RuntimeException',
        ]);

        $series = array_values($counterMeter->snapshot())[0];
        self::assertSame(
            [
                'exception.type' => 'RuntimeException',
                'http.request.method' => 'POST',
                'http.response.status_code' => 500,
            ],
            $series['attributes'],
            'the named extras and the allowed dimensions travel together',
        );
    }

    public function testTheResolveDurationRefusesTheNonStringServiceIdentity(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->observe('zef.container.resolve.duration_seconds', 0.001, ['zef.service.id' => 7]);

        $series = array_values($counterMeter->snapshot())[0];
        self::assertSame(
            [],
            $series['attributes'],
            'a non-string service identity is not a dimension the series keeps',
        );
    }

    public function testTheResolveDurationKeepsEveryDimensionTogether(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->observe('zef.container.resolve.duration_seconds', 0.001, [
            'http.request.method' => 'GET',
            'http.response.status_code' => 200,
            'zef.service.id' => 'app.orders.repository',
        ]);

        $series = array_values($counterMeter->snapshot())[0];
        self::assertSame(
            [
                'http.request.method' => 'GET',
                'http.response.status_code' => 200,
                'zef.service.id' => 'app.orders.repository',
            ],
            $series['attributes'],
        );
    }

    public function testTheServiceDimensionRefusesTheInteriorSpace(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->observe('zef.container.resolve.duration_seconds', 0.001, [
            'zef.service.id' => 'app orders.repository',
        ]);

        $series = array_values($counterMeter->snapshot())[0];
        self::assertSame(
            '[other]',
            $series['attributes']['zef.service.id'],
            'the grammar anchors the whole spelling -- a valid suffix never carries a broken prefix',
        );
    }

    public function testTheServiceDimensionAdmitsTheUppercaseSpelling(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->observe('zef.container.resolve.duration_seconds', 0.001, [
            'zef.service.id' => 'APP.ORDERS.REPOSITORY',
        ]);

        $series = array_values($counterMeter->snapshot())[0];
        self::assertSame(
            'APP.ORDERS.REPOSITORY',
            $series['attributes']['zef.service.id'],
            "the grammar folds case -- the identifier's own spelling travels verbatim",
        );
    }

    public function testTheCompositeKeySpellsItsGrammarVerbatim(): void
    {
        self::assertSame(
            'app.custom|{"k":"a/b é"}',
            MeterSlot::key('app.custom', ['k' => 'a/b é']),
            'the name, the separator, and the unescaped rendering the exposition splits back on',
        );
    }

    public function testTheOverflowFoldSplitsTheNameHalfAtItsOwnCharacters(): void
    {
        // The overflow fold's own twin pin ({@see
        // OtlpMetricKeyTest}'s multibyte lane): the name half a
        // composite key carries keeps the character-offset
        // discipline -- a byte-offset fold would truncate the
        // two-byte `é` mid-name, the split landing at the pipe's
        // byte spelling instead of its character one, and the
        // collapse's own ride onto its metric's bucket would lose
        // its way.
        self::assertSame('métric', MeterOverflow::nameOf('métric|{"k":"v"}'));
    }

    public function testTheMeterCarriesTheSnapshotTheExpositionRenders(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app.custom.requests');

        self::assertArrayHasKey(
            MeterSlot::key('app.custom.requests', []),
            $counterMeter->snapshot(),
            'the snapshot the renderer splits back on the separator',
        );
    }

    /**
     * The lifecycle series' recorded event name, read straight out of
     * the snapshot's composite key.
     */
    private function lifecycleEventName(CounterMeter $counterMeter, string $event): string
    {
        $series = $counterMeter->snapshot()[MeterSlot::key('zef.lifecycle.events.total', [
            'event.name' => $event,
        ])];
        $eventName = $series['attributes']['event.name'];
        \assert(\is_string($eventName));

        return $eventName;
    }
}
