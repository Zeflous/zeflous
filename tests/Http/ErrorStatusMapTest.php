<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Error;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Exception\AuthenticationException;
use Zef\Framework\Exception\AuthorizationException;
use Zef\Framework\Exception\CarriesHttpStatus;
use Zef\Framework\Exception\ConflictException;
use Zef\Framework\Exception\InternalException;
use Zef\Framework\Exception\MethodNotAllowedException;
use Zef\Framework\Exception\NotFoundException;
use Zef\Framework\Exception\RateLimitException;
use Zef\Framework\Exception\ValidationException;
use Zef\Framework\Exception\ZefException;
use Zef\Framework\Http\ErrorStatusMap;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\PayloadTooLargeException;

/**
 * @internal
 */
#[CoversClass(ErrorStatusMap::class)]
#[CoversClass(ValidationException::class)]
#[CoversClass(AuthenticationException::class)]
#[CoversClass(AuthorizationException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(MethodNotAllowedException::class)]
#[CoversClass(ConflictException::class)]
#[CoversClass(RateLimitException::class)]
#[CoversClass(InternalException::class)]
#[CoversClass(PayloadTooLargeException::class)]
final class ErrorStatusMapTest extends TestCase
{
    public function testTheTypedFamilyAnswersItsContractStatuses(): void
    {
        self::assertSame(400, ErrorStatusMap::statusFor(new ValidationException('Fields failed validation.')));
        self::assertSame(401, ErrorStatusMap::statusFor(new AuthenticationException('Authentication required.')));
        self::assertSame(403, ErrorStatusMap::statusFor(new AuthorizationException('Insufficient entitlement.')));
        self::assertSame(404, ErrorStatusMap::statusFor(new NotFoundException('User with ID 123 not found.')));
        self::assertSame(405, ErrorStatusMap::statusFor(new MethodNotAllowedException('GET', '/users', ['POST'])));
        self::assertSame(409, ErrorStatusMap::statusFor(new ConflictException('Email already registered.')));
        self::assertSame(429, ErrorStatusMap::statusFor(new RateLimitException(30)));
        self::assertSame(500, ErrorStatusMap::statusFor(new InternalException('Invariant broken.')));
    }

    public function testTheTypedBudgetRefusalAnswersContentTooLarge(): void
    {
        self::assertSame(413, ErrorStatusMap::statusFor(new PayloadTooLargeException()));
    }

    public function testAHostDefinedSignalJoinsTheSameResolution(): void
    {
        $hostSignal = new class extends ZefException implements CarriesHttpStatus {
            #[Override]
            public function httpStatus(): int
            {
                return 418;
            }
        };

        self::assertSame(418, ErrorStatusMap::statusFor($hostSignal));
    }

    public function testEveryOtherLayerFailureAnswersBadRequest(): void
    {
        self::assertSame(400, ErrorStatusMap::statusFor(new HttpException('Stream detached.')));
        self::assertSame(400, ErrorStatusMap::statusFor(new HttpException('Invalid request target.')));
        self::assertSame(400, ErrorStatusMap::statusFor(new HttpException('')));
    }

    /**
     * The retirement pin: the body-budget refusal text is no longer a
     * signal -- a bare layer failure wearing the refusal's words answers
     * the coarse 400 like every other untyped layer failure, because the
     * 413 reaches the map through the class, never the message.
     */
    public function testTheBudgetRefusalMessageIsNoLongerASignal(): void
    {
        self::assertSame(
            400,
            ErrorStatusMap::statusFor(new HttpException('Request body exceeds configured size limit.')),
        );
    }

    public function testAnApplicationFaultAnswersTheInternalError(): void
    {
        self::assertSame(500, ErrorStatusMap::statusFor(new RuntimeException('boom')));
        self::assertSame(500, ErrorStatusMap::statusFor(new Error('boom')));
    }
}
