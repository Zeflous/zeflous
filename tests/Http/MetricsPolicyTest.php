<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\MetricsBounds;
use Zef\Framework\Http\MetricsKnobs;
use Zef\Framework\Http\MetricsPolicy;
use Zef\Framework\Http\ObservabilityFamilies;

/**
 * The metrics family's knob reading, on its own terms: the absent
 * family's fail-closed default (the endpoints ship OFF), the strict
 * readings (the arm's closed vocabulary, the timeout's integer
 * corridor, the two paths' string types), the bounds (the timeout's
 * 1..10000 corridor, the path pair's grammar and collision refusal
 * the armed family answers), the two census halts with the known
 * lists spelled out, and the profile mirror every family's test pins
 * the same way.
 *
 * @internal
 */
#[CoversClass(MetricsPolicy::class)]
#[CoversClass(MetricsKnobs::class)]
#[CoversClass(MetricsBounds::class)]
#[CoversClass(ObservabilityFamilies::class)]
#[CoversClass(FrameworkDefaults::class)]
final class MetricsPolicyTest extends TestCase
{
    public function testTheAbsentFamilyReadsTheFailClosedDefault(): void
    {
        $policy = MetricsPolicy::fromMap(null);

        self::assertFalse($policy->enabled, 'the endpoints ship OFF');
        self::assertSame('/metrics', $policy->metricsPath);
        self::assertSame('/health', $policy->healthPath);
        self::assertSame(500, $policy->timeoutMs);
    }

    public function testTheFrameworkProfileMirrorsTheFailClosedDefault(): void
    {
        $fromEmptyMap = MetricsPolicy::fromMap([]);

        $observability = FrameworkDefaults::profile()['observability'] ?? null;
        self::assertIsArray($observability);
        $fromDefaults = $observability['metrics'] ?? null;
        self::assertIsArray($fromDefaults);

        self::assertSame(
            [
                'enabled' => $fromEmptyMap->enabled,
                'metrics_path' => $fromEmptyMap->metricsPath,
                'health_path' => $fromEmptyMap->healthPath,
                'timeout_ms' => $fromEmptyMap->timeoutMs,
            ],
            $fromDefaults,
            'the profile declaration and the constructor defaults are one posture'
            . ' -- the boot cannot drift between them',
        );
    }

    public function testTheEmptyMapReadsTheSameDefaults(): void
    {
        $policy = MetricsPolicy::fromMap([]);

        self::assertFalse($policy->enabled);
        self::assertSame('/metrics', $policy->metricsPath);
        self::assertSame('/health', $policy->healthPath);
        self::assertSame(500, $policy->timeoutMs);
    }

    public function testAMapReadsItsEveryKnob(): void
    {
        $policy = MetricsPolicy::fromMap([
            'enabled' => true,
            'metrics_path' => '/internal/metrics',
            'health_path' => '/status',
            'timeout_ms' => 250,
        ]);

        self::assertTrue($policy->enabled);
        self::assertSame('/internal/metrics', $policy->metricsPath);
        self::assertSame('/status', $policy->healthPath);
        self::assertSame(250, $policy->timeoutMs);
    }

