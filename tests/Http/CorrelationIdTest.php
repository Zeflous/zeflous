<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\CorrelationId;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\ForeignServerRequest;

/**
 * @internal
 */
#[CoversClass(CorrelationId::class)]
#[CoversClass(ServerRequest::class)]
final class CorrelationIdTest extends TestCase
{
    public function testAWellFormedClaimIsReused(): void
    {
        self::assertSame('req-123', CorrelationId::for($this->requestWithClaim('req-123')));
        self::assertSame('aB09._:-x', CorrelationId::for($this->requestWithClaim('aB09._:-x')));
    }

    /**
     * The grammar's own length bound: 128 bytes is still a claim, 129 is
     * not.
     */
    public function testTheClaimBoundIsExactlyOneHundredTwentyEightBytes(): void
    {
        $claimed = str_repeat('a', 128);

        self::assertSame($claimed, CorrelationId::for($this->requestWithClaim($claimed)));
        self::assertDoesNotMatchRegularExpression(
            '/^a+$/',
            CorrelationId::for($this->requestWithClaim(str_repeat('a', 129))),
        );
    }

    public function testAnAbsentClaimIsRegenerated(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', CorrelationId::for($this->requestWithClaim('')));
    }

    /**
     * The hardening pin: the legacy pattern's `$` anchor admitted a
     * trailing newline ("abc\n" matched, because `$` also matches
     * immediately before a final newline), which would have carried a
     * header-injected line break onto the response's field line. The
     * house request cannot carry such a value at all (its own header
     * grammar rejects it at construction), so the verbatim foreign
     * double is the honest carrier.
     */
    public function testATrailingNewlineIsNotAWellFormedClaim(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/^abc$/',
            CorrelationId::for($this->foreignRequestWithClaim("abc\n")),
        );
    }

    public function testMalformedClaimsAreRegenerated(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/^abc def$/',
            CorrelationId::for($this->requestWithClaim('abc def')),
        );
        self::assertDoesNotMatchRegularExpression('/^café$/', CorrelationId::for($this->requestWithClaim('café')));
        self::assertDoesNotMatchRegularExpression(
            '/^ab$/',
            CorrelationId::for($this->foreignRequestWithClaim('ab' . "\x00" . 'cd')),
        );
    }

    public function testGeneratedClaimsAreThirtyTwoHexDigitsAndFresh(): void
    {
        $first = CorrelationId::for($this->requestWithClaim('has space'));
        $second = CorrelationId::for($this->requestWithClaim('has space'));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $second);
        self::assertNotSame($first, $second);
    }

    private function requestWithClaim(string $claim): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('/users'),
            [],
            [],
            [],
            [],
            null,
            $claim === '' ? [] : ['X-Request-ID' => $claim],
        );
    }

    /**
     * The verbatim carrier for claims the house request's own header
     * grammar refuses at construction.
     */
    private function foreignRequestWithClaim(string $claim): ForeignServerRequest
    {
        return new ForeignServerRequest(
            new Uri('/users'),
            [],
            ['x-request-id' => [$claim]],
            Stream::fromString(''),
        );
    }
}
