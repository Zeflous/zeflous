<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\DiagnosticSeverity;

/**
 * Direct battery for the diagnostic lane's severity fold: every engine
 * severity the handler can be handed answers its PSR-3 level, and an
 * unmapped bit answers the fail-open warning posture.
 *
 * @internal
 */
#[CoversClass(DiagnosticSeverity::class)]
final class DiagnosticSeverityTest extends TestCase
{
    #[DataProvider('provideTheEngineSeveritiesAnswerTheirLevelsCases')]
    public function testTheEngineSeveritiesAnswerTheirLevels(int $severity, string $expectedLevel): void
    {
        self::assertSame($expectedLevel, DiagnosticSeverity::for($severity));
    }

    /**
     * @return iterable<non-empty-string, array{int, string}>
     */
    public static function provideTheEngineSeveritiesAnswerTheirLevelsCases(): iterable
    {
        yield 'engine error is critical' => [\E_ERROR, 'critical'];

        yield 'parse error is critical' => [\E_PARSE, 'critical'];

        yield 'core error is critical' => [\E_CORE_ERROR, 'critical'];

        yield 'core warning is critical' => [\E_CORE_WARNING, 'critical'];

        yield 'compile error is critical' => [\E_COMPILE_ERROR, 'critical'];

        yield 'compile warning is critical' => [\E_COMPILE_WARNING, 'critical'];

        yield 'user error is critical' => [\E_USER_ERROR, 'critical'];

        yield 'recoverable error is an error' => [\E_RECOVERABLE_ERROR, 'error'];

        yield 'warning is a warning' => [\E_WARNING, 'warning'];

        yield 'user warning is a warning' => [\E_USER_WARNING, 'warning'];

        yield 'notice is a notice' => [\E_NOTICE, 'notice'];

        yield 'user notice is a notice' => [\E_USER_NOTICE, 'notice'];

        yield 'deprecation is informational' => [\E_DEPRECATED, 'info'];

        yield 'user deprecation is informational' => [\E_USER_DEPRECATED, 'info'];
    }

    public function testAnUnmappedBitAnswersTheFailOpenWarning(): void
    {
        $beyondTheFold = 1 << 15;

        self::assertSame(0, $beyondTheFold & \E_ALL, "The witness bit must sit outside the engine's own mask.");
        self::assertSame('warning', DiagnosticSeverity::for($beyondTheFold));
    }

    public function testTheEmptySeverityAnswersTheFailOpenWarning(): void
    {
        self::assertSame('warning', DiagnosticSeverity::for(0));
    }

    public function testACombinedSeverityAnswersByTheHighestPriorityFamily(): void
    {
        self::assertSame('warning', DiagnosticSeverity::for(\E_WARNING | \E_USER_NOTICE));
        self::assertSame('notice', DiagnosticSeverity::for(\E_USER_NOTICE | \E_DEPRECATED));
    }

    public function testTheFatalFamilyVerdictAnswersTrueOnlyForEngineFatals(): void
    {
        self::assertTrue(DiagnosticSeverity::isFatalAtShutdown(\E_ERROR));
        self::assertTrue(DiagnosticSeverity::isFatalAtShutdown(\E_PARSE));
        self::assertTrue(DiagnosticSeverity::isFatalAtShutdown(\E_COMPILE_ERROR));
        self::assertFalse(DiagnosticSeverity::isFatalAtShutdown(\E_WARNING));
        self::assertFalse(DiagnosticSeverity::isFatalAtShutdown(\E_RECOVERABLE_ERROR));
        self::assertFalse(DiagnosticSeverity::isFatalAtShutdown(\E_DEPRECATED));
    }
}
