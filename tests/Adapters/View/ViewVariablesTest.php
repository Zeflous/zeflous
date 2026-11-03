<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Adapters\View\ViewVariables;
use Zef\Framework\View\ViewException;

/**
 * Pins the data key corridor's whole table: the letter-led grammar
 * that keeps the engine's own reserved corridor (the leading
 * underscore) unreachable by shape, the two names the grammar alone
 * cannot refuse (the scope binding and the auto-global) each
 * refused with its own reason, the non-string key the runtime bag
 * can hand -- and the values nobody's corridor ever polices.
 *
 * @internal
 */
#[CoversClass(ViewVariables::class)]
final class ViewVariablesTest extends TestCase
{
    public function testAcceptsALetterLedBag(): void
    {
        $this->expectNotToPerformAssertions();

        ViewVariables::guard([
            'title' => 'A title',
            'a' => 1,
            'mixedCase' => 2,
            'with_underscore' => 3,
            'withDigits0' => null,
        ]);
    }

    public function testRefusesAnEmptyKey(): void
    {
        try {
            ViewVariables::guard(['' => 'empty']);
            self::fail('The empty key was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "A view data key must be a letter-led variable spelling, got ''.",
                $viewException->getMessage(),
            );
        }
    }

    public function testRefusesADigitLedKey(): void
    {
        try {
            ViewVariables::guard(['0abc' => 'digit-led']);
            self::fail('The digit-led key was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "A view data key must be a letter-led variable spelling, got '0abc'.",
                $viewException->getMessage(),
            );
        }
    }

    public function testRefusesALeadingUnderscoreKey(): void
    {
        try {
            ViewVariables::guard(['_view' => 'impostor']);
            self::fail('The leading-underscore key was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "A view data key must be a letter-led variable spelling, got '_view'.",
                $viewException->getMessage(),
            );
        }
    }

    public function testRefusesTheEngineScopeBindingName(): void
    {
        try {
            ViewVariables::guard(['this' => 'binding']);
            self::fail('The scope binding name was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "The view data key 'this' is reserved by the engine's own scope.",
                $viewException->getMessage(),
            );
        }
    }

    public function testRefusesTheAutoGlobalName(): void
    {
        try {
            ViewVariables::guard(['GLOBALS' => 'global']);
            self::fail('The auto-global name was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "The view data key 'GLOBALS' is reserved by the engine's own scope.",
                $viewException->getMessage(),
            );
        }
    }

    public function testRefusesANonStringKey(): void
    {
        /** @var array<string, mixed> $bag the int key the runtime bag can hand -- the numeric key's folded view (the RequestFactoryTest's own seam) */
        $bag = [42 => 'an integer key', 'sentinel' => 'kept'];

        try {
            ViewVariables::guard($bag);
            self::fail('The non-string key was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                'A view data key must be a string, got int.',
                $viewException->getMessage(),
            );
        }
    }

    public function testRefusesADashedKey(): void
    {
        try {
            ViewVariables::guard(['with-dash' => 'dashed']);
            self::fail('The dashed key was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "A view data key must be a letter-led variable spelling, got 'with-dash'.",
                $viewException->getMessage(),
            );
        }
    }

    public function testRefusesADottedKey(): void
    {
        try {
            ViewVariables::guard(['with.dot' => 'dotted']);
            self::fail('The dotted key was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "A view data key must be a letter-led variable spelling, got 'with.dot'.",
                $viewException->getMessage(),
            );
        }
    }

    public function testRefusesASpacedKey(): void
    {
        try {
            ViewVariables::guard(['with space' => 'spaced']);
            self::fail('The spaced key was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "A view data key must be a letter-led variable spelling, got 'with space'.",
                $viewException->getMessage(),
            );
        }
    }

    public function testRefusesANonAsciiKey(): void
    {
        try {
            ViewVariables::guard(['clé' => 'non-ASCII']);
            self::fail('The non-ASCII key was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "A view data key must be a letter-led variable spelling, got 'clé'.",
                $viewException->getMessage(),
            );
        }
    }

    public function testTheValuesAreNobodysCorridor(): void
    {
        $this->expectNotToPerformAssertions();

        ViewVariables::guard([
            'anObject' => new stdClass(),
            'aNull' => null,
            'anArray' => [1, 2, 3],
            'aClosure' => static fn (): string => 'x',
            'aBool' => false,
        ]);
    }
}
