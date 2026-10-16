<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\ExportBounds;
use Zef\Framework\Http\ExportKnobs;
use Zef\Framework\Http\ExportPolicy;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ObservabilityFamilies;

/**
 * The export family's knob reading, on its own terms: the absent
 * family's triple fail-closed defaults (the delivery lane ships
 * OFF, the drain cadence ships OFF, the retry count ships at the
 * one-attempt zero), the strict readings (the two toggles' closed
 * vocabulary, the four integers' digit spellings, the two strings
 * with their types named on refusal), the bounds and the one
 * cross-knob invariant the retry lane carries, the armed family's
 * fail-loud endpoint and service-identity seams -- an unusable
 * collector address halts the resolution with the remediation
 * spelled out, while the disarmed policy stays readable data --
 * the census halt with the known list spelled out, and the
 * profile mirror every family's test pins the same way.
 *
 * @internal
 */
#[CoversClass(ExportPolicy::class)]
#[CoversClass(ExportBounds::class)]
#[CoversClass(ExportKnobs::class)]
#[CoversClass(ObservabilityFamilies::class)]
#[CoversClass(FrameworkDefaults::class)]
final class ExportPolicyTest extends TestCase
{
    public function testTheAbsentFamilyReadsTheTripleFailClosedDefaults(): void
    {
        $policy = ExportPolicy::fromMap(null);

        self::assertFalse($policy->enabled, 'the delivery lane ships OFF');
        self::assertFalse($policy->flushPerRequest, 'the drain cadence ships OFF');
        self::assertSame(0, $policy->maxRetries, 'the retry count ships at zero -- the one-attempt posture');
        self::assertSame('', $policy->endpoint, 'no collector is declared');
        self::assertSame(500, $policy->timeoutMs);
        self::assertSame('zef-application', $policy->serviceName);
        self::assertSame(100, $policy->retryDelayMs);
        self::assertSame(1000, $policy->retryDelayCapMs);
    }

    public function testTheFrameworkProfileMirrorsTheConstructorDefaults(): void
    {
        $fromEmptyMap = ExportPolicy::fromMap([]);

        $observability = FrameworkDefaults::profile()['observability'] ?? null;
        self::assertIsArray($observability);
        $fromDefaults = $observability['export'] ?? null;
        self::assertIsArray($fromDefaults);

        self::assertSame(
            [
                'enabled' => $fromEmptyMap->enabled,
                'endpoint' => $fromEmptyMap->endpoint,
                'timeout_ms' => $fromEmptyMap->timeoutMs,
                'service_name' => $fromEmptyMap->serviceName,
                'max_retries' => $fromEmptyMap->maxRetries,
                'retry_delay_ms' => $fromEmptyMap->retryDelayMs,
                'retry_delay_cap_ms' => $fromEmptyMap->retryDelayCapMs,
                'flush_per_request' => $fromEmptyMap->flushPerRequest,
            ],
            $fromDefaults,
            'the profile declaration and the constructor defaults are one posture'
            . ' -- the boot cannot drift between them',
        );
    }

    public function testTheEmptyMapReadsTheSameDefaults(): void
    {
        $policy = ExportPolicy::fromMap([]);

        self::assertFalse($policy->enabled);
        self::assertFalse($policy->flushPerRequest);
    }

    public function testAMapReadsItsEveryKnob(): void
    {
        $policy = ExportPolicy::fromMap([
            'enabled' => true,
            'endpoint' => 'http://collector:4318',
            'timeout_ms' => 1500,
            'service_name' => 'billing-api',
            'max_retries' => 3,
            'retry_delay_ms' => 50,
            'retry_delay_cap_ms' => 5000,
            'flush_per_request' => true,
        ]);

        self::assertTrue($policy->enabled);
        self::assertSame('http://collector:4318', $policy->endpoint);
        self::assertSame(1500, $policy->timeoutMs);
        self::assertSame('billing-api', $policy->serviceName);
        self::assertSame(3, $policy->maxRetries);
        self::assertSame(50, $policy->retryDelayMs);
        self::assertSame(5000, $policy->retryDelayCapMs);
        self::assertTrue($policy->flushPerRequest);
    }

