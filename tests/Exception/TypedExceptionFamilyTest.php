<?php

declare(strict_types=1);

namespace Zef\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Exception\AuthenticationException;
use Zef\Framework\Exception\AuthorizationException;
use Zef\Framework\Exception\CarriesHttpStatus;
use Zef\Framework\Exception\ConflictException;
use Zef\Framework\Exception\InternalException;
use Zef\Framework\Exception\InvariantViolationException;
use Zef\Framework\Exception\MethodNotAllowedException;
use Zef\Framework\Exception\NativeErrorException;
use Zef\Framework\Exception\NotAcceptableException;
use Zef\Framework\Exception\NotFoundException;
use Zef\Framework\Exception\RateLimitException;
use Zef\Framework\Exception\ValidationException;
use Zef\Framework\Exception\ZefException;

/**
 * @internal
 *
 * The per-member provider signature types its rows as the framework root,
 * so membership itself is pinned by the type system on every row, and the
 * {@see CarriesHttpStatus} capability is checked against the root type --
 * a fact PHPStan cannot fold, because it cannot speak for every subtype
 */
#[CoversClass(ValidationException::class)]
#[CoversClass(AuthenticationException::class)]
#[CoversClass(AuthorizationException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(MethodNotAllowedException::class)]
#[CoversClass(NotAcceptableException::class)]
#[CoversClass(ConflictException::class)]
#[CoversClass(RateLimitException::class)]
#[CoversClass(InternalException::class)]
#[CoversClass(NativeErrorException::class)]
final class TypedExceptionFamilyTest extends TestCase
{
    #[DataProvider('provideTheFamilyAnswersItsContractStatusesCases')]
    public function testTheFamilyAnswersItsContractStatuses(ZefException $zefException, int $expectedStatus): void
    {
        self::assertInstanceOf(CarriesHttpStatus::class, $zefException);
        self::assertSame($expectedStatus, $zefException->httpStatus());
    }

    /**
     * @return iterable<non-empty-string, array{ZefException, int}>
     */
    public static function provideTheFamilyAnswersItsContractStatusesCases(): iterable
    {
        yield 'validation answers 400' => [new ValidationException('The email field must be a valid address.'), 400];

        yield 'authentication answers 401' => [new AuthenticationException('Authentication required.'), 401];

        yield 'authorization answers 403' => [new AuthorizationException('Insufficient entitlement.'), 403];

        yield 'not found answers 404' => [new NotFoundException('User with ID 123 not found.'), 404];

        yield 'method not allowed answers 405' => [new MethodNotAllowedException('GET', '/users', ['POST']), 405];

        yield 'not acceptable answers 406' => [new NotAcceptableException(['application/json']), 406];

        yield 'conflict answers 409' => [new ConflictException('Email already registered.'), 409];

        yield 'rate limit answers 429' => [new RateLimitException(30), 429];

        yield 'internal answers 500' => [new InternalException('The queue worker observed an impossible state.'), 500];

        yield 'native error answers 500' => [
            NativeErrorException::diagnostic('A runtime notice.', 'warning', '/var/app/src/worker.php', 42),
            500,
        ];
    }

    public function testValidationCarriesTheAuthorMessage(): void
    {
        self::assertSame(
            'The email field must be a valid address.',
            new ValidationException('The email field must be a valid address.')->getMessage(),
        );
    }

    public function testMethodNotAllowedCarriesTheRefusalEvidence(): void
    {
        $methodNotAllowedException = new MethodNotAllowedException('GET', '/users', ['POST', 'PUT']);

        self::assertSame(405, $methodNotAllowedException->httpStatus());
        self::assertSame('GET', $methodNotAllowedException->method);
        self::assertSame('/users', $methodNotAllowedException->path);
        self::assertSame(['POST', 'PUT'], $methodNotAllowedException->allowedMethods);
        self::assertSame("Method 'GET' is not allowed for /users.", $methodNotAllowedException->getMessage());
    }

    public function testMethodNotAllowedPreservesTheDeclarationOrder(): void
    {
        $methodNotAllowedException = new MethodNotAllowedException('PATCH', '/users', ['PATCH', 'DELETE', 'GET']);

        self::assertSame(['PATCH', 'DELETE', 'GET'], $methodNotAllowedException->allowedMethods);
    }

