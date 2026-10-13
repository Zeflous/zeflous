<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\CidrAddress;
use Zef\Framework\Http\MaskedPrefix;
use Zef\Framework\Http\TrustedProxyMatcher;

/**
 * @internal
 */
#[CoversClass(TrustedProxyMatcher::class)]
#[CoversClass(CidrAddress::class)]
#[CoversClass(MaskedPrefix::class)]
final class TrustedProxyMatcherTest extends TestCase
{
    public function testAnExactEntryClaimsItsAddress(): void
    {
        self::assertTrue(TrustedProxyMatcher::matches('10.0.0.5', ['10.0.0.5']));
    }

    /**
     * The mixed list is the array_any kill: one non-matching entry beside
     * a matching one still claims the address.
     */
    public function testOneMatchingEntryAmongNonMatchingOnesClaimsTheAddress(): void
    {
        self::assertTrue(TrustedProxyMatcher::matches(
            '10.0.0.1',
            ['10.0.0.5', '10.0.0.0/8', '192.168.1.1'],
        ));
    }

    public function testNoMatchingEntryClaimsNothing(): void
    {
        self::assertFalse(TrustedProxyMatcher::matches(
            '10.0.0.1',
            ['10.0.0.5', '192.168.1.1', '172.16.0.0/12'],
        ));
    }

    public function testAnEmptyTrustedListClaimsNothing(): void
    {
        self::assertFalse(TrustedProxyMatcher::matches('10.0.0.1', []));
    }

    public function testSurroundingWhitespaceOnBothSidesIsTrimmed(): void
    {
        self::assertTrue(TrustedProxyMatcher::matches(' 10.0.0.5 ', [' 10.0.0.5 ']));
    }

    /**
     * The trim is the layer's Unicode trim: an ideographic space around
     * either side is surrounding whitespace, not part of the literal.
     */
    public function testUnicodeWhitespaceOnBothSidesIsTrimmed(): void
    {
        self::assertTrue(TrustedProxyMatcher::matches("\u{3000}10.0.0.5\u{3000}", ["\u{00A0}10.0.0.5\u{00A0}"]));
    }

    /**
     * A non-address under judgement answers false before any entry is
     * consulted -- even an entry that would textually equal it.
     */
    public function testANonAddressAnswersFalseEvenWhenAnEntryEqualsIt(): void
    {
        self::assertFalse(TrustedProxyMatcher::matches('not-an-address', ['not-an-address']));
    }

    public function testAnEmptyAddressAnswersFalse(): void
    {
        self::assertFalse(TrustedProxyMatcher::matches('', ['10.0.0.0/8']));
    }

