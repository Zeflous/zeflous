<?php

declare(strict_types=1);

namespace Zef\Test\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Zef\Framework\Logging\LogContextSanitizer;

/**
 * Direct battery for the structured-log sanitizer: depth bounds, entry
 * bounds, key-name redaction, throwable folding, and the string length
 * and multibyte boundaries.
 *
 * @internal
 */
#[CoversClass(LogContextSanitizer::class)]
final class LogContextSanitizerTest extends TestCase
{
    public function testDepthFiveSurvivesAndDepthSixIsTruncated(): void
    {
        $fiveDeep = ['a' => ['b' => ['c' => ['d' => ['e' => 'leaf']]]]];

        self::assertSame($fiveDeep, LogContextSanitizer::sanitize($fiveDeep));
        self::assertSame(
            ['a' => ['b' => ['c' => ['d' => ['e' => ['_truncated' => true]]]]]],
            LogContextSanitizer::sanitize(
                ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'leaf']]]]]],
            ),
        );
    }

    public function testTheHundredthEntrySurvivesAndTheHundredFirstIsDropped(): void
    {
        $context = [];
        for ($i = 0; $i < 101; ++$i) {
            $context['k' . $i] = 'v' . $i;
        }

        $sanitized = LogContextSanitizer::sanitize($context);

        self::assertCount(100, $sanitized);
        self::assertSame('v99', $sanitized['k99']);
        self::assertArrayNotHasKey('k100', $sanitized);
    }

    public function testSensitiveKeysAreRedactedRegardlessOfCase(): void
    {
        $sanitized = LogContextSanitizer::sanitize(
            ['PASSWORD' => 'hunter2', 'filter' => 'active'],
        );

        self::assertSame(['PASSWORD' => '[REDACTED]', 'filter' => 'active'], $sanitized);
    }

    public function testThrowablesFoldToClassMessageAndCode(): void
    {
        $sanitized = LogContextSanitizer::sanitize(
            ['failure' => new RuntimeException('password=secret123 gone', 42)],
        );

        self::assertSame(
            [
                'failure' => [
                    'class' => RuntimeException::class,
                    'message' => 'password=[REDACTED] gone',
                    'code' => 42,
                ],
            ],
            $sanitized,
        );
    }

    public function testStringValuesAreBoundedToFourThousandNinetySixCharacters(): void
    {
        $bounded = LogContextSanitizer::sanitize(['note' => str_repeat('a', 4097)]);
        self::assertSame(str_repeat('a', 4096), $bounded['note']);

        $multibyte = LogContextSanitizer::sanitize(['note' => str_repeat('é', 3000)]);
        self::assertSame(str_repeat('é', 3000), $multibyte['note']);
    }

    public function testUnsupportedValuesAnswerTheirDebugType(): void
    {
        self::assertSame(['probe' => '[stdClass]'], LogContextSanitizer::sanitize(['probe' => new stdClass()]));
    }
}
