<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Http\ErrorDisclosure;
use Zef\Framework\Http\RedactedMessage;

/**
 * @internal
 */
#[CoversClass(ErrorDisclosure::class)]
#[CoversClass(RedactedMessage::class)]
final class ErrorDisclosureTest extends TestCase
{
    public function testTheContentTooLargeAnswerIsTheFixedTitle(): void
    {
        $runtimeException = new RuntimeException('Request body exceeds configured size limit.');

        self::assertSame('Content Too Large', ErrorDisclosure::message($runtimeException, 413, true));
        self::assertSame('Content Too Large', ErrorDisclosure::message($runtimeException, 413, false));
    }

    public function testBadRequestExposesTheValidationTextInDevModeOnly(): void
    {
        $runtimeException = new RuntimeException('Invalid request target.');

        self::assertSame('Invalid request target.', ErrorDisclosure::message($runtimeException, 400, true));
        self::assertSame('Invalid request.', ErrorDisclosure::message($runtimeException, 400, false));
    }

    public function testTheInternalAnswerRedactsEvenInDevMode(): void
    {
        $runtimeException = new RuntimeException('login failed for token=abcd1234 at dawn');

        self::assertSame(
            'login failed for token=[REDACTED] at dawn',
            ErrorDisclosure::message($runtimeException, 500, true),
        );
        self::assertSame('An error occurred', ErrorDisclosure::message($runtimeException, 500, false));
    }

    public function testAnUnmappedStatusFallsToTheInternalPosture(): void
    {
        $runtimeException = new RuntimeException('password=hunter2 leaked');

        self::assertSame('password=[REDACTED] leaked', ErrorDisclosure::message($runtimeException, 405, true));
        self::assertSame('An error occurred', ErrorDisclosure::message($runtimeException, 405, false));
    }
}