    #[DataProvider('provideTheArmReadsTheToggleVocabularysTruthCases')]
    public function testTheArmReadsTheToggleVocabularysTruth(mixed $spelling): void
    {
        $policy = MetricsPolicy::fromMap(['enabled' => $spelling]);

        self::assertTrue($policy->enabled);
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function provideTheArmReadsTheToggleVocabularysTruthCases(): iterable
    {
        yield 'native true' => [true];

        yield 'digit one' => ['1'];

        yield 'lowercase true' => ['true'];

        yield 'uppercase TRUE' => ['TRUE'];

        yield 'mixed-case True' => ['True'];

        yield 'lowercase on' => ['on'];

        yield 'uppercase ON' => ['ON'];

        yield 'lowercase yes' => ['yes'];

        yield 'uppercase YES' => ['YES'];
    }

    public function testTheIntegerKnobReadsNativeAndDigitStringSpellings(): void
    {
        self::assertSame(250, MetricsPolicy::fromMap(['timeout_ms' => '250'])->timeoutMs);
    }

    public function testTheNonMapSubtreeIsRejected(): void
    {
        try {
            MetricsPolicy::fromMap(500);
            self::fail('A non-map subtree must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.metrics configuration must be a map, int given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheUnknownKnobIsRejectedWithTheKnownListSpelledOut(): void
    {
        try {
            MetricsPolicy::fromMap(['timeout' => 250]);
            self::fail('An unknown knob must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.metrics configuration carries the unknown knob 'timeout'"
                . ' (known knobs: enabled, metrics_path, health_path, timeout_ms).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheUnknownFamilyIsRejectedWithTheKnownFamiliesSpelledOut(): void
    {
        try {
            ObservabilityFamilies::subtree(['metricks' => []], 'metrics');
            self::fail('An unknown family must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability configuration carries the unknown family 'metricks'"
                . ' (known families: timing, tracing, correlation, export, metrics).',
                $httpException->getMessage(),
            );
        }
    }

    #[DataProvider('provideTheKnobsRefuseMistypedValuesCases')]
    public function testTheKnobsRefuseMistypedValues(string $knob, mixed $value, string $message): void
    {
        try {
            MetricsPolicy::fromMap([$knob => $value]);
            self::fail('A mistyped knob must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }

    /**
     * @return iterable<array{string,mixed,string}>
     */
    public static function provideTheKnobsRefuseMistypedValuesCases(): iterable
    {
        yield 'arm integer' => [
            'enabled',
            1,
            'The observability.metrics enabled knob must be a boolean or one of the boolean string spellings'
            . ' (true/false, on/off, yes/no, 1/0), int given.',
        ];

        yield 'timeout float' => [
            'timeout_ms',
            1.5,
            'The observability.metrics timeout_ms knob must be an integer or an all-digit string, float given.',
        ];

        yield 'timeout bool' => [
            'timeout_ms',
            true,
            'The observability.metrics timeout_ms knob must be an integer or an all-digit string, bool given.',
        ];

        yield 'metrics path integer' => [
            'metrics_path',
            8080,
            'The observability.metrics metrics_path knob must be a string, int given.',
        ];

        yield 'health path bool' => [
            'health_path',
            true,
            'The observability.metrics health_path knob must be a string, bool given.',
        ];
    }

    #[DataProvider('provideTheTimeoutCorridorRefusesItsOutsideCases')]
    public function testTheTimeoutCorridorRefusesItsOutside(int $timeoutMs): void
    {
        try {
            MetricsPolicy::fromMap(['timeout_ms' => $timeoutMs]);
            self::fail('A timeout outside the corridor must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.metrics timeout_ms knob must be between 1 and 10000, '
                . $timeoutMs . ' given.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<array{int}>
     */
    public static function provideTheTimeoutCorridorRefusesItsOutsideCases(): iterable
    {
        yield 'zero' => [0];

        yield 'one past the ceiling' => [10001];
    }

    public function testTheCorridorsOwnEdgesRead(): void
    {
        self::assertSame(1, MetricsPolicy::fromMap(['timeout_ms' => 1])->timeoutMs);
        self::assertSame(10000, MetricsPolicy::fromMap(['timeout_ms' => 10000])->timeoutMs);
    }

    #[DataProvider('provideTheArmedFamilyRefusesUnusablePathsCases')]
    public function testTheArmedFamilyRefusesUnusablePaths(
        string $metricsPath,
        string $healthPath,
        string $message,
    ): void {
        try {
            MetricsPolicy::fromMap([
                'enabled' => true,
                'metrics_path' => $metricsPath,
                'health_path' => $healthPath,
            ]);
            self::fail('An armed family with an unusable path must halt the resolution.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }

    /**
     * @return iterable<array{string,string,string}>
     */
    public static function provideTheArmedFamilyRefusesUnusablePathsCases(): iterable
    {
        yield 'empty metrics path' => [
            '',
            '/health',
            'The observability.metrics metrics_path knob must not be empty -- the endpoint it names is the'
            . ' surface the family arms; declare the path explicitly or disarm the family.',
        ];

        yield 'empty health path' => [
            '/metrics',
            '',
            'The observability.metrics health_path knob must not be empty -- the endpoint it names is the'
            . ' surface the family arms; declare the path explicitly or disarm the family.',
        ];

        yield 'relative metrics path' => [
            'metrics',
            '/health',
            "The observability.metrics metrics_path knob must be an absolute path starting with '/', 'metrics' given.",
        ];

        yield 'relative health path' => [
            '/metrics',
            'health',
            "The observability.metrics health_path knob must be an absolute path starting with '/', 'health' given.",
        ];

        yield 'space in the metrics path' => [
            '/met rics',
            '/health',
            "The observability.metrics metrics_path knob must not carry control or space bytes, '/met rics' given.",
        ];

        yield 'control byte in the health path' => [
            '/metrics',
            "/he\x01alth",
            'The observability.metrics health_path knob must not carry control or space bytes, '
            . "'/he\x01alth' given.",
        ];

        yield 'query in the metrics path' => [
            '/metrics?scope=all',
            '/health',
            'The observability.metrics metrics_path knob must be a path, not a query or fragment carrier'
            . " ('/metrics?scope=all' given) -- the query and the fragment belong to the client's"
            . ' request, never to the endpoint the family names.',
        ];

        yield 'fragment in the health path' => [
            '/metrics',
            '/health#top',
            'The observability.metrics health_path knob must be a path, not a query or fragment carrier'
            . " ('/health#top' given) -- the query and the fragment belong to the client's"
            . ' request, never to the endpoint the family names.',
        ];

        yield 'the metrics path collides with the health base' => [
            '/status',
            '/status',
            'The observability.metrics metrics_path knob must not collide with the health surface it'
            . ' travels with (/status is one of the health endpoints) -- rename one of the two paths.',
        ];

        yield 'the metrics path collides with the live view' => [
            '/status/live',
            '/status',
            'The observability.metrics metrics_path knob must not collide with the health surface it'
            . ' travels with (/status/live is one of the health endpoints) -- rename one of the two paths.',
        ];

        yield 'the metrics path collides with the ready view' => [
            '/status/ready',
            '/status',
            'The observability.metrics metrics_path knob must not collide with the health surface it'
            . ' travels with (/status/ready is one of the health endpoints) -- rename one of the two paths.',
        ];
    }

    public function testTheDisarmedFamilyReadsUnusablePathsAsData(): void
    {
        $policy = MetricsPolicy::fromMap([
            'enabled' => false,
            'metrics_path' => '',
            'health_path' => 'health',
        ]);

        self::assertFalse($policy->enabled, 'the disarmed posture is readable data, never an opt-in');
        self::assertSame('', $policy->metricsPath);
        self::assertSame('health', $policy->healthPath);
    }

    public function testTheArmedFamilyAcceptsADistinctPathPair(): void
    {
        $policy = MetricsPolicy::fromMap([
            'enabled' => true,
            'metrics_path' => '/internal/metrics',
            'health_path' => '/status',
        ]);

        self::assertTrue($policy->enabled);
        self::assertSame('/internal/metrics', $policy->metricsPath);
        self::assertSame('/status', $policy->healthPath);
    }
}
