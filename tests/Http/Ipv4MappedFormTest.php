<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\Ipv4MappedForm;

/**
 * The mapped-form fold's own battery: the fold itself, the one-way
 * boundary around it, and the exact twelve-byte prefix that admits a
 * packing to the fold. The prefix's own width is pinned by an address
 * whose first eleven bytes match the mapped prefix but whose twelfth
 * byte does not -- an eleven-byte comparison would fold an address the
 * range does not carry.
 *
 * @internal
 */
#[CoversClass(Ipv4MappedForm::class)]
final class Ipv4MappedFormTest extends TestCase
{
    public function testAMappedSpellingFoldsToTheEmbeddedIPv4Packing(): void
    {
        self::assertSame(inet_pton('10.0.0.1'), Ipv4MappedForm::packed('::ffff:10.0.0.1'));
    }

    public function testAMappedSpellingOfTheZeroHostFoldsToTheZeroPacking(): void
    {
        self::assertSame(inet_pton('0.0.0.0'), Ipv4MappedForm::packed('::ffff:0:0'));
    }

    public function testAPlainIPv4SpellingNeverExpands(): void
    {
        self::assertSame(inet_pton('10.0.0.1'), Ipv4MappedForm::packed('10.0.0.1'));
    }

    /**
     * The fold admits only the full RFC 4291 mapped prefix -- ten zero
     * bytes then ff ff. An address whose eleventh byte is zero (the
     * ::ff00:0:0 shape) matches the first eleven bytes of the prefix
     * but not its twelfth, and keeps its sixteen-byte packing: a
     * comparison one byte short would fold it into the four bytes it
     * does not carry.
     */
    public function testAnElevenBytePrefixMatchOutsideTheMappedRangeDoesNotFold(): void
    {
        self::assertSame(inet_pton('::ff00:0:0'), Ipv4MappedForm::packed('::ff00:0:0'));
    }

    public function testTheDeprecatedIPv4CompatibleSpellingKeepsItsOwnFamily(): void
    {
        self::assertSame(inet_pton('::10.0.0.1'), Ipv4MappedForm::packed('::10.0.0.1'));
    }

    public function testAnUnpackableAddressAnswersFalse(): void
    {
        self::assertFalse(Ipv4MappedForm::packed('not-an-address'));
    }
}