    #[DataProvider('provideTheArmReadsTheToggleVocabularysTruthCases')]
    public function testTheArmReadsTheToggleVocabularysTruth(mixed $spelling): void
    {
        // The armed family needs its collector declared -- the
        // endpoint seam below owns that halt -- so the vocabulary
        // arm carries one, and only the toggle's own spelling is
        // what this test reads.
        $policy = ExportPolicy::fromMap([
            'enabled' => $spelling,
            'endpoint' => 'http://collector:4318',
        ]);

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

        yield 'on' => ['on'];

        yield 'yes' => ['yes'];
    }

    #[DataProvider('provideTheArmReadsTheToggleVocabularysFalsehoodCases')]
    public function testTheArmReadsTheToggleVocabularysFalsehood(mixed $spelling): void
    {
        $policy = ExportPolicy::fromMap(['enabled' => $spelling]);

        self::assertFalse($policy->enabled);
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function provideTheArmReadsTheToggleVocabularysFalsehoodCases(): iterable
    {
        yield 'native false' => [false];

        yield 'digit zero' => ['0'];

        yield 'lowercase false' => ['false'];

        yield 'uppercase FALSE' => ['FALSE'];

        yield 'off' => ['off'];

        yield 'no' => ['no'];

        yield 'explicit empty' => [''];
    }

    #[DataProvider('provideTheCadenceReadsTheToggleVocabularyCases')]
    public function testTheCadenceReadsTheToggleVocabulary(mixed $spelling, bool $expected): void
    {
        $policy = ExportPolicy::fromMap(['flush_per_request' => $spelling]);

        self::assertSame($expected, $policy->flushPerRequest);
    }

    /**
     * @return iterable<array{mixed,bool}>
     */
    public static function provideTheCadenceReadsTheToggleVocabularyCases(): iterable
    {
        yield 'native true' => [true, true];

        yield 'digit one' => ['1', true];

        yield 'on' => ['on', true];

        yield 'native false' => [false, false];

        yield 'off' => ['off', false];

        yield 'digit zero' => ['0', false];
    }

    public function testTheIntegerKnobsReadNativeAndDigitStringSpellings(): void
    {
        self::assertSame(1500, ExportPolicy::fromMap(['timeout_ms' => '1500'])->timeoutMs);
        self::assertSame(3, ExportPolicy::fromMap(['max_retries' => '3'])->maxRetries);
        self::assertSame(50, ExportPolicy::fromMap(['retry_delay_ms' => '50'])->retryDelayMs);
        self::assertSame(5000, ExportPolicy::fromMap(['retry_delay_cap_ms' => '5000'])->retryDelayCapMs);
    }

    #[DataProvider('provideTheKnobsRefuseMistypedValuesCases')]
    public function testTheKnobsRefuseMistypedValues(string $knob, mixed $value, string $message): void
    {
        try {
            ExportPolicy::fromMap([$knob => $value]);
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
            'The observability.export enabled knob must be a boolean or one of the boolean string spellings'
                . ' (true/false, on/off, yes/no, 1/0), int given.',
        ];

        yield 'arm mistyped word' => [
            'enabled',
            'ture',
            'The observability.export enabled knob must be a boolean or one of the boolean string spellings'
                . ' (true/false, on/off, yes/no, 1/0), string given.',
        ];

        yield 'cadence integer' => [
            'flush_per_request',
            1,
            'The observability.export flush_per_request knob must be a boolean or one of the boolean string'
                . ' spellings (true/false, on/off, yes/no, 1/0), int given.',
        ];

        yield 'timeout float' => [
            'timeout_ms',
            1.5,
            'The observability.export timeout_ms knob must be an integer or an all-digit string, float given.',
        ];

        yield 'timeout bool' => [
            'timeout_ms',
            true,
            'The observability.export timeout_ms knob must be an integer or an all-digit string, bool given.',
        ];

        yield 'timeout negative string' => [
            'timeout_ms',
            ' 500',
            'The observability.export timeout_ms knob must be an integer or an all-digit string, string given.',
        ];

        yield 'retries float' => [
            'max_retries',
            0.5,
            'The observability.export max_retries knob must be an integer or an all-digit string, float given.',
        ];

        yield 'delay mistyped word' => [
            'retry_delay_ms',
            'fast',
            'The observability.export retry_delay_ms knob must be an integer or an all-digit string, string given.',
        ];

        yield 'cap float' => [
            'retry_delay_cap_ms',
            0.1,
            'The observability.export retry_delay_cap_ms knob must be an integer or an all-digit string,'
                . ' float given.',
        ];

        yield 'endpoint integer' => [
            'endpoint',
            4318,
            'The observability.export endpoint knob must be a string, int given.',
        ];

        yield 'service name bool' => [
            'service_name',
            true,
            'The observability.export service_name knob must be a string, bool given.',
        ];
    }

    #[DataProvider('provideTheBoundsAreRefusedOutsideTheirRangesCases')]
    public function testTheBoundsAreRefusedOutsideTheirRanges(string $knob, int $value, string $message): void
    {
        try {
            ExportPolicy::fromMap([$knob => $value]);
            self::fail('An out-of-range knob must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }

    /**
     * @return iterable<array{string,int,string}>
     */
    public static function provideTheBoundsAreRefusedOutsideTheirRangesCases(): iterable
    {
        yield 'timeout below' => [
            'timeout_ms',
            0,
            'The observability.export timeout_ms knob must be between 1 and 10000.',
        ];

        yield 'timeout above' => [
            'timeout_ms',
            10001,
            'The observability.export timeout_ms knob must be between 1 and 10000.',
        ];

        yield 'retries below' => [
            'max_retries',
            -1,
            'The observability.export max_retries knob must be between 0 and 10.',
        ];

        yield 'retries above' => [
            'max_retries',
            11,
            'The observability.export max_retries knob must be between 0 and 10.',
        ];

        yield 'delay below' => [
            'retry_delay_ms',
            -1,
            'The observability.export retry_delay_ms knob must be between 0 and 10000.',
        ];

        yield 'delay above' => [
            'retry_delay_ms',
            10001,
            'The observability.export retry_delay_ms knob must be between 0 and 10000.',
        ];

        yield 'cap below' => [
            'retry_delay_cap_ms',
            -1,
            'The observability.export retry_delay_cap_ms knob must be between 0 and 60000.',
        ];

        yield 'cap above' => [
            'retry_delay_cap_ms',
            60001,
            'The observability.export retry_delay_cap_ms knob must be between 0 and 60000.',
        ];
    }

    public function testTheUpperBoundsAreInclusive(): void
    {
        $policy = ExportPolicy::fromMap([
            'timeout_ms' => 10000,
            'max_retries' => 10,
            'retry_delay_ms' => 10000,
            'retry_delay_cap_ms' => 60000,
        ]);

        self::assertSame(10000, $policy->timeoutMs);
        self::assertSame(10, $policy->maxRetries);
        self::assertSame(10000, $policy->retryDelayMs);
        self::assertSame(60000, $policy->retryDelayCapMs);
    }

    public function testTheLowerBoundsAreInclusive(): void
    {
        $policy = ExportPolicy::fromMap([
            'timeout_ms' => 1,
            'max_retries' => 0,
            'retry_delay_ms' => 0,
            'retry_delay_cap_ms' => 0,
        ]);

        self::assertSame(1, $policy->timeoutMs);
        self::assertSame(0, $policy->maxRetries);
        self::assertSame(0, $policy->retryDelayMs);
        self::assertSame(0, $policy->retryDelayCapMs);
    }

    public function testTheCapAtTheInitialDelayIsTheNarrowestHonestPolicy(): void
    {
        $policy = ExportPolicy::fromMap([
            'retry_delay_ms' => 200,
            'retry_delay_cap_ms' => 200,
        ]);

        self::assertSame(200, $policy->retryDelayMs);
        self::assertSame(200, $policy->retryDelayCapMs, 'a cap sitting exactly at the initial delay is a policy');
    }

    public function testTheCapNeverSitsBelowTheInitialDelay(): void
    {
        try {
            ExportPolicy::fromMap([
                'retry_delay_ms' => 200,
                'retry_delay_cap_ms' => 100,
            ]);
            self::fail('A cap below the initial delay must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.export retry_delay_cap_ms knob must not sit below retry_delay_ms.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnArmedFamilyHaltsOnAnEmptyEndpoint(): void
    {
        try {
            ExportPolicy::fromMap(['enabled' => true]);
            self::fail('An armed family without a collector must halt the resolution.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.export family is armed but its endpoint knob is empty -- set '
                . "observability.export endpoint to the collector's absolute HTTP/HTTPS URL "
                . '(the environment overlay APP_OBSERVABILITY__EXPORT__ENDPOINT spells it), '
                . 'or disarm the family.',
                $httpException->getMessage(),
            );
        }
    }

    #[DataProvider('provideAnArmedFamilyHaltsOnAnUnusableEndpointCases')]
    public function testAnArmedFamilyHaltsOnAnUnusableEndpoint(string $endpoint): void
    {
        try {
            ExportPolicy::fromMap(['enabled' => true, 'endpoint' => $endpoint]);
            self::fail('An unusable endpoint must halt the resolution.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.export endpoint knob must be an absolute HTTP/HTTPS URL, '
                . "'" . $endpoint . "' given.",
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideAnArmedFamilyHaltsOnAnUnusableEndpointCases(): iterable
    {
        yield 'relative path' => ['/v1/traces'];

        yield 'host without scheme' => ['collector:4318'];

        yield 'wrong scheme' => ['ftp://collector:4318'];
    }

    public function testAnArmedFamilyHaltsOnEmbeddedCredentials(): void
    {
        try {
            ExportPolicy::fromMap(['enabled' => true, 'endpoint' => 'http://user:pass@collector:4318']);
            self::fail('A credential-bearing endpoint must halt the resolution.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.export endpoint knob must not carry embedded credentials -- '
                . "the transport's own failure diagnostics would echo them into exception text.",
                $httpException->getMessage(),
            );
        }
    }

    public function testAnArmedFamilyHaltsOnAUserNameAlone(): void
    {
        // The user-info arm of the grammar is one refusal, whichever
        // half of the pair the URL spells: a bare user name with no
        // password rides the transport's own failure diagnostics out
        // exactly as a user:pass pair would.
        try {
            ExportPolicy::fromMap(['enabled' => true, 'endpoint' => 'http://token@collector:4318']);
            self::fail('A user-name-bearing endpoint must halt the resolution.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.export endpoint knob must not carry embedded credentials -- '
                . "the transport's own failure diagnostics would echo them into exception text.",
                $httpException->getMessage(),
            );
        }
    }

    public function testAnArmedFamilyHaltsOnAnEmptyServiceName(): void
    {
        try {
            ExportPolicy::fromMap([
                'enabled' => true,
                'endpoint' => 'http://collector:4318',
                'service_name' => '',
            ]);
            self::fail('An armed family without a service identity must halt the resolution.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.export service_name knob must be a non-empty string when the '
                . 'family is armed -- set it through the environment overlay '
                . 'APP_OBSERVABILITY__EXPORT__SERVICE_NAME or a configuration file, or disarm '
                . 'the family.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheDisarmedFamilyKeepsAnUnusableEndpointAsReadableData(): void
    {
        $policy = ExportPolicy::fromMap([
            'enabled' => false,
            'endpoint' => 'not a url at all',
            'service_name' => '',
        ]);

        self::assertFalse($policy->enabled);
        self::assertSame('not a url at all', $policy->endpoint, 'the disarmed policy is data, never a halt');
        self::assertSame('', $policy->serviceName);
    }

    public function testANonMapFamilyIsRefused(): void
    {
        try {
            ExportPolicy::fromMap('off');
            self::fail('A non-map family must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability.export configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnUnknownKnobIsRefusedWithTheKnownList(): void
    {
        try {
            ExportPolicy::fromMap(['endpint' => 'http://collector:4318']);
            self::fail('An unknown knob must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.export configuration carries the unknown knob 'endpint'"
                . ' (known knobs: enabled, endpoint, timeout_ms, service_name, max_retries,'
                . ' retry_delay_ms, retry_delay_cap_ms, flush_per_request).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFamilySubtreeReadsOneFamily(): void
    {
        $map = [
            'timing' => ['enabled' => false],
            'export' => ['enabled' => false],
        ];

        self::assertSame(['enabled' => false], ObservabilityFamilies::subtree($map, 'export'));
        self::assertNull(ObservabilityFamilies::subtree(null, 'export'));
    }
}
