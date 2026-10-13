<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\TrustedHosts;

/**
 * @internal
 */
#[CoversClass(TrustedHosts::class)]
final class TrustedHostsTest extends TestCase
{
    public function testAHostOnTheAllowListIsTrusted(): void
    {
        $trustedHosts = new TrustedHosts(['api.example.com', 'admin.example.com']);

        $trustedHosts->assertTrusted('api.example.com');
        $trustedHosts->assertTrusted('admin.example.com');

        $this->assertThrows(
            'Untrusted host: staging.example.com.',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('staging.example.com');
            },
        );
    }

    public function testTheComparisonFoldsCaseAndSurroundingWhitespace(): void
    {
        $trustedHosts = new TrustedHosts(['api.example.com']);

        $trustedHosts->assertTrusted('  API.EXAMPLE.COM  ');

        $this->assertThrows(
            'Untrusted host: api.example.org.',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('api.example.org');
            },
        );
    }

    /**
     * The fold is the layer's full Unicode case map: the Kelvin sign
     * lower-cases to the ASCII `k`, so its spelling compares equal to the
     * ASCII one (the same fold point the URI host comparison documents).
     */
    public function testTheComparisonFoldsThroughTheUnicodeCaseMap(): void
    {
        $trustedHosts = new TrustedHosts(["\u{212A}ef.io"]);

        $trustedHosts->assertTrusted('kef.io');

        $this->assertThrows(
            'Untrusted host: lef.io.',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('lef.io');
            },
        );
    }

    /**
     * The fold trims Unicode whitespace: an ideographic space around the
     * host is surrounding whitespace, not part of the name.
     */
    public function testTheComparisonTrimsUnicodeWhitespace(): void
    {
        $trustedHosts = new TrustedHosts(['api.example.com']);

        $trustedHosts->assertTrusted("\u{3000}api.example.com\u{3000}");

        $this->assertThrows(
            'Untrusted host: api.example.net.',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('api.example.net');
            },
        );
    }

    public function testABracketedIpv6LiteralComparesLikeItsUriForm(): void
    {
        $trustedHosts = new TrustedHosts(['::1']);

        $trustedHosts->assertTrusted('[::1]');

        $this->assertThrows(
            'Untrusted host: [::2].',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('[::2]');
            },
        );
    }

    /**
     * A bracket that is not the literal's first byte strips nothing: the
     * anchored pattern demands the whole host sit between the brackets,
     * so a bracketed trusted name inside an untrusted host must not
     * smuggle itself into the comparison form.
     */
    public function testABracketInsideAHostStripsNothing(): void
    {
        $trustedHosts = new TrustedHosts(['api.example.com']);

        $this->assertThrows(
            'Untrusted host: evil.example.com[api.example.com].',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('evil.example.com[api.example.com]');
            },
        );

        $this->assertThrows(
            'Untrusted host: api.example.com[x].',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('api.example.com[x]');
            },
        );
    }

    /**
     * The bracket pattern is dotall: a literal whose bracketed content
     * spans a newline strips the brackets all the same, comparing the
     * content -- the byte-grammar shape the reference implementation's
     * indexed probes answered.
     */
    public function testABracketedLiteralWithANewlineStripsItsBrackets(): void
    {
        $trustedHosts = new TrustedHosts(["api.\nexample.com"]);

        $trustedHosts->assertTrusted("[api.\nexample.com]");

        $this->assertThrows(
            "Untrusted host: [api.\nexample.org].",
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted("[api.\nexample.org]");
            },
        );
    }

    public function testAStringableAllowListEntryComparesByItsStringForm(): void
    {
        $entry = new class implements Stringable {
            #[Override]
            public function __toString(): string
            {
                return 'api.example.com';
            }
        };

        $trustedHosts = new TrustedHosts([$entry]);

        $trustedHosts->assertTrusted('API.EXAMPLE.COM');

        $this->assertThrows(
            'Untrusted host: admin.example.com.',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('admin.example.com');
            },
        );
    }

    public function testAHostOutsideTheAllowListIsRejected(): void
    {
        $trustedHosts = new TrustedHosts(['api.example.com']);

        $this->assertThrows(
            'Untrusted host: evil.example.com.',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('evil.example.com');
            },
        );
    }

    /**
     * The rejection message carries the host as it was given, not the
     * comparison form -- the operator reads the spelling the client sent.
     */
    public function testTheRejectionMessageCarriesTheHostAsGiven(): void
    {
        $trustedHosts = new TrustedHosts(['api.example.com']);

        $this->assertThrows(
            'Untrusted host:   EVIL.example.com  .',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('  EVIL.example.com  ');
            },
        );
    }

    public function testAnEmptyAllowListTrustsEveryHostByDefault(): void
    {
        $trustedHosts = new TrustedHosts();

        $trustedHosts->assertTrusted('anything.example.net');

        $failClosed = new TrustedHosts([], true);

        $this->assertThrows(
            'Trusted host allow-list is empty — host-header pinning is misconfigured.',
            static function () use ($failClosed): void {
                $failClosed->assertTrusted('anything.example.net');
            },
        );
    }

    public function testAnEmptyAllowListCanFailClosedOnDemand(): void
    {
        $trustedHosts = new TrustedHosts([], true);

        $this->assertThrows(
            'Trusted host allow-list is empty — host-header pinning is misconfigured.',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('api.example.com');
            },
        );
    }

    public function testAnEmptyHostStaysAccepted(): void
    {
        $trustedHosts = new TrustedHosts(['api.example.com']);

        $trustedHosts->assertTrusted('');

        $this->assertThrows(
            'Untrusted host: evil.example.com.',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('evil.example.com');
            },
        );
    }

    /**
     * The FQDN root dot is NOT folded away here -- unlike the authority
     * parser's comparison form, the allow-list comparison keeps the entry
     * and the host exactly as given, so a root-dot spelling does not
     * silently match its dotless twin (move-verbatim from the reference
     * implementation).
     */
    public function testARootDotSpellingDoesNotMatchItsDotlessTwin(): void
    {
        $trustedHosts = new TrustedHosts(['example.com']);

        $this->assertThrows(
            'Untrusted host: example.com..',
            static function () use ($trustedHosts): void {
                $trustedHosts->assertTrusted('example.com.');
            },
        );
    }

    #[DataProvider('provideANonStringEntryIsRejectedFailClosedCases')]
    public function testANonStringEntryIsRejectedFailClosed(mixed $entry): void
    {
        $this->assertThrows(
            'Trusted host entries must be strings, got ' . get_debug_type($entry) . '.',
            static function () use ($entry): void {
                new TrustedHosts([$entry]);
            },
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideANonStringEntryIsRejectedFailClosedCases(): iterable
    {
        yield 'an integer entry' => [42];

        yield 'a float entry' => [1.5];

        yield 'a boolean entry' => [true];

        yield 'a null entry' => [null];

        yield 'an array entry' => [['api.example.com']];
    }

    /**
     * A non-string entry fails closed even when the list also carries
     * valid entries -- the validation walks the whole list, so the
     * rejection is not order-dependent.
     */
    public function testANonStringEntryIsRejectedAmongValidEntries(): void
    {
        $this->assertThrows(
            'Trusted host entries must be strings, got int.',
            static function (): void {
                new TrustedHosts(['api.example.com', 42]);
            },
        );
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
