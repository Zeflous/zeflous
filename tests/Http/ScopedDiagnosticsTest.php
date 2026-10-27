<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Http\ScopedDiagnostics;
use Zef\Test\Support\Http\DeprecatingStreamWrapper;
use Zef\Test\Support\Http\ThrowingStreamWrapper;
use Zef\Test\Support\Http\UnopenableStreamWrapper;

/**
 * @internal
 */
#[CoversClass(ScopedDiagnostics::class)]
final class ScopedDiagnosticsTest extends TestCase
{
    private const string DEPRECATING_PROTOCOL = 'zef-deprecating-open';

    private const string UNOPENABLE_PROTOCOL = 'zef-unopenable';

    private const string THROWING_PROTOCOL = 'zef-throwing-open';

    #[Override]
    protected function setUp(): void
    {
        stream_wrapper_register(self::DEPRECATING_PROTOCOL, DeprecatingStreamWrapper::class);
        stream_wrapper_register(self::UNOPENABLE_PROTOCOL, UnopenableStreamWrapper::class);
        stream_wrapper_register(self::THROWING_PROTOCOL, ThrowingStreamWrapper::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        stream_wrapper_unregister(self::DEPRECATING_PROTOCOL);
        stream_wrapper_unregister(self::UNOPENABLE_PROTOCOL);
        stream_wrapper_unregister(self::THROWING_PROTOCOL);
    }

    public function testFopenCapturesTheWarningGradeDiagnostic(): void
    {
        [$result, $warning] = ScopedDiagnostics::fopen(self::UNOPENABLE_PROTOCOL . '://target', 'x+b');

        self::assertFalse($result);
        self::assertNotNull($warning);
    }

    public function testFopenLeavesSubWarningGradeSeveritiesUncaptured(): void
    {
        [$result, $warning] = ScopedDiagnostics::fopen(self::DEPRECATING_PROTOCOL . '://target', 'rb');

        self::assertIsResource($result);
        self::assertNull($warning);

        fclose($result);
    }

    public function testFopenSwallowsTheDiagnosticSoTheEngineLoggerStaysSilent(): void
    {
        $log = (string) tempnam(sys_get_temp_dir(), 'zef-scoped-');
        $previousErrorLog = \ini_get('error_log');
        $previousDisplayErrors = \ini_get('display_errors');
        $previousLogErrors = \ini_get('log_errors');
        $previousLevel = error_reporting();

        ini_set('error_log', $log);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(\E_ALL);

        try {
            [$result, $warning] = ScopedDiagnostics::fopen(self::UNOPENABLE_PROTOCOL . '://target', 'x+b');

            self::assertFalse($result);
            self::assertNotNull($warning);

            $logged = file_get_contents($log);

            self::assertIsString($logged);
            self::assertSame('', $logged);
        } finally {
            ini_set('error_log', (string) $previousErrorLog);
            ini_set('display_errors', (string) $previousDisplayErrors);
            ini_set('log_errors', (string) $previousLogErrors);
            error_reporting($previousLevel);
            unlink($log);
        }
    }

    public function testFopenRestoresTheHandlerWhenTheEngineThrows(): void
    {
        $probeSawDiagnostic = false;
        set_error_handler(static function () use (&$probeSawDiagnostic): bool {
            $probeSawDiagnostic = true;

            return true;
        });

        try {
            try {
                ScopedDiagnostics::fopen(self::THROWING_PROTOCOL . '://resource', 'r');
                self::fail('The wrapper failure was expected to propagate.');
            } catch (RuntimeException) {
                // The engine-level wrapper failure propagates unchanged.
            }

            // With the capture's handler restored, the probe owns the stack
            // again and answers the diagnostic itself.
            trigger_error('probe', \E_USER_WARNING);
        } finally {
            restore_error_handler();
        }

        self::assertTrue($probeSawDiagnostic);
    }

    public function testRenameCapturesTheWarningGradeDiagnostic(): void
    {
        $missing = sys_get_temp_dir() . '/zef-scoped-' . bin2hex(random_bytes(8));

        [$renamed, $warning] = ScopedDiagnostics::rename($missing, $missing . '-target');

        self::assertFalse($renamed);
        self::assertNotNull($warning);
        self::assertStringContainsString('No such file or directory', $warning);
    }
}
