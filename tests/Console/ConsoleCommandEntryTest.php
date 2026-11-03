<?php

declare(strict_types=1);

namespace Zef\Test\Console;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Console\ConsoleCommandEntry;

/**
 * The declared entry's own bounds: the display safety the Standard CLI
 * Schema deliberately does not carry -- description length and control
 * bytes, the service's non-emptiness -- refused at the value's
 * construction, never stripped at render time.
 *
 * @internal
 */
#[CoversClass(ConsoleCommandEntry::class)]
final class ConsoleCommandEntryTest extends TestCase
{
    public function testTheEntryCarriesItsThreeFieldsVerbatim(): void
    {
        $entry = ConsoleCommandEntry::fromDeclaration([
            'name' => 'shop:ping',
            'description' => 'Answer the health probe.',
            'service' => 'test/shop/ping.handler',
        ]);

        self::assertSame('shop:ping', $entry->name->toString());
        self::assertSame('Answer the health probe.', $entry->description);
        self::assertSame('test/shop/ping.handler', $entry->service);
    }

    /**
     * @param array<string, string> $declaration the out-of-bounds declaration
     * @param non-empty-string      $sentence    the refusal the bounds carry
     */
    #[DataProvider('provideTheEntryRefusesEveryOutOfBoundsFieldCases')]
    public function testTheEntryRefusesEveryOutOfBoundsField(array $declaration, string $sentence): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($sentence);

        ConsoleCommandEntry::fromDeclaration($declaration);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function provideTheEntryRefusesEveryOutOfBoundsFieldCases(): iterable
    {
        yield 'an empty description' => [
            ['name' => 'shop:ping', 'description' => '', 'service' => 's'],
            'description must be between 1 and 200 characters',
        ];

        yield 'a too-long description' => [
            ['name' => 'shop:ping', 'description' => str_repeat('a', 201), 'service' => 's'],
            'description must be between 1 and 200 characters',
        ];

        yield 'a control byte in the description' => [
            ['name' => 'shop:ping', 'description' => "pi\x1bng", 'service' => 's'],
            'must not carry control bytes',
        ];

        yield 'an empty service' => [
            ['name' => 'shop:ping', 'description' => 'd', 'service' => ''],
            'service must not be empty',
        ];

        yield 'a wrong name grammar' => [
            ['name' => 'Shop:Ping', 'description' => 'd', 'service' => 's'],
            'must be lowercase labels',
        ];
    }

    public function testTheDescriptionBoundCountsCharactersNotBytes(): void
    {
        // A hundred and one characters at two hundred and two bytes: the
        // bound counts characters, so the multibyte description constructs
        // -- never refused by a byte count the display bound cannot see.
        $description = str_repeat('é', 101);

        $entry = ConsoleCommandEntry::fromDeclaration([
            'name' => 'shop:ping',
            'description' => $description,
            'service' => 's',
        ]);

        self::assertSame($description, $entry->description);
    }

    public function testTheControlByteRefusalCarriesItsWholeSentence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            "The 'shop:ping' command declaration's description must not carry control bytes"
            . " (the CLI directory renders it in the operator's terminal).",
        );

        ConsoleCommandEntry::fromDeclaration([
            'name' => 'shop:ping',
            'description' => "pi\x1bng",
            'service' => 's',
        ]);
    }

    public function testTheTwoHundredthCharacterIsStillWelcome(): void
    {
        $entry = ConsoleCommandEntry::fromDeclaration([
            'name' => 'shop:ping',
            'description' => str_repeat('a', 200),
            'service' => 's',
        ]);

        self::assertSame(200, mb_strlen($entry->description));
    }
}
