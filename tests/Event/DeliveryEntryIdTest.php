<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\DeliveryEntryId;

/**
 * Pins the entry identity: the mint's 32 lowercase hexadecimal
 * characters, the mint's uniqueness, and every refusal a
 * misspelled id answers with.
 *
 * @internal
 */
#[CoversClass(DeliveryEntryId::class)]
final class DeliveryEntryIdTest extends TestCase
{
    public function testTheMintAnswersThirtyTwoLowercaseHexCharacters(): void
    {
        $entryId = DeliveryEntryId::mint();

        self::assertSame(1, preg_match('/^[0-9a-f]{32}$/', $entryId->spelling()));
    }

    public function testTwoMintsAnswerDifferentIdentities(): void
    {
        self::assertNotSame(
            DeliveryEntryId::mint()->spelling(),
            DeliveryEntryId::mint()->spelling(),
        );
    }

    public function testAWellFormedIdIsAcceptedAndSpelled(): void
    {
        $deliveryEntryId = new DeliveryEntryId('0123456789abcdef0123456789abcdef');

        self::assertSame('0123456789abcdef0123456789abcdef', $deliveryEntryId->spelling());
    }

    public function testAnUppercaseIdIsRefused(): void
    {
        try {
            new DeliveryEntryId('0123456789ABCDEF0123456789ABCDEF');
            self::fail('The uppercase id was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A delivery entry id must be exactly 32 lowercase hexadecimal'
                . " characters, got '0123456789ABCDEF0123456789ABCDEF'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAShortIdIsRefused(): void
    {
        try {
            new DeliveryEntryId('0123456789abcdef');
            self::fail('The short id was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A delivery entry id must be exactly 32 lowercase hexadecimal'
                . " characters, got '0123456789abcdef'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANonHexIdIsRefused(): void
    {
        try {
            new DeliveryEntryId('g' . str_repeat('0', 31));
            self::fail('The non-hex id was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A delivery entry id must be exactly 32 lowercase hexadecimal'
                . " characters, got 'g" . str_repeat('0', 31) . "'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheEmptyIdIsRefused(): void
    {
        try {
            new DeliveryEntryId('');
            self::fail('The empty id was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A delivery entry id must be exactly 32 lowercase hexadecimal'
                . " characters, got ''.",
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
