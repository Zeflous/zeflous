<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Http\ReplayPayloadSanitizer;

/**
 * Direct battery for the opt-in replay redactor: depth bounds, entry
 * bounds, key-name redaction, scalar arms, and the string length and
 * multibyte boundaries are all pinned exactly.
 *
 * @internal
 */
#[CoversClass(ReplayPayloadSanitizer::class)]
final class ReplayPayloadSanitizerTest extends TestCase
{
    public function testScalarsPassThroughUnchangedExceptStrings(): void
    {
        self::assertSame(7, ReplayPayloadSanitizer::sanitize(7));
        self::assertSame(1.5, ReplayPayloadSanitizer::sanitize(1.5));
        self::assertTrue(ReplayPayloadSanitizer::sanitize(true));
        self::assertNull(ReplayPayloadSanitizer::sanitize(null));
        self::assertSame('plain', ReplayPayloadSanitizer::sanitize('plain'));
        self::assertSame('password=[REDACTED]', ReplayPayloadSanitizer::sanitize('password=secret123'));
        self::assertSame('[REDACTED_EMAIL]', ReplayPayloadSanitizer::sanitize('user@example.com'));
    }

    public function testUnsupportedValuesAnswerThePlaceholder(): void
    {
        self::assertSame('[UNSUPPORTED]', ReplayPayloadSanitizer::sanitize(new stdClass()));
    }

    public function testSensitiveKeysAreRedactedRegardlessOfCase(): void
    {
        $payload = [
            'password' => 'hunter2',
            'PASSWORD' => 'hunter3',
            'Api-Key' => 'abc123',
            'filter' => 'active',
        ];

        $sanitized = ReplayPayloadSanitizer::sanitize($payload);

        self::assertIsArray($sanitized);
        self::assertSame(
            [
                'password' => '[REDACTED]',
                'PASSWORD' => '[REDACTED]',
                'Api-Key' => '[REDACTED]',
                'filter' => 'active',
            ],
            $sanitized,
        );
    }

    public function testIntegerKeysKeepTheirValues(): void
    {
        $integerKeyed = ReplayPayloadSanitizer::sanitize([5 => 'value']);

        self::assertIsArray($integerKeyed);
        self::assertCount(1, $integerKeyed);
        self::assertContains('value', $integerKeyed);
    }

    public function testNestingFiveLevelsDeepSurvivesAndSixIsTruncated(): void
    {
        $fiveDeep = ['a' => ['b' => ['c' => ['d' => ['e' => 'leaf']]]]];
        $sixDeep = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'leaf']]]]]];

        $sanitizedFive = ReplayPayloadSanitizer::sanitize($fiveDeep);
        self::assertIsArray($sanitizedFive);
        self::assertSame($fiveDeep, $sanitizedFive);

        $sanitizedSix = ReplayPayloadSanitizer::sanitize($sixDeep);
        self::assertIsArray($sanitizedSix);
        self::assertSame(
            ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => '[TRUNCATED]']]]]]],
            $sanitizedSix,
        );
    }

    public function testTheHundredthEntrySurvivesAndTheHundredFirstIsDropped(): void
    {
        $entries = [];
        for ($i = 0; $i < 101; ++$i) {
            $entries['k' . $i] = 'v' . $i;
        }

        $sanitized = ReplayPayloadSanitizer::sanitize($entries);

        self::assertIsArray($sanitized);
        self::assertCount(100, $sanitized);
        self::assertSame('v99', $sanitized['k99']);
        self::assertArrayNotHasKey('k100', $sanitized);
    }

    public function testStringValuesAreBoundedToFourThousandNinetySixCharacters(): void
    {
        self::assertSame(str_repeat('a', 4096), ReplayPayloadSanitizer::sanitize(str_repeat('a', 4097)));
        self::assertSame(
            str_repeat('é', 3000),
            ReplayPayloadSanitizer::sanitize(str_repeat('é', 3000)),
        );
    }
}