    public function testMethodNotAllowedReindexesAnAssociativeList(): void
    {
        $methodNotAllowedException = new MethodNotAllowedException(
            'GET',
            '/users',
            ['first' => 'GET', 'second' => 'POST'],
        );

        self::assertSame(['GET', 'POST'], $methodNotAllowedException->allowedMethods);
    }

    public function testMethodNotAllowedRefusesAnEmptyAllowedList(): void
    {
        try {
            new MethodNotAllowedException('GET', '/users', []);
            self::fail('An empty allowed-methods list must be refused.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'A method-not-allowed refusal must name at least one allowed method.',
                $invariantViolationException->getMessage(),
            );
        }
    }

    #[DataProvider('provideMethodNotAllowedRefusesAMalformedAllowedMethodCases')]
    public function testMethodNotAllowedRefusesAMalformedAllowedMethod(mixed $entry, string $debugType): void
    {
        try {
            new MethodNotAllowedException('GET', '/users', ['POST', $entry]);
            self::fail('A malformed allowed-method entry must be refused.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'An allowed method must be a non-empty string, ' . $debugType . ' given.',
                $invariantViolationException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<non-empty-string, array{mixed, non-empty-string}>
     */
    public static function provideMethodNotAllowedRefusesAMalformedAllowedMethodCases(): iterable
    {
        yield 'a null entry' => [null, 'null'];

        yield 'an integer entry' => [42, 'int'];

        yield 'an empty string entry' => ['', 'string'];
    }

    public function testNotAcceptableCarriesTheProducibleMediaTypes(): void
    {
        $notAcceptableException = new NotAcceptableException(['application/json', 'text/html']);

        self::assertSame(406, $notAcceptableException->httpStatus());
        self::assertSame(['application/json', 'text/html'], $notAcceptableException->acceptableMediaTypes);
        self::assertSame('Supported media types: application/json, text/html', $notAcceptableException->getMessage());
        self::assertSame('N_A', $notAcceptableException->getErrorCode());
    }

    public function testNotAcceptableReindexesAnAssociativeList(): void
    {
        $notAcceptableException = new NotAcceptableException(
            ['first' => 'application/json', 'second' => 'text/html'],
        );

        self::assertSame(['application/json', 'text/html'], $notAcceptableException->acceptableMediaTypes);
    }

    public function testNotAcceptableRefusesAnEmptyMediaTypesList(): void
    {
        try {
            new NotAcceptableException([]);
            self::fail('An empty acceptable-media-types list must be refused.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'A not-acceptable refusal must name at least one acceptable media type.',
                $invariantViolationException->getMessage(),
            );
        }
    }

    #[DataProvider('provideNotAcceptableRefusesAMalformedMediaTypeCases')]
    public function testNotAcceptableRefusesAMalformedMediaType(mixed $entry, string $debugType): void
    {
        try {
            new NotAcceptableException(['application/json', $entry]);
            self::fail('A malformed acceptable-media-type entry must be refused.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'An acceptable media type must be a non-empty string, ' . $debugType . ' given.',
                $invariantViolationException->getMessage(),
            );
        }
    }

    /**
     * @return iterable<non-empty-string, array{mixed, non-empty-string}>
     */
    public static function provideNotAcceptableRefusesAMalformedMediaTypeCases(): iterable
    {
        yield 'a null entry' => [null, 'null'];

        yield 'an integer entry' => [42, 'int'];

        yield 'an empty string entry' => ['', 'string'];
    }

    public function testRateLimitCarriesTheRetryWindow(): void
    {
        $rateLimitException = new RateLimitException(30, 'Too many sign-in attempts.');

        self::assertSame(429, $rateLimitException->httpStatus());
        self::assertSame(30, $rateLimitException->retryAfterSeconds);
        self::assertSame('Too many sign-in attempts.', $rateLimitException->getMessage());
    }

    public function testRateLimitDefaultsItsMessage(): void
    {
        $rateLimitException = new RateLimitException(0);

        self::assertSame('Too many requests.', $rateLimitException->getMessage());
        self::assertSame(0, $rateLimitException->retryAfterSeconds);
    }

    public function testRateLimitRefusesANegativeRetryWindow(): void
    {
        try {
            new RateLimitException(-1);
            self::fail('A negative retry-after window must be refused.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'A rate-limit refusal cannot carry a negative retry-after window.',
                $invariantViolationException->getMessage(),
            );
        }
    }
}
