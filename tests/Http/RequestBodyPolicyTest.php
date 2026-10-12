<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\RequestBodyPolicy;

/**
 * @internal
 */
#[CoversClass(RequestBodyPolicy::class)]
final class RequestBodyPolicyTest extends TestCase
{
    public function testTheDefaultPolicyAnswersTwoMebibytes(): void
    {
        $requestBodyPolicy = new RequestBodyPolicy();

        self::assertFalse($requestBodyPolicy->claimExceeds('2097152'));
        self::assertTrue($requestBodyPolicy->claimExceeds('2097153'));
    }

    public function testACustomPolicyKeepsItsOwnLimit(): void
    {
        $requestBodyPolicy = new RequestBodyPolicy(1024);

        self::assertSame(1024, $requestBodyPolicy->maxBytes);
    }

    /**
     * The smallest workable policy is one byte: the boundary keeps the
     * guard honest against an off-by-one relaxation.
     */
    public function testASingleBytePolicyIsTheSmallestLegalOne(): void
    {
        $requestBodyPolicy = new RequestBodyPolicy(1);

        self::assertSame(1, $requestBodyPolicy->maxBytes);
    }

    #[DataProvider('provideANonPositiveMaximumIsRejectedFailClosedCases')]
    public function testANonPositiveMaximumIsRejectedFailClosed(int $maxBytes): void
    {
        $this->assertThrows(
            'Request body maxBytes must be greater than zero.',
            static function () use ($maxBytes): void {
                new RequestBodyPolicy($maxBytes);
            },
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideANonPositiveMaximumIsRejectedFailClosedCases(): iterable
    {
        yield 'zero bytes' => [0];

        yield 'a negative byte count' => [-1];

        yield 'a deeply negative byte count' => [-2097152];
    }

    public function testAClaimIsJudgedAgainstThePolicyLimit(): void
    {
        $requestBodyPolicy = new RequestBodyPolicy(100);

        self::assertFalse($requestBodyPolicy->claimExceeds('0'));
        self::assertFalse($requestBodyPolicy->claimExceeds('99'));
        self::assertFalse($requestBodyPolicy->claimExceeds('100'));
        self::assertTrue($requestBodyPolicy->claimExceeds('101'));
        self::assertTrue($requestBodyPolicy->claimExceeds('3145728'));
    }

    /**
     * A field value may legally carry surrounding OWS; the claim is judged
     * after the whitespace is trimmed away.
     */
    public function testAClaimCarryingSurroundingWhitespaceIsTrimmed(): void
    {
        $requestBodyPolicy = new RequestBodyPolicy(100);

        self::assertTrue($requestBodyPolicy->claimExceeds(' 101 '));
        self::assertTrue($requestBodyPolicy->claimExceeds("\t101\n"));
        self::assertFalse($requestBodyPolicy->claimExceeds(' 100 '));
    }

    public function testAnEmptyOrWhitespaceOnlyClaimCarriesNoSizeSignal(): void
    {
        $requestBodyPolicy = new RequestBodyPolicy(1);

        self::assertFalse($requestBodyPolicy->claimExceeds(''));
        self::assertFalse($requestBodyPolicy->claimExceeds('   '));
    }

    /**
     * Only a well-formed digit string is a size claim; anything else is
     * left to the lazy half's byte counting (and to the engine, which does
     * not believe the header either).
     */
    #[DataProvider('provideAMalformedClaimIsNeverASizeSignalCases')]
    public function testAMalformedClaimIsNeverASizeSignal(string $claim): void
    {
        $requestBodyPolicy = new RequestBodyPolicy(100);

        self::assertFalse($requestBodyPolicy->claimExceeds($claim));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAMalformedClaimIsNeverASizeSignalCases(): iterable
    {
        yield 'letters' => ['abc'];

        yield 'a sign prefix' => ['-5'];

        yield 'a plus prefix' => ['+5'];

        yield 'a numeric prefix with trailing garbage' => ['101abc'];

        yield 'an exponent form' => ['1e2'];

        yield 'a hexadecimal form' => ['0x65'];

        yield 'a float form' => ['101.0'];
    }

    /**
     * Leading zeros stay a numeric claim: `007` claims seven bytes, not an
     * octal value.
     */
    public function testLeadingZerosStayADecimalClaim(): void
    {
        $policy = new RequestBodyPolicy(10);

        self::assertFalse($policy->claimExceeds('007'));

        $strict = new RequestBodyPolicy(6);

        self::assertTrue($strict->claimExceeds('007'));
    }

    /**
     * A claim beyond the integer range still exceeds any sane policy: the
     * narrowing saturates rather than wrapping.
     */
    public function testAClaimBeyondTheIntegerRangeSaturatesAndExceeds(): void
    {
        $requestBodyPolicy = new RequestBodyPolicy(2097152);

        self::assertTrue($requestBodyPolicy->claimExceeds('9223372036854775808'));
    }

    /**
     * @param callable(): void $operation
     */
    private function assertThrows(string $message, callable $operation): void
    {
        try {
            $operation();
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }
}