    #[DataProvider('provideACidrEntryClaimsItsNetworkCases')]
    public function testACidrEntryClaimsItsNetwork(string $ip, string $entry): void
    {
        self::assertTrue(TrustedProxyMatcher::matches($ip, [$entry]));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideACidrEntryClaimsItsNetworkCases(): iterable
    {
        yield 'an IPv4 /8 network' => ['10.42.0.7', '10.0.0.0/8'];

        yield 'an IPv4 /8 network with a high-bit base byte' => ['10.1.0.1', '10.200.0.0/8'];

        yield 'an IPv4 /16 network' => ['192.168.5.200', '192.168.0.0/16'];

        yield 'an IPv4 /15 network with a one-bit remainder' => ['10.1.0.1', '10.0.0.0/15'];

        yield 'an IPv4 /24 network' => ['172.16.1.42', '172.16.1.0/24'];

        yield 'an IPv4 /32 network is the exact address' => ['10.0.0.1', '10.0.0.1/32'];

        yield 'an IPv4 /0 network claims the whole family' => ['203.0.113.9', '0.0.0.0/0'];

        yield 'an IPv4 partial-byte /17 boundary inside' => ['194.128.128.5', '194.128.128.0/17'];

        yield 'an IPv4 partial-byte /17 bit-six boundary inside' => ['194.128.64.1', '194.128.0.0/17'];

        yield 'a non-canonical network base still claims its network' => ['10.0.0.1', '10.0.0.5/8'];

        yield 'an IPv6 /0 network claims the whole family' => ['2001:db8::1', '::/0'];

        yield 'an IPv6 exact /128 network' => ['::1', '::1/128'];

        yield 'an IPv6 /32 network' => ['2001:db8:aaaa::1', '2001:db8::/32'];

        yield 'an IPv6 partial-byte /12 boundary inside' => ['2001:db8::1', '2002::/12'];
    }

    #[DataProvider('provideACidrEntryDoesNotClaimAnOutsideAddressCases')]
    public function testACidrEntryDoesNotClaimAnOutsideAddress(string $ip, string $entry): void
    {
        self::assertFalse(TrustedProxyMatcher::matches($ip, [$entry]));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideACidrEntryDoesNotClaimAnOutsideAddressCases(): iterable
    {
        yield 'an address outside the /8 network' => ['11.0.0.1', '10.0.0.0/8'];

        yield 'an address outside the /16 network' => ['192.169.0.1', '192.168.0.0/16'];

        yield 'an address outside the /24 network' => ['172.16.2.1', '172.16.1.0/24'];

        yield 'an IPv4 partial-byte /17 boundary outside' => ['194.128.127.5', '194.128.128.0/17'];

        yield 'an IPv6 address outside the /32 network' => ['2001:db9::1', '2001:db8::/32'];

        yield 'an IPv6 address outside the /12 network' => ['2001:db8::1', '2010::/12'];

        yield 'an IPv4 address never enters an IPv6 network' => ['10.0.0.1', '::/0'];

        yield 'an IPv6 address never enters an IPv4 network' => ['::1', '0.0.0.0/0'];
    }

    /**
     * The containment arithmetic's byte-level boundaries, stated as one
     * plain battery: every case pins one boundary of the prefix walk --
     * the byte divider, the zero prefix, the remainder's high-bit mask,
     * the byte-aligned exact prefix, the family width, and the byte
     * semantics of the full-byte comparison.
     */
    public function testTheContainmentArithmeticBoundaries(): void
    {
        // The byte divider: /15 covers one full byte plus the top bit of
        // the second -- 10.1.0.1 sits inside 10.0.0.0/15, 10.2.0.1 does not.
        self::assertTrue(TrustedProxyMatcher::matches('10.1.0.1', ['10.0.0.0/15']));
        self::assertFalse(TrustedProxyMatcher::matches('10.2.0.1', ['10.0.0.0/15']));

        // The zero prefix claims the whole family: the zero-length compare
        // and the zero-width mask judge nothing, whatever the base spells.
        self::assertTrue(TrustedProxyMatcher::matches('10.0.0.1', ['192.168.0.1/0']));

        // The compare reads from the first byte: an /8 claim holds for any
        // base spelling of the same network, high-bit base byte included.
        self::assertTrue(TrustedProxyMatcher::matches('10.1.0.1', ['10.200.0.0/8']));

        // The remainder's high-bit mask: /17 decides on the third byte's
        // top bit, inside and outside, bit six included.
        self::assertTrue(TrustedProxyMatcher::matches('194.128.128.5', ['194.128.128.0/17']));
        self::assertTrue(TrustedProxyMatcher::matches('194.128.64.1', ['194.128.0.0/17']));
        self::assertFalse(TrustedProxyMatcher::matches('194.128.127.5', ['194.128.128.0/17']));
        self::assertFalse(TrustedProxyMatcher::matches('194.128.192.1', ['194.128.0.0/17']));

        // The mask's width is exactly the high bits: bits below the
        // remainder never join the judgement, inside or outside.
        self::assertTrue(TrustedProxyMatcher::matches('194.128.8.1', ['194.128.0.0/17']));
        self::assertFalse(TrustedProxyMatcher::matches('194.128.0.1', ['194.128.128.0/17']));

        // The byte-aligned exact prefix ends on its last byte: the
        // zero-width mask claims the exact address without indexing past it.
        self::assertTrue(TrustedProxyMatcher::matches('10.0.0.1', ['10.0.0.1/32']));
        self::assertTrue(TrustedProxyMatcher::matches('::1', ['::1/128']));

        // The family width is the grammar's answer: a past-width prefix
        // claims nothing on either family.
        self::assertFalse(TrustedProxyMatcher::matches('10.0.0.1', ['10.0.0.1/33']));
        self::assertFalse(TrustedProxyMatcher::matches('::1', ['::1/129']));

        // The full-byte comparison is byte semantics: character-counted
        // slices of these packed forms substitute down to the same bytes.
        self::assertTrue(TrustedProxyMatcher::matches('194.128.128.5', ['194.128.0.0/16']));
        self::assertFalse(TrustedProxyMatcher::matches('192.169.0.1', ['192.168.0.0/16']));

        // A non-address network claims nothing, whichever family the
        // address under judgement belongs to.
        self::assertFalse(TrustedProxyMatcher::matches('::1', ['garbage/8']));
        self::assertFalse(TrustedProxyMatcher::matches('10.0.0.1', ['garbage/8']));
    }

    /**
     * The past-width prefix is judged on the address's own family: the
     * IPv4-width rejection a 10.x address sees must not leak into the
     * IPv6 family, whose width the grammar answers as 128.
     */
    public function testAPastWidthPrefixIsRejectedOnTheIpv6FamilyToo(): void
    {
        self::assertFalse(TrustedProxyMatcher::matches('::1', ['::1/129']));
    }

    /**
     * A non-address network fails the packing guard on the IPv6 side of
     * the family comparison: the address under judgement is valid IPv6
     * while the network packs nothing, so nothing can contain it.
     */
    public function testANonAddressNetworkFailsOnTheIpv6SideToo(): void
    {
        self::assertFalse(TrustedProxyMatcher::matches('::1', ['garbage/8']));
    }

    /**
     * The /16 boundary at the exact policy edge: the full two bytes are
     * compared, and the remaining zero bits claim nothing further.
     */
    public function testAHostExactlyOnThePrefixBoundaryIsClaimed(): void
    {
        self::assertTrue(TrustedProxyMatcher::matches('194.128.0.0', ['194.128.0.0/16']));
        self::assertFalse(TrustedProxyMatcher::matches('194.129.0.0', ['194.128.0.0/16']));
    }

    #[DataProvider('provideAMalformedEntryDoesNotClaimAnythingCases')]
    public function testAMalformedEntryDoesNotClaimAnything(string $entry): void
    {
        self::assertFalse(TrustedProxyMatcher::matches('10.0.0.1', [$entry]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAMalformedEntryDoesNotClaimAnythingCases(): iterable
    {
        yield 'an empty entry' => [''];

        yield 'a slash without a network' => ['/8'];

        yield 'a network without a prefix' => ['10.0.0.0/'];

        yield 'a non-digit prefix' => ['10.0.0.0/eight'];

        yield 'a negative prefix' => ['10.0.0.0/-8'];

        yield 'a prefix past the IPv4 width' => ['10.0.0.0/33'];

        yield 'a prefix past the IPv4 width on the exact address' => ['10.0.0.1/33'];

        yield 'a far past-width prefix' => ['10.0.0.0/129'];

        yield 'a prefix past the IPv6 width' => ['::1/129'];

        yield 'a duplicated slash' => ['10.0.0.0/8/extra'];

        yield 'a non-address network' => ['not-an-address/8'];

        yield 'an IPv4 network with an IPv6 width prefix' => ['10.0.0.0/64'];
    }

    public function testTheDirectContainmentRejectsANegativePrefix(): void
    {
        self::assertFalse(CidrAddress::contains('10.0.0.1', '10.0.0.0', -1));
    }

    public function testTheDirectContainmentRejectsAnInvalidAddressOrNetwork(): void
    {
        self::assertFalse(CidrAddress::contains('not-an-address', '10.0.0.0', 8));
        self::assertFalse(CidrAddress::contains('10.0.0.1', 'not-a-network', 8));
        self::assertFalse(CidrAddress::contains('not-an-address', '::1', 8));
        self::assertFalse(CidrAddress::contains('::1', 'not-a-network', 8));
    }

    /**
     * The zero prefix claims every address of its own family -- the
     * boundary that keeps the width guard honest against an off-by-one
     * relaxation.
     */
    public function testTheZeroPrefixClaimsEveryAddressOfItsFamily(): void
    {
        self::assertTrue(CidrAddress::contains('10.0.0.1', '192.168.0.1', 0));
        self::assertTrue(CidrAddress::contains('2001:db8::1', '::1', 0));
        self::assertFalse(CidrAddress::contains('10.0.0.1', '::1', 0));
    }
}
