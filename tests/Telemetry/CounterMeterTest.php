<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\MeterCount;
use Zef\Framework\Telemetry\MeterDimensions;
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
            1,
            $snapshot[MeterSlot::key('app.cardinality.probe', ['zef.cardinality.bucket' => 'overflow'])]['count'],
            "the series past the ceiling collapse into the bucket that carries the cardinality's name",
        );
        self::assertArrayNotHasKey(
            MeterSlot::key('app.cardinality.probe', ['ordinal' => 1023]),
            $snapshot,
            'the newest casualty is evicted to make the bucket room',
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
            2,
            $snapshot[MeterSlot::key('app.cardinality.probe', ['zef.cardinality.bucket' => 'overflow'])]['count'],
            'the bucket absorbs its own later collapses without evicting for them',
        );
        self::assertArrayHasKey(
            MeterSlot::key('app.cardinality.probe', ['ordinal' => 0]),
            $snapshot,
            'the eviction the first collapse paid is the only one',
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
