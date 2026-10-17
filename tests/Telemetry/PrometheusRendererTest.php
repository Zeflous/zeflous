<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\PrometheusHistogram;
use Zef\Framework\Telemetry\PrometheusLabels;
use Zef\Framework\Telemetry\PrometheusMetricName;
use Zef\Framework\Telemetry\PrometheusNumbers;
use Zef\Framework\Telemetry\PrometheusRenderer;
use Zef\Framework\Telemetry\PrometheusSeries;
use Zef\Test\Support\Telemetry\SeriesTableMeter;

/**
 * The exposition render, on its own terms: the TYPE header once per
 * metric name, the pure counter's count-alone shape, the classic
 * histogram members the diverging observation series earns, the NaN
 * contract the positive-form comparison carries, the sanitisation
 * alphabets and prefixes, the label escaping and ordering, the static
 * label merge, the number forms, and the bounded render -- the
 * campaign's closing family's delivery face.
 *
 * @internal
 */
#[CoversClass(PrometheusRenderer::class)]
#[CoversClass(PrometheusLabels::class)]
#[CoversClass(PrometheusNumbers::class)]
#[CoversClass(PrometheusSeries::class)]
#[CoversClass(PrometheusMetricName::class)]
#[CoversClass(PrometheusHistogram::class)]
final class PrometheusRendererTest extends TestCase
{
    public function testAnEmptySnapshotAnswersTheEmptyBody(): void
    {
        self::assertSame('', new PrometheusRenderer()->render(new CounterMeter()));
    }

    public function testAPureCounterRendersItsCountAlone(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef_requests', 3);

        self::assertSame(
            "# TYPE zef_requests counter\nzef_requests 3.0\n",
            new PrometheusRenderer()->render($counterMeter),
            'a pure counter keeps count == sum, so no _sum member duplicates it',
        );
    }

    public function testTheTypeHeaderEmitsOncePerMetricName(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('zef_requests', 1, ['tenant' => 'alpha']);
        $counterMeter->increment('zef_requests', 1, ['tenant' => 'beta']);

        $render = new PrometheusRenderer()->render($counterMeter);

        self::assertSame(1, mb_substr_count($render, '# TYPE zef_requests counter'));
        self::assertSame(
            "zef_requests{tenant=\"alpha\"} 1.0\nzef_requests{tenant=\"beta\"} 1.0\n",
            implode("\n", \array_slice(explode("\n", $render), 1)),
            'the series order follows the snapshot, the labels ordered by name',
        );
    }

    public function testTheDivergingSeriesEarnsItsHistogramMembers(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->observe('zef_duration', 0.5);
        $counterMeter->observe('zef_duration', 1.7);

        self::assertSame(
            "# TYPE zef_duration counter\n"
            . "zef_duration 2.0\n"
            . "# TYPE zef_duration_sum counter\n"
            . "# TYPE zef_duration_count counter\n"
            . "zef_duration_sum 2.2\n"
            . "zef_duration_count 2.0\n",
            new PrometheusRenderer()->render($counterMeter),
        );
    }

    public function testTheNaNSeriesRendersCountOnly(): void
    {
        $seriesTableMeter = new SeriesTableMeter([
            'zef_nan_probe' => ['count' => 1, 'sum' => \NAN, 'attributes' => []],
        ]);

        $render = new PrometheusRenderer()->render($seriesTableMeter);

        self::assertStringContainsString('zef_nan_probe 1.0', $render);
        self::assertStringNotContainsString(
            'zef_nan_probe_sum',
            $render,
            'the NaN sum renders no member -- every comparison against it is false',
        );
    }

    public function testTheNaNCountRendersTheNaNLiteral(): void
    {
        $seriesTableMeter = new SeriesTableMeter([
            'zef_nan_count' => ['count' => \NAN, 'sum' => 0.0, 'attributes' => []],
        ]);

        $render = new PrometheusRenderer()->render($seriesTableMeter);

        self::assertStringContainsString('zef_nan_count NaN', $render);
        self::assertStringNotContainsString('zef_nan_count_sum', $render, 'a NaN count never diverges either');
    }

