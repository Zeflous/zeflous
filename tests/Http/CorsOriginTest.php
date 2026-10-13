<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\CorsOrigin;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\OriginHost;
use Zef\Framework\Http\OriginParser;
use Zef\Framework\Http\OriginPort;

/**
 * @internal
 */
#[CoversClass(CorsOrigin::class)]
#[CoversClass(OriginParser::class)]
#[CoversClass(OriginHost::class)]
#[CoversClass(OriginPort::class)]
final class CorsOriginTest extends TestCase
{
    public function testTheComparisonFormBoundariesHoldInOneBattery(): void
    {
        self::assertSame('https://example.com', CorsOrigin::normalize('https://example.com'));
        self::assertSame('http://h', CorsOrigin::normalize('http://h:80'));
        self::assertSame('https://h', CorsOrigin::normalize('https://h:443'));
        self::assertSame('http://h:443', CorsOrigin::normalize('http://h:443'));
        self::assertSame('https://h:80', CorsOrigin::normalize('https://h:80'));
        self::assertSame('http://h:1', CorsOrigin::normalize('http://h:1'));
        self::assertSame('http://[::1]', CorsOrigin::normalize('http://[::1]'));
        self::assertSame(
            'http://xk.example',
            CorsOrigin::normalize('HTTP://X' . "\u{212A}" . '.EXAMPLE'),
            'the Kelvin sign lower-cases into the ASCII k it folds to',
        );
    }

    #[DataProvider('provideTheComparisonFormIsTheSerialisedLowercaseAuthorityCases')]
    public function testTheComparisonFormIsTheSerialisedLowercaseAuthority(string $origin, string $expected): void
    {
        self::assertSame($expected, CorsOrigin::normalize($origin));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTheComparisonFormIsTheSerialisedLowercaseAuthorityCases(): iterable
    {
        yield 'verbatim' => ['https://example.com', 'https://example.com'];

        yield 'surrounding whitespace is trimmed' => ['  https://example.com  ', 'https://example.com'];

        yield 'scheme and host fold to lowercase' => ['HTTPS://Example.COM', 'https://example.com'];

        yield 'mixed-case scheme' => ['HtTp://Example.com', 'http://example.com'];

        yield 'the http default port is omitted' => ['http://example.com:80', 'http://example.com'];

        yield 'the https default port is omitted' => ['https://example.com:443', 'https://example.com'];

        yield 'a non-default port is kept' => ['https://app.example:8443', 'https://app.example:8443'];

        yield 'an ipv4 host' => ['http://192.168.1.1', 'http://192.168.1.1'];

        yield 'an ipv6 host is re-bracketed' => ['http://[::1]', 'http://[::1]'];

        yield 'an ipv6 host with a port' => ['https://[2001:db8::1]:8443', 'https://[2001:db8::1]:8443'];

        yield 'the privacy origin is its own form' => ['null', 'null'];

        yield 'the privacy origin is trimmed' => [' null ', 'null'];

        yield 'a single-label host' => ['http://localhost', 'http://localhost'];

        yield 'a trailing carriage return is trimmed away' => ["https://example.com\r", 'https://example.com'];
    }

    #[DataProvider('provideACarriageReturnOrLineFeedIsMalformedBeforeAnythingElseCases')]
    public function testACarriageReturnOrLineFeedIsMalformedBeforeAnythingElse(string $origin): void
    {
        $this->assertThrows('Malformed Origin header.', static fn (): string => CorsOrigin::normalize($origin));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideACarriageReturnOrLineFeedIsMalformedBeforeAnythingElseCases(): iterable
    {
        yield 'a carriage return inside the host' => ["https://ex\rample.com"];

        yield 'a line feed inside the host' => ["https://exa\nmple.com"];
    }

    #[DataProvider('provideAnOriginIsAuthorityOnlyCases')]
    public function testAnOriginIsAuthorityOnly(string $origin): void
    {
        $this->assertThrows('Malformed Origin header.', static fn (): string => CorsOrigin::normalize($origin));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAnOriginIsAuthorityOnlyCases(): iterable
    {
        yield 'an empty authority' => ['http://'];

        yield 'no scheme' => ['//example.com'];

        yield 'a bare hostname is a path, not an origin' => ['example.com'];

        yield 'a user component is credentials' => ['https://user@example.com'];

        yield 'a user and password are credentials' => ['https://user:pass@example.com'];

        yield 'a path is extra' => ['https://example.com/app'];

        yield 'a query is extra' => ['https://example.com?query=1'];

        yield 'a fragment is extra' => ['https://example.com#fragment'];
    }

    #[DataProvider('provideOnlyTheWebSchemesAreSupportedCases')]
    public function testOnlyTheWebSchemesAreSupported(string $origin): void
    {
        $this->assertThrows('Unsupported Origin scheme.', static fn (): string => CorsOrigin::normalize($origin));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOnlyTheWebSchemesAreSupportedCases(): iterable
    {
        yield 'ftp' => ['ftp://example.com'];

        yield 'a chrome extension scheme' => ['chrome-extension://abcdefghijklmnop'];
    }

    #[DataProvider('provideTheHostMustBeAnIpOrARegisteredNameCases')]
    public function testTheHostMustBeAnIpOrARegisteredName(string $origin): void
    {
        $this->assertThrows('Malformed Origin host.', static fn (): string => CorsOrigin::normalize($origin));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTheHostMustBeAnIpOrARegisteredNameCases(): iterable
    {
        yield 'an underscore is not a hostname character' => ['https://under_score.example'];

        yield 'a leading hyphen edge' => ['https://-bad.example'];

        yield 'an empty label' => ['https://bad..example'];

        yield 'a hostname over 253 characters' => ['https://' . self::hostTooLongToRegister()];
    }

    public function testAZeroPortIsMalformed(): void
    {
        $this->assertThrows(
            'Malformed Origin port.',
            static fn (): string => CorsOrigin::normalize('https://example.com:0'),
        );
    }

    /**
     * A bracket pair that does not span the whole host is not an IPv6
     * literal: `parse_url()` answers `a[b]` as the host, and only the
     * pattern's start anchor keeps it out of the bracket strip so the
     * grammar below can reject it.
     */
    public function testAnUnanchoredBracketPairIsNotAnIpv6Literal(): void
    {
        $this->assertThrows(
            'Malformed Origin host.',
            static fn (): string => CorsOrigin::normalize('http://a[b]'),
        );
    }

    /**
     * A hostname one byte over the 253-character registration limit.
     */
    private static function hostTooLongToRegister(): string
    {
        return 'a.' . str_repeat('ab.', 83) . 'example';
    }

    /**
     * @param callable(): string $operation
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
