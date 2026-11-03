<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\View;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\View\TemplateFace;
use Zef\Test\Support\Adapters\View\RecordingViewEngine;
use Zef\Test\Support\Adapters\View\StringableValue;

/**
 * Pins the template's own handle: the escape fold is the tree's one
 * HTML-safe spelling (every special crossed, the Stringable cast,
 * the invalid byte substituted), and the partial verb re-enters the
 * engine with the port's own question -- the name and the bag the
 * caller spelled, the engine's own answer returned -- while a name
 * outside the grammar refuses at the identity fold, never inside
 * the engine.
 *
 * @internal
 */
#[CoversClass(TemplateFace::class)]
final class TemplateFaceTest extends TestCase
{
    public function testEscapesEveryHtmlSpecial(): void
    {
        $face = new TemplateFace(new RecordingViewEngine());

        self::assertSame(
            '&lt;&gt;&amp;&quot;&#039;',
            $face->escape('<>&"\''),
        );
    }

    public function testEscapesASafeTextVerbatim(): void
    {
        $face = new TemplateFace(new RecordingViewEngine());

        self::assertSame('Zef & Friends', $face->escape('Zef & Friends'));
    }

    public function testEscapesAnEmptyValueToTheEmptyText(): void
    {
        $face = new TemplateFace(new RecordingViewEngine());

        self::assertSame('', $face->escape(''));
    }

    public function testCastsAStringableThroughTheSameFold(): void
    {
        $face = new TemplateFace(new RecordingViewEngine());

        self::assertSame('&lt;b&gt;', $face->escape(new StringableValue('<b>')));
    }

    public function testSubstitutesTheInvalidByte(): void
    {
        $face = new TemplateFace(new RecordingViewEngine());

        self::assertSame("a\u{FFFD}b", $face->escape("a\xFFb"));
    }

    public function testThePartialVerbReentersTheEngine(): void
    {
        $engine = new RecordingViewEngine(['item' => 'the-answer']);
        $face = new TemplateFace($engine);

        self::assertSame(
            'the-answer',
            $face->render('item', ['name' => 'Zef']),
        );
        self::assertSame(
            [['name' => 'item', 'data' => ['name' => 'Zef']]],
            $engine->renders,
        );
    }

    public function testThePartialVerbRefusesANameOutsideTheGrammar(): void
    {
        $face = new TemplateFace(new RecordingViewEngine());

        try {
            $face->render('..', []);
            self::fail('The grammar-refusing name was not refused at the identity fold.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be letter-or-digit led and carry only letters,'
                . " digits, dots, underscores or hyphens, got '..'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