    public function testTheMetricNameSanitisesIntoTheExpositionAlphabet(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app metric!name', 1);

        $render = new PrometheusRenderer()->render($counterMeter);

        self::assertStringContainsString('# TYPE app_metric_name counter', $render);
        self::assertStringContainsString('app_metric_name 1.0', $render);
    }

    public function testANonAlphabeticStartIsPrefixed(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('1app.metric', 1);

        $render = new PrometheusRenderer()->render($counterMeter);

        self::assertStringContainsString('# TYPE zef_1app_metric counter', $render);
        self::assertStringContainsString('zef_1app_metric 1.0', $render);
    }

    public function testTheWhitespaceNameAnswersTheUnnamedDefault(): void
    {
        $seriesTableMeter = new SeriesTableMeter([
            '   ' => ['count' => 1, 'sum' => 1.0, 'attributes' => []],
        ]);

        $render = new PrometheusRenderer()->render($seriesTableMeter);

        self::assertStringContainsString('# TYPE zef_unnamed_metric counter', $render);
        self::assertStringContainsString('zef_unnamed_metric 1.0', $render);
    }

    public function testTheLabelNameSanitisesAndPrefixes(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app_requests', 1, ['tenant region' => 'eu', '1code' => 'x']);

        $render = new PrometheusRenderer()->render($counterMeter);

        self::assertStringContainsString(
            'app_requests{lbl_1code="x",tenant_region="eu"} 1.0',
            $render,
            'the sanitised names ordered: the prefix folds before the word',
        );
    }

    public function testTheLabelValueEscapesTheExpositionCarries(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app_requests', 1, ['note' => 'a "quoted" \ value']);

        $render = new PrometheusRenderer()->render($counterMeter);

        self::assertStringContainsString('app_requests{note="a \"quoted\" \\\ value"} 1.0', $render);
    }

    public function testTheSeriesWithNoPairsOmitsTheLabelBlock(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app_plain');

        self::assertStringContainsString("app_plain 1.0\n", new PrometheusRenderer()->render($counterMeter));
    }

    public function testTheStaticLabelsMergeIntoEverySeries(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app_requests', 1, ['tenant' => 'alpha']);

        $render = new PrometheusRenderer()->render($counterMeter, ['environment' => 'production']);

        self::assertStringContainsString('app_requests{environment="production",tenant="alpha"} 1.0', $render);
    }

    public function testTheNonScalarDimensionsDrop(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app_requests', 1, ['payload' => ['nested' => 'map']]);

        $render = new PrometheusRenderer()->render($counterMeter);

        self::assertStringContainsString('app_requests 1.0', $render);
        self::assertStringNotContainsString('payload', $render, 'a non-scalar dimension is dropped, never stringified');
    }

    public function testAZeroKeepsItsFloatShape(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app_zero');

        $render = new PrometheusRenderer()->render($counterMeter);

        self::assertStringContainsString('app_zero 1.0', $render);
        self::assertStringContainsString('# TYPE app_zero counter', $render);
    }

    public function testTheInfiniteSumsSpellTheirLiteralForms(): void
    {
        $seriesTableMeter = new SeriesTableMeter([
            'app_inf' => ['count' => 1, 'sum' => \INF, 'attributes' => []],
            'app_ninf' => ['count' => 1, 'sum' => -\INF, 'attributes' => []],
        ]);

        $render = new PrometheusRenderer()->render($seriesTableMeter);

        self::assertStringContainsString('app_inf 1.0', $render);
        self::assertStringContainsString('app_inf_sum +Inf', $render);
        self::assertStringContainsString('app_inf_count 1.0', $render);
        self::assertStringContainsString('app_ninf 1.0', $render);
        self::assertStringContainsString('app_ninf_sum -Inf', $render);
    }

