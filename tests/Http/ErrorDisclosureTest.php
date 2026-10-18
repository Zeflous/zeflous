<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Exception\MethodNotAllowedException;
use Zef\Framework\Exception\NotFoundException;
use Zef\Framework\Exception\ValidationException;
use Zef\Framework\Http\ErrorDisclosure;
use Zef\Framework\Http\PayloadTooLargeException;
use Zef\Framework\Http\RedactedMessage;

/**
 * @internal
 */
#[CoversClass(ErrorDisclosure::class)]
#[CoversClass(RedactedMessage::class)]
#[CoversClass(ValidationException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(MethodNotAllowedException::class)]
#[CoversClass(PayloadTooLargeException::class)]
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

    /**
     * The typed family's whole point: a message written for the client is
     * shown in production too -- the untyped arm's dev-only gate never
     * applied to a signal whose contract says the words are public.
     */
    public function testATypedSignalExposesItsMessageInBothModes(): void
    {
        $notFoundException = new NotFoundException('User with ID 123 not found.');

        self::assertSame('User with ID 123 not found.', ErrorDisclosure::message($notFoundException, 404, true));
        self::assertSame('User with ID 123 not found.', ErrorDisclosure::message($notFoundException, 404, false));
    }

    public function testATypedMethodRefusalExposesItsEvidenceMessage(): void
    {
        $methodNotAllowedException = new MethodNotAllowedException('GET', '/users', ['POST']);

        self::assertSame(
            "Method 'GET' is not allowed for /users.",
            ErrorDisclosure::message($methodNotAllowedException, 405, false),
        );
    }

    /**
     * Public by contract is not public without the redaction engine: an
     * author who embedded credential material in a client-facing message
     * is stopped by the policy, not by review.
     */
    public function testATypedSignalIsStillRedacted(): void
    {
        $validationException = new ValidationException('password=hunter2 is not an acceptable password');

        self::assertSame(
            'password=[REDACTED] is not an acceptable password',
            ErrorDisclosure::message($validationException, 400, false),
        );
        self::assertSame(
            'password=[REDACTED] is not an acceptable password',
            ErrorDisclosure::message($validationException, 400, true),
        );
    }

    /**
     * The fixed-title posture survives the typed swap: the budget refusal
     * now arrives as a class, and the 413 answer still says only the title
     * -- there is nothing to diagnose from a budget refusal.
     */
    public function testTheTypedBudgetRefusalKeepsTheFixedTitle(): void
    {
        $payloadTooLargeException = new PayloadTooLargeException();

        self::assertSame('Content Too Large', ErrorDisclosure::message($payloadTooLargeException, 413, true));
        self::assertSame('Content Too Large', ErrorDisclosure::message($payloadTooLargeException, 413, false));
    }
}
