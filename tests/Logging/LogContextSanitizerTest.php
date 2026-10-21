<?php

declare(strict_types=1);

namespace Zef\Test\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Zef\Framework\Logging\LogContextSanitizer;
use Zef\Framework\Logging\NonFiniteFloat;

/**
 * Direct battery for the structured-log sanitizer: depth bounds, entry
 * bounds, key-name redaction, throwable folding, and the string length
 * and multibyte boundaries.
 *
 * @internal
 */
#[CoversClass(LogContextSanitizer::class)]
#[CoversClass(NonFiniteFloat::class)]
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

    /**
     * The key-name gate's credential vocabulary is the replay sanitizer's
     * own: `passwd` and `csrf` spellings are redacted by key here exactly
     * as the replay payload gate redacts them, so a host context that
     * arrives under either spelling never reaches the record as its value.
     */
    public function testThePasswdAndCsrfSpellingsJoinTheVocabulary(): void
    {
        $sanitized = LogContextSanitizer::sanitize(
            ['passwd' => 'hunter2', 'X-Csrf-Token' => 'abc123', 'filter' => 'active'],
        );

        self::assertSame(
            ['passwd' => '[REDACTED]', 'X-Csrf-Token' => '[REDACTED]', 'filter' => 'active'],
            $sanitized,
        );
    }

    /**
     * Non-finite floats answer their string spelling -- the telemetry
     * sanitizer's own doctrine -- because the record's JSON encoding
     * refuses them as values: a bare NAN context entry must degrade to
     * its diagnostic spelling, never take the whole record down with it.
     */
    public function testNonFiniteFloatsAnswerTheirStringSpelling(): void
    {
        $sanitized = LogContextSanitizer::sanitize([
            'budget' => \NAN,
            'cap' => \INF,
            'floor' => -\INF,
            'ratio' => 1.5,
            'count' => 3,
        ]);

        self::assertSame(
            ['budget' => 'NAN', 'cap' => 'INF', 'floor' => '-INF', 'ratio' => 1.5, 'count' => 3],
            $sanitized,
        );
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