    public function testTheHistogramTypeHeadersEmitOnceAcrossTheSeries(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->observe('zef_duration', 0.5, ['tenant' => 'alpha']);
        $counterMeter->observe('zef_duration', 1.7, ['tenant' => 'beta']);

        self::assertSame(
            "# TYPE zef_duration counter\n"
            . "zef_duration{tenant=\"alpha\"} 1.0\n"
            . "# TYPE zef_duration_sum counter\n"
            . "# TYPE zef_duration_count counter\n"
            . "zef_duration_sum{tenant=\"alpha\"} 0.5\n"
            . "zef_duration_count{tenant=\"alpha\"} 1.0\n"
            . "zef_duration{tenant=\"beta\"} 1.0\n"
            . "zef_duration_sum{tenant=\"beta\"} 1.7\n"
            . "zef_duration_count{tenant=\"beta\"} 1.0\n",
            new PrometheusRenderer()->render($counterMeter),
            "the members' TYPE headers emit once for the whole metric, across its series",
        );
    }

    public function testTheIntegerKeyedDimensionDrops(): void
    {
        $seriesTableMeter = new SeriesTableMeter([
            'mixed|{"5":"v","k":"x"}' => ['count' => 1, 'sum' => 1.0, 'attributes' => []],
        ]);

        $render = new PrometheusRenderer()->render($seriesTableMeter);

        self::assertSame(
            "# TYPE mixed counter\nmixed{k=\"x\"} 1.0\n",
            $render,
            'the non-string key drops; the string key keeps its place',
        );
    }

    public function testTheNonScalarDimensionDropsButTheLaterScalarKeeps(): void
    {
        $seriesTableMeter = new SeriesTableMeter([
            'mixed' => [
                'count' => 1,
                'sum' => 1.0,
                'attributes' => ['payload' => ['nested' => 'map'], 'plain' => 'x'],
            ],
        ]);

        $render = new PrometheusRenderer()->render($seriesTableMeter);

        self::assertSame(
            "# TYPE mixed counter\nmixed{plain=\"x\"} 1.0\n",
            $render,
            'the walk drops the non-scalar and keeps walking -- the later scalar dimension keeps its place',
        );
    }

    public function testTheIntegerDimensionRendersItsScalarForm(): void
    {
        $counterMeter = new CounterMeter();
        $counterMeter->increment('app_events', 1, ['count' => 5]);

        $render = new PrometheusRenderer()->render($counterMeter);

        self::assertStringContainsString('app_events{count="5"} 1.0', $render);
    }

    public function testTheLabelsOrderIsTheAlphabetNotTheInsertion(): void
    {
        $seriesTableMeter = new SeriesTableMeter([
            'ordered' => [
                'count' => 1,
                'sum' => 1.0,
                'attributes' => ['z' => '1', 'a' => '2'],
            ],
        ]);

        $render = new PrometheusRenderer()->render($seriesTableMeter);

        self::assertStringContainsString('ordered{a="2",z="1"} 1.0', $render);
    }

    public function testTheEpsilonExactDivergenceDoesNotEarnMembers(): void
    {
        $seriesTableMeter = new SeriesTableMeter([
            'edge' => ['count' => 0.0, 'sum' => \PHP_FLOAT_EPSILON, 'attributes' => []],
        ]);

        self::assertSame(
            "# TYPE edge counter\nedge 0.0\n",
            new PrometheusRenderer()->render($seriesTableMeter),
            'a divergence of exactly the epsilon is not a divergence',
        );
    }

    public function testTheMultibyteNameSplitsOnTheSeparator(): void
    {
        $seriesTableMeter = new SeriesTableMeter([
            'métric|{"k":"v"}' => ['count' => 1, 'sum' => 1.0, 'attributes' => []],
        ]);

        $render = new PrometheusRenderer()->render($seriesTableMeter);

        self::assertStringContainsString('m__tric{k="v"} 1.0', $render);
    }

    public function testTheRenderStopsAtItsOwnSeriesCeiling(): void
    {
        $snapshot = [];
        for ($i = 0; $i < 4097; ++$i) {
            $snapshot['app_series_' . $i] = ['count' => 1, 'sum' => 1.0, 'attributes' => []];
        }

        $render = new PrometheusRenderer()->render(new SeriesTableMeter($snapshot));

        self::assertSame(
            4096,
            mb_substr_count($render, " 1.0\n"),
            "the render answers its own ceiling of series, the meter's bound already dwarfed",
        );
    }
}
